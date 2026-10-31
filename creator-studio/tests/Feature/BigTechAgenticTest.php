<?php

namespace Tests\Feature;

use App\Domains\Agents\Ensemble\MultiAgentDeliberation;
use App\Domains\Agents\Guardrails\AgentGuardrails;
use App\Domains\Agents\Harness\ToolRegistry;
use App\Domains\Agents\Mcp\McpServer;
use App\Domains\Agents\Memory\AgentEpisodicMemory;
use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Social\Models\SocialPost;
use App\Models\Channel;
use App\Models\Outlier;
use App\Models\Setting;
use App\Models\User;
use App\Services\Llm;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class BigTechAgenticTest extends TestCase
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

        $this->user = User::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'password' => 'secret123']);
        $this->actingAs($this->user);

        $this->agent = SocialAgent::create([
            'name' => 'Autonomous BigTech Agent',
            'platforms' => ['linkedin', 'x'],
            'post_types' => ['informative', 'opinions'],
            'posts_per_week' => 3,
            'approval' => 'auto',
            'brand' => 'Hardcore software architecture, distributed systems, zero fluff.',
            'audience' => 'Senior engineers and architects.',
        ]);
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        parent::tearDown();
    }

    public function test_mcp_server_implements_json_rpc_specification(): void
    {
        $server = app(McpServer::class);

        // 1. Initialize
        $initRes = $server->handle(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize']);
        $this->assertEquals('2.0', $initRes['jsonrpc']);
        $this->assertEquals('2024-11-05', $initRes['result']['protocolVersion']);
        $this->assertEquals('creator-studio-mcp-server', $initRes['result']['serverInfo']['name']);

        // 2. Tools List
        $listRes = $server->handle(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']);
        $this->assertCount(6, $listRes['result']['tools']);
        $this->assertEquals('fetch_breakouts', $listRes['result']['tools'][0]['name']);
        $this->assertArrayHasKey('inputSchema', $listRes['result']['tools'][0]);

        // 3. Tools Call
        $callRes = $server->handle([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => 'fetch_breakouts', 'arguments' => ['limit' => 2]],
        ]);
        $this->assertFalse($callRes['result']['isError']);
        $this->assertNotEmpty($callRes['result']['content']);

        // 4. Error recovery hint on unknown tool
        $errRes = $server->handle([
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => ['name' => 'non_existent_tool'],
        ]);
        $this->assertTrue($errRes['result']['isError']);
        $this->assertStringContainsString('Unknown tool', $errRes['result']['content'][0]['text']);

        // 5. HTTP Endpoints
        $httpRes = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 10,
            'method' => 'tools/list',
        ]);
        $httpRes->assertOk();
        $this->assertCount(6, $httpRes->json('result.tools'));

        $toolsRes = $this->getJson('/api/mcp/tools');
        $toolsRes->assertOk();
        $this->assertCount(6, $toolsRes->json('tools'));
    }

    public function test_episodic_memory_retrieves_winning_exemplars_and_anti_patterns(): void
    {
        // Seed winning post
        SocialPost::create([
            'social_agent_id' => $this->agent->id,
            'platform' => 'linkedin',
            'format' => 'post',
            'status' => 'posted',
            'body' => "Most distributed locks fail because engineers forget clocks drift.\n\nHere is how we solved consensus using hybrid logical clocks.",
            'posted_at' => now()->subDays(3),
            'metrics' => [
                'impressions' => 8000,
                'likes' => 350,
                'comments' => 50,
                'shares' => 20,
            ],
        ]);

        // Seed rejected draft and agent event
        $rejectedPost = SocialPost::create([
            'social_agent_id' => $this->agent->id,
            'platform' => 'linkedin',
            'format' => 'post',
            'approval' => 'rejected',
            'rejection_reason' => 'Too salesy and sound like generic AI fluff',
            'body' => 'Unlock the game-changer of paradigm shifts in 2026.',
        ]);

        AgentEvent::log('rejected', 'Too salesy and sound like generic AI fluff', $this->agent->id, $rejectedPost->id, 'linkedin');

        $memory = new AgentEpisodicMemory();
        $winning = $memory->getWinningExemplars($this->agent, 'linkedin');
        $this->assertCount(1, $winning);
        $this->assertEquals(5.25, $winning[0]['rate']);
        $this->assertStringContainsString('distributed locks', $winning[0]['hook']);

        $antiPatterns = $memory->getAntiPatterns($this->agent);
        $this->assertNotEmpty($antiPatterns);
        $this->assertStringContainsString('Too salesy', $antiPatterns[0]['reason']);

        $context = $memory->buildMemoryContext($this->agent, 'linkedin');
        $this->assertStringContainsString('AGENT EPISODIC & PROCEDURAL MEMORY', $context);
        $this->assertStringContainsString('Proven High-Engagement Exemplars', $context);
        $this->assertStringContainsString('Known Anti-Patterns & Rejection Traps', $context);

        // API Endpoint test
        $res = $this->getJson("/api/agents/{$this->agent->id}/memory");
        $res->assertOk();
        $res->assertJsonStructure(['winning_exemplars', 'anti_patterns', 'human_edits', 'total_memory_points']);
        $this->assertGreaterThanOrEqual(1, $res->json('total_memory_points'));
    }

    public function test_guardrails_prevent_loops_and_redact_secrets(): void
    {
        $guardrails = new AgentGuardrails();

        // 1. Loop detection circuit breaker
        $guardrails->recordAndValidateToolCall('fetch_breakouts', ['limit' => 3]);
        $guardrails->recordAndValidateToolCall('fetch_breakouts', ['limit' => 3]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/loop detected/i');
        $guardrails->recordAndValidateToolCall('fetch_breakouts', ['limit' => 3]);
    }

    public function test_guardrails_redact_secrets_and_pii(): void
    {
        $guardrails = new AgentGuardrails();

        $dirtyContent = "Our secret API key is sk-1234567890abcdef1234567890 and contact delia@internal-corp.org for unreleased rates.";
        $clean = $guardrails->sanitizeContent($dirtyContent);

        $this->assertStringNotContainsString('sk-1234567890abcdef1234567890', $clean);
        $this->assertStringNotContainsString('delia@internal-corp.org', $clean);
        $this->assertStringContainsString('[REDACTED_API_KEY_/_TOKEN]', $clean);
        $this->assertStringContainsString('[REDACTED_PRIVATE_EMAIL]', $clean);

        $summary = $guardrails->summary();
        $this->assertCount(2, $summary['redactions']);

        $res = $this->getJson('/api/agents/guardrails');
        $res->assertOk();
        $res->assertJsonStructure(['steps', 'prompt_tokens', 'completion_tokens', 'estimated_cost_usd', 'violations', 'redactions', 'healthy']);
    }

    public function test_multi_agent_deliberation_committee_pipeline(): void
    {
        $channel = Channel::create(['handle' => '@archtech', 'title' => 'Architecture Tech']);
        $outlier = Outlier::create([
            'channel_id' => $channel->id,
            'video_id' => 'v_delib_1',
            'title' => 'Why Monoliths Won',
            'url' => 'https://youtube.com/watch?v=v_delib_1',
            'views' => 200000,
            'median_views' => 30000,
            'outlier_score' => 6.6,
            'why_it_worked' => 'Cost savings benchmark analysis',
            'published_at' => now()->subDay(),
        ]);

        $callCount = 0;
        Llm::$fake = function (string $system, string $user) use (&$callCount) {
            $callCount++;
            if (str_contains($system, 'Creative Generator') || str_contains($system, 'Persona')) {
                return json_encode([
                    'body' => "We cut cloud infra bills by 68% migrating from Kubernetes back to a single 64-core Bare Metal server.\n\nHere is what nobody tells you about distributed complexity.",
                    'parts' => [],
                ]);
            }
            if (str_contains($system, 'factual auditor')) {
                return json_encode([
                    'grounding_score' => 92,
                    'hallucinated_claims' => [],
                    'verdict' => 'grounded',
                ]);
            }
            if (str_contains($system, 'editorial voice critic')) {
                return json_encode([
                    'voice_score' => 88,
                    'fluff_detected' => [],
                    'ftc_compliant' => true,
                ]);
            }
            if (str_contains($system, 'writingRules')) {
                return json_encode([
                    'body' => "We cut cloud infra bills by 68% migrating from Kubernetes back to a single 64-core Bare Metal server.\n\nHere is what nobody tells you about distributed complexity.",
                    'parts' => [],
                ]);
            }

            return 'Deliberation agent output';
        };

        $response = $this->postJson("/api/agents/{$this->agent->id}/deliberate", [
            'topic' => 'Kubernetes vs Bare Metal for solo founders',
            'platform' => 'linkedin',
            'source_signal' => [
                'outlier_title' => $outlier->title,
                'views' => $outlier->views,
            ],
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'post' => ['platform', 'body', 'parts'],
            'trace' => [
                '*' => ['agent', 'role', 'output', 'passed'],
            ],
            'grounding_score',
            'voice_score',
            'guardrails',
        ]);

        $this->assertEquals(92, $response->json('grounding_score'));
        $this->assertEquals(88, $response->json('voice_score'));
        $this->assertCount(4, $response->json('trace'));
        $this->assertEquals('Creative Generator', $response->json('trace.0.agent'));
        $this->assertEquals('Grounding & Fact Auditor', $response->json('trace.1.agent'));
        $this->assertEquals('Voice & Safety Critic', $response->json('trace.2.agent'));
        $this->assertEquals('Consensus & Optimizer', $response->json('trace.3.agent'));
    }
}
