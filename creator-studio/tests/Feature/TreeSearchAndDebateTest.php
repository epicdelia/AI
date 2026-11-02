<?php

namespace Tests\Feature;

use App\Domains\Agents\Debate\AdversarialDebate;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Agents\TreeSearch\AgentTreeSearch;
use App\Models\Setting;
use App\Models\User;
use App\Services\Llm;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreeSearchAndDebateTest extends TestCase
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
        $this->travelTo(CarbonImmutable::parse('2026-11-01 16:00', 'UTC'));

        $this->user = $this->admin([
            'name' => 'Margaret Hamilton',
            'email' => 'margaret@example.com',
            'password' => 'secret123',
        ]);
        $this->actingAs($this->user);

        $this->agent = SocialAgent::create([
            'name' => 'LATS & Debate AI Agent',
            'platforms' => ['linkedin', 'x'],
            'post_types' => ['informative', 'opinions'],
            'posts_per_week' => 3,
            'approval' => 'auto',
            'brand' => 'Mission-critical software, zero downtime, asynchronous event architectures.',
            'audience' => 'Principal engineers and infrastructure architects.',
        ]);
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        parent::tearDown();
    }

    public function test_language_agent_tree_search_explores_prunes_and_finds_winning_trajectory(): void
    {
        Llm::$fake = function (string $system, string $user) {
            if (str_contains($system, 'Principal engineer & architecture critic')) {
                return json_encode([
                    'angles' => [
                        [
                            'angle_name' => 'Zero-Downtime Migration',
                            'hypothesis' => 'Dual-write patterns eliminate migration downtime.',
                        ],
                        [
                            'angle_name' => 'Clock Drift Pitfalls',
                            'hypothesis' => 'Hardware clocks always desynchronize under network partitions.',
                        ],
                    ],
                ]);
            }
            if (str_contains($system, 'Writing rules') || str_contains($user, 'Draft 2 distinct post variations')) {
                return json_encode([
                    'drafts' => [
                        [
                            'body' => "Dual-write migrations fail when teams try to sync states instead of appending immutable transaction events.\n\nHere is how we migrated 40M rows with 0ms downtime.",
                            'parts' => [],
                        ],
                        [
                            'body' => "Let's delve into this game-changer in database migrations — leverage cutting-edge paradigms for your journey.",
                            'parts' => [],
                        ],
                    ],
                ]);
            }

            return 'Tree search output';
        };

        $treeSearch = app(AgentTreeSearch::class);
        $result = $treeSearch->search($this->agent, [
            'topic' => 'Zero-downtime database migrations',
            'platform' => 'linkedin',
            'branches_per_level' => 2,
        ]);

        $this->assertArrayHasKey('winning_post', $result);
        $this->assertArrayHasKey('tree', $result);
        $this->assertArrayHasKey('stats', $result);

        $tree = $result['tree'];
        $this->assertNotEmpty($tree['nodes']);
        $this->assertNotEmpty($tree['edges']);
        $this->assertNotEmpty($tree['pruned_nodes']); // Cliché draft was pruned
        $this->assertCount(3, $tree['winning_path']); // root -> angle -> winning draft

        $this->assertStringContainsString('Dual-write migrations', $result['winning_post']['body']);

        // API Endpoint test
        $res = $this->postJson("/api/agents/{$this->agent->id}/tree-search", [
            'topic' => 'Zero-downtime database migrations',
            'platform' => 'linkedin',
        ]);
        $res->assertOk();
        $res->assertJsonStructure([
            'message',
            'post',
            'tree' => ['nodes', 'edges', 'winning_path'],
            'stats',
        ]);
    }

    public function test_adversarial_red_team_debate_produces_armored_post(): void
    {
        Llm::$fake = function (string $system, string $user) {
            if (str_contains($system, 'Confident practitioner')) {
                return json_encode([
                    'body' => 'We replaced our 12-node microservices architecture with a single modular monolith and cut p99 response times from 340ms to 8ms.',
                    'parts' => [],
                ]);
            }
            if (str_contains($system, 'adversarial red-team code auditor')) {
                return json_encode([
                    'objections' => [
                        'Single monolith creates deployment bottlenecks with 50+ engineers.',
                        'Does not account for team ownership domains.',
                    ],
                    'counter_evidence' => 'Conway\'s law dictates organizational structure reflects codebase.',
                ]);
            }
            if (str_contains($system, 'Senior practitioner responding')) {
                return 'Valid point for 100-person teams, but for teams under 20 engineers, network hops cause more friction than code ownership.';
            }
            if (str_contains($system, 'Arbiter of truth')) {
                return json_encode([
                    'body' => "We replaced our 12-node microservices architecture with a modular monolith and cut p99 latency from 340ms to 8ms.\n\nWhile teams over 50 engineers eventually need bounded domains, teams under 20 lose more time debugging distributed network hops than writing features.",
                    'parts' => [],
                    'confidence_score' => 94,
                ]);
            }

            return 'Debate output';
        };

        $debate = app(AdversarialDebate::class);
        $result = $debate->debate($this->agent, [
            'topic' => 'Microservices vs Monolith for growing startups',
            'platform' => 'linkedin',
        ]);

        $this->assertArrayHasKey('armored_post', $result);
        $this->assertArrayHasKey('debate_transcript', $result);
        $this->assertArrayHasKey('vulnerabilities_neutralized', $result);
        $this->assertEquals(94, $result['confidence_score']);

        $transcript = $result['debate_transcript'];
        $this->assertCount(4, $transcript);
        $this->assertEquals('Creator Advocate', $transcript[0]['agent']);
        $this->assertEquals('Red-Team Cynic', $transcript[1]['agent']);
        $this->assertEquals('Creator Defense', $transcript[2]['agent']);
        $this->assertEquals('Arbiter Judge', $transcript[3]['agent']);

        // API Endpoint test
        $res = $this->postJson("/api/agents/{$this->agent->id}/debate", [
            'topic' => 'Microservices vs Monolith for growing startups',
            'platform' => 'linkedin',
        ]);
        $res->assertOk();
        $res->assertJsonStructure(['message', 'post', 'transcript', 'vulnerabilities_neutralized', 'confidence_score']);
        $this->assertEquals(94, $res->json('confidence_score'));
    }
}
