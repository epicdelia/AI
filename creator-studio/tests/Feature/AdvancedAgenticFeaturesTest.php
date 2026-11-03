<?php

namespace Tests\Feature;

use App\Domains\Agents\Engagement\LeadTriageAgent;
use App\Domains\Agents\Mcp\McpServer;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Agents\Outliers\CompetitorReverseEngineer;
use App\Domains\Agents\Scripts\ScriptRetentionAuditor;
use App\Domains\Agents\YouTube\TitleBanditOptimizer;
use App\Domains\Engagement\Models\EngagementItem;
use App\Models\Channel;
use App\Models\Outlier;
use App\Models\Script;
use App\Models\Setting;
use App\Models\User;
use App\Services\Llm;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedAgenticFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected SocialAgent $agent;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Setting::put('timezone', 'UTC');
        Setting::put('llm_api_key', 'test-key');
        $this->travelTo(CarbonImmutable::parse('2026-11-01 12:00', 'UTC'));

        $this->user = $this->admin([
            'name' => 'Delia Lazarescu',
            'email' => 'delia@example.com',
            'password' => 'secret123',
        ]);
        $this->actingAs($this->user);

        $this->agent = SocialAgent::create([
            'name' => 'Autonomous Tech Lead Agent',
            'platforms' => ['linkedin', 'x', 'youtube'],
            'post_types' => ['informative', 'tips_and_tricks', 'opinions'],
            'posts_per_week' => 4,
            'approval' => 'review',
            'brand' => 'High-throughput software engineering, distributed systems, zero fluff.',
            'audience' => 'Senior engineers, tech leads, and software architects.',
        ]);
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        parent::tearDown();
    }

    public function test_competitor_reverse_engineer_deconstructs_outlier_into_blue_ocean_angles(): void
    {
        $channel = Channel::create([
            'title' => 'ByteByteGo',
            'handle' => '@bytebytego',
        ]);
        $outlier = Outlier::create([
            'channel_id' => $channel->id,
            'video_id' => 'outlier_bbg_1',
            'title' => 'Why Amazon Prime Video Moved from Serverless to Monolith',
            'url' => 'https://youtube.com/watch?v=outlier_bbg_1',
            'views' => 1200000,
            'median_views' => 200000,
            'outlier_score' => 6.0,
            'why_it_worked' => 'Contrarian architecture post-mortem exposing 90% cloud cost reduction.',
        ]);

        Llm::$fake = function (string $system, string $user) {
            return json_encode([
                'outlier_summary' => [
                    'title' => 'Why Amazon Prime Video Moved from Serverless to Monolith',
                    'competitor' => 'ByteByteGo',
                    'outlier_multiplier' => '6.0x',
                    'core_topic' => 'Serverless vs Monolith cost trade-offs',
                ],
                'psychological_triggers' => [
                    'primary_trigger' => 'Financial Cost Horror & Serverless Fatigue',
                    'tension_mechanism' => 'Engineers felt validated that microservices add unnecessary network fees.',
                    'audience_friction' => 'Failed to show what happens when Prime Video video streams hit memory saturation.',
                ],
                'audience_demand_gap' => 'How to decide between Serverless and Monolith with concrete math.',
                'blue_ocean_angles' => [
                    [
                        'angle_name' => 'The Cloud Cost Calculation Formula',
                        'hook_line' => 'Here is the exact AWS billing formula to calculate whether serverless or containers will be cheaper.',
                        'thesis' => 'Step functions charge per transition; container memory is fixed cost.',
                        'why_it_stands_out' => 'Gives engineers an Excel/Python calculator instead of high-level commentary.',
                        'predicted_multiplier' => '4.8x',
                        'suggested_format' => 'YouTube Tutorial + Interactive Calculator',
                    ],
                    [
                        'angle_name' => 'The Hybrid Container Pattern',
                        'hook_line' => "Prime Video didn't move to a monolith—they moved to ECS containers. Here's why that distinction matters.",
                        'thesis' => 'Calling ECS tasks a traditional monolith misleads teams into coupling databases.',
                        'why_it_stands_out' => 'Corrects common architectural misconceptions with technical precision.',
                        'predicted_multiplier' => '3.9x',
                        'suggested_format' => 'LinkedIn Deep-Dive + Diagram',
                    ],
                    [
                        'angle_name' => 'Production Benchmark: Lambda vs ECS under 10k req/s',
                        'hook_line' => 'We deployed the exact same video transcoding worker on Lambda vs Fargate.',
                        'thesis' => 'Fargate cut latency variance by 40% while costing 1/5th the price.',
                        'why_it_stands_out' => 'Hard empirical numbers always outperform theory on developer YouTube.',
                        'predicted_multiplier' => '5.2x',
                        'suggested_format' => 'YouTube Benchmark Video',
                    ],
                ],
                'actionable_concept' => [
                    'title' => 'The Truth About Prime Video\'s "Monolith" (With AWS Cost Math)',
                    'elevator_pitch' => 'A rigorous breakdown of the Prime Video migration showing why step functions became cost-prohibitive and how to structure container workers properly.',
                    'key_talking_points' => [
                        'AWS Step Functions billing model breakdown',
                        'In-memory micro-batching pattern',
                        'When serverless still wins',
                        'Decision flowchart for engineering teams',
                    ],
                ],
            ]);
        };

        $engineer = app(CompetitorReverseEngineer::class);
        $result = $engineer->deconstruct($this->agent, [
            'outlier_id' => $outlier->id,
        ]);

        $this->assertArrayHasKey('blue_ocean_angles', $result);
        $this->assertCount(3, $result['blue_ocean_angles']);
        $this->assertEquals('The Cloud Cost Calculation Formula', $result['blue_ocean_angles'][0]['angle_name']);
        $this->assertStringContainsString('The Truth About Prime Video', $result['actionable_concept']['title']);

        // API Endpoint test
        $res = $this->postJson("/api/agents/{$this->agent->id}/reverse-engineer-outlier", [
            'outlier_id' => $outlier->id,
        ]);
        $res->assertOk();
        $res->assertJsonPath('result.outlier_summary.competitor', 'ByteByteGo');
    }

    public function test_title_bandit_optimizer_generates_variants_and_launches_ab_test(): void
    {
        Llm::$fake = function (string $system, string $user) {
            return json_encode([
                'variants' => [
                    [
                        'title' => 'Why Senior Devs Avoid Microservices',
                        'archetype' => 'Provocative Punchy',
                        'char_count' => 36,
                        'predicted_ctr_score' => 93,
                        'browse_affinity' => 'High',
                        'search_affinity' => 'Medium',
                        'thumbnail' => [
                            'focal_subject' => 'Split diagram showing tangled arrows vs single clean box',
                            'text_overlay' => 'AVOID THIS',
                            'color_palette' => 'Dark graphite with neon red warning highlight',
                        ],
                    ],
                    [
                        'title' => 'We Benchmarked Monoliths at 100k req/s',
                        'archetype' => 'Quantified Result',
                        'char_count' => 38,
                        'predicted_ctr_score' => 97,
                        'browse_affinity' => 'High',
                        'search_affinity' => 'High',
                        'thumbnail' => [
                            'focal_subject' => 'Latency bar chart showing p99 collapse',
                            'text_overlay' => '100k REQ/S',
                            'color_palette' => 'Vivid cyan, black, and lime green',
                        ],
                    ],
                ],
                'bandit_winner' => [
                    'title' => 'We Benchmarked Monoliths at 100k req/s',
                    'score' => 97,
                    'strategic_rationale' => 'Quantified benchmark titles produce the highest CTR across developer YouTube browse feeds.',
                ],
            ]);
        };

        $optimizer = app(TitleBanditOptimizer::class);
        $result = $optimizer->optimize($this->agent, [
            'topic' => 'Modular Monoliths vs Distributed Services',
            'video_id' => 'yt_vid_abc123',
        ]);

        $this->assertCount(2, $result['variants']);
        $this->assertEquals(97, $result['bandit_winner']['score']);
        $this->assertEquals('We Benchmarked Monoliths at 100k req/s', $result['bandit_winner']['title']);

        // Test API Endpoint for generation
        $res = $this->postJson("/api/agents/{$this->agent->id}/bandit-titles", [
            'topic' => 'Modular Monoliths vs Distributed Services',
            'video_id' => 'yt_vid_abc123',
        ]);
        $res->assertOk();
        $res->assertJsonPath('result.bandit_winner.score', 97);

        // Test Launching Title A/B Test
        $launchRes = $this->postJson('/api/agents/launch-title-test', [
            'video_id' => 'yt_vid_abc123',
            'titles' => [
                'Why Senior Devs Avoid Microservices',
                'We Benchmarked Monoliths at 100k req/s',
            ],
            'hours_per_window' => 4,
            'rounds' => 3,
        ]);
        $launchRes->assertCreated();
        $this->assertDatabaseHas('title_tests', [
            'video_id' => 'yt_vid_abc123',
            'status' => 'running',
            'hours' => 4,
        ]);
    }

    public function test_lead_triage_agent_detects_commercial_opportunities_and_escalates_to_deals(): void
    {
        $item1 = EngagementItem::create([
            'platform' => 'linkedin',
            'kind' => 'comment',
            'external_id' => 'li_comm_1',
            'author' => 'Sarah Connor (Head of Developer Relations at CloudDB)',
            'text' => 'Hi Delia! We love your distributed systems content. Would love to sponsor your next YouTube video and newsletter feature. Do you have a rate card or media kit?',
            'status' => 'new',
            'intent' => 'question',
            'received_at' => now(),
        ]);

        $item2 = EngagementItem::create([
            'platform' => 'youtube',
            'kind' => 'comment',
            'external_id' => 'yt_comm_2',
            'author' => 'John Doe',
            'text' => 'Great video! What theme do you use in VS Code?',
            'status' => 'new',
            'intent' => 'question',
            'received_at' => now(),
        ]);

        Llm::$fake = function (string $system, string $user) {
            return json_encode([
                'leads' => [
                    [
                        'index' => 0,
                        'intent' => 'sponsor_lead',
                        'lead_score' => 95,
                        'priority' => 'urgent',
                        'estimated_value_usd' => 4500,
                        'draft_response' => 'Hi Sarah! Thank you—I would love to collaborate with CloudDB. I will send over our Media Kit and partnership rates right away.',
                        'suggested_action' => 'Escalate to Deal Desk and share Media Kit',
                    ],
                    [
                        'index' => 1,
                        'intent' => 'general_audience',
                        'lead_score' => 20,
                        'priority' => 'low',
                        'estimated_value_usd' => 0,
                        'draft_response' => 'Thanks John! It is Tokyo Night.',
                        'suggested_action' => 'Reply directly',
                    ],
                ],
            ]);
        };

        $triage = app(LeadTriageAgent::class);
        $result = $triage->triage($this->agent, []);

        $this->assertGreaterThanOrEqual(1, $result['leads_detected']);
        $this->assertGreaterThanOrEqual(4500, $result['total_pipeline_value']);

        // API Endpoint test
        $res = $this->postJson("/api/agents/{$this->agent->id}/triage-leads", [
            'item_id' => $item1->id,
        ]);
        $res->assertOk();
        $res->assertJsonPath('result.leads_detected', 1);

        // Test escalating lead into Deal Desk pipeline
        $dealRes = $this->postJson("/api/agents/{$this->agent->id}/escalate-lead-deal", [
            'author' => 'CloudDB',
            'intent' => 'sponsor_lead',
            'estimated_value' => 4500,
            'text' => 'Would love to sponsor your next YouTube video and newsletter feature.',
            'contact_email' => 'sarah@clouddb.io',
        ]);
        $dealRes->assertCreated();
        $this->assertDatabaseHas('deals', [
            'brand_name' => 'CloudDB',
            'stage' => 'lead',
            'rate' => 4500,
        ]);
    }

    public function test_script_retention_auditor_analyzes_pacing_and_injects_resets(): void
    {
        $script = Script::create([
            'title' => 'Building Sagas Without Distributed Deadlocks',
            'body' => "Today we are discussing event sagas.\nSagas are an architectural pattern for distributed transactions.\nIn traditional systems, two-phase commits cause lock contention.\nHere is how we solved it with append-only events.",
            'status' => 'draft',
        ]);

        Llm::$fake = function (string $system, string $user) {
            return json_encode([
                'title' => 'Building Sagas Without Distributed Deadlocks',
                'target_duration_minutes' => 10,
                'retention_score' => 89,
                'predicted_avd_percent' => 58,
                'pacing_rating' => 'Strong (Projected AVD 58%)',
                'hazards_detected' => [
                    [
                        'timestamp_estimate' => '1:00',
                        'issue' => 'Explaining two-phase commit theory before showing the database timeout error.',
                        'risk_level' => 'High',
                        'remedy' => 'Demonstrate the 30-second lock timeout error in terminal first.',
                    ],
                ],
                'pacing_timeline' => [
                    [
                        'phase' => 'Hook & Stakes',
                        'timestamp' => '0:00 - 0:45',
                        'focus' => 'Demonstrating the lock deadlock collapse',
                        'projected_retention' => '84%',
                        'retention_resets' => ['[VISUAL: Red error deadlock on screen]', '[TEXT: THE 2PC TRAP]'],
                    ],
                    [
                        'phase' => 'The Anti-Pattern & False Start',
                        'timestamp' => '0:45 - 2:30',
                        'focus' => 'Why distributed transactions bottleneck database thread pools',
                        'projected_retention' => '71%',
                        'retention_resets' => ['[VISUAL: Step function timeout animation]'],
                    ],
                ],
                'optimized_script_excerpt' => "[0:00 - VISUAL: Zoom on terminal showing deadlock]\nIf your microservices use two-phase commits, you are one network glitch away from total cascade failure.\n[0:15 - TEXT: SAGA PATTERN GUIDE]\nHere is how to eliminate distributed deadlocks permanently.",
            ]);
        };

        $auditor = app(ScriptRetentionAuditor::class);
        $result = $auditor->audit($this->agent, [
            'script_id' => $script->id,
            'target_duration_minutes' => 10,
        ]);

        $this->assertEquals(89, $result['retention_score']);
        $this->assertEquals(58, $result['predicted_avd_percent']);
        $this->assertNotEmpty($result['hazards_detected']);
        $this->assertNotEmpty($result['pacing_timeline']);

        // API Endpoint test
        $res = $this->postJson("/api/agents/{$this->agent->id}/audit-retention", [
            'script_id' => $script->id,
            'target_duration_minutes' => 10,
        ]);
        $res->assertOk();
        $res->assertJsonPath('result.retention_score', 89);
    }

    public function test_tool_registry_and_mcp_execute_advanced_roi_tools(): void
    {
        Llm::$fake = function (string $system) {
            if (str_contains($system, 'Head of YouTube Packaging')) {
                return json_encode([
                    'variants' => [[
                        'title' => 'Bandit Title Test',
                        'archetype' => 'Provocative',
                        'char_count' => 18,
                        'predicted_ctr_score' => 95,
                        'browse_affinity' => 'High',
                        'search_affinity' => 'High',
                        'thumbnail' => [
                            'focal_subject' => 'Thumb',
                            'text_overlay' => 'WOW',
                            'color_palette' => 'Dark',
                        ],
                    ]],
                    'bandit_winner' => [
                        'title' => 'Bandit Title Test',
                        'score' => 95,
                        'strategic_rationale' => 'High CTR',
                    ],
                ]);
            }
            if (str_contains($system, 'Outlier Reverse-Engineer')) {
                return json_encode([
                    'outlier_summary' => [
                        'title' => 'Outlier',
                        'competitor' => 'Comp',
                        'outlier_multiplier' => '4x',
                        'core_topic' => 'Topic',
                    ],
                    'psychological_triggers' => [
                        'primary_trigger' => 'Curiosity',
                        'tension_mechanism' => 'Tension',
                        'audience_friction' => 'Friction',
                    ],
                    'audience_demand_gap' => 'Gap',
                    'blue_ocean_angles' => [[
                        'angle_name' => 'Angle',
                        'hook_line' => 'Hook',
                        'thesis' => 'Thesis',
                        'why_it_stands_out' => 'Standout',
                        'predicted_multiplier' => '3x',
                        'suggested_format' => 'YT',
                    ]],
                    'actionable_concept' => [
                        'title' => 'Concept',
                        'elevator_pitch' => 'Pitch',
                        'key_talking_points' => ['Pt 1'],
                    ],
                ]);
            }

            return json_encode([
                'result' => 'ok',
            ]);
        };

        $server = app(McpServer::class);
        $listRes = $server->handle([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ]);
        $toolNames = collect($listRes['result']['tools'])->pluck('name')->all();

        $this->assertContains('reverse_engineer_outlier', $toolNames);
        $this->assertContains('optimize_bandit_titles', $toolNames);
        $this->assertContains('triage_community_leads', $toolNames);
        $this->assertContains('audit_script_retention', $toolNames);
        $this->assertGreaterThanOrEqual(14, count($toolNames));

        // Call optimize_bandit_titles tool via MCP
        $callRes = $server->handle([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'optimize_bandit_titles',
                'arguments' => [
                    'topic' => 'Building Event Streams',
                ],
            ],
        ]);
        $this->assertFalse($callRes['result']['isError']);
        $this->assertStringContainsString('Bandit Title Test', $callRes['result']['content'][0]['text']);
    }
}
