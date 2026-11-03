<?php

namespace Tests\Feature;

use App\Domains\Agents\Arbitrage\RepurposingArbitrageEngine;
use App\Domains\Agents\Harness\ToolRegistry;
use App\Domains\Agents\Infra\StudioInfraGuardian;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Agents\Monetization\SponsorRevenueArchitect;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HighRoiFeaturesTest extends TestCase
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
        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'UTC'));

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
            'brand' => 'Tech Engineering & Infrastructure',
            'audience' => 'Senior Developers and Tech Leads',
        ]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_sponsor_revenue_architect_calculates_optimal_placement_and_cpm_yield(): void
    {
        $architect = new SponsorRevenueArchitect();
        $sampleScript = str_repeat('In this video we are building scalable Kubernetes clusters with Terraform. ', 40);

        $result = $architect->architect($sampleScript, [
            'brand' => 'Datadog Cloud',
            'base_cpm' => 50.0,
            'projected_views' => 20000,
        ]);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('target_timestamp', $result['video_metrics']);
        $this->assertEquals(1000.0, $result['financial_yield']['gross_sponsor_fee']);
        $this->assertEquals(850.0, $result['financial_yield']['net_creator_yield']);
        $this->assertArrayHasKey('segue_transition', $result['integration']);
        $this->assertArrayHasKey('ftc_disclosure', $result['integration']);
    }

    public function test_repurposing_arbitrage_engine_synthesizes_multi_platform_assets(): void
    {
        $engine = new RepurposingArbitrageEngine();
        $sourceText = 'Modern cloud architecture requires declarative Infrastructure as Code. Never configure cloud servers by hand.';

        $result = $engine->synthesize($sourceText);

        $this->assertEquals('success', $result['status']);
        $this->assertCount(4, $result['generated_platforms']);
        $this->assertArrayHasKey('linkedin', $result['output']);
        $this->assertArrayHasKey('x_thread', $result['output']);
        $this->assertArrayHasKey('tiktok_script', $result['output']);
        $this->assertArrayHasKey('instagram_carousel', $result['output']);
        $this->assertGreaterThanOrEqual(80, $result['output']['arbitrage_analysis']['viral_velocity_score']);
    }

    public function test_studio_infra_guardian_audits_subsystems(): void
    {
        $guardian = new StudioInfraGuardian();
        $report = $guardian->audit();

        $this->assertEquals('operational', $report['status']);
        $this->assertEquals('healthy', $report['diagnostics']['database']['status']);
        $this->assertEquals('healthy', $report['diagnostics']['cache']['status']);
        $this->assertTrue($report['diagnostics']['terraform']['validation_ready']);
        $this->assertTrue($report['diagnostics']['helm']['all_templates_present']);
    }

    public function test_tool_registry_exposes_and_executes_new_high_roi_tools(): void
    {
        $registry = new ToolRegistry();
        $toolNames = array_column(ToolRegistry::definitions(), 'name');

        $this->assertContains('architect_sponsor_placement', $toolNames);
        $this->assertContains('calculate_repurposing_arbitrage', $toolNames);
        $this->assertContains('inspect_infra_health', $toolNames);

        $infraReport = $registry->executeTool('inspect_infra_health');
        $this->assertEquals('operational', $infraReport['status']);

        $arbitrageReport = $registry->executeTool('calculate_repurposing_arbitrage', [
            'content' => 'Building high-performance distributed systems with Redis and Laravel.',
        ]);
        $this->assertEquals('success', $arbitrageReport['status']);
    }

    public function test_agent_api_endpoints_for_high_roi_features(): void
    {
        // 1. Architect Sponsor Placement Endpoint
        $sponsorRes = $this->postJson("/api/agents/{$this->agent->id}/architect-sponsor-placement", [
            'script_text' => 'Let us explore how to configure EKS clusters cleanly using Terraform modules without config drift.',
            'brand' => 'HashiCorp',
            'base_cpm' => 55.0,
            'projected_views' => 25000,
        ]);
        $sponsorRes->assertOk();
        $sponsorRes->assertJsonPath('result.financial_yield.gross_sponsor_fee', 1375);

        // 2. Repurposing Arbitrage Endpoint
        $arbitrageRes = $this->postJson("/api/agents/{$this->agent->id}/repurposing-arbitrage", [
            'content' => 'Why manual cloud infrastructure is a technical liability in 2026.',
            'niche' => 'DevOps & Cloud Native',
        ]);
        $arbitrageRes->assertOk();
        $arbitrageRes->assertJsonPath('result.status', 'success');

        // 3. Infra Health Endpoint
        $infraRes = $this->getJson('/api/studio/infra/health');
        $infraRes->assertOk();
        $infraRes->assertJsonPath('status', 'operational');
    }

    public function test_community_blueprints_and_scaffolder_flow(): void
    {
        $blueprintsRes = $this->getJson('/api/portal/community/blueprints');
        $blueprintsRes->assertOk();
        $blueprintsRes->assertJsonStructure(['blueprints']);
        $this->assertNotEmpty($blueprintsRes->json('blueprints'));

        $scaffoldRes = $this->postJson('/api/portal/community/blueprints/scaffold', [
            'blueprint_id' => 'rag_enterprise',
            'project_name' => 'unicorn-rag-app',
            'database' => 'postgres',
            'cache' => 'redis',
        ]);
        $scaffoldRes->assertOk();
        $scaffoldRes->assertJsonPath('ok', true);
        $scaffoldRes->assertJsonStructure(['files' => ['docker_compose', 'readme', 'env']]);
        $this->assertStringContainsString('pgvector/pgvector:pg16', $scaffoldRes->json('files.docker_compose'));
    }

    public function test_community_showcase_submission_and_upvoting(): void
    {
        $showcaseRes = $this->getJson('/api/portal/community/showcase');
        $showcaseRes->assertOk();
        $showcaseRes->assertJsonStructure(['showcases']);

        $submitRes = $this->postJson('/api/portal/community/showcase', [
            'title' => 'Edge AI Streaming Proxy',
            'description' => 'Ultra-low-latency SSE proxy buffering tokens at Cloudflare edge.',
            'demo_url' => 'https://demo.ai-proxy.dev',
            'repo_url' => 'https://github.com/engineer/ai-proxy',
            'tags' => 'cloudflare,edge,streaming',
        ]);
        $submitRes->assertStatus(201);
        $submitRes->assertJsonPath('ok', true);
        $showcaseId = $submitRes->json('project.id');

        $upvoteRes = $this->postJson("/api/portal/community/showcase/{$showcaseId}/upvote");
        $upvoteRes->assertOk();
        $upvoteRes->assertJsonPath('ok', true);
        $this->assertGreaterThanOrEqual(1, $upvoteRes->json('upvotes'));
    }

    public function test_client_live_sprint_milestones_tracker(): void
    {
        $client = $this->client();
        $deal = \App\Models\Deal::create([
            'brand_name' => 'Tech Corp',
            'contact_email' => $client->email,
            'stage' => 'production',
            'user_id' => $client->id,
        ]);

        $milestonesRes = $this->actingAs($client)->getJson("/api/portal/requests/{$deal->id}/milestones");
        $milestonesRes->assertOk();
        $milestonesRes->assertJsonStructure([
            'deal_id',
            'deal_title',
            'client_company',
            'progress_percent',
            'phases',
            'deliverables',
            'next_demo_call',
        ]);
        $this->assertEquals(68, $milestonesRes->json('progress_percent'));
        $this->assertCount(5, $milestonesRes->json('phases'));

        // Restore admin user
        $this->actingAs($this->user);
    }

    public function test_team_production_pipeline_auto_repurpose_and_thumbnail_simulation(): void
    {
        $teamRes = $this->getJson('/api/team');
        $teamRes->assertOk();
        $teamRes->assertJsonStructure(['members', 'tasks', 'qa', 'metrics', 'roles_available']);
        $this->assertGreaterThan(0, $teamRes->json('metrics.velocity_monthly'));

        // Auto-repurpose cascade
        $repurposeRes = $this->postJson('/api/team/tasks/auto-repurpose', [
            'title' => 'Google Gemini 2.5 Coding Agents Complete Guide',
            'video_url' => 'https://youtube.com/watch?v=12345678',
        ]);
        $repurposeRes->assertStatus(201);
        $repurposeRes->assertJsonPath('ok', true);
        $this->assertCount(4, $repurposeRes->json('tasks_created'));

        // Thumbnail A/B Mobile Simulator
        $simRes = $this->postJson('/api/team/thumbnails/simulate', [
            'title' => 'Stop Writing Unit Tests Like This',
            'text_words_count' => 4,
            'has_face' => true,
            'has_text' => true,
        ]);
        $simRes->assertOk();
        $simRes->assertJsonPath('ok', true);
        $this->assertEquals(96, $simRes->json('metrics.mobile_readability_score'));
        $this->assertStringContainsString('AAA', $simRes->json('metrics.contrast_dark_mode'));

        // QA checklist toggle
        $qaRes = $this->postJson('/api/team/qa/toggle', [
            'key' => 'sponsor_disclosure',
        ]);
        $qaRes->assertOk();
        $qaRes->assertJsonPath('ok', true);
    }
}
