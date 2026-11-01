<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Port of ideas/find_outliers.py and ideas/channel_stats.py.
 *
 * Outlier score = a video's views / median views of that channel's older long-form uploads.
 * Quota: channels.list, playlistItems.list and videos.list only (1 unit each), about 3 units per
 * channel. search.list costs 100 units per call and is deliberately avoided.
 */
class YouTube
{
    public const API = 'https://www.googleapis.com/youtube/v3/';
    public const SHORTS_MAX_SECONDS = 180; // Shorts can run up to 3 minutes

    /** @var callable|null test hook: fn(string $endpoint, array $params): array */
    public $getter = null;

    public function get(string $endpoint, array $params): array
    {
        if ($this->getter) {
            return ($this->getter)($endpoint, $params);
        }
        $key = Setting::get('youtube_api_key');
        if (! $key) {
            throw new RuntimeException('No YouTube API key yet. Add one under Settings.');
        }
        $resp = Http::timeout(30)->get(self::API.$endpoint, $params + ['key' => $key]);
        if ($resp->failed()) {
            $msg = $resp->json('error.message') ?? $resp->body();
            throw new RuntimeException("YouTube API error ({$resp->status()}): ".mb_substr(strip_tags($msg), 0, 200));
        }

        return $resp->json() ?? [];
    }

    /** ISO 8601 duration (PT1H2M3S) -> seconds. */
    public static function parseDuration(?string $iso): int
    {
        if (! preg_match('/^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', (string) $iso, $m)) {
            return 0;
        }
        [$d, $h, $mi, $s] = array_map(fn ($x) => (int) ($x ?? 0), array_pad(array_slice($m, 1), 4, 0));

        return $d * 86400 + $h * 3600 + $mi * 60 + $s;
    }

    /** @return array{0: ?string, 1: ?string} [uploads playlist ID, channel title] */
    public function channelUploads(string $handle): array
    {
        $key = preg_match('/^UC[\w-]{22}$/', $handle) ? 'id' : 'forHandle';
        $data = $this->get('channels', ['part' => 'contentDetails,snippet', $key => $handle]);
        $ch = $data['items'][0] ?? null;
        if (! $ch) {
            return [null, null];
        }

        return [$ch['contentDetails']['relatedPlaylists']['uploads'], $ch['snippet']['title']];
    }

    public function recentVideos(string $playlistId, int $limit = 50): array
    {
        $items = $this->get('playlistItems', ['part' => 'contentDetails', 'playlistId' => $playlistId, 'maxResults' => $limit])['items'] ?? [];
        $ids = array_map(fn ($it) => $it['contentDetails']['videoId'], $items);
        if (! $ids) {
            return [];
        }
        $data = $this->get('videos', ['part' => 'snippet,statistics,contentDetails', 'id' => implode(',', $ids)]);

        return array_map(fn ($v) => [
            'id' => $v['id'],
            'title' => $v['snippet']['title'],
            'published_at' => $v['snippet']['publishedAt'],
            'views' => (int) ($v['statistics']['viewCount'] ?? 0),
            'comments' => (int) ($v['statistics']['commentCount'] ?? 0),
            'likes' => (int) ($v['statistics']['likeCount'] ?? 0),
            'seconds' => self::parseDuration($v['contentDetails']['duration'] ?? ''),
            'description' => mb_substr((string) ($v['snippet']['description'] ?? ''), 0, 5000),
        ], $data['items'] ?? []);
    }

    /** Breakout candidates for one channel's recent uploads. */
    public static function scoreChannel(array $videos, CarbonImmutable $now, float $lookbackDays, float $minAgeDays = 2, int $minBaseline = 5): array
    {
        $longForm = [];
        foreach ($videos as $v) {
            if ($v['seconds'] > self::SHORTS_MAX_SECONDS) {
                $v['age_days'] = ($now->getTimestamp() - CarbonImmutable::parse($v['published_at'])->getTimestamp()) / 86400;
                $longForm[] = $v;
            }
        }
        $baseline = array_values(array_map(fn ($v) => $v['views'], array_filter($longForm, fn ($v) => $v['age_days'] > $lookbackDays)));
        if (count($baseline) < $minBaseline) {
            return [];
        }
        $median = self::median($baseline);
        if ($median <= 0) {
            return [];
        }
        $out = [];
        foreach ($longForm as $v) {
            if ($v['age_days'] >= $minAgeDays && $v['age_days'] <= $lookbackDays) {
                $out[] = $v + ['channel_median' => $median, 'outlier_score' => round($v['views'] / $median, 2)];
            }
        }

        return $out;
    }

    public static function median(array $values): float
    {
        sort($values);
        $n = count($values);
        if ($n === 0) {
            return 0;
        }
        $mid = intdiv($n, 2);

        return $n % 2 ? (float) $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }

    /**
     * Scan every handle. Returns ['results' => [...], 'channels' => [handle => ['title'=>..,'error'=>..]]].
     */
    public function findOutliers(array $handles, array $config = [], ?CarbonImmutable $now = null, array $seen = []): array
    {
        $now ??= CarbonImmutable::now('UTC');
        $lookback = (float) ($config['lookback_days'] ?? 14);
        $minScore = (float) ($config['min_outlier_score'] ?? 3);
        $minViews = (int) ($config['min_views'] ?? 20000);
        $results = [];
        $channels = [];
        $sponsored = []; // every scanned upload with a description, for sponsor prospecting
        foreach ($handles as $handle) {
            try {
                [$playlist, $title] = $this->channelUploads($handle);
                if (! $playlist) {
                    $channels[$handle] = ['title' => null, 'error' => 'Channel not found'];

                    continue;
                }
                $channels[$handle] = ['title' => $title, 'error' => null];
                $videos = $this->recentVideos($playlist);
                foreach ($videos as $v) {
                    if (($v['description'] ?? '') !== '') {
                        $sponsored[] = $v + ['channel' => $title];
                    }
                }
                foreach (self::scoreChannel($videos, $now, $lookback) as $v) {
                    if (in_array($v['id'], $seen, true) || $v['outlier_score'] < $minScore || $v['views'] < $minViews) {
                        continue;
                    }
                    $results[] = $v + ['channel' => $title, 'handle' => $handle, 'url' => "https://youtu.be/{$v['id']}"];
                }
            } catch (RuntimeException $e) {
                $channels[$handle] = ['title' => null, 'error' => $e->getMessage()];
            }
        }
        usort($results, fn ($a, $b) => $b['outlier_score'] <=> $a['outlier_score']);

        return ['results' => $results, 'channels' => $channels, 'videos' => $sponsored];
    }

    /**
     * Her recent long-form uploads, each scored against her own median (the breakout maths
     * pointed at her channel). Only videos at least $matureDays old form the baseline, so a
     * video from yesterday isn't counted against her usual.
     */
    public function channelReport(string $channel, ?CarbonImmutable $now = null, float $matureDays = 7): array
    {
        $now ??= CarbonImmutable::now('UTC');
        [$playlist, $title] = $this->channelUploads($channel);
        if (! $playlist) {
            throw new RuntimeException("Channel not found: {$channel}");
        }

        $videos = [];
        foreach ($this->recentVideos($playlist) as $v) {
            if ($v['seconds'] <= self::SHORTS_MAX_SECONDS) {
                continue;
            }
            $v['age_days'] = round(($now->getTimestamp() - CarbonImmutable::parse($v['published_at'])->getTimestamp()) / 86400, 1);
            $videos[] = $v;
        }
        $mature = array_values(array_map(fn ($v) => $v['views'], array_filter($videos, fn ($v) => $v['age_days'] >= $matureDays)));
        $median = count($mature) >= 3 ? self::median($mature) : 0.0;

        foreach ($videos as &$v) {
            $v['url'] = "https://youtu.be/{$v['id']}";
            $v['score'] = $median > 0 ? round($v['views'] / $median, 2) : null;
            $v['early'] = $v['age_days'] < $matureDays;
        }
        unset($v);
        usort($videos, fn ($a, $b) => strcmp($b['published_at'], $a['published_at']));

        return ['title' => $title, 'median' => $median, 'mature_days' => $matureDays, 'videos' => $videos];
    }

    /** Her own channel ID (UC...), derived from the uploads playlist (UU...) without another call. */
    public function channelId(string $channel): ?string
    {
        [$playlist] = $this->channelUploads($channel);

        return $playlist && str_starts_with($playlist, 'UU') ? 'UC'.substr($playlist, 2) : null;
    }

    /** Her subscriber count (1 quota unit), or null when YouTube hides it. */
    public function subscribers(string $channel): ?int
    {
        $key = preg_match('/^UC[\w-]{22}$/', $channel) ? 'id' : 'forHandle';
        $s = $this->get('channels', ['part' => 'statistics', $key => $channel])['items'][0]['statistics'] ?? null;

        return $s && empty($s['hiddenSubscriberCount']) && isset($s['subscriberCount']) ? (int) $s['subscriberCount'] : null;
    }

    /** One video's title (1 quota unit). */
    public function videoTitle(string $videoId): ?string
    {
        return $this->get('videos', ['part' => 'snippet', 'id' => $videoId])['items'][0]['snippet']['title'] ?? null;
    }

    /**
     * Top-level comments, most relevant first (commentThreads.list: 1 quota unit per 100).
     *
     * @param  string  $order  relevance (most useful first) or time (newest first)
     * @return array<int, array{id: string, text: string, likes: int, replies: int, author_channel: ?string, published_at: ?string}>
     */
    public function comments(string $videoId, int $max = 100, string $order = 'relevance'): array
    {
        try {
            $data = $this->get('commentThreads', [
                'part' => 'snippet', 'videoId' => $videoId, 'order' => $order === 'time' ? 'time' : 'relevance',
                'maxResults' => max(1, min(100, $max)), 'textFormat' => 'plainText',
            ]);
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), 'disabled comments') || str_contains($e->getMessage(), 'commentsDisabled')) {
                throw new RuntimeException('Comments are turned off on this video.', 0, $e);
            }
            throw $e;
        }

        $out = [];
        foreach ($data['items'] ?? [] as $item) {
            $c = $item['snippet']['topLevelComment']['snippet'] ?? [];
            $text = trim((string) ($c['textDisplay'] ?? $c['textOriginal'] ?? ''));
            if ($text !== '') {
                $out[] = [
                    'id' => (string) ($item['id'] ?? ''),
                    'text' => mb_substr($text, 0, 1500),
                    'likes' => (int) ($c['likeCount'] ?? 0),
                    'replies' => (int) ($item['snippet']['totalReplyCount'] ?? 0),
                    'author_channel' => $c['authorChannelId']['value'] ?? null,
                    'published_at' => $c['publishedAt'] ?? null,
                ];
            }
        }

        return $out;
    }

    /** Her own uploads since a date (port of channel_stats.py). */
    public function uploadsSince(string $channel, CarbonImmutable $since, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now('UTC');
        [$playlist] = $this->channelUploads($channel);
        if (! $playlist) {
            throw new RuntimeException("Channel not found: {$channel}");
        }
        $out = [];
        foreach ($this->recentVideos($playlist) as $v) {
            $published = CarbonImmutable::parse($v['published_at']);
            if ($published->toDateString() < $since->toDateString()) {
                continue;
            }
            $out[] = [
                'id' => $v['id'],
                'title' => $v['title'],
                'published' => $published->toDateString(),
                'published_at' => $published,
                'age_days' => round(($now->getTimestamp() - $published->getTimestamp()) / 86400, 1),
                'views' => $v['views'],
                'long_form' => $v['seconds'] > self::SHORTS_MAX_SECONDS,
                'url' => "https://youtu.be/{$v['id']}",
            ];
        }
        usort($out, fn ($a, $b) => strcmp($a['published'], $b['published']));

        return $out;
    }
}

