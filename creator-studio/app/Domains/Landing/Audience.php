<?php

namespace App\Domains\Landing;

use App\Domains\Social\Models\SocialPost;
use Illuminate\Support\Facades\DB;

/**
 * Her audience numbers, one source for the landing page, media kit, rate card and AI prompts.
 * Synced follower counts win (Connections sync, studio:stats, or Analytics → Update); until a
 * network has one, its public figure from her site is used.
 */
class Audience
{
    /**
     * Public figures per network, used until the studio has a synced count for it.
     */
    public const FALLBACK = [
        'youtube' => 22000,
        'instagram' => 384000,
        'linkedin' => 25000,
        'tiktok' => 45000,
        'x' => 28000,
        'threads' => 15000,
    ];

    /**
     * Her public profiles, in the order the landing page shows them.
     */
    public const PROFILES = [
        'instagram' => [
            'label' => 'Instagram',
            'handle' => '@tech.unicorn',
            'url' => 'https://instagram.com/tech.unicorn',
            'unit' => 'followers',
            'blurb' => 'Daily tech news, AI experiments, and nomad engineering life in 60 seconds.',
        ],
        'youtube' => [
            'label' => 'YouTube',
            'handle' => '@Tech.Unicorn',
            'url' => 'https://youtube.com/@Tech.Unicorn',
            'unit' => 'subscribers',
            'blurb' => 'Deep dives, build-alongs, and coding challenges from beach cafés.',
        ],
        'linkedin' => [
            'label' => 'LinkedIn',
            'handle' => 'Delia Lazarescu',
            'url' => 'https://linkedin.com/in/delialazarescu',
            'unit' => 'followers',
            'blurb' => 'Career honesty, industry architecture takes, and lessons from Big Tech.',
        ],
        'tiktok' => [
            'label' => 'TikTok',
            'handle' => '@tech.unicorn',
            'url' => 'https://tiktok.com/@tech.unicorn',
            'unit' => 'followers',
            'blurb' => 'Short, sharp takes on building with AI and shipping fast.',
        ],
        'x' => [
            'label' => 'X',
            'handle' => '@tech__unicorn',
            'url' => 'https://x.com/tech__unicorn',
            'unit' => 'followers',
            'blurb' => 'Raw engineering notes, launches, and hot takes as they happen.',
        ],
        'threads' => [
            'label' => 'Threads',
            'handle' => '@tech.unicorn',
            'url' => 'https://threads.net/@tech.unicorn',
            'unit' => 'followers',
            'blurb' => 'Conversations about building in public and remote engineering.',
        ],
    ];

    /**
     * The figure written into the landing page copy; the page swaps it for headline() when they differ.
     */
    public const CLAIM = '472K+';

    /**
     * live = a real number of hers (synced or typed on her dashboard); source says which
     * (manual, or the network it was synced from), "public" for the fallback figure.
     *
     * @return array<string, array{count: int, live: bool, source: string}>
     */
    public static function counts(): array
    {
        // Latest real snapshot per network; "Show demo posts" sample followers never reach the public page.
        $synced = rescue(fn () => DB::table('follower_snapshots')->where('source', '!=', 'demo')->where('count', '>', 0)
            ->orderBy('recorded_on')->get(['platform', 'count', 'source'])->keyBy('platform')->all(), [], false);
        $out = [];
        foreach (self::FALLBACK as $platform => $fallback) {
            $row = $synced[$platform] ?? null;
            $out[$platform] = $row
                ? [
                    'count' => (int)$row->count,
                    'live' => true,
                    'source' => (string)$row->source,
                ]
                : [
                    'count' => $fallback,
                    'live' => false,
                    'source' => 'public',
                ];
        }

        return $out;
    }

    public static function count(string $platform): int
    {
        return self::counts()[$platform]['count'] ?? 0;
    }

    public static function total(): int
    {
        return array_sum(array_column(self::counts(), 'count'));
    }

    /**
     * The total of the networks shown on her page ("519K+"), so the headline always adds up to
     * the per-network numbers visitors see next to it.
     */
    public static function headline(): string
    {
        $hidden = LandingFeed::keys('landing_reach_hidden');
        $visible = array_diff_key(self::counts(), array_flip($hidden));

        return self::compact(array_sum(array_column($visible, 'count'))) . '+';
    }

    /**
     * "Reach & Content" on the landing page and "Public reach" on her dashboard: per network the
     * count, its 30-day change and trend (real snapshots only), posts this month and median
     * engagement from her published posts, and its share. Hidden networks are flagged.
     */
    public static function reach(): array
    {
        $counts = self::counts();
        $hidden = LandingFeed::keys('landing_reach_hidden');
        $since = now()->subDays(30);

        $series = rescue(fn () => DB::table('follower_snapshots')->where('source', '!=', 'demo')->where('count', '>', 0)
            ->where('recorded_on', '>=', now()->subDays(90)->toDateString())->orderBy('recorded_on')
            ->get(['platform', 'count', 'recorded_on'])->groupBy('platform'), collect(), false);

        $posts = SocialPost::where('status', 'posted')->where('posted_at', '>=', $since)
            ->where(fn ($q) => $q->whereNull('source')->orWhere('source', '!=', 'demo'))->get(['platform', 'metrics']);

        $visibleTotal = 0;
        foreach ($counts as $key => $c) {
            if (!in_array($key, $hidden, true)) {
                $visibleTotal += $c['count'];
            }
        }

        $networks = [];
        foreach (self::PROFILES as $key => $profile) {
            $c = $counts[$key];
            $points = $series->get($key, collect());
            $change = null;
            if ($c['live'] && $points->count() >= 2) {
                $base = $points->last(fn ($r) => $r->recorded_on <= $since->toDateString()) ?? $points->first();
                $change = (int)$points->last()->count - (int)$base->count;
            }
            $mine = $posts->where('platform', $key);
            $rates = $mine->map(function ($p) {
                $m = $p->metrics ?? [];
                $views = (int)($m['impressions'] ?? 0);

                return $views > 0 ? ((int)($m['likes'] ?? 0) + (int)($m['comments'] ?? 0) + (int)($m['shares'] ?? 0) + (int)($m['saves'] ?? 0)) / $views * 100 : null;
            })->filter(fn ($r) => $r !== null)->sort()->values();
            $isHidden = in_array($key, $hidden, true);

            $networks[] = [
                'key' => $key,
            ] + $profile + [
                'count' => $c['count'],
                'count_label' => self::compact($c['count']),
                'live' => $c['live'],
                // Synced from the account (not typed by hand): the page shows a "Live" mark for these.
                'synced' => $c['live'] && $c['source'] !== 'manual',
                'source' => $c['source'],
                'change_30d' => $change,
                // Up to 30 real points for the sparkline; never drawn from public figures.
                'trend' => $c['live'] && $points->count() >= 2 ? $points->take(-30)->pluck('count')->map(fn ($n) => (int)$n)->values()->all() : [],
                'posts_30d' => $mine->count(),
                // Median of at least 3 posts with views, else unknown.
                'engagement_rate' => $rates->count() >= 3 ? round($rates[intdiv($rates->count(), 2)], 1) : null,
                'share' => !$isHidden && $visibleTotal > 0 ? round($c['count'] / $visibleTotal * 100, 1) : 0,
                'hidden' => $isHidden,
            ];
        }

        $visible = array_values(array_filter($networks, fn ($n) => !$n['hidden']));
        $changes = array_filter(array_column($visible, 'change_30d'), fn ($v) => $v !== null);

        return [
            'headline' => self::headline(),
            'total' => $visibleTotal,
            'total_label' => self::compact($visibleTotal),
            'total_change_30d' => $changes ? array_sum($changes) : null,
            'live_networks' => count(array_filter($visible, fn ($n) => $n['live'])),
            'networks' => $networks,
        ];
    }

    public static function compact(int $n): string
    {
        return match (true) {
            $n >= 1_000_000 => rtrim(rtrim(number_format($n / 1_000_000, 1), '0'), '.') . 'M',
            $n >= 1_000 => round($n / 1_000) . 'K',
            default => (string)$n,
        };
    }
}
