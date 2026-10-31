<?php

namespace App\Domains\Landing;

use App\Domains\Social\Models\SocialPost;
use App\Models\Setting;
use App\Services\YouTube;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The "Content & Social Channels" section of her public landing page, built from her own data:
 * her latest YouTube uploads (YouTube API) and the posts she published from the studio or that
 * Connections synced in, with their real numbers. Nothing is invented: a network with no
 * published posts shows a follow card instead. She pins and hides items from the dashboard,
 * and clicks on each item are counted per day.
 *
 * Cached for an hour; `studio:landing` rebuilds it hourly and publishing a post clears it.
 */
class LandingFeed
{
    public const CACHE = 'landing_feed_v4'; // bump when the feed's shape changes, so a cached old shape is never served

    public const PER_PLATFORM = 8;

    /** Feed key => studio platform and her public profile. "twitter" is kept as the key for X. */
    public const PLATFORMS = [
        'youtube' => ['platform' => 'youtube', 'label' => 'YouTube', 'action' => 'Visit YouTube', 'unit' => 'subscribers'],
        'instagram' => ['platform' => 'instagram', 'label' => 'Instagram', 'action' => 'Follow on Instagram', 'unit' => 'followers'],
        'tiktok' => ['platform' => 'tiktok', 'label' => 'TikTok', 'action' => 'Follow on TikTok', 'unit' => 'followers'],
        'twitter' => ['platform' => 'x', 'label' => 'X', 'action' => 'Follow on X', 'unit' => 'followers'],
        'threads' => ['platform' => 'threads', 'label' => 'Threads', 'action' => 'Follow on Threads', 'unit' => 'followers'],
    ];

    /** Real videos of hers, shown only until the YouTube API (or a synced upload) has her latest. */
    private const SAMPLE_VIDEOS = [
        ['5p3Mg4VEkbI', '10 Brutal Truths I Learned as a Google Software Engineer', 'Google & Career'],
        ['DSXRUnBCYtQ', 'I am back at Google! (after I quit... here is why)', 'Career'],
        ['YJlJ12Txhkg', 'Is OpenClaw Still Worth It in 2026? (Ex-Google Engineer)', 'AI'],
        ['5jYD7ZRMJ5I', 'WTF Is Happening To The Job Market (Ex-Googler Perspective)', 'Tech Market'],
        ['iT0iE6GGuQk', 'How A.I. crosses my way as a Digital Nomad (Keynote Talk)', 'Keynote'],
        ['z5lKZX9dB-4', 'On the Importance of Big Tech Clout, Especially as a Woman in Tech', 'Women in Tech'],
    ];

    /** The public feed (cached). */
    public static function get(): array
    {
        return Cache::remember(self::CACHE, now()->addHour(), fn () => self::build());
    }

    /** Rebuild now (hourly job, "Refresh now", after she curates). */
    public static function refresh(): array
    {
        $feed = self::build();
        Cache::put(self::CACHE, $feed, now()->addHour());

        return $feed;
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE);
    }

    /** @param bool $withHidden the dashboard sees hidden items too (flagged), the public page doesn't */
    public static function build(bool $withHidden = false): array
    {
        $hidden = self::keys('landing_hidden');
        $pinned = self::keys('landing_pinned');
        $counts = Audience::counts();

        $platforms = [];
        $sources = [];
        foreach (self::PLATFORMS as $key => $cfg) {
            [$items, $sources[$key]] = $key === 'youtube' ? self::youtubeItems() : self::postItems($cfg['platform']);
            $items = array_map(fn ($i) => $i + ['pinned' => in_array($i['key'], $pinned, true), 'hidden' => in_array($i['key'], $hidden, true)], $items);
            // Pinned first (in the order she pinned them), then newest first as built.
            usort($items, fn ($a, $b) => self::pinRank($a['key'], $pinned) <=> self::pinRank($b['key'], $pinned));
            if (! $withHidden) {
                $items = array_values(array_filter($items, fn ($i) => ! $i['hidden']));
            }
            $platforms[$key] = array_slice($items, 0, $withHidden ? 30 : self::PER_PLATFORM);
        }

        $followers = [];
        foreach (self::PLATFORMS as $key => $cfg) {
            $c = $counts[$cfg['platform']] ?? ['count' => 0, 'live' => false];
            $followers[$key] = $c + ['label' => Audience::compact($c['count']), 'unit' => $cfg['unit']];
        }

        return [
            'updated_at' => now()->toIso8601String(),
            'active' => 'youtube',
            'channel' => Audience::PROFILES['youtube']['handle'],
            'headline' => Audience::headline(),
            'followers' => $followers,
            // Short badges for the tabs ("22K Subs"), kept for older pages.
            'stats' => array_map(fn ($f) => $f['label'].($f['unit'] === 'subscribers' ? ' Subs' : ''), $followers),
            'handles' => array_map(fn ($c) => Audience::PROFILES[$c['platform']]['handle'], self::PLATFORMS),
            'urls' => array_map(fn ($c) => Audience::PROFILES[$c['platform']]['url'], self::PLATFORMS),
            'labels' => array_map(fn ($c) => $c['label'], self::PLATFORMS),
            'actions' => array_map(fn ($c) => $c['action'], self::PLATFORMS),
            // live = her real, current content · sample = curated real videos until YouTube is connected · none = nothing published yet
            'sources' => $sources,
            'platforms' => $platforms,
            // "Reach & Content": all six networks (LinkedIn too); hidden ones left out of the public copy.
            'reach' => $withHidden ? Audience::reach() : self::publicReach(),
        ];
    }

    private static function publicReach(): array
    {
        $reach = Audience::reach();
        $reach['networks'] = array_values(array_map(
            fn ($n) => array_diff_key($n, ['hidden' => true]),
            array_filter($reach['networks'], fn ($n) => ! $n['hidden']),
        ));

        return $reach;
    }

    /** @return array{0: list<array>, 1: string} */
    private static function youtubeItems(): array
    {
        if (Setting::has('youtube_api_key')) {
            try {
                $yt = app(YouTube::class);
                [$uploadsId] = $yt->channelUploads(Setting::get('channel_id') ?: Audience::PROFILES['youtube']['handle']);
                $raw = $uploadsId ? $yt->recentVideos($uploadsId, 12) : [];
                if ($raw) {
                    return [array_map(fn ($v) => self::video($v['id'], $v['title'], '',
                        (int) ($v['seconds'] ?? 0), isset($v['views']) ? (int) $v['views'] : null, $v['published_at'] ?? null), $raw), 'live'];
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Uploads she published or synced into the studio.
        $posts = self::published('youtube')->filter(fn ($p) => self::youtubeId($p->url))->take(12);
        if ($posts->isNotEmpty()) {
            return [$posts->map(fn ($p) => self::video(self::youtubeId($p->url), $p->title ?: self::firstLine($p->body), self::excerpt($p->body, 160),
                0, isset($p->metrics['impressions']) ? (int) $p->metrics['impressions'] : null, $p->posted_at?->toIso8601String()))->values()->all(), 'live'];
        }

        return [array_map(fn ($v) => self::video($v[0], $v[1], '', 0, null, null, $v[2]), self::SAMPLE_VIDEOS), 'sample'];
    }

    private static function video(string $id, string $title, string $desc, int $durationSec, ?int $views, ?string $publishedAt, ?string $tag = null): array
    {
        return [
            'key' => 'yt:'.$id,
            'type' => 'video',
            'id' => $id,
            'title' => $title,
            'desc' => $desc,
            'duration' => $durationSec > 0 ? ($durationSec >= 3600 ? gmdate('G:i:s', $durationSec) : gmdate('i:s', $durationSec)) : null,
            'short' => $durationSec > 0 && $durationSec <= 60,
            'views' => $views,
            'views_label' => $views !== null ? Audience::compact($views).' views' : null,
            'date' => $publishedAt,
            'tag' => $tag,
            'url' => 'https://www.youtube.com/watch?v='.$id,
            'thumb' => 'https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg',
        ];
    }

    /** @return array{0: list<array>, 1: string} */
    private static function postItems(string $platform): array
    {
        $items = self::published($platform)->take(30)->map(function (SocialPost $p) {
            $m = $p->metrics ?? [];
            $image = collect($p->media ?? [])->first(fn ($x) => str_starts_with((string) ($x['mime'] ?? ''), 'image/'));

            return [
                'key' => 'post:'.$p->id,
                'type' => 'post',
                'id' => $p->id,
                'format' => $p->format,
                'title' => $p->title ?: self::firstLine($p->body),
                'text' => self::excerpt($p->body, 280),
                'date' => $p->posted_at?->toIso8601String(),
                'url' => $p->url,
                'thumb' => $image ? url('/landing/thumb/'.$p->id) : null,
                'alt' => $image['alt'] ?? null,
                // Only numbers she logged or that were synced; missing ones are left out, never guessed.
                'metrics' => array_filter([
                    'views' => isset($m['impressions']) ? (int) $m['impressions'] : null,
                    'likes' => isset($m['likes']) ? (int) $m['likes'] : null,
                    'comments' => isset($m['comments']) ? (int) $m['comments'] : null,
                    'shares' => isset($m['shares']) ? (int) $m['shares'] : null,
                ], fn ($v) => $v !== null),
            ];
        })->values()->all();

        return [$items, $items ? 'live' : 'none'];
    }

    /** Her published posts on a network that link to the live post (demo posts never). */
    public static function published(string $platform)
    {
        return SocialPost::where('platform', $platform)->where('status', 'posted')
            ->whereNotNull('url')->where('url', 'like', 'http%')
            ->where(fn ($q) => $q->whereNull('source')->orWhere('source', '!=', 'demo'))
            ->orderByDesc('posted_at')->limit(60)->get();
    }

    /** Clicks per item over the last N days. @return array<string, int> */
    public static function clicks(int $days = 30): array
    {
        return DB::table('landing_clicks')->where('day', '>=', now()->subDays($days - 1)->toDateString())
            ->selectRaw('item_key, sum(count) as n')->groupBy('item_key')->pluck('n', 'item_key')->map(fn ($n) => (int) $n)->all();
    }

    public static function recordClick(string $key, string $platform): void
    {
        DB::table('landing_clicks')->upsert(
            [['item_key' => $key, 'platform' => $platform, 'day' => now()->toDateString(), 'count' => 1]],
            ['item_key', 'day'], ['count' => DB::raw('count + 1')],
        );
    }

    /** @return list<string> */
    public static function keys(string $setting): array
    {
        $v = json_decode((string) Setting::get($setting, '[]'), true);

        return is_array($v) ? array_values(array_filter($v, 'is_string')) : [];
    }

    public static function youtubeId(?string $url): ?string
    {
        return $url && preg_match('~(?:youtu\.be/|v=|/shorts/|/live/)([\w-]{11})~', $url, $m) ? $m[1] : null;
    }

    private static function pinRank(string $key, array $pinned): int
    {
        $i = array_search($key, $pinned, true);

        return $i === false ? PHP_INT_MAX : $i;
    }

    private static function firstLine(?string $text): string
    {
        return mb_substr(trim(strtok((string) $text, "\n") ?: ''), 0, 120);
    }

    private static function excerpt(?string $text, int $max): string
    {
        $t = trim(preg_replace('/\s+/', ' ', (string) $text));

        return mb_strlen($t) > $max ? rtrim(mb_substr($t, 0, $max - 1)).'…' : $t;
    }
}
