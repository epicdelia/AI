<?php

namespace App\Domains\Clients;

use App\Domains\Social\Models\SocialPost;
use App\Models\Deal;
use App\Models\Script;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * A sponsor's results report: only numbers the studio actually recorded.
 *
 * - The linked YouTube video's views, likes and comments from the daily snapshots
 *   (studio:stats), including the views at day 1, 7 and 28 after publishing.
 * - The linked social post's logged impressions and engagement.
 * - Visits through the deal's tracked links (?ref=deal-<id>, kit-<id> or its coupon code).
 *
 * Nothing is estimated: with no data yet the numbers are 0 and `has_data` is false.
 * Cost metrics (CPM, cost per click) only appear when both the price and the count exist.
 * A benchmark only appears when she has entered one (Setting `sponsor_benchmark_cpm`).
 */
class ClientAnalytics
{
    public const MILESTONES = [1, 7, 28];

    public static function forDeal(Deal $deal): array
    {
        $deliverables = collect($deal->deliverables ?? [])->filter(fn ($d) => is_array($d))->values();
        $rate = (float)($deal->proposal['investment']['amount'] ?? $deal->rate);
        $currency = $deal->proposal['investment']['currency'] ?? ($deal->currency ?: 'USD');

        $impressions = 0;
        $engagements = 0;
        $views = 0;
        $items = [];

        if ($deal->social_post_id && ($post = SocialPost::find($deal->social_post_id))) {
            $postImpressions = (int)($post->impressions ?? 0);
            $postEngagements = (int)(($post->likes ?? 0) + ($post->comments ?? 0) + ($post->shares ?? 0) + ($post->saves ?? 0));
            $impressions += $postImpressions;
            $engagements += $postEngagements;
            $items[] = [
                'platform' => $post->platform,
                'type' => 'post',
                'title' => mb_substr($post->title ?: (string)$post->body, 0, 80),
                'views' => $postImpressions,
                'engagements' => $postEngagements,
                'url' => $post->url,
                'source' => 'Logged post numbers',
            ];
        }

        $video = null;
        if ($deal->script_id && ($script = Script::find($deal->script_id)) && $script->youtube_url
            && preg_match('/(?:v=|youtu\.be\/|shorts\/)([a-zA-Z0-9_-]{11})/', $script->youtube_url, $m)) {
            $snapshots = DB::table('video_stats')->where('video_id', $m[1])->orderBy('captured_on')->get();
            $latest = $snapshots->last();
            $vidViews = (int)($latest->views ?? 0);
            $vidEngagements = (int)(($latest->likes ?? 0) + ($latest->comments ?? 0));
            $views += $vidViews;
            $impressions += $vidViews;
            $engagements += $vidEngagements;
            $milestones = [];
            foreach (self::MILESTONES as $day) {
                // The first snapshot taken on or after that age.
                $snap = $snapshots->first(fn ($s) => (float)$s->age_days >= $day);
                $milestones[] = ['day' => $day, 'views' => $snap ? (int)$snap->views : null];
            }
            $video = [
                'title' => $script->title,
                'url' => $script->youtube_url,
                'published_at' => $latest?->published_at,
                'views' => $vidViews,
                'likes' => (int)($latest->likes ?? 0),
                'comments' => (int)($latest->comments ?? 0),
                'tracked_since' => $snapshots->first()?->captured_on,
                'last_checked' => $latest?->captured_on,
                'milestones' => $milestones,
                'series' => $snapshots->map(fn ($s) => ['date' => $s->captured_on, 'views' => (int)$s->views])->values(),
            ];
            $items[] = [
                'platform' => 'youtube',
                'type' => 'video',
                'title' => $script->title ?: 'YouTube video',
                'views' => $vidViews,
                'engagements' => $vidEngagements,
                'url' => $script->youtube_url,
                'source' => $snapshots->isEmpty() ? 'Not tracked yet' : 'Daily YouTube snapshots',
            ];
        }

        // Visits to her site through this deal's tracked links (?ref=deal-<id>, kit-<id> or the coupon).
        $refs = array_values(array_filter(["deal-{$deal->id}", "kit-{$deal->id}", $deal->coupon_code ?: null]));
        $trackedClicks = (int)DB::table('landing_events')->where('event', 'visit')->whereIn('source', $refs)->sum('count');

        $hasData = $impressions > 0 || $trackedClicks > 0;
        $eCpm = ($impressions > 0 && $rate > 0) ? round(($rate / $impressions) * 1000, 2) : null;
        $cpc = ($trackedClicks > 0 && $rate > 0) ? round($rate / $trackedClicks, 2) : null;
        $ctr = ($impressions > 0 && $trackedClicks > 0) ? round(($trackedClicks / $impressions) * 100, 2) : null;
        $engagementRate = ($impressions > 0) ? round(($engagements / $impressions) * 100, 2) : null;

        $benchmarkCpm = (float)Setting::get('sponsor_benchmark_cpm', 0);
        $benchmark = $benchmarkCpm > 0 && $eCpm !== null ? [
            'cpm' => $benchmarkCpm,
            'source' => (string)Setting::get('sponsor_benchmark_source', 'Her own benchmark'),
            'verdict' => $eCpm <= $benchmarkCpm
                ? 'Cost per 1,000 views is ' . round((1 - $eCpm / $benchmarkCpm) * 100) . '% below the benchmark.'
                : 'Cost per 1,000 views is ' . round(($eCpm / $benchmarkCpm - 1) * 100) . '% above the benchmark.',
        ] : null;

        return [
            'deal_id' => $deal->id,
            'brand_name' => $deal->brand_name,
            'campaign_title' => $deal->proposal['title'] ?? $deal->brand_name,
            'currency' => $currency,
            'investment' => $rate,
            'payment_status' => $deal->payment_status,
            'has_data' => $hasData,
            'note' => $hasData ? null : 'No results recorded yet. Numbers appear once the content is live and tracked.',
            'metrics' => [
                'total_impressions' => $impressions,
                'total_views' => $views ?: $impressions,
                'total_engagements' => $engagements,
                'tracked_clicks' => $trackedClicks,
                'click_through_rate' => $ctr,
                'engagement_rate' => $engagementRate,
                'effective_cpm' => $eCpm,
                'cost_per_click' => $cpc,
            ],
            'video' => $video,
            'benchmark' => $benchmark,
            'deliverables_summary' => [
                'total' => count($deliverables),
                'completed' => count($deliverables->where('status', 'completed')),
                'items' => $items,
            ],
            'renewal_incentive' => in_array($deal->stage, ['paid', 'invoiced'], true) && $hasData ? [
                'eligible' => true,
                'offer' => 'Want to run it again? Start a new request and reference this campaign.',
            ] : null,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * The same report as Markdown, for printing or saving as PDF.
     */
    public static function markdown(array $r): string
    {
        $n = fn ($v) => $v === null ? '–' : number_format((float)$v);
        $money = fn ($v) => $v === null ? '–' : $r['currency'] . ' ' . number_format((float)$v, 2);
        $m = $r['metrics'];
        $lines = [
            "# Results: {$r['campaign_title']}",
            '',
            "{$r['brand_name']} · report generated " . now()->toFormattedDateString(),
            '',
        ];
        if (!$r['has_data']) {
            $lines[] = '_' . $r['note'] . '_';

            return implode("\n", $lines);
        }
        $lines[] = '| Metric | Value |';
        $lines[] = '|---|---|';
        $lines[] = '| Views / impressions | ' . $n($m['total_views']) . ' |';
        $lines[] = '| Engagements | ' . $n($m['total_engagements']) . ' |';
        $lines[] = '| Tracked clicks | ' . $n($m['tracked_clicks']) . ' |';
        $lines[] = '| Click-through rate | ' . ($m['click_through_rate'] === null ? '–' : $m['click_through_rate'] . '%') . ' |';
        $lines[] = '| Cost per 1,000 views | ' . $money($m['effective_cpm']) . ' |';
        $lines[] = '| Cost per click | ' . $money($m['cost_per_click']) . ' |';
        if ($r['video']) {
            $lines[] = '';
            $lines[] = "## Video: {$r['video']['title']}";
            foreach ($r['video']['milestones'] as $ms) {
                $lines[] = "- Day {$ms['day']}: " . ($ms['views'] === null ? 'not reached yet' : $n($ms['views']) . ' views');
            }
            $lines[] = "- Latest ({$r['video']['last_checked']}): " . $n($r['video']['views']) . ' views';
        }
        if ($r['benchmark']) {
            $lines[] = '';
            $lines[] = "Benchmark ({$r['benchmark']['source']}): {$r['benchmark']['verdict']}";
        }
        $lines[] = '';
        $lines[] = '_All numbers are as recorded by the studio; nothing is estimated._';

        return implode("\n", $lines);
    }
}
