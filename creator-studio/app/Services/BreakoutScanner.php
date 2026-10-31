<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\Outlier;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use RuntimeException;

/** Scan the active watchlist for breakout videos (used by the Scan button and the daily schedule). */
class BreakoutScanner
{
    public function __construct(private YouTube $yt) {}

    /** @return int new breakouts found */
    public function scan(): int
    {
        $channels = Channel::where('active', true)->get();
        if ($channels->isEmpty()) {
            throw new RuntimeException('Please add active competitor channels first.');
        }

        @set_time_limit(300);

        $out = $this->yt->findOutliers($channels->pluck('handle')->all(), [
            'lookback_days' => Setting::get('lookback_days', '4'),
            'min_outlier_score' => Setting::get('min_outlier_score', '3'),
            'min_views' => Setting::get('min_views', '20000'),
        ], null, Outlier::pluck('video_id')->all());

        $byHandle = $channels->keyBy('handle');
        foreach ($out['channels'] as $handle => $info) {
            $ch = $byHandle->get($handle);
            $ch?->update([
                'title' => $info['title'] ?? $ch->title,
                'last_error' => $info['error'],
                'last_scanned_at' => now(),
            ]);
        }

        $created = 0;
        foreach ($out['results'] as $v) {
            Outlier::create([
                'video_id' => $v['id'],
                'channel_id' => $byHandle->get($v['handle'])?->id,
                'channel_title' => $v['channel'],
                'title' => $v['title'],
                'url' => $v['url'],
                'views' => $v['views'],
                'channel_median' => (int) $v['channel_median'],
                'outlier_score' => $v['outlier_score'],
                'age_days' => round($v['age_days'], 1),
                'published_at' => CarbonImmutable::parse($v['published_at']),
            ]);
            $created++;
        }

        return $created;
    }
}
