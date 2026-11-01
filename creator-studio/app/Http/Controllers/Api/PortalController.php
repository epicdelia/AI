<?php

namespace App\Http\Controllers\Api;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Clients\ClientAnalytics;
use App\Domains\Clients\ClientComplianceAuditor;
use App\Domains\Clients\ClientDeal;
use App\Domains\Clients\ClientNotifier;
use App\Domains\Clients\ClientWebhookDispatcher;
use App\Domains\Clients\DealAddOns;
use App\Domains\Clients\DealFiles;
use App\Domains\Clients\Models\ClientBrandVault;
use App\Domains\Clients\Models\DealMessage;
use App\Domains\Clients\Models\DeliverableAnnotation;
use App\Domains\Clients\Models\TeamInvite;
use App\Domains\Clients\PartnerReferrals;
use App\Domains\Clients\TaxCompliance;
use App\Domains\Deals\ProposalTiers;
use App\Domains\Landing\BookingSlots;
use App\Domains\Landing\Pricing;
use App\Domains\Privacy\DataProcessingAgreement;
use App\Domains\Privacy\DataRetentionPurger;
use App\Domains\Privacy\PrivacyAccessLogger;
use App\Domains\Privacy\ZeroDataRetention;
use App\Domains\Security\Audit;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\Prompts;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A client's side of the dashboard: their team's requests (deals), proposals, deliverables,
 * files, payments and messages with her. Every query starts from the signed-in client's team's
 * deals, so anyone else's deal is simply "not found". What they see of a deal is ClientDeal's
 * allow-list.
 */
class PortalController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $deals = $this->mine($request)->latest()->get();
        $open = $deals->filter(fn (Deal $d) => ! in_array($d->stage, ['paid', 'lost'], true));
        $next = $deals->filter(fn (Deal $d) => $d->meeting_at && $d->meeting_at->isFuture())->sortBy('meeting_at')->first();

        return response()->json([
            'creator' => Prompts::name(),
            'counts' => [
                'open' => $open->count(),
                'done' => $deals->where('stage', 'paid')->count(),
                'unread' => $this->unreadCount($deals->pluck('id')->all()),
            ],
            'next_call' => $next ? ['deal_id' => $next->id, 'title' => ClientDeal::summary($next)['title'], 'at' => $next->meeting_at->toIso8601String()] : null,
            'needs_you' => $deals->map(fn (Deal $d) => ($why = ClientDeal::needsYou($d)) ? ['deal_id' => $d->id, 'title' => ClientDeal::summary($d)['title'], 'what' => $why] : null)
                ->filter()->values(),
            'recent' => $open->take(4)->map(fn (Deal $d) => ClientDeal::summary($d))->values(),
        ]);
    }

    /** The sidebar's unread badge (cheap, polled). */
    public function unread(Request $request): JsonResponse
    {
        return response()->json(['unread' => $this->unreadCount($this->mine($request)->pluck('id')->all())]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'requests' => $this->mine($request)->latest()->get()->map(fn (Deal $d) => ClientDeal::summary($d))->values(),
            'services' => self::services(),
        ]);
    }

    public function show(Request $request, int $deal): JsonResponse
    {
        return response()->json(['request' => ClientDeal::detail($this->find($request, $deal))]);
    }

    /** "New request": a new inquiry in her pipeline, already linked to this client's team. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service' => ['required', Rule::in(array_keys(BookingController::PROJECT_LABELS))],
            'details' => 'required|string|min:10|max:4000',
            'budget' => 'nullable|string|max:100',
            'deadline' => 'nullable|string|max:100',
        ]);
        $user = $request->user();
        $label = BookingController::PROJECT_LABELS[$data['service']];

        $deal = $this->newDeal($user, [
            'stage' => 'inquiry',
            'rate' => BookingController::PROJECT_RATES[$data['service']] ?? 0,
            'deliverables' => [['platform' => 'request', 'format' => $label, 'project_type' => $data['service']]],
            'notes' => "Requested from the client dashboard.\n\n{$data['details']}"
                .(! empty($data['budget']) ? "\n\nBudget: {$data['budget']}" : '').(! empty($data['deadline']) ? "\nDeadline: {$data['deadline']}" : ''),
        ]);
        // Their own words open the thread, so she can answer them right there.
        $deal->messages()->create(['user_id' => $user->id, 'from_admin' => false, 'body' => $data['details']]);

        AgentEvent::log('lead', "New request from {$user->name}".($user->company ? " ({$user->company})" : '').": {$label}.");
        self::tellAdmins("New request from {$user->name}: {$label}", "{$user->name} ({$user->email}) asked for: {$label}\n\n{$data['details']}\n\nOpen it: ".url('/clients/'.$user->teamOwnerId()));

        return response()->json(['message' => 'Request sent! You’ll hear back soon.', 'request' => ClientDeal::summary($deal->fresh())], 201);
    }

    public function accept(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        abort_unless($deal->proposal && $deal->stage !== 'lost', 409, 'There’s no proposal to accept yet.');
        $data = $request->validate(['name' => 'required|string|min:2|max:120', 'agree' => 'accepted']);

        return DealRoomController::acceptDeal($deal, $data['name']);
    }

    public function pay(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        if (! ClientDeal::canPay($deal)) {
            return response()->json(['message' => $deal->payment_status === 'paid' ? 'This is already paid. Thank you!' : 'There’s nothing to pay online for this request yet.'], 409);
        }

        return DealRoomController::checkout($deal, '/requests/'.$deal->id);
    }

    /** Sign-off on work in review: it moves on to scheduled (then her invoice). */
    public function approve(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        abort_unless(ClientDeal::canReviewWork($deal), 409, 'There’s nothing waiting for your review on this request.');
        $user = $request->user();
        $deal->forceFill(['approved_at' => now(), 'stage' => 'scheduled', 'last_contacted_at' => now()])->save();
        $deal->messages()->create(['user_id' => $user->id, 'from_admin' => false, 'body' => 'Approved. Thank you!']);
        ClientNotifier::event($deal, 'approved', "Approved by {$user->name}");

        AgentEvent::log('lead', "{$user->name} approved ".ClientDeal::summary($deal)['title'].'.');
        self::tellAdmins("Approved: {$deal->brand_name}", "{$user->name} approved the work on \"".ClientDeal::summary($deal)['title']."\".\n\n".url('/deals'));

        return response()->json(['message' => 'Approved. Thank you!', 'request' => ClientDeal::detail($deal->fresh())]);
    }

    /** "Request changes": back to production, with what they want changed in the thread. */
    public function requestChanges(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        abort_unless(ClientDeal::canReviewWork($deal), 409, 'There’s nothing waiting for your review on this request.');
        $data = $request->validate(['note' => 'required|string|min:5|max:5000']);
        $user = $request->user();
        $deal->update(['stage' => 'production', 'last_contacted_at' => now()]);
        $deal->messages()->create(['user_id' => $user->id, 'from_admin' => false, 'body' => "Changes requested:\n\n".trim($data['note'])]);
        ClientNotifier::event($deal, 'changes', "Changes requested by {$user->name}");

        AgentEvent::log('lead', "{$user->name} asked for changes on ".ClientDeal::summary($deal)['title'].'.');
        self::tellAdmins("Changes requested: {$deal->brand_name}", "{$user->name} asked for changes on \"".ClientDeal::summary($deal)['title']."\":\n\n".trim($data['note'])."\n\n".url('/deals'));

        return response()->json(['message' => 'Sent. You’ll get the updated version for review.', 'request' => ClientDeal::detail($deal->fresh())]);
    }

    public function messages(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        $deal->messages()->where('from_admin', true)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['messages' => $deal->messages()->with('user:id,name')->oldest()->get()->map->toThread()->values()]);
    }

    public function sendMessage(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        $data = $request->validate(['body' => 'required|string|max:5000']);
        $user = $request->user();
        $message = $deal->messages()->create(['user_id' => $user->id, 'from_admin' => false, 'body' => trim($data['body'])]);

        AgentEvent::log('lead', "{$user->name} sent you a message about ".ClientDeal::summary($deal)['title'].'.');
        self::tellAdmins("New message from {$user->name}", "{$user->name} wrote:\n\n".trim($data['body'])."\n\nReply in the studio: ".url('/clients/'.$deal->user_id));

        return response()->json(['message' => $message->load('user:id,name')->toThread()], 201);
    }

    // ------------------------------------------------------------ calls

    public function slots(): JsonResponse
    {
        return response()->json(BookingSlots::open());
    }

    /** Book an intro or follow-up call in one of her open slots (on a request, or a new one). */
    public function bookCall(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slot' => 'required|date',
            'request_id' => 'nullable|integer',
            'topic' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:2000',
        ]);
        $at = rescue(fn () => CarbonImmutable::parse($data['slot'])->utc(), null, false);
        if (! $at || ! BookingSlots::isOpen($at)) {
            return response()->json(['message' => 'That time was just taken. Please pick another one.', 'errors' => ['slot' => ['That time is no longer open.']]], 422);
        }
        $user = $request->user();
        $deal = ! empty($data['request_id']) ? $this->find($request, (int) $data['request_id']) : null;
        $topic = ($data['topic'] ?? null) ?: ($deal ? ClientDeal::summary($deal)['title'] : 'Intro call');

        if ($deal) {
            $deal->update(['meeting_at' => $at]);
        } else {
            $deal = $this->newDeal($user, [
                'stage' => 'inquiry', 'rate' => 0, 'meeting_at' => $at,
                'deliverables' => [['platform' => 'meeting', 'format' => 'Intro Call: '.$topic, 'project_type' => 'other']],
                'notes' => 'Call booked from the client dashboard. Notes: '.(($data['notes'] ?? null) ?: 'None'),
            ]);
        }
        ClientNotifier::event($deal, 'call', 'Call booked');
        $tz = Setting::get('timezone') ?: 'UTC';
        $when = $at->setTimezone($tz)->format('D j M Y, H:i T');
        AgentEvent::log('lead', "Call booked by {$user->name}: {$topic}, {$when}.");
        self::tellAdmins("Call booked: {$user->name}, {$when}", "{$user->name} ({$user->email}) booked a call for {$when} about: {$topic}.\n\n".(($data['notes'] ?? null) ?: '')."\n\nSend a calendar invite.");

        return response()->json(['message' => 'Booked! You’ll get a calendar invite.', 'request_id' => $deal->id, 'at' => $at->toIso8601ZuluString()], 201);
    }

    // ------------------------------------------------------------ money

    public function billing(Request $request): JsonResponse
    {
        $deals = $this->mine($request)->get();
        $byId = $deals->keyBy('id');

        return response()->json([
            'payments' => Payment::whereIn('deal_id', $deals->pluck('id'))->latest('paid_at')->get()->map(fn (Payment $p) => [
                'id' => $p->id,
                'request_id' => $p->deal_id,
                'title' => ClientDeal::summary($byId[$p->deal_id])['title'],
                'amount' => (float) $p->amount,
                'currency' => $p->currency,
                'kind' => $p->kind,
                'paid_at' => $p->paid_at?->toIso8601String(),
            ])->values(),
            'due' => $deals->filter(fn (Deal $d) => ClientDeal::canPay($d))->map(fn (Deal $d) => ClientDeal::summary($d))->values(),
            'retainers' => $deals->filter(fn (Deal $d) => $d->subscription_status)->map(fn (Deal $d) => ClientDeal::summary($d) + ['retainer' => $d->subscription_status])->values(),
            'packages' => array_values(array_map(fn ($p) => ['id' => $p['id'], 'name' => $p['name'], 'description' => $p['description'] ?? null,
                'amount' => $p['amount'] / 100, 'currency' => strtoupper($p['currency'] ?? 'usd'), 'interval' => $p['interval'] ?? null], Pricing::packages())),
            'online' => StripePaymentController::configured(),
        ]);
    }

    /** A receipt for one payment, as Markdown the dashboard prints (or saves as PDF). */
    public function receipt(Request $request, int $payment): JsonResponse
    {
        $p = Payment::whereIn('deal_id', $this->mine($request)->pluck('id'))->findOrFail($payment);
        $deal = $p->deal_id ? Deal::find($p->deal_id) : null;
        $title = $deal ? ClientDeal::summary($deal)['title'] : 'Payment';

        return response()->json(['title' => "Receipt {$this->number('R', $p->id, $p->paid_at)}", 'markdown' => $this->document('Receipt', $this->number('R', $p->id, $p->paid_at), $request->user(), [
            [$title.($p->kind === 'renewal' ? ' (renewal)' : ''), (float) $p->amount, $p->currency],
        ], 'Paid '.$p->paid_at?->toFormattedDayDateString().'. Thank you!', $deal?->brand_name)]);
    }

    /** An invoice for a request she has invoiced (or accepted), as printable Markdown. */
    public function invoice(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        abort_unless(ClientDeal::amountShown($deal) && (float) $deal->rate > 0, 404);
        $s = ClientDeal::summary($deal);
        $at = $deal->invoiced_at ?? $deal->accepted_at ?? now();
        $status = $deal->payment_status === 'paid' ? 'Paid '.$deal->paid_at?->toFormattedDayDateString().'. Thank you!'
            : ($deal->payment_terms ? "Payment terms: {$deal->payment_terms}." : 'Due on receipt.').(ClientDeal::canPay($deal) ? ' Pay online: '.url('/requests/'.$deal->id) : '');

        return response()->json(['title' => "Invoice {$this->number('INV', $deal->id, $at)}", 'markdown' => $this->document('Invoice', $this->number('INV', $deal->id, $at), $request->user(), [
            [$s['title'], (float) $s['amount'], $s['currency']],
        ], $status, $deal->brand_name)]);
    }

    /** Buy a package or retainer from the dashboard (Stripe), linked to this account. */
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'package_id' => ['required', Rule::in(array_keys(StripePaymentController::PACKAGES))],
            'notes' => 'nullable|string|max:1000',
        ]);
        $user = $request->user();
        $pkg = Pricing::package($data['package_id']);
        $amount = $pkg['amount'] / 100;

        $deal = $this->newDeal($user, [
            'stage' => 'inquiry', 'rate' => $amount,
            'deliverables' => [['platform' => 'stripe', 'format' => $pkg['name'], 'package' => $pkg['id']]],
            'key_talking_points' => $pkg['description'] ?? null,
            'notes' => 'Bought from the client dashboard: '.$pkg['name'].'. Notes: '.(($data['notes'] ?? null) ?: 'None'),
        ]);
        AgentEvent::log('lead', "{$user->name} started buying {$pkg['name']} from their dashboard.");

        if (StripePaymentController::configured()) {
            $session = StripePaymentController::startCheckout($deal, $pkg, $user->email, '/requests/'.$deal->id);
            if ($session) {
                return response()->json(['checkout_url' => $session['url'], 'request_id' => $deal->id]);
            }
        }
        self::tellAdmins("Invoice requested: {$pkg['name']}", "{$user->name} ({$user->email}) wants {$pkg['name']} (\$".number_format($amount)."). Online payment isn't set up: send an invoice.");

        return response()->json(['request_id' => $deal->id, 'message' => 'Thanks! You’ll get an invoice for '.$pkg['name'].' by email.'], 201);
    }

    // ------------------------------------------------------------ files

    public function files(Request $request, int $deal): JsonResponse
    {
        return response()->json(['files' => $this->find($request, $deal)->files()->with('user:id,name')->latest()->get()->map->toRow()->values(),
            'max_mb' => DealFiles::maxMb(), 'types' => DealFiles::EXTENSIONS]);
    }

    public function uploadFile(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        $request->validate(DealFiles::rules());
        $user = $request->user();
        $file = DealFiles::store($deal, $request->file('file'), $user, false);
        ClientNotifier::event($deal, 'file', "File added: {$file->name}");
        AgentEvent::log('lead', "{$user->name} added a file to ".ClientDeal::summary($deal)['title'].": {$file->name}.");

        return response()->json(['file' => $file->load('user:id,name')->toRow()], 201);
    }

    public function downloadFile(Request $request, int $deal, int $file): StreamedResponse
    {
        return DealFiles::download($this->find($request, $deal)->files()->findOrFail($file));
    }

    /** Clients remove their own files (hers stay). */
    public function deleteFile(Request $request, int $deal, int $file): JsonResponse
    {
        $f = $this->find($request, $deal)->files()->where('from_admin', false)->findOrFail($file);
        $f->delete();

        return response()->json(['message' => 'Removed.']);
    }

    // ------------------------------------------------------------ proofing & annotations

    public function annotations(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        return response()->json([
            'annotations' => $deal->annotations()->with(['user:id,name,role', 'resolver:id,name'])->latest()->get()->map->toRow()->values(),
        ]);
    }

    public function addAnnotation(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        $data = $request->validate([
            'deliverable_index' => 'nullable|integer|min:0',
            'kind' => 'nullable|string|in:video,image,slide,document,general',
            'timecode_seconds' => 'nullable|numeric|min:0',
            'position_xy' => 'nullable|string|max:50',
            'page_or_slide' => 'nullable|integer|min:1',
            'body' => 'required|string|min:3|max:3000',
        ]);

        $user = $request->user();
        $annotation = $deal->annotations()->create([
            'user_id' => $user->id,
            'deliverable_index' => (int) ($data['deliverable_index'] ?? 0),
            'kind' => $data['kind'] ?? 'general',
            'timecode_seconds' => $data['timecode_seconds'] ?? null,
            'position_xy' => $data['position_xy'] ?? null,
            'page_or_slide' => $data['page_or_slide'] ?? null,
            'body' => trim($data['body']),
            'status' => 'open',
        ]);

        Audit::log('deliverable_annotated', $user, $deal, [
            'annotation_id' => $annotation->id,
            'kind' => $annotation->kind,
            'timecode' => $annotation->timecode_seconds,
        ]);
        AgentEvent::log('lead', "{$user->name} added revision feedback on ".ClientDeal::summary($deal)['title'].'.');
        self::tellAdmins("Feedback on {$deal->brand_name}", "{$user->name} left revision feedback on \"".ClientDeal::summary($deal)['title']."\":\n\n".trim($data['body'])."\n\nReview: ".url('/deals'));

        return response()->json(['annotation' => $annotation->load(['user:id,name,role', 'resolver:id,name'])->toRow()], 201);
    }

    public function resolveAnnotation(Request $request, int $deal, int $annotation): JsonResponse
    {
        $deal = $this->find($request, $deal);
        $item = $deal->annotations()->findOrFail($annotation);
        $user = $request->user();

        $item->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $user->id,
        ]);

        Audit::log('deliverable_resolved', $user, $deal, ['annotation_id' => $item->id]);

        return response()->json(['message' => 'Feedback marked resolved.', 'annotation' => $item->fresh()->load(['user:id,name,role', 'resolver:id,name'])->toRow()]);
    }

    // ------------------------------------------------------------ campaign ROI & performance

    public function roi(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        return response()->json(ClientAnalytics::forDeal($deal));
    }

    // ------------------------------------------------------------ add-ons & upsells

    public function addOns(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        return response()->json([
            'catalog' => DealAddOns::catalog(),
            'applied' => $deal->add_ons ?? [],
            'currency' => $deal->currency ?: 'USD',
        ]);
    }

    public function applyAddOn(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        $data = $request->validate(['add_on' => 'required|string']);

        $res = DealAddOns::apply($deal, $data['add_on'], $request->user());
        return response()->json($res, 201);
    }

    // ------------------------------------------------------------ brand vault

    public function brandVault(Request $request): JsonResponse
    {
        $user = $request->user();
        $ownerId = $user->teamOwnerId();
        $vault = ClientBrandVault::firstOrCreate(
            ['user_id' => $ownerId],
            [
                'brand_name' => $user->company ?: $user->name,
                'website' => null,
                'brand_colors' => ['primary' => '#6366f1', 'secondary' => '#ec4899', 'accent' => '#10b981', 'bg' => '#0f172a'],
                'guidelines_markdown' => '',
                'talking_points' => [],
                'dos_and_donts' => ['dos' => [], 'donts' => []],
                'utm_params' => ['utm_source' => 'tech-unicorn', 'utm_medium' => 'creator-partnership'],
            ]
        );

        return response()->json(['vault' => $vault->toResponse()]);
    }

    public function selectTier(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        abort_if($deal->accepted_at, 409, 'This proposal has already been signed.');
        $data = $request->validate(['tier' => 'required|string|in:essential,growth,enterprise']);

        $res = ProposalTiers::selectTier($deal, $data['tier'], $request->user());
        return response()->json($res);
    }

    public function compliance(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        return response()->json(ClientComplianceAuditor::audit($deal));
    }

    public function complianceReport(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        return response()->json([
            'deal_id' => $deal->id,
            'brand_name' => $deal->brand_name,
            'title' => 'Executive Campaign Wrap & Compliance Report',
            'markdown' => ClientComplianceAuditor::wrapReportMarkdown($deal),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function dpa(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        PrivacyAccessLogger::log($request->user(), 'dpa_view', $deal);

        return response()->json([
            'deal_id' => $deal->id,
            'brand' => $deal->brand_name,
            'signed' => (bool) $deal->dpa_signed_at,
            'signed_at' => $deal->dpa_signed_at?->toIso8601String(),
            'signer_name' => $deal->dpa_signer_name,
            'signer_email' => $deal->dpa_signer_email,
            'signature_hash' => $deal->dpa_signature_hash,
            'markdown' => DataProcessingAgreement::dpaMarkdown($deal, $request->user()),
        ]);
    }

    public function signDpa(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        $data = $request->validate([
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email|max:150',
            'agree' => 'accepted',
        ]);

        $res = DataProcessingAgreement::sign($deal, $data['name'], $data['email'], $request->ip() ?? '127.0.0.1');
        PrivacyAccessLogger::log($request->user(), 'dpa_sign', $deal, ['hash' => $res['signature_hash'] ?? null]);

        return response()->json($res);
    }

    public function zdrCertificate(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        $cert = ZeroDataRetention::certify($deal);
        PrivacyAccessLogger::log($request->user(), 'zdr_cert_view', $deal, ['certificate_id' => $cert['certificate_id']]);

        return response()->json($cert);
    }

    public function purgeDealData(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        $res = DataRetentionPurger::purgeDeal($deal, $request->user());
        PrivacyAccessLogger::log($request->user(), 'deal_data_purged', $deal, $res);

        return response()->json($res);
    }

    public function testWebhook(Request $request): JsonResponse
    {
        $url = $request->input('webhook_url') ?: $request->input('url');
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json(['message' => 'A valid webhook URL is required.'], 422);
        }
        $res = ClientWebhookDispatcher::testPing($url, $request->user());
        return response()->json($res, $res['ok'] ? 200 : 422);
    }

    public function referrals(Request $request): JsonResponse
    {
        return response()->json(PartnerReferrals::summary($request->user()));
    }

    public function saveBrandVault(Request $request): JsonResponse
    {
        $user = $request->user();
        $ownerId = $user->teamOwnerId();
        $vault = ClientBrandVault::firstOrCreate(['user_id' => $ownerId]);

        $data = $request->validate([
            'brand_name' => 'nullable|string|max:150',
            'website' => 'nullable|url|max:255',
            'brand_colors' => 'nullable|array',
            'guidelines_markdown' => 'nullable|string|max:10000',
            'talking_points' => 'nullable|array',
            'dos_and_donts' => 'nullable|array',
            'utm_params' => 'nullable|array',
            'slack_webhook_url' => 'nullable|url|max:500',
            'tax_id' => 'nullable|string|max:50',
            'billing_address' => 'nullable|string|max:500',
            'country_code' => 'nullable|string|max:3',
            'retention_days' => 'nullable|integer|min:7|max:365',
            'zdr_enabled' => 'nullable|boolean',
            'auto_redact_secrets' => 'nullable|boolean',
        ]);

        $taxId = $data['tax_id'] ?? $vault->tax_id;
        $countryCode = $data['country_code'] ?? $vault->country_code;
        $viesData = [];

        if ($taxId) {
            $taxRes = TaxCompliance::validateTaxId($taxId, $countryCode);
            if (($taxRes['type'] ?? '') === 'EU_VAT' && isset($taxRes['vies'])) {
                $vies = $taxRes['vies'];
                $viesData = [
                    'vies_verified' => (bool) ($vies['valid'] ?? false),
                    'vies_request_identifier' => $vies['request_identifier'] ?? null,
                    'vies_verified_at' => ($vies['valid'] ?? false) ? now() : null,
                    'vies_company_name' => $vies['name'] ?? null,
                    'vies_company_address' => $vies['address'] ?? null,
                ];
            }
        }

        $vault->update(array_filter([
            'brand_name' => $data['brand_name'] ?? $vault->brand_name,
            'website' => $data['website'] ?? $vault->website,
            'brand_colors' => $data['brand_colors'] ?? $vault->brand_colors,
            'guidelines_markdown' => $data['guidelines_markdown'] ?? $vault->guidelines_markdown,
            'talking_points' => $data['talking_points'] ?? $vault->talking_points,
            'dos_and_donts' => $data['dos_and_donts'] ?? $vault->dos_and_donts,
            'utm_params' => $data['utm_params'] ?? $vault->utm_params,
            'slack_webhook_url' => $data['slack_webhook_url'] ?? $vault->slack_webhook_url,
            'tax_id' => $data['tax_id'] ?? $vault->tax_id,
            'billing_address' => $data['billing_address'] ?? $vault->billing_address,
            'country_code' => $data['country_code'] ?? $vault->country_code,
            'retention_days' => $data['retention_days'] ?? $vault->retention_days,
            'zdr_enabled' => array_key_exists('zdr_enabled', $data) ? (bool) $data['zdr_enabled'] : $vault->zdr_enabled,
            'auto_redact_secrets' => array_key_exists('auto_redact_secrets', $data) ? (bool) $data['auto_redact_secrets'] : $vault->auto_redact_secrets,
        ] + $viesData, fn ($v) => $v !== null));

        Audit::log('brand_vault_updated', $user, $vault);

        return response()->json(['message' => 'Brand Vault saved.', 'vault' => $vault->fresh()->toResponse()]);
    }

    public function verifyVies(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tax_id' => 'required|string|max:50',
            'country_code' => 'nullable|string|max:3',
        ]);
        $res = TaxCompliance::verifyVies($data['tax_id'], $data['country_code'] ?? null);

        return response()->json($res);
    }

    // ------------------------------------------------------------ team

    public function team(Request $request): JsonResponse
    {
        $user = $request->user();
        $owner = User::find($user->teamOwnerId());

        return response()->json([
            'owner' => ['id' => $owner->id, 'name' => $owner->name, 'email' => $owner->email],
            'is_owner' => $owner->id === $user->id,
            'members' => User::where('team_owner_id', $owner->id)->get()->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->values(),
            'pending' => $owner->id === $user->id
                ? TeamInvite::where('owner_id', $owner->id)->whereNull('accepted_at')->where('expires_at', '>', now())->get(['id', 'email', 'expires_at'])
                : [],
            // Invites to this account's email from another client's team.
            'invites_for_me' => TeamInvite::with('owner:id,name,company')->whereRaw('lower(email) = ?', [strtolower($user->email)])
                ->whereNull('accepted_at')->where('expires_at', '>', now())->get()
                ->map(fn (TeamInvite $i) => ['token' => $i->token, 'from' => $i->owner?->name, 'company' => $i->owner?->company])->values(),
        ]);
    }

    /** The team's owner invites a colleague by email (they sign up or sign in, then join). */
    public function invite(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->team_owner_id, 403, 'Only the person who owns these requests can invite colleagues.');
        $data = $request->validate(['email' => 'required|email|max:200']);
        $email = strtolower(trim($data['email']));
        abort_if($email === strtolower($user->email), 422, 'That’s your own email.');
        abort_if(User::where('team_owner_id', $user->id)->count() + TeamInvite::where('owner_id', $user->id)->whereNull('accepted_at')->count() >= 10, 422, 'A team can have up to 10 colleagues.');

        $invite = TeamInvite::updateOrCreate(['owner_id' => $user->id, 'email' => $email, 'accepted_at' => null],
            ['token' => Str::random(48), 'expires_at' => now()->addDays(14)]);
        $sent = AuthController::mail($email, "{$user->name} invited you to their projects with ".Prompts::name(),
            "{$user->name}".($user->company ? " ({$user->company})" : '')." invited you to follow their projects with ".Prompts::name().": requests, proposals, files and messages.\n\n"
            ."Create your account with this email ({$email}), or sign in if you have one, then accept the invite under Account & security:\n\n".url('/login?'.http_build_query(['signup' => 1, 'email' => $email]))
            ."\n\nThe invite is valid for 14 days.");

        return response()->json(['message' => $sent ? "Invite sent to {$email}." : 'The invite is saved, but the email couldn’t be sent.', 'id' => $invite->id], 201);
    }

    /** Accept an invite to this account's email: this account then shares that team's requests. */
    public function join(Request $request): JsonResponse
    {
        $user = $request->user();
        $invite = TeamInvite::where('token', (string) $request->input('token'))->whereNull('accepted_at')->where('expires_at', '>', now())
            ->whereRaw('lower(email) = ?', [strtolower($user->email)])->firstOrFail();
        abort_if(User::where('team_owner_id', $user->id)->exists(), 409, 'You have your own colleagues: remove them before joining another team.');
        abort_if($invite->owner_id === $user->id || $invite->owner?->role() !== Role::Client, 409, 'This invite can’t be used.');

        $user->forceFill(['team_owner_id' => $invite->owner_id])->save();
        $invite->forceFill(['accepted_at' => now()])->save();
        Audit::log('team_member_added', $user, $invite->owner, ['team_owner' => $invite->owner_id]);

        return response()->json(['message' => 'You’ve joined '.($invite->owner?->name ?? 'the team').'’s projects.']);
    }

    /** The owner removes a colleague (or a colleague leaves; or an invite is withdrawn). */
    public function removeMember(Request $request, int $member): JsonResponse
    {
        $user = $request->user();
        $m = User::where('team_owner_id', $user->teamOwnerId())->findOrFail($member);
        abort_unless($m->id === $user->id || ! $user->team_owner_id, 403);
        $m->forceFill(['team_owner_id' => null])->save();
        Audit::log('team_member_removed', $user, $m);

        return response()->json(['message' => $m->id === $user->id ? 'You’ve left the team.' : "{$m->name} no longer sees these projects."]);
    }

    public function withdrawInvite(Request $request, int $invite): JsonResponse
    {
        TeamInvite::where('owner_id', $request->user()->id)->whereNull('accepted_at')->findOrFail($invite)->delete();

        return response()->json(['message' => 'Invite withdrawn.']);
    }

    // ------------------------------------------------------------ helpers

    /** @return list<array{key: string, label: string}> */
    public static function services(): array
    {
        return array_map(fn ($k, $v) => ['key' => $k, 'label' => $v], array_keys(BookingController::PROJECT_LABELS), BookingController::PROJECT_LABELS);
    }

    /** Email every admin (through her SMTP); never blocks the client's action. */
    public static function tellAdmins(string $subject, string $body): void
    {
        foreach (User::where('role', Role::Admin->value)->whereNull('disabled_at')->pluck('email') as $email) {
            AuthController::mail($email, $subject, $body);
        }
    }

    private function newDeal(User $user, array $attributes): Deal
    {
        $owner = User::find($user->teamOwnerId()) ?? $user;
        $deal = new Deal($attributes + [
            'brand_name' => $owner->company ?: $owner->name,
            'contact_name' => $user->name,
            'contact_email' => $user->email,
            'currency' => 'USD',
            'payment_status' => 'pending',
            'source' => 'portal',
        ]);
        $deal->user_id = $owner->id;
        $deal->save();

        return $deal;
    }

    private function unreadCount(array $dealIds): int
    {
        return DealMessage::whereIn('deal_id', $dealIds)->where('from_admin', true)->whereNull('read_at')->count();
    }

    private function number(string $prefix, int $id, $at): string
    {
        return $prefix.'-'.($at ? $at->format('Y') : now()->format('Y')).'-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    /** @param list<array{0: string, 1: float, 2: string}> $lines */
    private function document(string $kind, string $number, User $user, array $lines, string $footer, ?string $company = null): string
    {
        $owner = User::find($user->teamOwnerId()) ?? $user;
        $companyName = $owner->company ?: $company;
        $from = Prompts::name();
        $business = trim((string) Setting::get('invoice_details', ''));
        $money = fn ($n, $c) => strtoupper($c ?: 'USD').' '.number_format((float) $n, 2);
        $total = array_sum(array_column($lines, 1));
        $taxNotice = TaxCompliance::billingNoticeForUser($user);
        $taxHeader = TaxCompliance::billingHeaderForUser($user);

        $md = "# {$kind} {$number}\n\n**From:** {$from}".($business ? "\n\n".$business : '')."\n\n{$taxHeader}\n\n**Date:** ".now()->toFormattedDayDateString()."\n\n";
        $md .= "| Item | Amount |\n|---|---|\n";
        foreach ($lines as [$item, $amount, $currency]) {
            $md .= '| '.str_replace('|', '/', $item).' | '.$money($amount, $currency)." |\n";
        }

        $noticeSection = $taxNotice ? "\n\n> **Tax & Statutory Compliance:** {$taxNotice}\n" : '';

        return $md.'| **Total** | **'.$money($total, $lines[0][2] ?? 'USD')."** |\n\n{$footer}{$noticeSection}\n";
    }

    private function mine(Request $request): Builder
    {
        return Deal::whereIn('user_id', $request->user()->teamIds())->whereIn('stage', ClientDeal::visibleStages());
    }

    private function find(Request $request, int $id): Deal
    {
        return $this->mine($request)->whereKey($id)->firstOrFail();
    }
}
