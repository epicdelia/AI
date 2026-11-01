<?php

namespace App\Domains\Deals;

use App\Domains\Landing\Attribution;
use App\Models\Deal;
use App\Models\Testimonial;
use App\Models\Setting;
use App\Services\Llm;
use App\Services\Prompts;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Deals that have gone quiet: a website lead nobody answered, a checkout that wasn't paid, a
 * proposal with no reply, a negotiation that stalled, an invoice past its terms, a brand that
 * just opened her media kit, or a happy client a week after paying (ask for a testimonial). Counted from the last touch (created, stage moved, or "I followed up"). Drafts are
 * for her to copy and send herself: nothing is ever emailed from the studio.
 */
class FollowUps
{
    /** Stage => [days quiet before it's due, why]. */
    public const RULES = [
        'inquiry' => [2, 'New lead waiting for a reply'],
        'pitching' => [5, 'Proposal sent, no reply yet'],
        'negotiating' => [4, 'Negotiation has gone quiet'],
        'invoiced' => [30, 'Invoice unpaid past its terms'],
    ];

    /** Days after payment to ask a client for a testimonial. */
    public const TESTIMONIAL_AFTER = 7;

    /** @return Collection<int, array{deal: Deal, days: int, reason: string, kind: string}> */
    public static function due(): Collection
    {
        return Deal::where(fn ($q) => $q->whereNotIn('stage', ['paid', 'lost'])
            ->orWhere(fn ($q) => $q->where('paid_at', '<=', now()->subDays(self::TESTIMONIAL_AFTER))->where('paid_at', '>=', now()->subDays(120))
                ->whereNotIn('id', Testimonial::whereNotNull('deal_id')->select('deal_id'))))
            ->where(fn ($q) => $q->whereNull('follow_up_snoozed_until')->orWhere('follow_up_snoozed_until', '<=', now()))
            ->get()
            ->map(fn (Deal $d) => self::check($d))
            ->filter()
            ->sortByDesc(fn ($r) => [in_array($r['kind'], ['kit_opened', 'proposal_opened', 'checkout'], true) ? 1 : 0, $r['days'] * max(1, (float) $r['deal']->rate)])
            ->values();
    }

    public static function check(Deal $deal): ?array
    {
        $touch = $deal->lastTouchAt();

        if ($deal->stage === 'paid') {
            $paidDays = $deal->paid_at ? (int) floor($deal->paid_at->diffInDays(now())) : 0;

            return $deal->paid_at && $paidDays >= self::TESTIMONIAL_AFTER && ! Testimonial::where('deal_id', $deal->id)->exists()
                ? ['deal' => $deal, 'days' => $paidDays, 'kind' => 'testimonial', 'reason' => "Paid {$paidDays} days ago: ask for a testimonial"]
                : null;
        }

        // Started a checkout on the website and didn't pay: the warmest lead there is.
        $first = is_array($deal->deliverables) ? ($deal->deliverables[0] ?? []) : [];
        if ($deal->stage === 'inquiry' && ($first['platform'] ?? null) === 'stripe' && $deal->payment_status !== 'paid' && $touch->lte(now()->subDay())) {
            return ['deal' => $deal, 'days' => (int) floor($touch->diffInDays(now())), 'kind' => 'checkout', 'reason' => 'Started a checkout but didn’t pay'];
        }

        // Opening her proposal page (without accepting yet) after she last wrote: follow up now.
        if ($deal->room_viewed_at && ! $deal->accepted_at && $deal->room_viewed_at->greaterThan($touch)) {
            return ['deal' => $deal, 'days' => (int) $touch->diffInDays(now()), 'kind' => 'proposal_opened',
                'reason' => 'Opened your proposal '.$deal->room_viewed_at->diffForHumans()];
        }

        // A brand opening her media kit after she last wrote is the best moment to follow up.
        if ($deal->kit_viewed_at && $deal->kit_viewed_at->greaterThan($touch)) {
            return ['deal' => $deal, 'days' => (int) $touch->diffInDays(now()), 'kind' => 'kit_opened',
                'reason' => 'Opened your media kit '.$deal->kit_viewed_at->diffForHumans()];
        }
        $rule = self::RULES[$deal->stage] ?? null;
        if (! $rule) {
            return null;
        }
        $since = $deal->stage === 'invoiced' ? ($deal->invoiced_at ?? $touch) : $touch;
        $days = (int) floor($since->diffInDays(now()));
        if ($days < $rule[0]) {
            return null;
        }

        return ['deal' => $deal, 'days' => $days, 'kind' => $deal->stage, 'reason' => $rule[1]];
    }

    /** For the API, the brief and the Copilot. */
    public static function list(int $limit = 20): array
    {
        return self::due()->take($limit)->map(fn ($r) => [
            'deal_id' => $r['deal']->id,
            'brand_name' => $r['deal']->brand_name,
            'contact_name' => $r['deal']->contact_name,
            'contact_email' => $r['deal']->contact_email,
            'stage' => $r['deal']->stage,
            'rate' => (float) $r['deal']->rate,
            'currency' => $r['deal']->currency ?: 'USD',
            'source' => $r['deal']->source ? Attribution::label($r['deal']->source) : null,
            'days_quiet' => $r['days'],
            'kind' => $r['kind'],
            'reason' => $r['reason'],
            'lead_score' => $r['deal']->lead_score,
            'last_contacted_at' => $r['deal']->last_contacted_at?->toIso8601String(),
        ])->values()->all();
    }

    /** A follow-up email in her voice: {subject, body}. Falls back to a plain template. */
    public static function draft(Deal $deal, Llm $llm, ?string $angle = null): array
    {
        $check = self::check($deal);
        $name = Prompts::name();
        $review = $deal->stage === 'paid' ? Testimonial::requestFor($deal)->url() : null;
        $first = trim(explode(' ', (string) $deal->contact_name)[0] ?? '') ?: 'there';
        $context = implode("\n", array_filter([
            "Company: {$deal->brand_name}",
            $deal->contact_name ? "Contact: {$deal->contact_name}" : null,
            "Stage: {$deal->stage}",
            $deal->rate ? 'Fee discussed: '.($deal->currency ?: 'USD').' '.number_format((float) $deal->rate) : null,
            $check ? "Why now: {$check['reason']} ({$check['days']} days since the last touch)" : null,
            $deal->proposal ? 'Proposal sent: '.($deal->proposal['title'] ?? 'yes') : null,
            $deal->meeting_at ? 'Intro call: '.$deal->meeting_at->setTimezone(Setting::get('timezone') ?: 'UTC')->format('D j M, H:i T') : null,
            $deal->key_talking_points ? "What they asked about: {$deal->key_talking_points}" : null,
            $deal->notes ? 'Notes: '.mb_substr($deal->notes, 0, 1200) : null,
            $review ? "This is a thank-you and testimonial request. Their private review link (include it exactly): {$review}" : null,
            ($check['kind'] ?? null) === 'checkout' ? 'They started paying online for the package above and stopped: ask if anything was unclear, offer an invoice or a quick call instead.' : null,
            $angle ? "Her angle for this email: {$angle}" : null,
        ]));

        $system = Prompts::profile()."\n\n".Prompts::writingRules()."\n\nYou write short follow-up emails for {$name}'s client and sponsor pipeline. ".
            'Rules: under 120 words; warm, direct, no guilt-tripping and no "just circling back"; one clear next step (a time, a yes/no, or the payment); '.
            'sign off with her first name. For an unpaid invoice be polite and specific about the amount and ask if anything is blocking it. '.
            'Never invent facts, dates, prices or results that are not in the context. Return JSON: {"subject": "...", "body": "..."}';

        try {
            $r = $llm->as('deal:follow_up')->json($system, $context);
            if (! empty($r['subject']) && ! empty($r['body'])) {
                return ['subject' => (string) $r['subject'], 'body' => (string) $r['body'], 'ai' => true];
            }
        } catch (Throwable) {
            // fall through to the template
        }

        $sign = explode(' ', $name)[0];
        $body = match (true) {
            $review !== null => "Hi {$first},

Thank you again for working with me on {$deal->brand_name}. If you have two minutes, a few words about how it went would mean a lot (and help others decide):
{$review}

No pressure at all, and thank you!
{$sign}",
            ($check['kind'] ?? null) === 'checkout' => "Hi {$first},

I saw you started booking with me but didn't finish the payment. Was anything unclear? Happy to send an invoice instead, or jump on a quick call to answer questions.

Best,
{$sign}",
            default => null,
        } ?? match ($deal->stage) {
            'invoiced' => "Hi {$first},\n\nA quick note on the invoice for {$deal->brand_name}".($deal->rate ? ' ('.($deal->currency ?: 'USD').' '.number_format((float) $deal->rate).')' : '').". It's now past its terms on my side. Is anything blocking it, or is there someone in accounts I should send it to?\n\nThanks,\n{$sign}",
            'pitching' => "Hi {$first},\n\nI wanted to make sure my proposal reached you. Happy to adjust the scope or timing if anything doesn't fit. Would a 15-minute call this week help?\n\nBest,\n{$sign}",
            'negotiating' => "Hi {$first},\n\nPicking our conversation back up: where did you land on the details? If it helps, I can hold the slot for another week while you check internally.\n\nBest,\n{$sign}",
            default => "Hi {$first},\n\nThanks for reaching out about {$deal->brand_name}. I'd love to hear more. Does a 30-minute intro call later this week work for you? Send me two times that suit and I'll confirm one.\n\nBest,\n{$sign}",
        };

        return ['subject' => "Re: {$deal->brand_name} × {$name}", 'body' => $body, 'ai' => false];
    }
}
