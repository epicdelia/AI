<?php

namespace App\Domains\Landing;

use App\Models\Deal;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The landing page funnel: visits → clicked "Book a call" / a package → started the form →
 * sent a request → paid. The first three are counted per day and source in landing_events
 * (no IPs, no cookies: the page sends "visit" once per browser session). Requests and
 * payments come from Deals, so they always match her pipeline.
 */
class LandingFunnel
{
    public const EVENTS = ['visit', 'cta', 'form_start'];

    public static function record(string $event, string $source = 'direct'): void
    {
        if (! in_array($event, self::EVENTS, true)) {
            return;
        }
        $source = isset(Attribution::LABELS[$source]) ? $source : 'other';
        DB::table('landing_events')->upsert(
            [['day' => now(Setting::get('timezone'))->toDateString(), 'event' => $event, 'source' => $source, 'count' => 1]],
            ['day', 'event', 'source'], ['count' => DB::raw('count + 1')],
        );
    }

    public static function summary(int $days = 30): array
    {
        $tz = Setting::get('timezone') ?: 'UTC';
        $from = CarbonImmutable::now($tz)->startOfDay()->subDays($days - 1);
        $events = DB::table('landing_events')->where('day', '>=', $from->toDateString())->get();
        $count = fn (string $e) => (int) $events->where('event', $e)->sum('count');

        $leads = Deal::whereNotNull('source')->where('created_at', '>=', $from->utc())->get(['id', 'source', 'rate', 'stage', 'paid_at', 'created_at']);
        $paid = Deal::whereNotNull('source')->where('payment_status', 'paid')->where('paid_at', '>=', $from->utc())->get(['id', 'source', 'rate']);

        $visits = $count('visit');
        $steps = [
            ['key' => 'visit', 'label' => 'Visits', 'count' => $visits],
            ['key' => 'cta', 'label' => 'Clicked book or buy', 'count' => $count('cta')],
            ['key' => 'form_start', 'label' => 'Started a form', 'count' => $count('form_start')],
            ['key' => 'lead', 'label' => 'Sent a request', 'count' => $leads->count()],
            ['key' => 'paid', 'label' => 'Paid', 'count' => $paid->count()],
        ];
        foreach ($steps as $i => &$s) {
            $prev = $i ? $steps[$i - 1]['count'] : null;
            $s['of_previous'] = $prev ? round($s['count'] / $prev * 100, 1) : null;
            $s['of_visits'] = $visits && $i ? round($s['count'] / $visits * 100, 2) : null;
        }
        unset($s);

        $sources = $events->where('event', 'visit')->groupBy('source')->map(fn ($r) => (int) $r->sum('count'));
        $bySource = collect(array_unique([...$sources->keys(), ...$leads->pluck('source'), ...$paid->pluck('source')]))
            ->map(fn ($src) => [
                'source' => $src,
                'label' => Attribution::label($src),
                'visits' => $sources[$src] ?? 0,
                'leads' => $leads->where('source', $src)->count(),
                'paid' => $paid->where('source', $src)->count(),
                'revenue' => round((float) $paid->where('source', $src)->sum('rate'), 2),
                'conversion' => ($sources[$src] ?? 0) ? round($leads->where('source', $src)->count() / $sources[$src] * 100, 2) : null,
            ])->sortByDesc(fn ($r) => [$r['revenue'], $r['leads'], $r['visits']])->values();

        $series = [];
        for ($d = $from; $d <= CarbonImmutable::now($tz); $d = $d->addDay()) {
            $day = $d->toDateString();
            $series[] = [
                'day' => $day,
                'visits' => (int) $events->where('event', 'visit')->where('day', $day)->sum('count'),
                'leads' => $leads->filter(fn ($l) => $l->created_at->setTimezone($tz)->toDateString() === $day)->count(),
            ];
        }

        return [
            'days' => $days,
            'steps' => $steps,
            'by_source' => $bySource,
            'series' => $series,
            'revenue' => round((float) $paid->sum('rate'), 2),
            'tracking_since' => DB::table('landing_events')->min('day'),
        ];
    }
}
