<?php

namespace App\Domains\Audience;

use App\Domains\Social\SocialDashboard;
use App\Http\Controllers\Api\AudienceController;
use App\Models\Setting;
use App\Services\YouTube;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Daily snapshots of her own videos' numbers (studio:stats). With them, "views at day 7" is
 * the real day-7 number, and a 3-day-old video is compared with her other videos at day 3
 * instead of with their lifetime views. Tracking starts the day it's switched on: YouTube's
 * Data API has no history to fill in.
 */
class VideoStats
{
    public const MILESTONES = [1, 3, 7, 28];

    public function __construct(
        private YouTube $yt
    ) {
    }

    /**
     * Snapshot her recent long-form videos (about 3 quota units). Returns how many were saved.
     */
    public function snapshot(?CarbonImmutable $now = null): int
    {
        $channel = Setting::get('channel_id');
        if (!$channel) {
            throw new RuntimeException('Add your channel handle or ID in Settings.');
        }
        $now ??= CarbonImmutable::now('UTC');
        $report = $this->yt->channelReport($channel, $now);
        // My channel reads the same report; keep its cache fresh while we're at it.
        Cache::put(AudienceController::cacheKey($channel), $report + [
            'fetched_at' => $now->toIso8601String(),
        ], now()->addMinutes(AudienceController::CACHE_MINUTES));

        $today = $now->setTimezone(Setting::get('timezone', 'Europe/London'))->toDateString();
        foreach ($report['videos'] as $v) {
            DB::table('video_stats')->updateOrInsert(
                [
                    'video_id' => $v['id'],
                    'captured_on' => $today,
                ],
                [
                    'title' => mb_substr($v['title'], 0, 250),
                    'published_at' => CarbonImmutable::parse($v['published_at'])->utc(),
                    'age_days' => $v['age_days'],
                    'views' => $v['views'],
                    'likes' => $v['likes'] ?? 0,
                    'comments' => $v['comments'] ?? 0,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        // Subscribers too, so the analytics dashboard has a YouTube follower line without typing.
        if (($subs = $this->yt->subscribers($channel)) !== null) {
            SocialDashboard::recordFollowers([
                'youtube' => $subs,
            ], 'youtube');
        }

        return count($report['videos']);
    }

    /**
     * Views at about day N: the snapshot nearest that age, within a day. Null if none.
     */
    public static function viewsAt(string $videoId, int $day): ?int
    {
        $row = DB::table('video_stats')->where('video_id', $videoId)
            ->whereBetween('age_days', [$day - 1, $day + 1])
            ->orderByRaw('ABS(age_days - ?)', [$day])->first();

        return $row ? (int)$row->views : null;
    }

    /**
     * Her usual views at day N: the median across her videos with a snapshot near that age (3 or more).
     */
    public static function usualAt(int $day, ?string $except = null): ?float
    {
        $ids = DB::table('video_stats')->whereBetween('age_days', [$day - 1, $day + 1])
            ->when($except, fn ($q, $id) => $q->where('video_id', '!=', $id))->distinct()->pluck('video_id');
        $views = $ids->map(fn ($id) => self::viewsAt($id, $day))->filter(fn ($v) => $v !== null)->values()->all();

        return count($views) >= 3 ? YouTube::median($views) : null;
    }

    /**
     * [{age_days, views}] oldest first, for a sparkline.
     */
    public static function series(string $videoId): array
    {
        return DB::table('video_stats')->where('video_id', $videoId)->orderBy('age_days')
            ->get(['age_days', 'views'])->map(fn ($r) => [
                'age_days' => (float)$r->age_days,
                'views' => (int)$r->views,
            ])->all();
    }

    /**
     * Same-age comparison for one video: views at each milestone it has reached, her usual at
     * those milestones, and the pace at the latest milestone that has both (views / usual).
     */
    public static function compare(string $videoId): array
    {
        return self::compareAll([$videoId])[$videoId];
    }

    /**
     * compare() for many videos with one query: snapshots are loaded once and the milestones
     * and medians are worked out in memory (My channel shows up to 50 videos).
     *
     * @param  array<int, string>  $ids
     * @return array<string, array{at: array, pace: ?array, series: array}>
     */
    public static function compareAll(array $ids): array
    {
        $rows = DB::table('video_stats')->orderBy('age_days')->get(['video_id', 'age_days', 'views'])->groupBy('video_id');
        $near = function ($list, int $day): ?int {
            $best = null;
            foreach ($list as $r) {
                $d = abs((float)$r->age_days - $day);
                if ($d <= 1 && ($best === null || $d < abs((float)$best->age_days - $day))) {
                    $best = $r;
                }
            }

            return $best ? (int)$best->views : null;
        };
        $at = [];
        foreach ($rows as $id => $list) {
            foreach (self::MILESTONES as $day) {
                if (($v = $near($list, $day)) !== null) {
                    $at[$id][$day] = $v;
                }
            }
        }

        $out = [];
        foreach ($ids as $id) {
            $res = [
                'at' => [],
                'pace' => null,
                'series' => ($rows[$id] ?? collect())->map(fn ($r) => [
                    'age_days' => (float)$r->age_days,
                    'views' => (int)$r->views,
                ])->values()->all(),
            ];
            foreach (self::MILESTONES as $day) {
                if (!isset($at[$id][$day])) {
                    continue;
                }
                $others = [];
                foreach ($at as $other => $m) {
                    if ($other !== $id && isset($m[$day])) {
                        $others[] = $m[$day];
                    }
                }
                $usual = count($others) >= 3 ? YouTube::median($others) : null;
                $res['at'][$day] = [
                    'views' => $at[$id][$day],
                    'usual' => $usual,
                ];
                if ($usual) {
                    $res['pace'] = [
                        'day' => $day,
                        'ratio' => round($at[$id][$day] / $usual, 2),
                    ];
                }
            }
            $out[$id] = $res;
        }

        return $out;
    }

    public static function tracking(): bool
    {
        return DB::table('video_stats')->exists();
    }
}
