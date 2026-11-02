<?php

namespace App\Http\Controllers\Api;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Deals\FollowUps;
use App\Domains\Deals\MediaKit;
use App\Domains\Deals\ProposalWriter;
use App\Domains\Deals\SponsorProspects;
use App\Domains\Landing\Attribution;
use App\Domains\Landing\BookingSlots;
use App\Domains\Landing\ClientJourney;
use App\Domains\Landing\LandingFunnel;
use App\Domains\Landing\RevenueAttribution;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Services\Llm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The money loop around Deals: where leads come from, who needs a follow-up, proposals with a
 * payment link, booking hours, sponsor prospects from her niche, and per-brand media kit links
 * that tell her when they were opened. Nothing here sends anything on her behalf.
 */
class PipelineController extends Controller
{
    // ------------------------------------------------------------ public (landing page)

    /**
     * The landing page counts a visit / CTA click / form start (sendBeacon, no visitor data).
     */
    public function event(Request $request): Response
    {
        $data = $request->validate([
            'event' => ['required', Rule::in(LandingFunnel::EVENTS)],
            'attribution' => 'nullable|array',
        ] + Attribution::rules());
        LandingFunnel::record($data['event'], Attribution::resolve($data['attribution'] ?? null, $request->getHost())['source']);

        return response()->noContent();
    }

    /**
     * A brand's own media kit link: the kit, and she's told they opened it.
     */
    public function kit(string $token): JsonResponse
    {
        // An expired link (60 days, or switched off by her) is simply gone.
        $deal = Deal::where('kit_token', $token)->where(fn ($q) => $q->whereNull('kit_expires_at')->orWhere('kit_expires_at', '>', now()))->first();
        abort_unless($deal && strlen($token) >= 20, 404);
        $first = !$deal->kit_viewed_at || $deal->kit_viewed_at->lt(now()->subHour());
        $deal->forceFill([
            'kit_views' => $deal->kit_views + 1,
            'kit_viewed_at' => now(),
        ])->saveQuietly();
        if ($first) {
            AgentEvent::log('lead', "{$deal->brand_name} opened your media kit. A good moment to follow up.");
            \App\Domains\Workflows\Engine::fire('deal_opened', \App\Domains\Workflows\Engine::dealContext($deal, [
                'what' => 'media kit',
            ]), "kit:{$deal->id}:" . now()->format('YmdH'));
        }

        return response()->json(MediaKit::data() + [
            'prepared_for' => $deal->brand_name,
            'contact' => [
                'book' => url('/?ref=kit-' . $deal->id . '#contact'),
            ],
        ]);
    }

    // ------------------------------------------------------------ follow-ups

    public function followUps(): JsonResponse
    {
        return response()->json([
            'follow_ups' => FollowUps::list(50),
            'rules' => FollowUps::RULES,
        ]);
    }

    public function draftFollowUp(Request $request, Deal $deal, Llm $llm): JsonResponse
    {
        $data = $request->validate([
            'angle' => 'nullable|string|max:500',
        ]);

        return response()->json([
            'draft' => FollowUps::draft($deal, $llm, $data['angle'] ?? null),
            'to' => $deal->contact_email,
        ]);
    }

    /**
     * "I followed up": restarts the quiet-days clock (and optionally moves the stage).
     */
    public function contacted(Request $request, Deal $deal): JsonResponse
    {
        $data = $request->validate([
            'stage' => ['nullable', Rule::in(Deal::STAGES)],
            'note' => 'nullable|string|max:1000',
        ]);
        $fields = [
            'last_contacted_at' => now(),
            'follow_up_snoozed_until' => null,
        ];
        if (!empty($data['stage'])) {
            $fields['stage'] = $data['stage'];
        }
        if (!empty($data['note'])) {
            $fields['notes'] = trim(($deal->notes ? $deal->notes . "\n\n" : '') . now(Setting::get('timezone') ?: 'UTC')->format('j M') . ": {$data['note']}");
        }
        $deal->update($fields);

        return response()->json([
            'message' => 'Marked as followed up.',
            'deal' => $deal->fresh(),
        ]);
    }

    public function snooze(Request $request, Deal $deal): JsonResponse
    {
        $data = $request->validate([
            'days' => 'required|integer|min:1|max:60',
        ]);
        $deal->update([
            'follow_up_snoozed_until' => now()->addDays($data['days']),
        ]);

        return response()->json([
            'message' => "Snoozed for {$data['days']} day(s).",
            'deal' => $deal->fresh(),
        ]);
    }

    // ------------------------------------------------------------ proposals & payment

    public function proposal(Request $request, Deal $deal, ProposalWriter $writer): JsonResponse
    {
        @set_time_limit(180);
        $data = $request->validate([
            'plan' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:2000',
        ]);
        $proposal = $writer->write($deal, $data['plan'] ?? null, $data['notes'] ?? null);
        $deal->update([
            'proposal' => $proposal,
            'proposal_at' => now(),
        ]);

        return response()->json([
            'proposal' => $proposal,
            'markdown' => ProposalWriter::markdown($proposal),
            'plans' => self::plans(),
        ]);
    }

    /**
     * Her edits to the proposal (the price stays the deal's).
     */
    public function saveProposal(Request $request, Deal $deal): JsonResponse
    {
        $data = $request->validate([
            'proposal' => 'required|array',
        ]);
        $proposal = ProposalWriter::clean($data['proposal'] + ($deal->proposal ?? []));
        $deal->update([
            'proposal' => $proposal,
        ]);

        return response()->json([
            'proposal' => $proposal,
            'markdown' => ProposalWriter::markdown($proposal),
        ]);
    }

    public function showProposal(Deal $deal): JsonResponse
    {
        return response()->json([
            'proposal' => $deal->proposal,
            'markdown' => $deal->proposal ? ProposalWriter::markdown($deal->proposal) : null,
            'plans' => self::plans(),
            'suggested_plan' => ProposalWriter::plan($deal),
            'stripe' => StripePaymentController::configured(),
        ]);
    }

    /**
     * Proposal sent: moves an inquiry or prospect to Pitching and starts the follow-up clock.
     */
    public function proposalSent(Deal $deal): JsonResponse
    {
        $deal->update([
            'last_contacted_at' => now(),
        ] + (in_array($deal->stage, ['prospect', 'inquiry'], true) ? [
            'stage' => 'pitching',
        ] : []));

        return response()->json([
            'message' => 'Proposal marked as sent.',
            'deal' => $deal->fresh(),
        ]);
    }

    /**
     * A Stripe payment link for this deal's fee (or a deposit), added to the proposal.
     */
    public function paymentLink(Request $request, Deal $deal): JsonResponse
    {
        $data = $request->validate([
            'percent' => 'nullable|integer|in:25,30,50,100',
            'monthly' => 'nullable|boolean',
        ]);
        if (!StripePaymentController::configured()) {
            return response()->json([
                'message' => 'Stripe isn’t set up yet: add your Stripe secret key in Settings → Payments. Until then, send the invoice instead.',
            ], 422);
        }
        if ((float)$deal->rate <= 0) {
            return response()->json([
                'message' => 'Set the deal’s fee first.',
            ], 422);
        }
        $percent = $data['percent'] ?? 100;
        $cents = (int)round((float)$deal->rate * 100 * $percent / 100);
        $title = $deal->proposal['title'] ?? $deal->brand_name;
        $session = StripePaymentController::startCheckout($deal, [
            'id' => 'proposal',
            'name' => $percent < 100 ? "{$percent}% deposit: {$title}" : $title,
            'description' => $deal->proposal['summary'] ?? null,
            'amount' => $cents,
            'currency' => strtolower($deal->currency ?: 'usd'),
            'interval' => !empty($data['monthly']) ? 'month' : null,
        ], $deal->contact_email);
        if (!$session) {
            return response()->json([
                'message' => 'Stripe didn’t create the link. Check your Stripe key in Settings and try again.',
            ], 502);
        }
        if ($deal->proposal) {
            $deal->update([
                'proposal' => [
                    'payment_url' => $session['url'],
                ] + $deal->proposal,
            ]);
        }

        return response()->json([
            'url' => $session['url'],
            'amount' => $cents / 100,
            'currency' => $deal->currency ?: 'USD',
            'markdown' => $deal->proposal ? ProposalWriter::markdown($deal->fresh()->proposal) : null,
        ]);
    }

    // ------------------------------------------------------------ attribution & funnel

    public function attribution(Request $request): JsonResponse
    {
        $days = (int)$request->integer('days', 90);

        return response()->json(RevenueAttribution::summary(in_array($days, [30, 90, 365], true) ? $days : 90));
    }

    public function funnel(Request $request): JsonResponse
    {
        $days = (int)$request->integer('days', 30);

        return response()->json(LandingFunnel::summary(in_array($days, [7, 30, 90], true) ? $days : 30));
    }

    /**
     * Her site link with ?ref=…, for a description, a post or her bio.
     */
    public function trackedLink(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ref' => ['required', 'string', 'max:60', 'regex:/^[\w-]+$/'],
        ]);

        return response()->json([
            'url' => Attribution::trackedUrl(self::siteUrl(), $data['ref']),
        ]);
    }

    /**
     * The one-glance money card on the dashboard.
     */
    public static function revenue(): array
    {
        $open = Deal::whereNotIn('stage', Deal::NOT_PIPELINE)->get(['stage', 'rate']);
        $tz = Setting::get('timezone') ?: 'UTC';
        $thisMonth = now($tz)->startOfMonth()->utc();
        $lastMonth = now($tz)->subMonthNoOverflow()->startOfMonth()->utc();
        // A deal counts once, when it's paid; retainer renewals (Stripe invoices) count every month on top.
        $paid = fn ($from, $to) => (float)Deal::where('payment_status', 'paid')->where('paid_at', '>=', $from)->when($to, fn ($q) => $q->where('paid_at', '<', $to))->sum('rate')
            + (float)Payment::where('kind', 'renewal')->where('paid_at', '>=', $from)->when($to, fn ($q) => $q->where('paid_at', '<', $to))->sum('amount');

        return [
            'paid_this_month' => round($paid($thisMonth, null), 2),
            'paid_last_month' => round($paid($lastMonth, $thisMonth), 2),
            'mrr' => round((float)Deal::where('subscription_status', 'active')->sum('rate'), 2),
            'retainers' => Deal::where('subscription_status', 'active')->count(),
            'subscribers_30d' => Subscriber::whereNull('unsubscribed_at')->where('created_at', '>=', now()->subDays(30))->count(),
            'pipeline' => round((float)$open->sum('rate'), 2),
            'weighted' => round((float)$open->sum(fn ($d) => (float)$d->rate * (Deal::WIN_ODDS[$d->stage] ?? 0)), 2),
            'open_deals' => $open->count(),
            'follow_ups' => FollowUps::due()->count(),
            'meetings' => Deal::whereNotNull('meeting_at')->whereBetween('meeting_at', [now(), now()->addDays(7)])->orderBy('meeting_at')->take(3)
                ->get(['id', 'brand_name', 'contact_name', 'meeting_at'])->map(fn ($d) => [
                    'id' => $d->id,
                    'brand_name' => $d->brand_name,
                    'contact_name' => $d->contact_name,
                    'at' => $d->meeting_at->toIso8601String(),
                ])->all(),
            'capacity' => ClientJourney::availability(),
            'funnel_30d' => collect(LandingFunnel::summary(30)['steps'])->mapWithKeys(fn ($s) => [
                $s['key'] => $s['count'],
            ])->all(),
        ];
    }

    // ------------------------------------------------------------ booking hours

    public function bookingHours(): JsonResponse
    {
        return response()->json([
            'config' => BookingSlots::config(),
            'preview' => array_slice(BookingSlots::open()['days'], 0, 3),
        ]);
    }

    public function saveBookingHours(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => 'required|boolean',
            'days' => 'required|array',
            'days.*' => 'integer|min:1|max:7',
            'start' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'end' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'minutes' => 'required|integer|in:15,20,30,45,60',
            'notice_hours' => 'required|integer|min:0|max:168',
            'horizon_days' => 'required|integer|min:1|max:60',
        ]);

        return response()->json([
            'config' => BookingSlots::save($data),
            'preview' => array_slice(BookingSlots::open()['days'], 0, 3),
            'message' => 'Booking hours saved.',
        ]);
    }

    /**
     * An .ics for a booked intro call, to drop into her own calendar.
     */
    public function meetingIcs(Deal $deal): Response
    {
        abort_unless($deal->meeting_at, 404);
        $minutes = BookingSlots::config()['minutes'];
        $fmt = fn ($t) => $t->utc()->format('Ymd\THis\Z');
        $esc = fn (string $s) => str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $s);
        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Creator Studio//Intro call//EN', 'BEGIN:VEVENT',
            'UID:deal-' . $deal->id . '-' . $deal->meeting_at->getTimestamp() . '@creator-studio',
            'DTSTAMP:' . $fmt(now()), 'DTSTART:' . $fmt($deal->meeting_at), 'DTEND:' . $fmt($deal->meeting_at->copy()->addMinutes($minutes)),
            'SUMMARY:' . $esc("Intro call: {$deal->brand_name}"),
            'DESCRIPTION:' . $esc(trim(($deal->contact_name ? "{$deal->contact_name} " : '') . ($deal->contact_email ? "<{$deal->contact_email}>\n" : '') . "\n" . (string)$deal->key_talking_points)),
            'END:VEVENT', 'END:VCALENDAR', '',
        ]);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="intro-call-' . Str::slug($deal->brand_name) . '.ics"',
        ]);
    }

    // ------------------------------------------------------------ sponsors & media kit

    public function prospects(Request $request): JsonResponse
    {
        return response()->json([
            'prospects' => SponsorProspects::list((int)$request->integer('days', 180)),
        ]);
    }

    /**
     * Add a sponsor from her niche to the pipeline as a prospect (or return the deal she already has).
     */
    public function addProspect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'brand' => 'required|string|max:150',
            'domain' => 'nullable|string|max:150',
            'pitch' => 'nullable|string|max:5000',
        ]);
        $existing = Deal::get(['id', 'brand_name'])->first(fn ($d) => SponsorProspects::key($d->brand_name) === SponsorProspects::key($data['brand']));
        if ($existing) {
            return response()->json([
                'message' => "{$data['brand']} is already in your deals.",
                'deal' => Deal::find($existing->id),
            ]);
        }
        $deal = Deal::create([
            'brand_name' => $data['brand'],
            'stage' => 'prospect',
            'rate' => 0,
            'currency' => 'USD',
            'payment_status' => 'pending',
            'tracking_url' => !empty($data['domain']) ? 'https://' . ltrim($data['domain'], '/') : null,
            'notes' => trim('Spotted sponsoring videos in your niche (Sponsor prospects).' . (!empty($data['pitch']) ? "\n\nPitch draft:\n{$data['pitch']}" : '')),
        ]);

        return response()->json([
            'message' => "{$deal->brand_name} added as a prospect.",
            'deal' => $deal,
        ], 201);
    }

    /**
     * A private media kit link for one brand; opening it shows up as a follow-up.
     */
    public function kitLink(Deal $deal): JsonResponse
    {
        if (!$deal->kit_token || ($deal->kit_expires_at && $deal->kit_expires_at->isPast())) {
            $deal->forceFill([
                'kit_token' => Str::random(32),
                'kit_expires_at' => now()->addDays(DealRoomController::LINK_DAYS),
            ])->saveQuietly();
        } elseif (!$deal->kit_expires_at) {
            $deal->forceFill([
                'kit_expires_at' => now()->addDays(DealRoomController::LINK_DAYS),
            ])->saveQuietly();
        }

        return response()->json([
            'url' => url('/kit/' . $deal->kit_token),
            'views' => $deal->kit_views,
            'viewed_at' => $deal->kit_viewed_at?->toIso8601String(),
        ]);
    }

    // ------------------------------------------------------------ helpers

    /**
     * Her site, only when it's reachable by visitors (not localhost), for links that leave the studio.
     */
    public static function publicSiteUrl(): ?string
    {
        $url = self::siteUrl();
        $host = (string)parse_url($url, PHP_URL_HOST);

        return $host === '' || $host === 'localhost' || preg_match('/^(127\.|0\.0\.0\.0|\[?::1)|\.(test|local|localhost)$/', $host) ? null : $url;
    }

    public static function siteUrl(): string
    {
        return rtrim((string)(Setting::get('site_url') ?: config('app.url') ?: url('/')), '/') . '/';
    }

    private static function plans(): array
    {
        return collect(\App\Domains\Landing\Pricing::PLANS)->map(fn ($p, $k) => [
            'key' => $k,
            'name' => $p['name'],
            'price' => \App\Domains\Landing\Pricing::price($k),
        ])->values()->all();
    }
}
