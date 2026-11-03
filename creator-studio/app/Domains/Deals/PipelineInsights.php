<?php

namespace App\Domains\Deals;

use App\Domains\Landing\Attribution;
use App\Models\Deal;
use App\Services\YouTube;
use Illuminate\Support\Collection;

/**
 * What her own closed deals say: win rate (paid ÷ paid + lost), days from first contact to
 * paid, average and median deal size, which sources and deal types win, why deals are lost,
 * and what brands actually paid her next to the rate card's suggestion. Below 3 closed deals it
 * says it needs more history instead of showing shaky percentages.
 */
class PipelineInsights
{
    public const MIN_CLOSED = 3;

    public static function summary(int $days = 365): array
    {
        $deals = Deal::where('created_at', '>=', now()->subDays($days))->whereNotIn('stage', ['prospect'])->get();
        $won = $deals->where('stage', 'paid');
        $lost = $deals->where('stage', 'lost');
        $closed = $won->count() + $lost->count();

        $closeDays = $won->filter(fn ($d) => $d->paid_at)->map(fn ($d) => max(0, $d->created_at->diffInDays($d->paid_at)))->values()->all();
        $rate = fn (Collection $w, Collection $l) => ($w->count() + $l->count()) >= self::MIN_CLOSED ? round($w->count() / ($w->count() + $l->count()) * 100) : null;
        $type = fn (Deal $d) => self::type($d);

        $groups = fn (callable $key, callable $label) => $deals->groupBy($key)->map(function ($g, $k) use ($label, $rate) {
            $w = $g->where('stage', 'paid');

            return [
                'key' => $k,
                'label' => $label($k),
                'deals' => $g->count(),
                'won' => $w->count(),
                'lost' => $g->where('stage', 'lost')->count(),
                'win_rate' => $rate($w, $g->where('stage', 'lost')),
                'revenue' => round((float)$w->sum('rate'), 2),
                'avg_won' => $w->count() ? round((float)$w->avg('rate')) : null,
            ];
        })->sortByDesc(fn ($r) => [$r['revenue'], $r['won'], $r['deals']])->values();

        $brandPaid = $won->filter(fn ($d) => self::type($d) === 'brand')->pluck('rate')->map(fn ($v) => (float)$v)->all();
        $rateCard = rescue(fn () => DealCalculator::calculateRates(), null, false);
        $suggested = collect($rateCard['rates'] ?? [])->pluck('rate')->filter()->values();

        return [
            'days' => $days,
            'enough' => $closed >= self::MIN_CLOSED,
            'needed' => self::MIN_CLOSED,
            'closed' => $closed,
            'won' => $won->count(),
            'lost' => $lost->count(),
            'open' => $deals->whereNotIn('stage', Deal::NOT_PIPELINE)->count(),
            'win_rate' => $rate($won, $lost),
            'revenue' => round((float)$won->sum('rate'), 2),
            'avg_deal' => $won->count() ? round((float)$won->avg('rate')) : null,
            'median_deal' => $won->count() ? round(YouTube::median($won->pluck('rate')->map(fn ($v) => (float)$v)->all())) : null,
            'median_days_to_close' => $closeDays ? (int)round(YouTube::median($closeDays)) : null,
            'by_source' => $groups(fn ($d) => $d->source ?? 'manual', fn ($k) => $k === 'manual' ? 'Added by hand' : Attribution::label($k)),
            'by_type' => $groups($type, fn ($k) => [
                'client' => 'Client work',
                'brand' => 'Brand deals',
                'speaking' => 'Speaking',
            ][$k] ?? ucfirst($k)),
            'lost_reasons' => $lost->groupBy(fn ($d) => $d->lost_reason ?: 'unknown')->map(fn ($g, $k) => [
                'reason' => $k,
                'label' => Deal::LOST_REASONS[$k] ?? 'No reason given',
                'count' => $g->count(),
            ])
                ->sortByDesc('count')->values(),
            'brand_rates' => [
                'paid_median' => $brandPaid ? round(YouTube::median($brandPaid)) : null,
                'paid_count' => count($brandPaid),
                'rate_card_median' => $suggested->isNotEmpty() ? round(YouTube::median($suggested->all())) : null,
            ],
        ];
    }

    /**
     * client (website services), brand (sponsorships) or speaking.
     */
    public static function type(Deal $d): string
    {
        $first = is_array($d->deliverables) ? ($d->deliverables[0] ?? []) : [];
        $pt = is_array($first) ? ($first['project_type'] ?? null) : null;

        return match (true) {
            $pt === 'speaking' => 'speaking',
            $pt === 'brand_deal' => 'brand',
            in_array($pt, ['mvp_build', 'retainer', 'consulting', 'other'], true), ($first['platform'] ?? null) === 'stripe' => 'client',
            $d->source !== null && $pt === null && ($first['platform'] ?? null) === 'meeting' => 'client',
            default => 'brand', // added in Deals by hand: sponsorships
        };
    }
}
