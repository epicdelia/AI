<?php

namespace App\Domains\Agents\Harness;

use App\Domains\Audience\Models\AudienceInsight;
use App\Domains\Feeds\Models\FeedItem;
use App\Domains\Social\SocialScheduler;
use App\Models\Deal;
use App\Models\Outlier;
use App\Models\Script;
use Illuminate\Support\Collection;

/**
 * The Agent Tool Registry provides standardized, sanitized read and scheduling tools
 * that allow autonomous agents to inspect current market intelligence and execute safely.
 */
class ToolRegistry
{
    /** @return array<int, array{name: string, description: string, parameters: array}> */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'fetch_breakouts',
                'description' => 'Retrieve top recent competitor breakout videos and why they succeeded.',
                'parameters' => ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer', 'default' => 5]]],
            ],
            [
                'name' => 'fetch_news_feeds',
                'description' => 'Retrieve fresh technical articles from creator RSS feeds.',
                'parameters' => ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer', 'default' => 5]]],
            ],
            [
                'name' => 'fetch_recent_scripts',
                'description' => 'Retrieve the creator\'s recent YouTube video concepts and outlines.',
                'parameters' => ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer', 'default' => 3]]],
            ],
            [
                'name' => 'fetch_audience_asks',
                'description' => 'Retrieve questions asked by followers in video comments.',
                'parameters' => ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer', 'default' => 3]]],
            ],
            [
                'name' => 'fetch_active_sponsors',
                'description' => 'Retrieve active brand sponsorships with pending deliverables.',
                'parameters' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name' => 'find_optimal_slot',
                'description' => 'Find the next high-engagement free slot on a social network.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['platform'],
                    'properties' => ['platform' => ['type' => 'string', 'enum' => ['linkedin', 'x', 'threads', 'instagram', 'tiktok', 'youtube']]],
                ],
            ],
        ];
    }

    public function listTools(): array
    {
        return self::definitions();
    }

    public function executeTool(string $name, array $args = []): mixed
    {
        return match ($name) {
            'fetch_breakouts' => $this->fetchBreakouts((int) ($args['limit'] ?? 5)),
            'fetch_news_feeds', 'fetch_rss_feeds' => $this->fetchNewsFeeds((int) ($args['limit'] ?? 5)),
            'fetch_recent_scripts', 'fetch_channel_scripts' => $this->fetchRecentScripts((int) ($args['limit'] ?? 3)),
            'fetch_audience_asks' => $this->fetchAudienceAsks((int) ($args['limit'] ?? 3)),
            'fetch_active_sponsors' => $this->fetchActiveSponsors(),
            'find_optimal_slot', 'get_open_schedule_slots' => $this->findOptimalSlot($args['platform'] ?? 'linkedin'),
            default => throw new \InvalidArgumentException("Unknown tool: {$name}"),
        };
    }

    public function fetchBreakouts(int $limit = 5): Collection
    {
        return Outlier::with('channel:id,title,handle')
            ->where('created_at', '>=', now()->subDays(14))
            ->orderByDesc('outlier_score')
            ->take($limit)
            ->get(['id', 'channel_id', 'title', 'outlier_score', 'views', 'why_it_worked', 'url']);
    }

    public function fetchNewsFeeds(int $limit = 5): Collection
    {
        return FeedItem::latest()
            ->take($limit)
            ->get(['id', 'title', 'source', 'url', 'published_at']);
    }

    public function fetchRecentScripts(int $limit = 3): Collection
    {
        return Script::whereIn('status', ['published', 'reviewed', 'filmed'])
            ->latest('updated_at')
            ->take($limit)
            ->get(['id', 'title', 'status', 'outline']);
    }

    public function fetchAudienceAsks(int $limit = 3): Collection
    {
        return AudienceInsight::latest()
            ->take($limit)
            ->get(['id', 'video_id', 'video_title', 'summary', 'items']);
    }

    public function fetchActiveSponsors(): Collection
    {
        return Deal::whereIn('stage', ['contracted', 'production', 'in_review'])
            ->get(['id', 'brand_name', 'deliverables', 'rate', 'coupon_code', 'tracking_url', 'key_talking_points']);
    }

    public function findOptimalSlot(string $platform): array
    {
        return SocialScheduler::nextSlot($platform);
    }
}
