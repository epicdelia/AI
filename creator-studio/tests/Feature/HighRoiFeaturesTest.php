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

    public function test_sponsor_revenue_architect_calculates_optimal_placement_and_cpm_yield(): void
    {
        $architect = new SponsorRevenueArchitect();
        $sampleScript = str_repeat("In this video we are building scalable Kubernetes clusters with Terraform. ", 40);

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
        $sourceText = "Modern cloud architecture requires declarative Infrastructure as Code. Never configure cloud servers by hand.";

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
}
