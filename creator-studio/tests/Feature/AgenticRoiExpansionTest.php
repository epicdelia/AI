<?php

namespace Tests\Feature;

use App\Domains\Agents\Deals\BrandPitchSynthesizer;
use App\Domains\Agents\Deals\DealFulfillmentGuardian;
use App\Domains\Agents\Guardrails\PlatformAlgorithmShield;
use App\Domains\Agents\Harness\SprintOrchestrator;
use App\Domains\Agents\Harness\ToolRegistry;
use App\Domains\Agents\Mcp\McpServer;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Agents\Optimization\EvergreenRecycler;
use App\Domains\Social\Models\SocialPost;
use App\Models\Deal;
use App\Models\Script;
use App\Models\Setting;
use App\Models\User;
use App\Services\Llm;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgenticRoiExpansionTest extends TestCase
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
        $this->travelTo(CarbonImmutable::parse('2026-11-01 14:00', 'UTC'));

        $this->user = $this->admin([
            'name' => 'Delia Lazarescu',
            'email' => 'delia@example.com',
            'password' => 'secret123',
        ]);
        $this->actingAs($this->user);

        $this->agent = SocialAgent::create([
            'name' => 'Autonomous Engineering Director Agent',
            'platforms' => ['linkedin', 'x', 'youtube', 'tiktok'],
            'post_types' => ['informative', 'tips_and_tricks', 'opinions'],
            'posts_per_week' => 5,
            'approval' => 'review',
            'brand' => 'High-throughput software engineering, modern databases, zero fluff.',
            'audience' => 'Senior engineers, tech leads, and software architects.',
        ]);
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        parent::tearDown();
    }

    public function test_deal_fulfillment_guardian_audits_draft_compliance_and_generates_wrap_report(): void
    {
        $deal = Deal::create([
            'brand_name' => 'Neon Database',
            'stage' => 'production',
            'rate' => 4500,
            'deliverables' => ['YouTube 60s Integration', 'LinkedIn Post'],
            'coupon_code' => 'UNICORN50',
            'tracking_url' => 'https://neon.tech/unicorn',
            'key_talking_points' => 'Serverless Postgres with instant branching, scale-to-zero compute, and NVMe speeds.',
            'do_nots' => 'Do not disparage SQLite or claim 100% SLA.',
        ]);

        Llm::$fake = function (string $system, string $user) {
            if (str_contains($system, 'Deal Fulfillment Guardian') || str_contains($system, 'Legal Compliance')) {
                return json_encode([
                    'compliance_score' => 96,
                    'status' => 'approved',
                    'checks' => [
                        'deliverable_coverage' => ['status' => 'verified', 'details' => 'Full 60s dedicated midroll with branching demo.'],
                        'talking_points_checklist' => [
                            'Serverless Postgres branching' => true,
                            'Scale-to-zero compute' => true,
                        ],
                        'ftc_disclosure' => [
                            'compliant' => true,
                            'detected_phrase' => 'Special thanks to Neon for sponsoring today\'s video',
                            'notes' => 'Disclosed verbally at 0:24.',
                        ],
                        'tracking_and_promo' => [
                            'compliant' => true,
                            'promo_detected' => true,
                            'link_detected' => true,
                        ],
                        'competitor_conflicts' => [
                            'has_conflict' => false,
                            'conflicting_brands' => [],
                            'details' => 'No competitor database mentions.',
                        ],
                    ],
                    'missing_mandatories' => [],
                    'suggested_remedies' => ['Include tracking link in first comment of LinkedIn post as well.'],
                    'armored_disclosure_snippet' => 'This video is sponsored by Neon. Use code UNICORN50 at neon.tech/unicorn for $50 free credits.',
                ]);
            }

            // Wrap report response
            return json_encode([
                'brand_name' => 'Neon Database',
                'campaign_status' => 'Complete & Verified',
                'executive_summary' => 'The Neon campaign delivered 54,200 impressions with 2,410 verified clicks.',
                'key_metrics' => [
                    'impressions' => 54200,
                    'engagement_rate' => '5.2%',
                    'ctr' => '4.4%',
                    'estimated_cpm' => 83,
                ],
                'audience_sentiment' => [
                    'overall' => 'Extremely Positive',
                    'sentiment_score' => 96,
                    'highlighted_quotes' => ['Already testing Neon branching on staging!'],
                ],
                'contractual_fulfillment_statement' => 'All agreed deliverables and FTC disclosures verified. Invoice dispersement approved.',
                'ready_for_invoicing' => true,
            ]);
        };

        $guardian = app(DealFulfillmentGuardian::class);

        // 1. Audit Draft
        $audit = $guardian->auditDraft($this->agent, [
            'deal_id' => $deal->id,
            'content' => "Special thanks to Neon for sponsoring today's video. Neon is serverless Postgres with instant branching. Use code UNICORN50 at neon.tech/unicorn.",
            'platform' => 'youtube',
            'title' => 'Building Zero-Downtime Database Migrations',
        ]);

        $this->assertEquals(96, $audit['compliance_score']);
        $this->assertEquals('approved', $audit['status']);
        $this->assertTrue($audit['checks']['ftc_disclosure']['compliant']);
        $this->assertTrue($audit['checks']['tracking_and_promo']['compliant']);

        // Check deal updated
        $deal->refresh();
        $this->assertNotNull($deal->talking_points_checklist);

        // 2. Generate Wrap Report
        $wrap = $guardian->generateWrapReport($this->agent, [
            'deal_id' => $deal->id,
            'impressions' => 54200,
            'clicks' => 2410,
            'live_url' => 'https://youtube.com/watch?v=neon_demo',
        ]);

        $this->assertEquals('Neon Database', $wrap['brand_name']);
        $this->assertTrue($wrap['ready_for_invoicing']);
        $this->assertEquals(54200, $wrap['key_metrics']['impressions']);

        // 3. Test API Endpoints
        $apiAudit = $this->postJson("/api/agents/{$this->agent->id}/audit-deal-fulfillment", [
            'deal_id' => $deal->id,
            'content' => 'Sample draft content for sponsor test.',
        ]);
        $apiAudit->assertOk();
        $apiAudit->assertJsonPath('result.compliance_score', 96);

        $apiWrap = $this->postJson("/api/agents/{$this->agent->id}/generate-deal-wrap-report", [
            'deal_id' => $deal->id,
            'impressions' => 54200,
        ]);
        $apiWrap->assertOk();
        $apiWrap->assertJsonPath('result.ready_for_invoicing', true);
    }

    public function test_brand_pitch_synthesizer_generates_proactive_outbound_campaign(): void
    {
        Llm::$fake = function (string $system, string $user) {
            return json_encode([
                'target_brand' => 'Supabase',
                'audience_synergy_score' => 97,
                'developer_demographic_fit' => '84% senior engineers and tech leads with direct database purchasing authority.',
                'integration_concepts' => [
                    [
                        'title' => 'Benchmarking 50,000 WebSocket Subscriptions with Supabase Realtime',
                        'format' => 'YouTube 60s Integration + LinkedIn Breakdown',
                        'hook' => 'We stress-tested real-time postgres notifications under 50k concurrent client connections.',
                        'technical_premise' => 'Live demonstration showing Elixir-backed real-time replication.',
                        'projected_views' => '30,000 - 55,000',
                    ],
                ],
                'tiered_packages' => [
                    'midroll_spotlight' => ['name' => 'Video Midroll', 'rate' => 2500, 'deliverables' => '60s YouTube midroll', 'cpm' => 45],
                    'omnichannel_synergy' => ['name' => 'Omnichannel Synergy', 'rate' => 4200, 'deliverables' => 'YouTube + LinkedIn + X Thread', 'cpm' => 38],
                    'full_category_takeover' => ['name' => 'Category Takeover', 'rate' => 7500, 'deliverables' => 'Dedicated Video + Usage Rights', 'cpm' => 32],
                ],
                'outbound_email' => [
                    'subject' => 'Delia (@Tech.Unicorn) × Supabase — Realtime Benchmark Video Concept',
                    'recipient_title' => 'Head of DevRel / VP Marketing',
                    'body' => 'Hi Ant & Paul, huge fan of Supabase\'s work on Realtime v2...',
                    'call_to_action' => 'Open to reviewing a 60-second integration concept for our upcoming video?',
                ],
                'roi_projection' => [
                    'estimated_impressions' => 75000,
                    'estimated_developer_clicks' => 3400,
                    'target_cpa_benchmark' => '$1.25 per qualified developer visit.',
                ],
            ]);
        };

        $synthesizer = app(BrandPitchSynthesizer::class);
        $result = $synthesizer->synthesizePitch($this->agent, [
            'brand_name' => 'Supabase',
            'product_category' => 'Backend-as-a-Service & Postgres Realtime',
            'pitch_angle' => 'Benchmarking WebSocket scalability',
        ]);

        $this->assertEquals('Supabase', $result['target_brand']);
        $this->assertEquals(97, $result['audience_synergy_score']);
        $this->assertNotEmpty($result['integration_concepts']);
        $this->assertArrayHasKey('omnichannel_synergy', $result['tiered_packages']);
        $this->assertStringContainsString('Supabase', $result['outbound_email']['subject']);

        // API Endpoint test
        $res = $this->postJson("/api/agents/{$this->agent->id}/synthesize-brand-pitch", [
            'brand_name' => 'Supabase',
            'product_category' => 'Postgres Infrastructure',
        ]);
        $res->assertOk();
        $res->assertJsonPath('result.target_brand', 'Supabase');
    }

    public function test_evergreen_recycler_rejuvenates_past_hits_and_schedules_reboot(): void
    {
        $script = Script::create([
            'title' => 'Why Monoliths Scale Better Than You Think',
            'outline' => '1. Network hop latency. 2. Shared memory caching. 3. Zero distributed locks.',
            'status' => 'reviewed',
        ]);

        Llm::$fake = function (string $system, string $user) {
            return json_encode([
                'original_topic' => 'Why Monoliths Scale Better Than You Think',
                'historical_performance_driver' => 'Engineers engage with contrarian latency comparisons.',
                'reboot_angles' => [
                    [
                        'archetype' => 'paradigm_shift',
                        'title' => 'The 2026 Modular Monolith Architecture',
                        'hook' => 'Single-node NVMe servers now push 1M requests/second without Kubernetes overhead.',
                        'the_2026_shift' => 'Hardware advances made distributed network boundaries unnecessary for 90% of teams.',
                        'suggested_platform' => 'linkedin',
                        'projected_virality_score' => 96,
                    ],
                    [
                        'archetype' => 'contrarian_retraction',
                        'title' => 'I Was Right About Monoliths, But Wrong About SQLite in Production',
                        'hook' => 'In 2023 I said never use SQLite for concurrent web traffic. Here is why that advice is now wrong.',
                        'the_2026_shift' => 'WAL mode + Litestream solved replication.',
                        'suggested_platform' => 'x',
                        'projected_virality_score' => 94,
                    ],
                ],
                'winning_reboot' => [
                    'archetype' => 'paradigm_shift',
                    'platform' => 'linkedin',
                    'title' => 'The 2026 Modular Monolith Architecture',
                    'body' => "Single-node NVMe servers now push 1M requests/second without Kubernetes overhead.\n\nHere are 3 reasons top teams are consolidating microservices:\n1. Zero inter-service serialization overhead.\n2. In-process ACID transactions.\n3. Operational debugging in minutes instead of distributed tracing dead-ends.",
                    'parts' => [],
                    'why_it_wins' => 'Contrarian architecture posts with hard metrics drive peak bookmark rates.',
                ],
            ]);
        };

        $recycler = app(EvergreenRecycler::class);
        $result = $recycler->recycle($this->agent, [
            'script_id' => $script->id,
            'target_platform' => 'linkedin',
        ]);

        $this->assertCount(2, $result['reboot_angles']);
        $this->assertEquals('paradigm_shift', $result['winning_reboot']['archetype']);
        $this->assertStringContainsString('Modular Monolith', $result['winning_reboot']['title']);

        // Schedule the winning reboot
        $post = $recycler->scheduleReboot($this->agent, [
            'reboot' => $result['winning_reboot'],
        ]);
        $this->assertEquals('linkedin', $post->platform);
        $this->assertEquals('evergreen_reboot', $post->source);
        $this->assertDatabaseHas('social_posts', [
            'id' => $post->id,
            'source' => 'evergreen_reboot',
        ]);

        // API Endpoint tests
        $apiRecycle = $this->postJson("/api/agents/{$this->agent->id}/recycle-evergreen", [
            'script_id' => $script->id,
        ]);
        $apiRecycle->assertOk();
        $apiRecycle->assertJsonPath('result.winning_reboot.archetype', 'paradigm_shift');

        $apiSched = $this->postJson("/api/agents/{$this->agent->id}/schedule-evergreen-reboot", [
            'reboot' => $result['winning_reboot'],
        ]);
        $apiSched->assertCreated();
    }

    public function test_platform_algorithm_shield_detects_reach_penalties_and_armors_content(): void
    {
        Llm::$fake = function (string $system, string $user) {
            return json_encode([
                'platform' => 'linkedin',
                'algorithm_reach_score' => 94,
                'risk_level' => 'safe',
                'penalties_detected' => [
                    [
                        'penalty_type' => 'external_link_suppression',
                        'severity' => 'high',
                        'explanation' => 'External links in main LinkedIn text cut reach by up to 60%.',
                        'fix_applied' => 'Moved URL to first comment prompt.',
                    ],
                    [
                        'penalty_type' => 'excessive_hashtags',
                        'severity' => 'medium',
                        'explanation' => 'Using >3 hashtags triggers spam filtering.',
                        'fix_applied' => 'Preserved only the top 2 high-signal hashtags.',
                    ],
                ],
                'dwell_time_assessment' => [
                    'opening_hook_power' => 'High dwell-tension opening statement before cutoff.',
                    'cutoff_character_count' => 132,
                    'tension_score' => 95,
                ],
                'armored_content' => "90% of distributed systems crash because teams treat microservices like function calls.\n\nHere is how to design idempotent sagas that survive network partitions (link to code in comments 👇).\n\n#SoftwareEngineering #DistributedSystems",
                'first_comment_prompt' => 'Here is the full open-source repo and benchmark harness: https://github.com/example/saga-benchmark',
                'optimization_summary' => 'Removed outbound link penalty, optimized opening 140-char dwell-time tension, sanitized hashtags.',
            ]);
        };

        $shield = app(PlatformAlgorithmShield::class);
        $result = $shield->auditAndArmor($this->agent, [
            'platform' => 'linkedin',
            'content' => "Check out our repo https://github.com/example/saga-benchmark #code #dev #tech #ai #software #eng #systems",
        ]);

        $this->assertEquals(94, $result['algorithm_reach_score']);
        $this->assertEquals('safe', $result['risk_level']);
        $this->assertNotEmpty($result['penalties_detected']);
        $this->assertNotNull($result['first_comment_prompt']);
        $this->assertStringContainsString('comments', $result['armored_content']);

        // API Endpoint test
        $res = $this->postJson("/api/agents/{$this->agent->id}/shield-algorithm", [
            'platform' => 'linkedin',
            'content' => 'Sample post with link https://example.com',
        ]);
        $res->assertOk();
        $res->assertJsonPath('result.algorithm_reach_score', 94);
    }

    public function test_sprint_orchestrator_plans_high_leverage_weekly_production_sprint(): void
    {
        Deal::create([
            'brand_name' => 'DataFlow AI',
            'stage' => 'contracted',
            'rate' => 3650,
            'deliverables' => ['YouTube 60s Integration'],
        ]);

        Script::create([
            'title' => 'Why Event Sagas Outperform 2PC Locks',
            'status' => 'draft',
        ]);

        Llm::$fake = function (string $system, string $user) {
            return json_encode([
                'sprint_dates' => 'Nov 1 - Nov 7, 2026',
                'target_commitments' => [
                    'youtube_videos' => 1,
                    'social_posts' => 5,
                    'sponsor_deliverables' => 1,
                ],
                'batching_strategy' => [
                    [
                        'session_name' => 'Studio Filming & B-Roll Block',
                        'duration_hours' => 2.5,
                        'tasks_combined' => [
                            'Record YouTube A-Roll',
                            'Record DataFlow AI 60s integration cut',
                            'Record 3x TikTok/Shorts hooks',
                        ],
                        'efficiency_multiplier' => '3x leverage',
                        'day' => 'Tuesday',
                    ],
                ],
                'daily_schedule' => [
                    'Monday' => ['Finalize Event Sagas script outline', 'Audit DataFlow AI sponsor talking points'],
                    'Tuesday' => ['Batch-film main video + sponsor cut + vertical hooks'],
                    'Wednesday' => ['Transmedia cascade: schedule 3 LinkedIn & X posts'],
                    'Thursday' => ['Package YouTube metadata & run Title Bandit optimizer'],
                    'Friday' => ['Publish YouTube video & monitor initial velocity'],
                    'Saturday' => ['Review community comments and triage sponsor leads'],
                    'Sunday' => ['Review weekly KPIs & run Agent Learner'],
                ],
                'sponsor_deadline_alerts' => [
                    [
                        'brand' => 'DataFlow AI',
                        'deliverable' => 'YouTube 60s Integration',
                        'status' => 'contracted',
                        'urgency' => 'High',
                    ],
                ],
                'operations_advice' => 'Batching video filming into a single 2.5-hour block protects 12+ hours for deep engineering work.',
            ]);
        };

        $orchestrator = app(SprintOrchestrator::class);
        $result = $orchestrator->planSprint($this->agent, [
            'hours_available' => 12,
            'focus_topic' => 'Distributed Event Sagas',
        ]);

        $this->assertEquals(1, $result['target_commitments']['youtube_videos']);
        $this->assertNotEmpty($result['batching_strategy']);
        $this->assertArrayHasKey('Tuesday', $result['daily_schedule']);
        $this->assertNotEmpty($result['sponsor_deadline_alerts']);

        // API Endpoint test
        $res = $this->postJson("/api/agents/{$this->agent->id}/plan-sprint", [
            'hours_available' => 12,
        ]);
        $res->assertOk();
        $res->assertJsonPath('result.target_commitments.youtube_videos', 1);
    }

    public function test_tool_registry_and_mcp_server_support_all_expanded_roi_tools(): void
    {
        Llm::$fake = function (string $system) {
            if (str_contains($system, 'Deal Fulfillment Guardian') || str_contains($system, 'Legal Compliance')) {
                return json_encode([
                    'compliance_score' => 98,
                    'status' => 'approved',
                    'checks' => ['deliverable_coverage' => ['status' => 'verified', 'details' => 'ok'], 'talking_points_checklist' => ['tp1' => true], 'ftc_disclosure' => ['compliant' => true, 'detected_phrase' => '#ad', 'notes' => 'ok'], 'tracking_and_promo' => ['compliant' => true, 'promo_detected' => true, 'link_detected' => true], 'competitor_conflicts' => ['has_conflict' => false, 'conflicting_brands' => [], 'details' => 'none']],
                    'missing_mandatories' => [],
                    'suggested_remedies' => [],
                    'armored_disclosure_snippet' => 'Sponsored by Brand.',
                ]);
            }
            if (str_contains($system, 'Platform Algorithm Shield')) {
                return json_encode([
                    'platform' => 'linkedin',
                    'algorithm_reach_score' => 95,
                    'risk_level' => 'safe',
                    'penalties_detected' => [],
                    'dwell_time_assessment' => ['opening_hook_power' => 'Strong', 'cutoff_character_count' => 120, 'tension_score' => 95],
                    'armored_content' => 'Armored text',
                    'first_comment_prompt' => null,
                    'optimization_summary' => 'Clean',
                ]);
            }
            return json_encode([
                'target_brand' => 'TargetCorp',
                'audience_synergy_score' => 95,
                'developer_demographic_fit' => 'Engineers',
                'integration_concepts' => [],
                'tiered_packages' => ['midroll_spotlight' => ['name' => 'Midroll', 'rate' => 2500, 'deliverables' => '60s', 'cpm' => 45]],
                'outbound_email' => ['subject' => 'Partnership', 'recipient_title' => 'DevRel', 'body' => 'Body', 'call_to_action' => 'Call'],
                'roi_projection' => ['estimated_impressions' => 50000, 'estimated_developer_clicks' => 2000, 'target_cpa_benchmark' => '$1.50'],
            ]);
        };

        $server = app(McpServer::class);

        // 1. Check MCP tool listing includes all tools (at least 20 tools now)
        $listRes = $server->handle(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);
        $toolNames = collect($listRes['result']['tools'])->pluck('name')->all();

        $this->assertContains('audit_deal_fulfillment', $toolNames);
        $this->assertContains('generate_deal_wrap_report', $toolNames);
        $this->assertContains('synthesize_brand_pitch', $toolNames);
        $this->assertContains('recycle_evergreen_content', $toolNames);
        $this->assertContains('shield_platform_algorithm', $toolNames);
        $this->assertContains('orchestrate_weekly_sprint', $toolNames);
        $this->assertGreaterThanOrEqual(20, count($toolNames));

        // 2. Call audit_deal_fulfillment via MCP
        $callRes = $server->handle([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'audit_deal_fulfillment',
                'arguments' => [
                    'content' => 'Sponsored by Brand. Check out https://brand.com code UNICORN #ad',
                    'brand_name' => 'Brand',
                ],
            ],
        ]);
        $this->assertFalse($callRes['result']['isError']);
        $this->assertStringContainsString('approved', $callRes['result']['content'][0]['text']);

        // 3. Call shield_platform_algorithm via MCP
        $shieldCall = $server->handle([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'shield_platform_algorithm',
                'arguments' => [
                    'platform' => 'linkedin',
                    'content' => 'High-throughput system design breakdown.',
                ],
            ],
        ]);
        $this->assertFalse($shieldCall['result']['isError']);
        $this->assertStringContainsString('Armored text', $shieldCall['result']['content'][0]['text']);
    }
}
