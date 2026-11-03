<?php

namespace App\Domains\Social;

use App\Domains\Social\Models\SocialPost;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * When to post: the posting queue (what's due now) and the next good slot for a post. Good
 * slots come from her own best times once she has enough posts with numbers on that network;
 * before that, a plain starter time per network, labelled as such (not evidence).
 */
class SocialScheduler
{
    /**
     * Starter times until her own data exists: [ISO weekdays], 'HH:MM'.
     */
    public const STARTERS = [
        'linkedin' => [[2, 3, 4], '08:30'],
        'instagram' => [[1, 2, 3, 4, 5, 6, 7], '19:00'],
        'x' => [[1, 2, 3, 4, 5], '12:30'],
        'tiktok' => [[1, 2, 3, 4, 5, 6, 7], '20:00'],
        'threads' => [[1, 2, 3, 4, 5, 6, 7], '10:00'],
        'youtube' => [[1, 3, 5], '17:00'],
    ];

    public const GAP_HOURS = 3; // never two posts on the same network this close together

    /**
     * Middle hour of each best-times block (SocialAnalytics::BLOCKS).
     */
    private const BLOCK_TIME = ['07:30', '10:00', '13:00', '16:30', '19:00', '22:00'];

    /**
     * Scheduled posts due now or overdue, and what's coming up in the next 24 hours.
     */
    public static function due(): array
    {
        $now = now();
        $scheduled = SocialPost::where('status', 'scheduled')->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', $now->copy()->addDay())->orderBy('scheduled_for')->get();

        return [
            'due' => $scheduled->filter(fn ($p) => $p->scheduled_for->lte($now->copy()->addMinutes(10)))->values(),
            'upcoming' => $scheduled->filter(fn ($p) => $p->scheduled_for->gt($now->copy()->addMinutes(10)))->values(),
            'now' => $now->toIso8601String(),
        ];
    }

    /**
     * The next good, free time for a post on this network after $after.
     *
     * @param  Collection<int, SocialPost>|null  $taken  extra posts to treat as booked (planning a batch)
     * @return array{at: CarbonImmutable, reason: string, source: string}
     */
    public static function nextSlot(string $platform, ?CarbonImmutable $after = null, ?int $exceptId = null, ?Collection $taken = null): array
    {
        $tz = SocialAnalytics::tz();
        $after = ($after ?? CarbonImmutable::now('UTC')->addMinutes(30))->setTimezone($tz);
        [$candidates, $source] = self::candidates($platform);

        $booked = SocialPost::where('platform', $platform)->whereIn('status', ['scheduled', 'posted'])
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->where(fn ($q) => $q->where('scheduled_for', '>=', $after->subDay()->utc())->orWhere('posted_at', '>=', $after->subDay()->utc()))
            ->get()->map(fn ($p) => ($p->status === 'posted' ? $p->posted_at : $p->scheduled_for)?->setTimezone($tz))->filter();
        foreach ($taken ?? [] as $t) {
            if ($t->platform === $platform && $t->scheduled_for) {
                $booked->push(CarbonImmutable::parse($t->scheduled_for)->setTimezone($tz));
            }
        }
        $free = fn (CarbonImmutable $at) => $booked->every(fn ($b) => abs($b->diffInMinutes($at, false)) >= self::GAP_HOURS * 60)
            && $booked->filter(fn ($b) => $b->isSameDay($at))->isEmpty();

        // The first free time for each candidate, then: the best-ranked one that's free within a
        // week (a strong slot on Tuesday beats a weak one on Monday), else the earliest.
        $options = [];
        foreach ($candidates as $rank => [$weekdays, $time, $why]) {
            for ($d = 0; $d < 28; $d++) {
                $day = $after->startOfDay()->addDays($d);
                if (!in_array($day->dayOfWeekIso, $weekdays, true)) {
                    continue;
                }
                $at = $day->setTimeFromTimeString($time);
                if ($at->gt($after) && $free($at)) {
                    $options[] = [
                        'rank' => $rank,
                        'at' => $at,
                        'why' => $why,
                    ];
                    break;
                }
            }
        }
        if ($options) {
            $soon = array_filter($options, fn ($o) => $o['at']->lte($after->addDays(7)));
            usort($soon, fn ($a, $b) => $a['rank'] <=> $b['rank']);
            usort($options, fn ($a, $b) => $a['at'] <=> $b['at']);
            $pick = $soon[0] ?? $options[0];

            return [
                'at' => $pick['at']->utc(),
                'reason' => $pick['why'],
                'source' => $source,
            ];
        }
        // Fully booked for four weeks: the first time after the last booking.
        $at = ($booked->max() ?? $after)->addHours(self::GAP_HOURS + 1);

        return [
            'at' => $at->utc(),
            'reason' => 'Your calendar is full, so the first free time after it.',
            'source' => 'fallback',
        ];
    }

    /**
     * How many posts a week she aims for on each network (Settings → Social).
     */
    public static function cadence(): array
    {
        $out = [];
        foreach (array_keys(Platforms::PLATFORMS) as $p) {
            $out[$p] = (int)Setting::get("cadence_{$p}", '0');
        }

        return $out;
    }

    /**
     * Ranked [weekdays, time, reason] from her data, or the starter time.
     */
    private static function candidates(string $platform): array
    {
        $bt = SocialAnalytics::bestTimes($platform);
        if (($bt['enough'] ?? false) && $bt['best']) {
            $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            $out = [];
            $top = $bt['best'][0]['median'];
            foreach (array_filter($bt['best'], fn ($b) => $b['median'] >= $top * 0.6) as $slot) {
                $out[] = [[$slot['day'] + 1], self::BLOCK_TIME[$slot['block']], "One of your best slots: {$days[$slot['day']]} " . strtolower($bt['blocks'][$slot['block']]) . " (median {$slot['median']} {$bt['metric']} over {$slot['posts']} posts)."];
            }

            return [$out, 'your data'];
        }
        [$weekdays, $time] = self::STARTERS[$platform] ?? [[1, 2, 3, 4, 5], '12:00'];

        return [[[$weekdays, $time, 'A starter time: you don’t have 8 ' . (Platforms::PLATFORMS[$platform]['label'] ?? $platform) . ' posts with numbers yet, so this isn’t from your data.']], 'starter'];
    }
}
