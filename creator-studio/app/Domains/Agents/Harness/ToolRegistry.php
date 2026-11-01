<?php

namespace App\Domains\Agents\Harness;

use App\Domains\Agents\Cascade\TransmediaCascade;
use App\Domains\Agents\Deals\BrandPitchSynthesizer;
use App\Domains\Agents\Deals\DealFulfillmentGuardian;
use App\Domains\Agents\Deals\SponsorNegotiator;
use App\Domains\Agents\Engagement\LeadTriageAgent;
use App\Domains\Agents\Guardrails\PlatformAlgorithmShield;
use App\Domains\Agents\Harness\SprintOrchestrator;
use App\Domains\Agents\Hooks\HookMatrixOptimizer;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Agents\Optimization\EvergreenRecycler;
use App\Domains\Agents\Outliers\CompetitorReverseEngineer;
use App\Domains\Agents\Scripts\ScriptRetentionAuditor;
use App\Domains\Agents\Verification\FactCheckShield;
use App\Domains\Agents\YouTube\TitleBanditOptimizer;
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
            [
                'name' => 'analyze_sponsor_inquiry',
                'description' => 'Autonomous Deal Desk: analyze inbound sponsor inquiry, check brand conflicts, and calculate tiered rates.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['inbound_text'],
                    'properties' => [
                        'inbound_text' => ['type' => 'string', 'description' => 'Raw email text or DM pitch from the sponsor.'],
                        'brand_name' => ['type' => 'string', 'description' => 'Optional brand name.'],
                    ],
                ],
            ],
            [
                'name' => 'generate_transmedia_cascade',
                'description' => 'Generate a 5-platform transmedia distribution cascade (LinkedIn, X thread, TikTok/Shorts, Newsletter, Carousel).',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['topic'],
                    'properties' => [
                        'topic' => ['type' => 'string', 'description' => 'Core technical topic or video title.'],
                    ],
                ],
            ],
            [
                'name' => 'generate_hook_matrix',
                'description' => 'Viral Hook Matrix: brainstorm 6 psychological hook archetypes and predict virality scores.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['topic'],
                    'properties' => [
                        'topic' => ['type' => 'string', 'description' => 'Subject or proposition to hook.'],
                        'platform' => ['type' => 'string', 'default' => 'linkedin'],
                    ],
                ],
            ],
            [
                'name' => 'verify_technical_facts',
                'description' => 'Grounding & Hallucination Shield: audit draft text for factual benchmark claims and deprecated APIs.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['text'],
                    'properties' => [
                        'text' => ['type' => 'string', 'description' => 'Draft post text or script segment to verify.'],
                    ],
                ],
            ],
            [
                'name' => 'reverse_engineer_outlier',
                'description' => 'Reverse-engineer a viral competitor breakout video and generate 3 Blue Ocean angles.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'outlier_id' => ['type' => 'integer', 'description' => 'Optional Outlier model ID.'],
                        'title' => ['type' => 'string', 'description' => 'Outlier video title.'],
                    ],
                ],
            ],
            [
                'name' => 'optimize_bandit_titles',
                'description' => 'Multi-Armed Bandit title generator: 4 algorithmic title variants with CTR scores and thumbnail concepts.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['topic'],
                    'properties' => [
                        'topic' => ['type' => 'string', 'description' => 'Video topic or proposition.'],
                        'video_id' => ['type' => 'string', 'description' => 'Optional YouTube video ID.'],
                    ],
                ],
            ],
            [
                'name' => 'triage_community_leads',
                'description' => 'Scan community comments and DMs to detect commercial leads (sponsors, consulting, speaking).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'item_id' => ['type' => 'integer', 'description' => 'Optional EngagementItem ID to triage.'],
                    ],
                ],
            ],
            [
                'name' => 'audit_script_retention',
                'description' => 'Audit YouTube script retention pacing, detect drop-off hazards, and inject 90-second resets.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'script_id' => ['type' => 'integer', 'description' => 'Optional Script ID.'],
                        'title' => ['type' => 'string', 'description' => 'Video title.'],
                        'body' => ['type' => 'string', 'description' => 'Script outline or text.'],
                    ],
                ],
            ],
            [
                'name' => 'audit_deal_fulfillment',
                'description' => 'Audit draft content against contracted Deal stipulations, FTC disclosures, and non-compete rules.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['content'],
                    'properties' => [
                        'content' => ['type' => 'string', 'description' => 'Draft script or social post content.'],
                        'deal_id' => ['type' => 'integer', 'description' => 'Optional Deal model ID.'],
                        'platform' => ['type' => 'string', 'description' => 'Target platform.'],
                        'brand_name' => ['type' => 'string', 'description' => 'Optional sponsor brand name.'],
                    ],
                ],
            ],
            [
                'name' => 'generate_deal_wrap_report',
                'description' => 'Synthesize executive sponsor wrap report verifying campaign deliverables and audience metrics for invoice sign-off.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'deal_id' => ['type' => 'integer', 'description' => 'Deal model ID.'],
                        'impressions' => ['type' => 'integer', 'description' => 'Total recorded impressions.'],
                        'clicks' => ['type' => 'integer', 'description' => 'Total tracked link clicks.'],
                        'live_url' => ['type' => 'string', 'description' => 'Published video or post URL.'],
                    ],
                ],
            ],
            [
                'name' => 'synthesize_brand_pitch',
                'description' => 'Synthesize proactive outbound sponsorship pitch package, customized integration concepts, and cold email for target brands.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['brand_name'],
                    'properties' => [
                        'brand_name' => ['type' => 'string', 'description' => 'Target company or product name.'],
                        'product_category' => ['type' => 'string', 'description' => 'Category of product.'],
                        'pitch_angle' => ['type' => 'string', 'description' => 'Specific video concept or theme.'],
                    ],
                ],
            ],
            [
                'name' => 'recycle_evergreen_content',
                'description' => 'Recycle historical top-performing technical topics or scripts into 3 modernized 2026 Fresh Take angles.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'topic' => ['type' => 'string', 'description' => 'Past successful topic or proposition.'],
                        'script_id' => ['type' => 'integer', 'description' => 'Optional Script ID.'],
                        'target_platform' => ['type' => 'string', 'description' => 'Platform for reboot post.'],
                    ],
                ],
            ],
            [
                'name' => 'shield_platform_algorithm',
                'description' => 'Audit and auto-armor draft content against platform ranking algorithm penalties (links, dwell time, hashtags).',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['content', 'platform'],
                    'properties' => [
                        'content' => ['type' => 'string', 'description' => 'Draft post text.'],
                        'platform' => ['type' => 'string', 'description' => 'Platform name (linkedin, x, youtube, tiktok, threads).'],
                        'title' => ['type' => 'string', 'description' => 'Optional title.'],
                    ],
                ],
            ],
            [
                'name' => 'orchestrate_weekly_sprint',
                'description' => 'Autonomous Executive Producer: synthesize active brand deals, pipeline scripts, and social slots into an optimal weekly sprint.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'hours_available' => ['type' => 'integer', 'description' => 'Weekly production hours available.'],
                        'focus_topic' => ['type' => 'string', 'description' => 'Primary video or content focus.'],
                    ],
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
            'analyze_sponsor_inquiry' => $this->analyzeSponsorInquiry($args),
            'generate_transmedia_cascade' => $this->generateTransmediaCascade($args),
            'generate_hook_matrix' => $this->generateHookMatrix($args),
            'verify_technical_facts' => $this->verifyTechnicalFacts($args),
            'reverse_engineer_outlier' => $this->reverseEngineerOutlier($args),
            'optimize_bandit_titles' => $this->optimizeBanditTitles($args),
            'triage_community_leads' => $this->triageCommunityLeads($args),
            'audit_script_retention' => $this->auditScriptRetention($args),
            'audit_deal_fulfillment' => $this->auditDealFulfillment($args),
            'generate_deal_wrap_report' => $this->generateDealWrapReport($args),
            'synthesize_brand_pitch' => $this->synthesizeBrandPitch($args),
            'recycle_evergreen_content' => $this->recycleEvergreenContent($args),
            'shield_platform_algorithm' => $this->shieldPlatformAlgorithm($args),
            'orchestrate_weekly_sprint' => $this->orchestrateWeeklySprint($args),
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

    public function analyzeSponsorInquiry(array $args): array
    {
        $agent = SocialAgent::first() ?? new SocialAgent(['name' => 'Creator Agent', 'brand' => 'Tech Engineering']);
        return app(SponsorNegotiator::class)->negotiate($agent, [
            'inbound_text' => $args['inbound_text'] ?? '',
            'brand_name' => $args['brand_name'] ?? '',
        ]);
    }

    public function generateTransmediaCascade(array $args): array
    {
        $agent = SocialAgent::first() ?? new SocialAgent(['name' => 'Creator Agent', 'brand' => 'Tech Engineering']);
        return app(TransmediaCascade::class)->generate($agent, [
            'topic' => $args['topic'] ?? '',
        ]);
    }

    public function generateHookMatrix(array $args): array
    {
        $agent = SocialAgent::first() ?? new SocialAgent(['name' => 'Creator Agent', 'brand' => 'Tech Engineering']);
        return app(HookMatrixOptimizer::class)->generateMatrix($agent, [
            'topic' => $args['topic'] ?? '',
            'platform' => $args['platform'] ?? 'linkedin',
        ]);
    }

    public function verifyTechnicalFacts(array $args): array
    {
        $agent = SocialAgent::first() ?? new SocialAgent(['name' => 'Creator Agent', 'brand' => 'Tech Engineering']);
        return app(FactCheckShield::class)->verify($agent, [
            'text' => $args['text'] ?? '',
        ]);
    }

    public function reverseEngineerOutlier(array $args): array
    {
        $agent = SocialAgent::first() ?? new SocialAgent(['name' => 'Creator Agent', 'brand' => 'Tech Engineering']);
        return app(CompetitorReverseEngineer::class)->deconstruct($agent, $args);
    }

    public function optimizeBanditTitles(array $args): array
    {
        $agent = SocialAgent::first() ?? new SocialAgent(['name' => 'Creator Agent', 'brand' => 'Tech Engineering']);
        return app(TitleBanditOptimizer::class)->optimize($agent, $args);
    }

    public function triageCommunityLeads(array $args): array
    {
        $agent = SocialAgent::first() ?? new SocialAgent(['name' => 'Creator Agent', 'brand' => 'Tech Engineering']);
        return app(LeadTriageAgent::class)->triage($agent, $args);
    }

    public function auditScriptRetention(array $args): array
    {
        $agent = SocialAgent::first() ?? new SocialAgent(['name' => 'Creator Agent', 'brand' => 'Tech Engineering']);
        return app(ScriptRetentionAuditor::class)->audit($agent, $args);
    }

    public function auditDealFulfillment(array $args): array
    {
        $agent = SocialAgent::first() ?? new SocialAgent(['name' => 'Creator Agent', 'brand' => 'Tech Engineering']);
        return app(DealFulfillmentGuardian::class)->auditDraft($agent, $args);
    }

    public function generateDealWrapReport(array $args): array
    {
        $agent = SocialAgent::first() ?? new SocialAgent(['name' => 'Creator Agent', 'brand' => 'Tech Engineering']);
        return app(DealFulfillmentGuardian::class)->generateWrapReport($agent, $args);
    }

    public function synthesizeBrandPitch(array $args): array
    {
        $agent = SocialAgent::first() ?? new SocialAgent(['name' => 'Creator Agent', 'brand' => 'Tech Engineering']);
        return app(BrandPitchSynthesizer::class)->synthesizePitch($agent, $args);
    }

    public function recycleEvergreenContent(array $args): array
    {
        $agent = SocialAgent::first() ?? new SocialAgent(['name' => 'Creator Agent', 'brand' => 'Tech Engineering']);
        return app(EvergreenRecycler::class)->recycle($agent, $args);
    }

    public function shieldPlatformAlgorithm(array $args): array
    {
        $agent = SocialAgent::first() ?? new SocialAgent(['name' => 'Creator Agent', 'brand' => 'Tech Engineering']);
        return app(PlatformAlgorithmShield::class)->auditAndArmor($agent, $args);
    }

    public function orchestrateWeeklySprint(array $args): array
    {
        $agent = SocialAgent::first() ?? new SocialAgent(['name' => 'Creator Agent', 'brand' => 'Tech Engineering']);
        return app(SprintOrchestrator::class)->planSprint($agent, $args);
    }
}
