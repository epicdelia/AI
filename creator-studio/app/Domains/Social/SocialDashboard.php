<?php

namespace App\Domains\Social;

use App\Domains\Social\Models\SocialPost;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The analytics dashboard: headline numbers, a rotating line of plain-language insights, and
 * daily series (posts, engagements, impressions, followers) for all platforms or one.
 */
class SocialDashboard
{
    /** Save today's follower counts: ['instagram' => 1234, ...]. */
    public static function recordFollowers(array $counts, string $source = 'manual'): int
    {
        $today = CarbonImmutable::now(SocialAnalytics::tz())->toDateString();
        $n = 0;
        foreach ($counts as $platform => $count) {
            if (! isset(Platforms::PLATFORMS[$platform]) || $count === null || $count === '' || ! is_numeric($count)) {
                continue;
            }
            DB::table('follower_snapshots')->updateOrInsert(
                ['platform' => $platform, 'recorded_on' => $today],
                ['count' => max(0, (int) $count), 'source' => $source, 'updated_at' => now(), 'created_at' => now()],
            );
            $n++;
        }
        if ($n && $source !== 'demo') {
            \App\Domains\Landing\LandingFeed::forget();
        }

        return $n;
    }

    /** Latest follower count per platform, with the count about 30 days earlier (for growth). */
    public static function followers(): array
    {
        $out = [];
        foreach (array_keys(Platforms::PLATFORMS) as $p) {
            $latest = DB::table('follower_snapshots')->where('platform', $p)->orderByDesc('recorded_on')->first();
            if (! $latest) {
                continue;
            }
            $before = DB::table('follower_snapshots')->where('platform', $p)
                ->where('recorded_on', '<=', CarbonImmutable::parse($latest->recorded_on)->subDays(28)->toDateString())
                ->orderByDesc('recorded_on')->first();
            $out[$p] = ['count' => (int) $latest->count, 'recorded_on' => $latest->recorded_on, 'source' => $latest->source,
                'change_30d' => $before ? (int) $latest->count - (int) $before->count : null];
        }

        return $out;
    }

    public static function build(int $days = 30, ?string $platform = null): array
    {
        $tz = SocialAnalytics::tz();
        $today = CarbonImmutable::now($tz)->startOfDay();
        $from = $today->subDays($days - 1);
        $only = $platform && isset(Platforms::PLATFORMS[$platform]) ? $platform : null;

        $posts = SocialPost::where('status', 'posted')->whereNotNull('posted_at')
            ->where('posted_at', '>=', $from->utc())->when($only, fn ($q) => $q->where('platform', $only))->get();
        $prev = SocialPost::where('status', 'posted')->whereNotNull('posted_at')
            ->whereBetween('posted_at', [$from->subDays($days)->utc(), $from->utc()])->when($only, fn ($q) => $q->where('platform', $only))->get();

        // Daily series, oldest first.
        $dates = [];
        $series = ['posts' => [], 'engagement' => [], 'impressions' => []];
        for ($d = $from; $d <= $today; $d = $d->addDay()) {
            $key = $d->toDateString();
            $dates[] = $key;
            $onDay = $posts->filter(fn ($p) => $p->posted_at->setTimezone($tz)->toDateString() === $key);
            $series['posts'][] = $onDay->count();
            $series['engagement'][] = $onDay->sum(fn ($p) => $p->engagement['total'] ?? 0);
            $series['impressions'][] = $onDay->sum(fn ($p) => (int) ($p->metrics['impressions'] ?? 0));
        }

        // Followers: the summed latest count per day (carried forward between snapshots).
        $snaps = DB::table('follower_snapshots')->when($only, fn ($q) => $q->where('platform', $only))
            ->where('recorded_on', '<=', $today->toDateString())->orderBy('recorded_on')->get()->groupBy('platform');
        $followerSeries = [];
        foreach ($dates as $key) {
            $sum = null;
            foreach ($snaps as $rows) {
                $last = $rows->filter(fn ($r) => $r->recorded_on <= $key)->last();
                if ($last) {
                    $sum = ($sum ?? 0) + (int) $last->count;
                }
            }
            $followerSeries[] = $sum;
        }

        $logged = $posts->filter(fn ($p) => $p->engagement !== null);
        $rates = $logged->pluck('engagement.rate')->filter(fn ($r) => $r !== null);
        $prevRates = $prev->filter(fn ($p) => $p->engagement !== null)->pluck('engagement.rate')->filter(fn ($r) => $r !== null);
        $followers = self::followers();
        $fol = $only ? array_intersect_key($followers, [$only => 1]) : $followers;
        $folTotal = $fol ? array_sum(array_column($fol, 'count')) : null;
        $folChange = $fol && count(array_filter(array_column($fol, 'change_30d'), fn ($c) => $c !== null)) ? array_sum(array_filter(array_column($fol, 'change_30d'))) : null;

        $kpis = [
            'avg_engagement' => $rates->isNotEmpty() ? round($rates->avg(), 1) : null,
            'avg_engagement_prev' => $prevRates->isNotEmpty() ? round($prevRates->avg(), 1) : null,
            'followers' => $folTotal,
            'followers_change' => $folChange,
            'posts' => $posts->count(),
            'posts_prev' => $prev->count(),
            'engagements' => $logged->sum(fn ($p) => $p->engagement['total']),
            'posts_today' => end($series['posts']),
        ];

        return [
            'days' => $days, 'platform' => $only, 'dates' => $dates, 'series' => $series + ['followers' => $followerSeries],
            'kpis' => $kpis, 'followers' => $followers, 'ticker' => self::ticker($kpis, $only),
        ];
    }

    /** Short, true sentences about her accounts, for the rotating line at the top. */
    private static function ticker(array $kpis, ?string $only): array
    {
        $q = SocialPost::query()->when($only, fn ($x) => $x->where('platform', $only));
        $live = (clone $q)->where('status', 'posted')->count();
        $last = (clone $q)->where('status', 'posted')->max('posted_at');
        $scheduled = (clone $q)->where('status', 'scheduled')->where('scheduled_for', '>=', now())->count();
        $drafts = (clone $q)->where('status', 'draft')->count();
        $noNumbers = (clone $q)->where('status', 'posted')->whereNull('metrics')->where('posted_at', '<=', now()->subDay())->count();

        $lines = [];
        $lines[] = $live ? "A total of {$live} post".($live === 1 ? ' is' : 's are').' live.' : 'Nothing published yet. Your first post is one click away on the calendar.';
        if ($last) {
            $lines[] = 'Your last post went out '.CarbonImmutable::parse($last)->diffForHumans().'.';
        }
        if ($scheduled) {
            $lines[] = "{$scheduled} post".($scheduled === 1 ? ' is' : 's are').' scheduled and ready to go.';
        }
        if ($drafts) {
            $lines[] = "{$drafts} draft".($drafts === 1 ? ' is' : 's are').' waiting for a date. Drag them onto the calendar.';
        }
        if ($kpis['followers_change']) {
            $lines[] = ($kpis['followers_change'] > 0 ? '+' : '').number_format($kpis['followers_change']).' followers in the last 30 days.';
        }
        if ($noNumbers) {
            $lines[] = "{$noNumbers} posted post".($noNumbers === 1 ? ' has' : 's have').' no numbers yet. Import your analytics to fill them in.';
        }

        return $lines;
    }
}
