<?php

namespace Tests\Feature;

use App\Domains\Agents\Models\AgentPromptVersion;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Agents\Models\SwarmMission;
use App\Domains\Agents\Optimization\PromptOptimizer;
use App\Domains\Agents\Swarm\AgentSwarm;
use App\Models\Channel;
use App\Models\Outlier;
use App\Models\Setting;
use App\Models\User;
use App\Services\Llm;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwarmAndOptimizerTest extends TestCase
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

        $this->user = $this->admin(['name' => 'Grace Hopper', 'email' => 'grace@example.com', 'password' => 'secret123']);
        $this->actingAs($this->user);

        $this->agent = SocialAgent::create([
            'name' => 'High-Autonomy Swarm Agent',
            'platforms' => ['linkedin', 'x'],
            'post_types' => ['informative', 'opinions'],
            'posts_per_week' => 3,
            'approval' => 'auto',
            'brand' => 'Systems programming, high-concurrency benchmarks, zero buzzwords.',
            'audience' => 'Founders, staff engineers, and systems architects.',
        ]);
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        parent::tearDown();
    }

    public function test_swarm_plans_and_executes_mission_with_a2a_handoffs(): void
    {
        $channel = Channel::create(['handle' => '@distributedtech', 'title' => 'Distributed Tech']);
        Outlier::create([
            'channel_id' => $channel->id,
            'video_id' => 'v_swarm_1',
            'title' => 'SQLite Under 100k Concurrent Conns',
            'url' => 'https://youtube.com/watch?v=v_swarm_1',
            'views' => 180000,
            'median_views' => 25000,
            'outlier_score' => 7.2,
            'why_it_worked' => 'Shocking in-memory latency tests',
            'published_at' => now()->subDay(),
        ]);

        $callStep = 0;
        Llm::$fake = function (string $system, string $user) use (&$callStep) {
            $callStep++;
            if (str_contains($system, 'multi-agent coordinator')) {
                return json_encode([
                    'title' => 'SQLite vs Redis Campaign',
                    'steps' => [
                        ['id' => 'step_1', 'agent' => 'signal_scout', 'task' => 'Scan competitor breakouts on SQLite.', 'handoff_to' => 'angle_strategist'],
                        ['id' => 'step_2', 'agent' => 'angle_strategist', 'task' => 'Find contrarian engineering angle.', 'handoff_to' => 'platform_copywriter'],
                        ['id' => 'step_3', 'agent' => 'platform_copywriter', 'task' => 'Draft native LinkedIn and X posts.', 'handoff_to' => 'grounding_auditor'],
                        ['id' => 'step_4', 'agent' => 'grounding_auditor', 'task' => 'Verify claims and lint AI tells.', 'handoff_to' => null],
                    ],
                ]);
            }
            if (str_contains($system, 'Engineering strategist')) {
                return json_encode([
                    'angles' => [
                        [
                            'headline' => 'Local SQLite Beats Distributed Redis',
                            'hook' => 'We benchmarked SQLite vs Redis over 100k requests: zero network hops won by 4.2x.',
                            'rationale' => 'Network roundtrips dominate latency at scale.',
                        ],
                    ],
                ]);
            }
            if (str_contains($system, 'Writing rules') || str_contains($user, 'Draft native social posts')) {
                return json_encode([
                    'posts' => [
                        [
                            'platform' => 'linkedin',
                            'body' => "We benchmarked SQLite vs Redis over 100k requests: zero network hops won by 4.2x.\n\nHere is our architectural breakdown of eliminating Redis clusters.",
                            'parts' => [],
                        ],
                    ],
                ]);
            }

            return 'Swarm execution step output';
        };

        $swarm = app(AgentSwarm::class);

        // 1. Plan Mission
        $mission = $swarm->planMission($this->agent, 'Create a launch campaign around SQLite vs Redis');
        $this->assertEquals('SQLite vs Redis Campaign', $mission->title);
        $this->assertCount(4, $mission->steps);
        $this->assertEquals(0, $mission->current_step);
        $this->assertEquals('pending', $mission->status);

        // 2. Step First Agent (Signal Scout)
        $stepped = $swarm->stepMission($mission);
        $this->assertEquals(1, $stepped->current_step);
        $this->assertEquals('completed', $stepped->steps[0]['status']);
        $this->assertNotEmpty($stepped->steps[0]['output']['breakouts']);

        // 3. Run Remaining Steps To Completion
        $completed = $swarm->runToCompletion($stepped);
        $this->assertEquals('completed', $completed->status);
        $this->assertEquals(4, $completed->current_step);
        $this->assertNotNull($completed->result);
    }

    public function test_swarm_api_endpoints(): void
    {
        Llm::$fake = function (string $system, string $user) {
            return json_encode([
                'title' => 'API Swarm Campaign',
                'steps' => [
                    ['id' => 'step_1', 'agent' => 'signal_scout', 'task' => 'Inspect signals', 'handoff_to' => 'platform_copywriter'],
                    ['id' => 'step_2', 'agent' => 'platform_copywriter', 'task' => 'Write post', 'handoff_to' => null],
                ],
                'posts' => [
                    ['platform' => 'linkedin', 'body' => 'High performance post.', 'parts' => []],
                ],
            ]);
        };

        // Plan
        $planRes = $this->postJson("/api/agents/{$this->agent->id}/swarm/plan", [
            'goal' => 'Test campaign rollout',
            'platforms' => ['linkedin'],
        ]);
        $planRes->assertOk();
        $planRes->assertJsonStructure(['message', 'mission' => ['id', 'title', 'steps', 'status']]);
        $missionId = $planRes->json('mission.id');

        // Step
        $stepRes = $this->postJson("/api/agents/missions/{$missionId}/step");
        $stepRes->assertOk();
        $this->assertEquals(1, $stepRes->json('mission.current_step'));

        // Run full
        $runRes = $this->postJson("/api/agents/missions/{$missionId}/run");
        $runRes->assertOk();
        $this->assertEquals('completed', $runRes->json('mission.status'));

        // List
        $listRes = $this->getJson("/api/agents/{$this->agent->id}/missions");
        $listRes->assertOk();
        $this->assertNotEmpty($listRes->json('missions'));
    }

    public function test_automated_prompt_optimizer_dspy_pipeline(): void
    {
        Llm::$fake = function (string $system, string $user) {
            if (str_contains($system, 'prompt-optimizer')) {
                return json_encode([
                    'changes_summary' => 'Added explicit opening metric anchor heuristic and banned generic adjectives.',
                    'optimized_prompt' => "Systems programming practitioner. Rule 1: Always open with a concrete number in sentence 1. Never use corporate filler words.",
                ]);
            }

            return "100k requests per second benchmark on 64-core bare metal server. Zero network hops with local SQLite.";
        };

        $optimizer = app(PromptOptimizer::class);
        $res = $optimizer->optimize($this->agent);

        $this->assertArrayHasKey('baseline_score', $res);
        $this->assertArrayHasKey('optimized_score', $res);
        $this->assertArrayHasKey('version', $res);
        $this->assertEquals(1, $res['version']);

        $this->assertEquals(1, AgentPromptVersion::where('social_agent_id', $this->agent->id)->count());

        // API Endpoint tests
        $apiOptRes = $this->postJson("/api/agents/{$this->agent->id}/prompt/optimize");
        $apiOptRes->assertOk();
        $apiOptRes->assertJsonStructure(['message', 'result' => ['baseline_score', 'optimized_score', 'version']]);

        $versRes = $this->getJson("/api/agents/{$this->agent->id}/prompt/versions");
        $versRes->assertOk();
        $this->assertCount(2, $versRes->json('versions'));
    }
}
