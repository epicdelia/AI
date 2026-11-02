<?php

namespace Tests\Feature;

use App\Domains\Agents\Cascade\TransmediaCascade;
use App\Domains\Agents\Deals\SponsorNegotiator;
use App\Domains\Agents\Hooks\HookMatrixOptimizer;
use App\Domains\Agents\Mcp\McpServer;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Agents\Verification\FactCheckShield;
use App\Models\Deal;
use App\Models\Outlier;
use App\Models\Script;
use App\Models\Setting;
use App\Models\User;
use App\Services\Llm;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgenticRoiFeaturesTest extends TestCase
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
        $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00', 'UTC'));

        $this->user = $this->admin([
            'name' => 'Delia Lazarescu',
            'email' => 'delia@example.com',
            'password' => 'secret123',
        ]);
        $this->actingAs($this->user);

        $this->agent = SocialAgent::create([
            'name' => 'Autonomous Creator Agent',
            'platforms' => ['linkedin', 'x', 'tiktok'],
            'post_types' => ['informative', 'tips_and_tricks', 'opinions'],
            'posts_per_week' => 4,
            'approval' => 'review',
            'brand' => 'High-signal software engineering, AI tooling, and distributed systems architecture.',
            'audience' => 'Senior engineers, tech leads, and software architects.',
        ]);
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        parent::tearDown();
    }

    public function test_sponsor_negotiator_analyzes_inbound_inquiry_and_generates_tiered_packages(): void
    {
        Deal::create([
            'brand_name' => 'ExistingCloudCorp',
            'stage' => 'contracted',
            'rate' => 3000,
            'deliverables' => ['YouTube 60s Integration'],
        ]);

        Llm::$fake = function (string $system, string $user) {
            return json_encode([
                'analysis' => [
                    'brand_name' => 'DataFlow AI',
                    'product_name' => 'DataFlow Managed Vector Pipeline',
                    'brand_fit_score' => 92,
                    'developer_audience_relevance' => 'Directly matches viewer interest in scalable RAG infrastructure.',
                    'conflicts_detected' => [],
                    'requested_deliverables' => ['YouTube Integration', 'LinkedIn Post'],
                ],
                'tiered_packages' => [
                    'tier_1_essential' => [
                        'name' => 'Single Platform Midroll',
                        'rate' => 2500,
                        'deliverables' => '60s organic YouTube integration + description link',
                        'cpm' => 45,
                        'rationale' => 'Focused product demo inside tutorial.',
                    ],
                    'tier_2_synergy' => [
                        'name' => 'Cross-Platform Tech Synergy (Recommended)',
                        'rate' => 3650,
                        'deliverables' => 'YouTube 60s integration + 1x LinkedIn post + 1x X thread breakdown',
                        'cpm' => 40,
                        'rationale' => 'Reinforces video demo with LinkedIn B2B reach.',
                    ],
                    'tier_3_takeover' => [
                        'name' => 'Full Omnichannel Authority Campaign',
                        'rate' => 7500,
                        'deliverables' => 'Dedicated YouTube feature + LinkedIn post + X thread + 30-day paid usage rights',
                        'cpm' => 35,
                        'rationale' => 'Complete category takeover.',
                    ],
                ],
                'negotiation_email' => [
                    'subject' => 'Delia Lazarescu (@Tech.Unicorn) × DataFlow AI Partnership',
                    'body' => 'Hi DataFlow team, thanks for reaching out...',
                    'call_to_action' => 'Reply to confirm tier or schedule 15-min call.',
                ],
                'contract_stipulations' => [
                    'usage_rights' => '30 days organic digital rights.',
                    'payment_terms' => 'Net 30 upon publication.',
                    'revision_limits' => 'Up to 2 rounds.',
                    'ftc_compliance' => 'Mandatory #ad disclosure.',
                ],
            ]);
        };

        $negotiator = app(SponsorNegotiator::class);
        $res = $negotiator->negotiate($this->agent, [
            'inbound_text' => 'Hi Delia, we love your videos at DataFlow AI! We want to sponsor your next YouTube video about Vector Databases and maybe a LinkedIn post.',
            'brand_name' => 'DataFlow AI',
        ]);

        $this->assertEquals('DataFlow AI', $res['analysis']['brand_name']);
        $this->assertEquals(92, $res['analysis']['brand_fit_score']);
        $this->assertArrayHasKey('tier_2_synergy', $res['tiered_packages']);
        $this->assertEquals(3650, $res['tiered_packages']['tier_2_synergy']['rate']);
        $this->assertStringContainsString('DataFlow AI Partnership', $res['negotiation_email']['subject']);

        // Test API Endpoint
        $apiRes = $this->postJson("/api/agents/{$this->agent->id}/sponsor-negotiate", [
            'inbound_text' => 'Hi Delia, we want to sponsor your channel for DataFlow AI.',
            'brand_name' => 'DataFlow AI',
        ]);
        $apiRes->assertOk();
        $apiRes->assertJsonPath('result.analysis.brand_name', 'DataFlow AI');

        // Test saving negotiation package directly to Deal pipeline
        $saveRes = $this->postJson("/api/agents/{$this->agent->id}/sponsor-save-deal", [
            'brand_name' => 'DataFlow AI',
            'tier_key' => 'tier_2_synergy',
            'rate' => 3650,
            'deliverables' => 'YouTube 60s integration + 1x LinkedIn post + 1x X thread breakdown',
            'contact_email' => 'partnerships@dataflow.ai',
        ]);
        $saveRes->assertCreated();
        $this->assertDatabaseHas('deals', [
            'brand_name' => 'DataFlow AI',
            'rate' => 3650,
            'stage' => 'negotiating',
        ]);
    }

    public function test_transmedia_cascade_generates_and_schedules_five_platform_battery(): void
    {
        $script = Script::create([
            'title' => 'Building Event-Driven Systems That Never Crash',
            'outline' => '1. The problem with HTTP locks. 2. Append-only logs. 3. Idempotent retries.',
            'status' => 'reviewed',
        ]);

        Llm::$fake = function (string $system, string $user) {
            return json_encode([
                'linkedin' => [
                    'title' => 'Event-Driven Architecture Blueprint',
                    'body' => "Most distributed systems crash because engineers treat microservices like synchronous function calls.\n\nHere is our 3-step blueprint for zero-downtime event streams:\n\n1. Use append-only commit logs.\n2. Ensure consumer handlers are strictly idempotent.\n3. Implement bulkhead isolation.\n\nWhat is your team's strategy for event replays?",
                    'parts' => [],
                ],
                'x_thread' => [
                    'title' => 'Zero-Downtime Event Sagas',
                    'hook_tweet' => "Microservices without asynchronous event queues are just slow monoliths with network lag.\n\nHere's how to build event-driven sagas that survive network partitions: 🧵👇",
                    'thread_tweets' => [
                        '1/ Never perform distributed multi-table transactions across service boundaries.',
                        '2/ Instead, adopt the Saga pattern with compensating rollback transactions.',
                        '3/ Every message must carry an idempotent deduplication key.',
                        "4/ Bookmark this for your team's next distributed systems review.",
                    ],
                ],
                'short_form' => [
                    'title' => 'The #1 Mistake in Distributed Architecture',
                    'hook_3s' => '[VISUAL: Pointing at screen error log] Stop calling internal microservices over synchronous HTTP right now.',
                    'script_body' => "[TEXT: SYNCHRONOUS HTTP IS KILLING YOUR LATENCY]\nWhen one service slows down, thread pools cascade into a complete outage.\n\n[VISUAL: Switching to event queue diagram]\nSwitch to an asynchronous event bus instead.",
                    'on_screen_cues' => ['SYNCHRONOUS HTTP TRAP', 'EVENT QUEUES', 'IDEMPOTENCY'],
                    'duration_seconds' => 45,
                ],
                'newsletter' => [
                    'title' => 'Designing Fault-Tolerant Distributed Sagas',
                    'executive_summary' => 'Why distributed transactions fail and how event-driven sagas eliminate deadlocks.',
                    'deep_dive' => '## Architectural Breakdown\n\nWhen scaling to 50k events per second...',
                    'action_item' => 'Audit your inter-service calls this week for timeouts and deadlocks.',
                ],
                'carousel' => [
                    'title' => 'The 7 Laws of Event-Driven Systems',
                    'slides' => [
                        [
                            'slide_number' => 1,
                            'headline' => 'Event-Driven Systems Blueprint',
                            'body' => '7 practical rules',
                            'visual_cue' => 'Cover card',
                        ],
                        [
                            'slide_number' => 2,
                            'headline' => '1. Idempotency is Non-Negotiable',
                            'body' => 'Deduplicate at the consumer',
                            'visual_cue' => 'Diagram',
                        ],
                    ],
                ],
            ]);
        };

        $cascadeEngine = app(TransmediaCascade::class);
        $result = $cascadeEngine->generate($this->agent, [
            'script_id' => $script->id,
            'topic' => $script->title,
        ]);

        $this->assertEquals(5, $result['total_assets']);
        $this->assertArrayHasKey('linkedin', $result['cascade']);
        $this->assertArrayHasKey('x_thread', $result['cascade']);
        $this->assertArrayHasKey('short_form', $result['cascade']);
        $this->assertArrayHasKey('newsletter', $result['cascade']);
        $this->assertArrayHasKey('carousel', $result['cascade']);

        $this->assertGreaterThan(0, $result['cascade']['linkedin']['score']);
        $this->assertGreaterThan(0, $result['cascade']['x_thread']['score']);

        // Test API Endpoint for generation
        $res = $this->postJson("/api/agents/{$this->agent->id}/transmedia-cascade", [
            'script_id' => $script->id,
        ]);
        $res->assertOk();
        $res->assertJsonPath('total_assets', 5);

        // Test Batch-scheduling cascade to social calendar
        $schedRes = $this->postJson("/api/agents/{$this->agent->id}/cascade-schedule", [
            'cascade' => $result['cascade'],
            'platforms_to_schedule' => ['linkedin', 'x'],
        ]);
        $schedRes->assertOk();
        $this->assertCount(2, $schedRes->json('posts'));

        $this->assertDatabaseHas('social_posts', [
            'platform' => 'linkedin',
            'source' => 'transmedia_cascade',
            'social_agent_id' => $this->agent->id,
        ]);
        $this->assertDatabaseHas('social_posts', [
            'platform' => 'x',
            'source' => 'transmedia_cascade',
            'social_agent_id' => $this->agent->id,
        ]);
    }

    public function test_viral_hook_matrix_explores_six_archetypes_and_predicts_virality(): void
    {
        Outlier::create([
            'video_id' => 'outlier_test_123',
            'title' => 'I Built a Distributed System on SQLite',
            'url' => 'https://youtube.com/watch?v=outlier_test_123',
            'outlier_score' => 4.8,
            'views' => 180000,
            'why_it_worked' => 'Contrarian SQLite benchmark in production.',
        ]);

        Llm::$fake = function (string $system, string $user) {
            return json_encode([
                'matrix' => [
                    'contrarian' => [
                        'archetype' => 'contrarian',
                        'name' => 'Contrarian Paradigm Shift',
                        'hook_text' => 'Microservices are an organizational solution, not a technical performance upgrade.',
                        'lead_in' => '90% of startups adopting Kubernetes would run 10x faster on a single modern bare-metal server.',
                        'predictive_virality_score' => 94,
                        'curiosity_tension' => 'High',
                        'retention_risk' => 'Low',
                        'estimated_ctr_multiplier' => '2.5x',
                        'rationale' => 'Challenges prevailing industry cargo-culting.',
                    ],
                    'post_mortem' => [
                        'archetype' => 'post_mortem',
                        'name' => 'Disaster Post-Mortem',
                        'hook_text' => 'How a 2-character Redis config bug cost our team $48,000 in 3 hours.',
                        'lead_in' => 'Everything looked green until cache eviction cascaded into our database primary.',
                        'predictive_virality_score' => 91,
                        'curiosity_tension' => 'Extreme',
                        'retention_risk' => 'Low',
                        'estimated_ctr_multiplier' => '2.3x',
                        'rationale' => 'Loss aversion drives high completion rates.',
                    ],
                    'curiosity_gap' => [
                        'archetype' => 'curiosity_gap',
                        'name' => 'Curiosity Gap / Insider Secret',
                        'hook_text' => 'The undocumented Linux kernel tuning trick high-frequency trading systems rely on.',
                        'lead_in' => 'It bypasses standard socket buffers to cut latency to sub-microsecond levels.',
                        'predictive_virality_score' => 88,
                        'curiosity_tension' => 'High',
                        'retention_risk' => 'Medium',
                        'estimated_ctr_multiplier' => '2.0x',
                        'rationale' => 'Appeals to elite practitioner curiosity.',
                    ],
                    'benchmark' => [
                        'archetype' => 'benchmark',
                        'name' => 'Counter-Intuitive Benchmark',
                        'hook_text' => 'We benchmarked Rust vs Go vs Bun under 150,000 concurrent WebSocket connections.',
                        'lead_in' => 'The framework with the highest memory consumption was not what we predicted.',
                        'predictive_virality_score' => 96,
                        'curiosity_tension' => 'Extreme',
                        'retention_risk' => 'Low',
                        'estimated_ctr_multiplier' => '2.8x',
                        'rationale' => 'Concrete numbers trigger viral bookmarking and sharing.',
                    ],
                    'pain_relief' => [
                        'archetype' => 'pain_relief',
                        'name' => 'Direct Developer Pain Relief',
                        'hook_text' => 'Stop writing repetitive Dockerfile multi-stage builds by hand.',
                        'lead_in' => 'Here is a 5-line Nix build script that caches layers instantly.',
                        'predictive_virality_score' => 83,
                        'curiosity_tension' => 'Medium',
                        'retention_risk' => 'Low',
                        'estimated_ctr_multiplier' => '1.7x',
                        'rationale' => 'Instant developer workflow upgrade.',
                    ],
                    'confession' => [
                        'archetype' => 'confession',
                        'name' => 'Vulnerable Confession',
                        'hook_text' => 'I spent 6 months building a custom message broker before realizing Postgres LISTEN/NOTIFY already solved it.',
                        'lead_in' => 'Here are the expensive lessons I learned the hard way.',
                        'predictive_virality_score' => 89,
                        'curiosity_tension' => 'High',
                        'retention_risk' => 'Low',
                        'estimated_ctr_multiplier' => '2.1x',
                        'rationale' => 'Practitioner humility builds immense creator affinity.',
                    ],
                ],
                'winning_hook' => [
                    'archetype' => 'benchmark',
                    'hook_text' => 'We benchmarked Rust vs Go vs Bun under 150,000 concurrent WebSocket connections.',
                    'score' => 96,
                    'why_it_wins' => 'Benchmark hooks with empirical numbers produce the highest CTR and bookmark rates in tech content.',
                ],
                'benchmark_insights' => [
                    'Benchmark hooks drive 2.8x higher bookmark rates.',
                ],
            ]);
        };

        $optimizer = app(HookMatrixOptimizer::class);
        $matrix = $optimizer->generateMatrix($this->agent, [
            'topic' => 'High-throughput concurrency in modern backends',
            'platform' => 'linkedin',
        ]);

        $this->assertCount(6, $matrix['matrix']);
        $this->assertEquals('benchmark', $matrix['winning_hook']['archetype']);
        $this->assertEquals(96, $matrix['winning_hook']['score']);

        // API Endpoint test
        $res = $this->postJson("/api/agents/{$this->agent->id}/hook-matrix", [
            'topic' => 'High-throughput concurrency in modern backends',
            'platform' => 'linkedin',
        ]);
        $res->assertOk();
        $res->assertJsonPath('winning_hook.archetype', 'benchmark');
    }

    public function test_technical_fact_checker_detects_questionable_claims_and_armors_draft(): void
    {
        Llm::$fake = function (string $system, string $user) {
            return json_encode([
                'credibility_score' => 74,
                'status' => 'warning',
                'claims_evaluated' => [
                    [
                        'claim' => 'PostgreSQL cannot handle more than 5,000 writes per second without sharding.',
                        'category' => 'benchmark',
                        'status' => 'questionable',
                        'confidence' => 92,
                        'analysis' => 'Modern PostgreSQL on NVMe storage routinely achieves 30,000+ writes/sec with tuned WAL buffers.',
                        'suggested_correction' => 'Standard unoptimized PostgreSQL installations often bottleneck at 5,000 writes/sec without connection pooling.',
                    ],
                    [
                        'claim' => 'Python 3.13 introduces experimental free-threaded execution without the GIL.',
                        'category' => 'api_syntax',
                        'status' => 'verified',
                        'confidence' => 98,
                        'analysis' => 'PEP 703 officially added free-threading support in Python 3.13t.',
                        'suggested_correction' => null,
                    ],
                ],
                'armored_text' => 'Standard unoptimized PostgreSQL installations often bottleneck at 5,000 writes/sec without connection pooling. Meanwhile, Python 3.13 introduces experimental free-threaded execution without the GIL.',
                'disclaimers_recommended' => ['Benchmarks tested on dedicated NVMe instances.'],
            ]);
        };

        $shield = app(FactCheckShield::class);
        $result = $shield->verify($this->agent, [
            'text' => 'PostgreSQL cannot handle more than 5,000 writes per second without sharding. Python 3.13 introduces experimental free-threaded execution without the GIL.',
        ]);

        $this->assertEquals(74, $result['credibility_score']);
        $this->assertEquals('warning', $result['status']);
        $this->assertCount(2, $result['claims_evaluated']);
        $this->assertEquals('questionable', $result['claims_evaluated'][0]['status']);
        $this->assertEquals('verified', $result['claims_evaluated'][1]['status']);

        // API Endpoint test
        $res = $this->postJson("/api/agents/{$this->agent->id}/fact-check", [
            'text' => 'PostgreSQL cannot handle more than 5,000 writes per second.',
        ]);
        $res->assertOk();
        $res->assertJsonPath('credibility_score', 74);
    }

    public function test_mcp_server_lists_and_executes_new_high_roi_tools(): void
    {
        Llm::$fake = function () {
            return json_encode([
                'analysis' => [
                    'brand_name' => 'MCP Sponsor',
                    'product_name' => 'DevTool',
                    'brand_fit_score' => 90,
                    'conflicts_detected' => [],
                    'requested_deliverables' => [],
                ],
                'tiered_packages' => [
                    'tier_1_essential' => [
                        'name' => 'Midroll',
                        'rate' => 2500,
                        'deliverables' => '60s',
                        'cpm' => 45,
                        'rationale' => 'Test',
                    ],
                ],
                'negotiation_email' => [
                    'subject' => 'Partnership',
                    'body' => 'Body',
                    'call_to_action' => 'Reply',
                ],
                'contract_stipulations' => [
                    'usage_rights' => '30 days',
                ],
            ]);
        };

        $server = app(McpServer::class);

        // 1. Tool listing includes all 10 tools now
        $listRes = $server->handle([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ]);
        $toolNames = collect($listRes['result']['tools'])->pluck('name')->all();

        $this->assertContains('analyze_sponsor_inquiry', $toolNames);
        $this->assertContains('generate_transmedia_cascade', $toolNames);
        $this->assertContains('generate_hook_matrix', $toolNames);
        $this->assertContains('verify_technical_facts', $toolNames);
        $this->assertGreaterThanOrEqual(10, count($toolNames));

        // 2. Call analyze_sponsor_inquiry via MCP
        $callRes = $server->handle([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'analyze_sponsor_inquiry',
                'arguments' => [
                    'inbound_text' => 'Hi, we want to sponsor Delia.',
                ],
            ],
        ]);
        $this->assertFalse($callRes['result']['isError']);
        $this->assertStringContainsString('MCP Sponsor', $callRes['result']['content'][0]['text']);
    }
}
