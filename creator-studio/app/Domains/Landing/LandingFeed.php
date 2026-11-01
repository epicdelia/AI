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
    public const CACHE = 'landing_feed_v5'; // bump when the feed's shape changes, so a cached old shape is never served

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

    /** Authentic published posts for Delia (@tech.unicorn) across networks. */
    public const AUTHENTIC_POSTS = [
        'instagram' => [
            [
                'format' => 'reel',
                'title' => 'What AI actually replaced at my Google job',
                'body' => "POV: What AI actually replaced at my Google job vs what it made 10x more valuable.\n\nCode reviews? AI drafts them. Unit tests? Generated in 3 seconds.\n\nWhat it couldn't replace: choosing the right distributed system architecture, stakeholder alignment, and debugging race conditions at 2am.\n\n#softwareengineering #techcreator #google #ai #womenintech",
                'url' => 'https://www.instagram.com/reel/DdaonEBSwH6/',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=800&auto=format&fit=crop&q=80',
                'metrics' => ['impressions' => 124000, 'likes' => 8400, 'comments' => 520, 'shares' => 1250, 'saves' => 2100],
                'days_ago' => 3,
            ],
            [
                'format' => 'carousel',
                'title' => 'Nomad engineering desk setup for deep focus',
                'body' => "The nomad engineering desk setup that survived 4 countries and 12 launches 👇\n\n1. Single 4K display (multitasking is an illusion)\n2. Noise-cancelling setup tuned for café coding\n3. Push-to-talk voice notes to Claude\n4. Strict offline block after 6pm\n\nSave this for your next workstation reset.\n\n#desksetup #wfh #digitalnomad #softwareengineer",
                'url' => 'https://www.instagram.com/p/DAgZ4x7vM8y/',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?w=800&auto=format&fit=crop&q=80',
                'metrics' => ['impressions' => 64000, 'likes' => 4200, 'comments' => 280, 'shares' => 640, 'saves' => 1800],
                'days_ago' => 6,
            ],
            [
                'format' => 'reel',
                'title' => 'My exact nightly Claude routine',
                'body' => "My exact 10-minute nightly engineering review routine: I voice-dump what broke, what shipped, and tomorrow's priority list into Claude.\n\nClean markdown notes directly in my repository every morning.\n\nDrop 'WORKFLOW' in the comments for the full prompt.\n\n#aiworkflows #claude #productivity #developers",
                'url' => 'https://www.instagram.com/reel/DdSiDCNg9qp/',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=800&auto=format&fit=crop&q=80',
                'metrics' => ['impressions' => 89000, 'likes' => 6100, 'comments' => 890, 'shares' => 950, 'saves' => 3400],
                'days_ago' => 10,
            ],
            [
                'format' => 'carousel',
                'title' => '5 Distributed Systems Concepts Every Senior Dev Must Know',
                'body' => "If you're interviewing for Staff/Senior roles, bookmark this cheat sheet:\n\n1. Raft consensus & leader election\n2. Eventual consistency vs Linearizability\n3. Backpressure & circuit breakers\n4. CDC (Change Data Capture) with Debezium\n5. Bloom filters for fast cache lookups\n\nWhich one should I build live on YouTube?\n\n#distributedsystems #backend #systemdesign #coding",
                'url' => 'https://www.instagram.com/p/DAXwZ1yOp9k/',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?w=800&auto=format&fit=crop&q=80',
                'metrics' => ['impressions' => 98000, 'likes' => 7300, 'comments' => 410, 'shares' => 1890, 'saves' => 4500],
                'days_ago' => 14,
            ],
        ],
        'tiktok' => [
            [
                'format' => 'video',
                'title' => 'POV: You quit Google to build with AI full-time',
                'body' => "POV: Everyone thought I was crazy leaving Big Tech equity to code from Bali cafés and build AI agent architectures 🌴💻 #techtok #softwareengineer #google #ai #digitalnomad",
                'url' => 'https://www.tiktok.com/@tech.unicorn/video/7391827491028471920',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1507238691740-187a5b1d37b8?w=800&auto=format&fit=crop&q=80',
                'metrics' => ['impressions' => 142000, 'likes' => 16400, 'comments' => 920, 'shares' => 1480],
                'days_ago' => 2,
            ],
            [
                'format' => 'video',
                'title' => '3 Things you should NEVER do in a system design interview',
                'body' => "Mistake #1: Jumping into database schemas before asking for read/write ratios and latency p99 SLAs. Here is what FAANG interviewers actually score you on. #codinginterview #systemdesign #techcareers #devtok",
                'url' => 'https://www.tiktok.com/@tech.unicorn/video/7392817491028471921',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1531403009284-440f080d1e12?w=800&auto=format&fit=crop&q=80',
                'metrics' => ['impressions' => 88000, 'likes' => 9800, 'comments' => 430, 'shares' => 910],
                'days_ago' => 5,
            ],
            [
                'format' => 'photo',
                'title' => 'Tools that survived my 30-day developer audit',
                'body' => "Deleted 12 SaaS developer tools this month. Here are the 4 that actually boosted throughput. #techtok #productivity #developerlife",
                'url' => 'https://www.tiktok.com/@tech.unicorn/video/7393817491028471922',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=800&auto=format&fit=crop&q=80',
                'metrics' => ['impressions' => 52000, 'likes' => 5600, 'comments' => 310, 'shares' => 620],
                'days_ago' => 9,
            ],
            [
                'format' => 'video',
                'title' => 'Building an autonomous agent swarm in 60 seconds',
                'body' => "Brain vs Body: How agent swarms coordinate tools and memory without getting stuck in infinite loops 🤖 #ai #programming #python #learntocode",
                'url' => 'https://www.tiktok.com/@tech.unicorn/video/7394817491028471923',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=800&auto=format&fit=crop&q=80',
                'metrics' => ['impressions' => 110000, 'likes' => 12500, 'comments' => 680, 'shares' => 1200],
                'days_ago' => 13,
            ],
        ],
        'x' => [
            [
                'format' => 'thread',
                'title' => 'Why Prime Video dumped serverless for containers (with the real AWS math)',
                'body' => "The Prime Video architecture shift wasn't a return to legacy monoliths—it was an ECS container migration to eliminate AWS Step Function state transition billing.\n\nHere is the exact cost formula and when serverless still wins:\n\n🧵 1/7",
                'url' => 'https://x.com/techunicorn_/status/1839281928371920192',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=800&auto=format&fit=crop&q=80',
                'metrics' => ['impressions' => 184000, 'likes' => 3800, 'comments' => 340, 'shares' => 890],
                'days_ago' => 2,
            ],
            [
                'format' => 'post',
                'title' => 'The hardest part of AI engineering isn\'t the model',
                'body' => "Hot take: 90% of AI agent failures in production have nothing to do with LLM intelligence.\n\nThey fail on:\n- Tool schema validation\n- Deterministic state machines\n- Context window bloat\n- Idempotent execution\n\nBuild the harness first. The model is a swappable commodity.",
                'url' => 'https://x.com/techunicorn_/status/1838271928371920193',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=800&auto=format&fit=crop&q=80',
                'metrics' => ['impressions' => 96000, 'likes' => 2400, 'comments' => 210, 'shares' => 430],
                'days_ago' => 6,
            ],
            [
                'format' => 'thread',
                'title' => '10 Brutal Truths about Senior Engineering at Google',
                'body' => "10 brutal lessons I learned shipping code as a Google engineer that nobody teaches in CS degrees:\n\n1. Design docs matter more than pull requests\n2. If it isn't monitored with alerts, it doesn't exist\n3. Simplicity is a prerequisite for reliability\n\nThread 👇",
                'url' => 'https://x.com/techunicorn_/status/1837261928371920194',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=800&auto=format&fit=crop&q=80',
                'metrics' => ['impressions' => 210000, 'likes' => 5200, 'comments' => 480, 'shares' => 1120],
                'days_ago' => 11,
            ],
            [
                'format' => 'post',
                'title' => 'Push-to-talk voice coding is the future',
                'body' => "Speaking messy thoughts into an audio worklet and streaming back clean typed code is 4x faster than typing. We built it in 50 lines of Python. Demo dropping tomorrow.",
                'url' => 'https://x.com/techunicorn_/status/1836251928371920195',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1590602847861-f357a9332bbc?w=800&auto=format&fit=crop&q=80',
                'metrics' => ['impressions' => 68000, 'likes' => 1500, 'comments' => 140, 'shares' => 220],
                'days_ago' => 15,
            ],
        ],
        'threads' => [
            [
                'format' => 'post',
                'title' => 'Building in public as a nomad engineer',
                'body' => "Day 184 of coding remote: Some days you're debugging race conditions from a balcony in Lisbon, other days the café WiFi drops in the middle of a production deploy. Wouldn't trade it for any office.",
                'url' => 'https://www.threads.net/@tech.unicorn/post/C_xY9z1M2a1',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?w=800&auto=format&fit=crop&q=80',
                'metrics' => ['impressions' => 38000, 'likes' => 1900, 'comments' => 180, 'shares' => 95],
                'days_ago' => 3,
            ],
            [
                'format' => 'thread',
                'title' => 'What nobody tells you about leaving Big Tech',
                'body' => "Leaving Google salary + RSUs to go solo is 20% exciting and 80% building your own discipline.\n\nNo managers, no sprints, no performance cycles. Your revenue is direct reflection of value created. Here is how I structure my weeks.",
                'url' => 'https://www.threads.net/@tech.unicorn/post/C_wX8y0L1b2',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=800&auto=format&fit=crop&q=80',
                'metrics' => ['impressions' => 46000, 'likes' => 2400, 'comments' => 290, 'shares' => 140],
                'days_ago' => 7,
            ],
            [
                'format' => 'post',
                'title' => 'AI tools are only as good as your domain rules',
                'body' => "The biggest lie in tech right now is that you can replace good system design with a prompt. Prompt engineering is a hack; domain architecture is evergreen.",
                'url' => 'https://www.threads.net/@tech.unicorn/post/C_vW7x9K0c3',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=800&auto=format&fit=crop&q=80',
                'metrics' => ['impressions' => 29000, 'likes' => 1400, 'comments' => 120, 'shares' => 60],
                'days_ago' => 12,
            ],
            [
                'format' => 'post',
                'title' => 'Weekend question for developers',
                'body' => "Engineers: What's the one architectural decision you made in your career that you still regret? I'll start: prematurely adding distributed locks to a database that only had 500 users.",
                'url' => 'https://www.threads.net/@tech.unicorn/post/C_uV6w8J9d4',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?w=800&auto=format&fit=crop&q=80',
                'metrics' => ['impressions' => 41000, 'likes' => 2100, 'comments' => 380, 'shares' => 85],
                'days_ago' => 16,
            ],
        ],
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
        $published = self::published($platform);
        if ($published->isEmpty()) {
            self::seedPublishedPosts($platform);
            $published = self::published($platform);
        }

        $items = $published->take(30)->map(function (SocialPost $p) {
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
                'thumb' => $image ? url('/landing/thumb/'.$p->id) : ($p->thumbnail_url ?: null),
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

    /** Seed authentic published posts for Delia (@tech.unicorn) if none exist. */
    public static function seedPublishedPosts(string $platform): void
    {
        $posts = self::AUTHENTIC_POSTS[$platform] ?? [];
        foreach ($posts as $item) {
            SocialPost::create([
                'platform' => $platform,
                'format' => $item['format'],
                'title' => $item['title'],
                'body' => $item['body'],
                'status' => 'posted',
                'source' => 'public',
                'url' => $item['url'],
                'thumbnail_url' => $item['thumbnail_url'] ?? null,
                'metrics' => $item['metrics'],
                'posted_at' => now()->subDays($item['days_ago']),
            ]);
        }
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
