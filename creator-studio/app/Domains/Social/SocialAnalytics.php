<?php

namespace App\Domains\Social;

use App\Domains\Social\Models\SocialPost;
use App\Models\Setting;
use App\Services\YouTube;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What's working, across every platform, from the numbers she logged or imported. Everything
 * here is arithmetic on her own posts; nothing is a benchmark from elsewhere. Small samples are
 * flagged rather than dressed up: best times need 8+ posts with numbers on that platform.
 */
class SocialAnalytics
{
    public const MIN_FOR_TIMES = 8;

    /**
     * Time-of-day blocks for the best-times grid (her timezone).
     */
    public const BLOCKS = [[6, 9, 'Early morning'], [9, 12, 'Morning'], [12, 15, 'Lunch & early afternoon'], [15, 18, 'Late afternoon'], [18, 21, 'Evening'], [21, 30, 'Late night']];

    public static function tz(): string
    {
        return Setting::get('timezone', 'Europe/London') ?: 'Europe/London';
    }

    /**
     * Posted posts in a window, newest first.
     */
    public static function posted(CarbonImmutable $from, ?CarbonImmutable $to = null): Collection
    {
        return SocialPost::where('status', 'posted')->whereNotNull('posted_at')
            ->where('posted_at', '>=', $from)->when($to, fn ($q) => $q->where('posted_at', '<', $to))
            ->latest('posted_at')->get();
    }

    public static function overview(int $weeks = 4): array
    {
        $tz = self::tz();
        $start = CarbonImmutable::now($tz)->startOfWeek()->subWeeks($weeks - 1);
        $prevStart = $start->subWeeks($weeks);
        $current = self::posted($start->utc());
        $previous = self::posted($prevStart->utc(), $start->utc());

        $platforms = [];
        foreach (Platforms::PLATFORMS as $key => $meta) {
            $cur = $current->where('platform', $key)->values();
            $prev = $previous->where('platform', $key)->values();
            if ($cur->isEmpty() && $prev->isEmpty()) {
                continue;
            }
            $logged = $cur->filter(fn ($p) => $p->engagement !== null);
            $rates = $logged->pluck('engagement.rate')->filter(fn ($r) => $r !== null);
            $prevTotal = $prev->sum(fn ($p) => $p->engagement['total'] ?? 0);
            $total = $logged->sum(fn ($p) => $p->engagement['total']);

            // Engagement per week, oldest first, with how many posts went out that week.
            $series = [];
            for ($w = 0; $w < $weeks; $w++) {
                $ws = $start->addWeeks($w);
                $inWeek = $cur->filter(fn ($p) => $p->posted_at->setTimezone($tz)->betweenIncluded($ws, $ws->addWeek()->subSecond()));
                $series[] = [
                    'week' => $ws->toDateString(),
                    'posts' => $inWeek->count(),
                    'engagement' => $inWeek->sum(fn ($p) => $p->engagement['total'] ?? 0),
                ];
            }

            $byFormat = $logged->groupBy('format')->map(fn ($g, $f) => [
                'format' => $f,
                'label' => Platforms::FORMATS[$key][$f][0] ?? $f,
                'posts' => $g->count(),
                'median_engagement' => (int)YouTube::median($g->pluck('engagement.total')->all()),
            ])->sortByDesc('median_engagement')->values();

            $platforms[$key] = [
                'label' => $meta['label'],
                'posts' => $cur->count(),
                'logged' => $logged->count(),
                'engagement' => $total,
                'previous_engagement' => $prevTotal,
                'change' => $prevTotal > 0 ? round(($total - $prevTotal) / $prevTotal * 100) : null,
                'avg_rate' => $rates->isNotEmpty() ? round($rates->avg(), 2) : null,
                'series' => $series,
                'by_format' => $byFormat,
                'missing_numbers' => $cur->filter(fn ($p) => $p->engagement === null && $p->posted_at->lte(now()->subDay()))->count(),
            ];
        }

        $logged = $current->filter(fn ($p) => $p->engagement !== null);
        $top = $logged->groupBy('platform')->flatMap(function ($g) {
            $rate = self::useRate($g);
            $median = YouTube::median($g->map(fn ($p) => self::score($p, $rate))->filter(fn ($s) => $s !== null)->values()->all());

            return $g->filter(fn ($p) => self::score($p, $rate) !== null)
                ->map(fn ($p) => [
                    'post' => $p,
                    'vs_median' => $median > 0 ? round(self::score($p, $rate) / $median, 2) : null,
                ]);
        })->filter(fn ($x) => $x['vs_median'] !== null)->sortByDesc('vs_median')->take(6)->values();

        // The content mix: posts and median engagement per pillar on each network.
        $pillars = $logged->filter(fn ($p) => $p->pillar)->groupBy(fn ($p) => $p->pillar . '|' . $p->platform)
            ->map(function ($g, $key) {
                [$pillar, $platform] = explode('|', $key);

                return [
                    'pillar' => $pillar,
                    'platform' => $platform,
                    'label' => Platforms::PLATFORMS[$platform]['label'] ?? $platform,
                    'posts' => $g->count(),
                    'median_engagement' => (int)YouTube::median($g->pluck('engagement.total')->all()),
                ];
            })->sortByDesc('median_engagement')->values();

        return [
            'weeks' => $weeks,
            'from' => $start->toDateString(),
            'platforms' => $platforms,
            'top' => $top,
            'pillars' => $pillars,
            'untagged' => $logged->filter(fn ($p) => !$p->pillar)->count(),
        ];
    }

    /**
     * Median score per weekday × time block, for one platform, from all her posts with numbers.
     * Null when there are fewer than MIN_FOR_TIMES (a heatmap of 3 posts is noise).
     */
    public static function bestTimes(string $platform): ?array
    {
        $posts = SocialPost::where('platform', $platform)->where('status', 'posted')->whereNotNull('posted_at')->get()
            ->filter(fn ($p) => $p->engagement !== null)->values();
        if ($posts->count() < self::MIN_FOR_TIMES) {
            return [
                'enough' => false,
                'posts' => $posts->count(),
                'needed' => self::MIN_FOR_TIMES,
            ];
        }
        $rate = self::useRate($posts);
        $tz = self::tz();
        $cells = [];
        foreach ($posts as $p) {
            if (($score = self::score($p, $rate)) === null) {
                continue;
            }
            $at = $p->posted_at->setTimezone($tz);
            $hour = $at->hour < 6 ? $at->hour + 24 : $at->hour;
            foreach (self::BLOCKS as $b => [$from, $to]) {
                if ($hour >= $from && $hour < $to) {
                    $cells[$at->dayOfWeekIso - 1][$b][] = $score;
                }
            }
        }
        $grid = [];
        $best = [];
        foreach (range(0, 6) as $d) {
            foreach (array_keys(self::BLOCKS) as $b) {
                $vals = $cells[$d][$b] ?? [];
                $median = $vals ? YouTube::median($vals) : null;
                $grid[$d][$b] = [
                    'posts' => count($vals),
                    'median' => $median !== null ? round($median, 2) : null,
                ];
                if (count($vals) >= 2) {
                    $best[] = [
                        'day' => $d,
                        'block' => $b,
                        'median' => $median,
                        'posts' => count($vals),
                    ];
                }
            }
        }
        usort($best, fn ($a, $b) => $b['median'] <=> $a['median']);

        return [
            'enough' => true,
            'posts' => $posts->count(),
            'metric' => $rate ? 'engagement rate (%)' : 'engagements',
            'blocks' => array_map(fn ($b) => $b[2], self::BLOCKS),
            'grid' => $grid,
            'best' => array_slice($best, 0, 3),
            'timezone' => $tz,
        ];
    }

    /**
     * Her proven posts worth a fresh take: 1.5× her platform median or better, 45+ days old, not already recycled.
     */
    public static function recyclable(int $limit = 8): Collection
    {
        $done = SocialPost::whereNotNull('source_post_id')->pluck('source_post_id')->all();
        $all = SocialPost::where('status', 'posted')->whereNotNull('posted_at')->get()->filter(fn ($p) => $p->engagement !== null);

        return $all->groupBy('platform')->flatMap(function ($g) use ($done) {
            $rate = self::useRate($g);
            $median = YouTube::median($g->map(fn ($p) => self::score($p, $rate))->filter(fn ($s) => $s !== null)->values()->all());
            if ($median <= 0 || $g->count() < 3) {
                return [];
            }

            return $g->filter(fn ($p) => $p->posted_at->lte(now()->subDays(45)) && !in_array($p->id, $done, true)
                && self::score($p, $rate) >= $median * 1.5)
                ->map(fn ($p) => [
                    'post' => $p,
                    'vs_median' => round(self::score($p, $rate) / $median, 2),
                ]);
        })->sortByDesc('vs_median')->take($limit)->values();
    }

    /**
     * A compact text summary of the last N days for the AI review (no numbers it can't see).
     */
    public static function reviewInput(int $days = 28): string
    {
        $tz = self::tz();
        $now = CarbonImmutable::now('UTC');
        $cur = self::posted($now->subDays($days));
        $prev = self::posted($now->subDays($days * 2), $now->subDays($days));
        if ($cur->isEmpty()) {
            return '';
        }
        $lines = [];
        foreach ($cur->groupBy('platform') as $platform => $posts) {
            $label = Platforms::PLATFORMS[$platform]['label'] ?? $platform;
            $p = $prev->where('platform', $platform);
            $lines[] = "## {$label}: {$posts->count()} posts in the last {$days} days (previous {$days} days: {$p->count()} posts, "
                . $p->sum(fn ($x) => $x->engagement['total'] ?? 0) . ' engagements logged)';
            foreach ($posts as $post) {
                $text = mb_substr(preg_replace('/\s+/', ' ', trim($post->body ?: implode(' / ', $post->parts ?? []))), 0, 140);
                $when = $post->posted_at->setTimezone($tz)->format('D H:i');
                $e = $post->engagement;
                $nums = $e ? "{$e['total']} engagements" . ($e['rate'] !== null ? ", {$e['rate']}% of impressions" : '') : 'no numbers logged';
                $lines[] = "- {$when}, " . (Platforms::FORMATS[$platform][$post->format][0] ?? $post->format) . ": \"{$text}\" ({$nums})";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * The comparable score for a post: engagement rate when impressions exist for most posts, else total engagement.
     */
    private static function useRate(Collection $posts): bool
    {
        $with = $posts->filter(fn ($p) => $p->engagement !== null);

        return $with->isNotEmpty() && $with->filter(fn ($p) => $p->engagement['rate'] !== null)->count() >= $with->count() * 0.7;
    }

    private static function score(SocialPost $p, bool $rate): ?float
    {
        $e = $p->engagement;

        return $e === null ? null : (float)($rate ? ($e['rate'] ?? null) : $e['total']);
    }
}
