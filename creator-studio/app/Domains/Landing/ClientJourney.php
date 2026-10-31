<?php

namespace App\Domains\Landing;

use App\Models\Deal;
use App\Models\Setting;

/**
 * The landing page's "How it works" phases, mapped onto her deal stages, so the page and her
 * dashboard tell the same story: how many clients are in each phase, and whether a build slot
 * is open. The public page only ever sees counts, never who.
 */
class ClientJourney
{
    /** Phase => the deal stages it covers (Deal::STAGES). Paid deals count as shipped. */
    public const PHASES = [
        'diagnostic' => ['label' => 'Diagnostic', 'stages' => ['inquiry']],
        'blueprint' => ['label' => 'Blueprint', 'stages' => ['pitching', 'negotiating']],
        'execution' => ['label' => 'Execution', 'stages' => ['contracted', 'production', 'in_review']],
        'deployment' => ['label' => 'Deployment', 'stages' => ['scheduled', 'invoiced']],
    ];

    public const DEFAULT_CAPACITY = 2;

    /** Builds she takes on at once; 0 hides availability from the landing page. */
    public static function capacity(): int
    {
        return max(0, min(10, (int) Setting::get('build_capacity', (string) self::DEFAULT_CAPACITY)));
    }

    /** For her dashboard: deals per phase and capacity. */
    public static function summary(): array
    {
        $byStage = Deal::query()->selectRaw('stage, count(*) as n')->groupBy('stage')->pluck('n', 'stage');
        $phases = [];
        foreach (self::PHASES as $key => $p) {
            $phases[] = ['key' => $key, 'label' => $p['label'], 'stages' => $p['stages'],
                'count' => (int) collect($p['stages'])->sum(fn ($s) => $byStage[$s] ?? 0)];
        }

        return [
            'phases' => $phases,
            'shipped_90d' => Deal::where('stage', 'paid')->where('paid_at', '>=', now()->subDays(90))->count(),
        ] + self::availability();
    }

    /** For the public page: is a build slot open? */
    public static function availability(): array
    {
        $capacity = self::capacity();
        $active = Deal::whereIn('stage', self::PHASES['execution']['stages'])->count();
        $open = max(0, $capacity - $active);

        return [
            'show' => $capacity > 0,
            'capacity' => $capacity,
            'active' => $active,
            'open' => $open,
            'status' => $open > 0 ? 'open' : 'waitlist',
            'label' => $capacity === 0 ? null : ($open > 0
                ? ($open === 1 ? '1 build slot open' : "{$open} build slots open")
                : 'Fully booked: join the waitlist'),
        ];
    }
}
