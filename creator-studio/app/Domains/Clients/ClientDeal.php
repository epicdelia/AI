<?php

namespace App\Domains\Clients;

use App\Domains\Clients\Models\DealEvent;
use App\Domains\Deals\ProposalWriter;
use App\Http\Controllers\Api\StripePaymentController;
use App\Models\Deal;
use App\Models\Testimonial;

/**
 * A deal as its client sees it. An allow-list, never the model: her notes, talking points,
 * do-nots, call prep, lead score, attribution, tokens and internal rate estimates stay hers.
 * Her pipeline stages become a plain status and a progress step.
 */
class ClientDeal
{
    /**
     * The client's progress bar, in order.
     */
    public const STEPS = [
        'requested' => 'Requested',
        'proposal' => 'Proposal',
        'accepted' => 'Accepted',
        'in_progress' => 'In progress',
        'in_review' => 'In review',
        'invoiced' => 'Invoiced',
        'paid' => 'Paid',
    ];

    /**
     * Her stage → the client's step. `prospect` (a brand she might pitch) is never shown.
     */
    private const STAGE_TO_STEP = [
        'inquiry' => 'requested',
        'pitching' => 'proposal',
        'negotiating' => 'proposal',
        'contracted' => 'accepted',
        'production' => 'in_progress',
        'in_review' => 'in_review',
        'scheduled' => 'in_review',
        'invoiced' => 'invoiced',
        'paid' => 'paid',
    ];

    /**
     * Deliverable fields a client may see.
     */
    private const DELIVERABLE_KEYS = ['format', 'platform', 'service', 'due_date', 'status', 'url'];

    /**
     * Stages a client may see their deal in.
     */
    public static function visibleStages(): array
    {
        return array_values(array_diff(Deal::STAGES, ['prospect']));
    }

    public static function status(Deal $deal): array
    {
        if ($deal->stage === 'lost') {
            return [
                'key' => 'closed',
                'label' => 'Closed',
                'step' => null,
            ];
        }
        $step = self::STAGE_TO_STEP[$deal->stage] ?? 'requested';
        // A proposal that's been sent counts, even while she still calls it "inquiry".
        if ($step === 'requested' && $deal->proposal_at) {
            $step = 'proposal';
        }

        return [
            'key' => $step,
            'label' => self::STEPS[$step],
            'step' => array_search($step, array_keys(self::STEPS), true),
        ];
    }

    /**
     * Money is only shown once it's been put to them (a proposal, acceptance or invoice).
     */
    public static function amountShown(Deal $deal): bool
    {
        return (bool)$deal->proposal || $deal->accepted_at || in_array($deal->stage, ['contracted', 'production', 'in_review', 'scheduled', 'invoiced', 'paid'], true);
    }

    public static function canPay(Deal $deal): bool
    {
        return $deal->payment_status !== 'paid' && $deal->stage !== 'lost' && (float)$deal->rate > 0
            && ($deal->accepted_at || $deal->stage === 'invoiced') && StripePaymentController::configured();
    }

    /**
     * The list view.
     */
    public static function summary(Deal $deal): array
    {
        $p = $deal->proposal ?? [];
        $shown = self::amountShown($deal);

        return [
            'id' => $deal->id,
            'title' => $p['title'] ?? self::title($deal),
            'company' => $deal->brand_name,
            'status' => self::status($deal),
            'created_at' => $deal->created_at?->toIso8601String(),
            'updated_at' => ($deal->stage_changed_at ?? $deal->updated_at)?->toIso8601String(),
            'meeting_at' => $deal->meeting_at?->toIso8601String(),
            'amount' => $shown ? (float)($p['investment']['amount'] ?? $deal->rate) : null,
            'currency' => $p['investment']['currency'] ?? ($deal->currency ?: 'USD'),
            'payment_status' => $shown ? $deal->payment_status : null,
            'needs_you' => self::needsYou($deal),
            'unread' => $deal->messages()->where('from_admin', true)->whereNull('read_at')->count(),
        ];
    }

    /**
     * The detail view: everything in the summary plus proposal, deliverables and billing.
     */
    public static function detail(Deal $deal): array
    {
        $p = $deal->proposal;
        $review = Testimonial::where('deal_id', $deal->id)->where('status', 'requested')->whereNotNull('token')->value('token');

        return self::summary($deal) + [
            'steps' => array_map(fn ($k, $v) => [
                'key' => $k,
                'label' => $v,
            ], array_keys(self::STEPS), self::STEPS),
            'proposal' => $p ? [
                'markdown' => rescue(fn () => ProposalWriter::markdown($p), fn () => trim(($p['title'] ?? '') . '

' . ($p['summary'] ?? '')), false),
                'title' => $p['title'] ?? null,
            ] : null,
            'accepted_at' => $deal->accepted_at?->toIso8601String(),
            'accepted_by' => $deal->accepted_by,
            'signature_hash' => $deal->signature_hash,
            'signer_email' => $deal->signer_email,
            'signer_ip' => $deal->signer_ip,
            'add_ons' => $deal->add_ons ?? [],
            'annotations_count' => $deal->annotations()->where('status', 'open')->count(),
            'tiers' => self::amountShown($deal) ? \App\Domains\Deals\ProposalTiers::forDeal($deal)['tiers'] : [],
            'selected_tier' => self::amountShown($deal) ? ($deal->selected_tier ?: 'growth') : null,
            'deliverables' => self::deliverables($deal),
            'payment_terms' => self::amountShown($deal) ? $deal->payment_terms : null,
            'invoiced_at' => $deal->invoiced_at?->toIso8601String(),
            'paid_at' => $deal->paid_at?->toIso8601String(),
            'can_accept' => (bool)$p && !$deal->accepted_at && $deal->stage !== 'lost',
            'can_pay' => self::canPay($deal),
            'retainer' => $deal->subscription_status,
            'review_url' => $review ? url('/review/' . $review) : null,
            'can_review_work' => self::canReviewWork($deal),
            'approved_at' => $deal->approved_at?->toIso8601String(),
            'timeline' => self::timeline($deal),
            'files' => $deal->files()->with('user:id,name')->latest()->get()->map->toRow()->values(),
        ];
    }

    /**
     * What's waiting on the client: accept, pay, or review.
     */
    public static function needsYou(Deal $deal): ?string
    {
        if ($deal->stage === 'lost') {
            return null;
        }
        if ($deal->proposal && !$deal->accepted_at) {
            return 'Review and accept the proposal';
        }
        if (self::canReviewWork($deal)) {
            return 'Review the work: approve it or ask for changes';
        }
        if (self::canPay($deal)) {
            return 'Payment due';
        }
        if (Testimonial::where('deal_id', $deal->id)->where('status', 'requested')->whereNotNull('token')->exists()) {
            return 'Leave a review';
        }

        return null;
    }

    /**
     * Work waiting for the client's sign-off (her "In review" stage).
     */
    public static function canReviewWork(Deal $deal): bool
    {
        return $deal->stage === 'in_review';
    }

    /**
     * What happened when, worded for the client (the request date if nothing else was logged).
     */
    public static function timeline(Deal $deal): array
    {
        $events = DealEvent::where('deal_id', $deal->id)->oldest()->get()->map->toRow()->all();

        return $events ?: [[
            'kind' => 'requested',
            'label' => 'Request received',
            'at' => $deal->created_at?->toIso8601String(),
        ]];
    }

    private static function deliverables(Deal $deal): array
    {
        return array_values(array_map(
            fn ($d) => array_intersect_key(is_array($d) ? $d : [], array_flip(self::DELIVERABLE_KEYS)),
            array_filter($deal->deliverables ?? [], fn ($d) => is_array($d) && ($d['platform'] ?? null) !== 'meeting'),
        ));
    }

    private static function title(Deal $deal): string
    {
        foreach ($deal->deliverables ?? [] as $d) {
            if (is_array($d) && !empty($d['format'])) {
                return preg_replace('/^Intro Call:\s*/', '', (string)$d['format']);
            }
        }

        return $deal->brand_name;
    }
}
