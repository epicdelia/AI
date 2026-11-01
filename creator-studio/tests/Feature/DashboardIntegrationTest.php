<?php

namespace Tests\Feature;

use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Social\Models\SocialPost;
use App\Models\Deal;
use App\Models\Script;
use App\Models\Setting;
use App\Models\User;
use App\Models\Week;
use App\Services\NextStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        $this->user = $this->admin(['name' => 'Delia', 'email' => 'delia@example.com', 'password' => 'secret123']);
        $this->actingAs($this->user);
    }

    public function test_dashboard_endpoint_includes_deliverables_and_agent_approvals(): void
    {
        // 1. Create an active deal with a pending deliverable
        $deal = Deal::create([
            'brand_name' => 'Supabase',
            'stage' => 'contracted',
            'rate' => 2500,
            'deliverables' => [
                [
                    'platform' => 'youtube',
                    'format' => 'integration',
                    'due_date' => now()->addDays(2)->toDateString(),
                    'status' => 'pending',
                ],
            ],
        ]);

        // 2. Create an agent with a pending draft
        $agent = SocialAgent::create([
            'name' => 'Tech Pulse Agent',
            'platforms' => ['linkedin'],
            'post_types' => ['informative'],
            'brand' => 'Tech Unicorn',
            'audience' => 'Engineers',
            'posts_per_week' => 5,
        ]);

        $post = SocialPost::create([
            'platform' => 'linkedin',
            'format' => 'post',
            'title' => 'Why SQLite in production makes sense',
            'body' => 'Here is why SQLite is amazing...',
            'approval' => 'pending',
            'status' => 'draft',
            'social_agent_id' => $agent->id,
            'scheduled_for' => now()->addDays(1),
        ]);

        $res = $this->getJson('/api/dashboard');
        $res->assertOk();
        $res->assertJsonStructure([
            'active_deliverables',
            'agent_approvals' => ['total', 'items'],
            'deals_summary' => ['active_count', 'confirmed_revenue', 'pipeline_total'],
        ]);

        $this->assertSame(1, $res->json('agent_approvals.total'));
        $this->assertSame($post->id, $res->json('agent_approvals.items.0.id'));
        $this->assertSame('Tech Pulse Agent', $res->json('agent_approvals.items.0.agent_name'));

        $this->assertCount(1, $res->json('active_deliverables'));
        $this->assertSame('Supabase', $res->json('active_deliverables.0.brand_name'));
        $this->assertSame(2500.0, (float) $res->json('active_deliverables.0.rate'));
        $this->assertStringContainsString('Due in 2 days', $res->json('active_deliverables.0.due_label'));
    }

    public function test_next_step_prioritizes_urgent_brand_deliverable(): void
    {
        Deal::create([
            'brand_name' => 'Notion',
            'stage' => 'production',
            'rate' => 3000,
            'deliverables' => [
                [
                    'platform' => 'youtube',
                    'format' => 'integration',
                    'due_date' => now()->addDay()->toDateString(),
                    'status' => 'pending',
                ],
            ],
        ]);

        $step = NextStep::current();
        $this->assertNotNull($step);
        $this->assertSame('deal_deliverable', $step['key']);
        $this->assertStringContainsString('Notion', $step['title']);
        $this->assertStringContainsString('Due tomorrow', $step['detail']);
    }

    public function test_next_step_prioritizes_agent_drafts_when_script_shipped(): void
    {
        $week = Week::create([
            'number' => 1,
            'start_date' => now()->subDays(6)->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
            'status' => 'shipped',
        ]);

        $agent = SocialAgent::create([
            'name' => 'Unicorn Post Bot',
            'platforms' => ['x'],
            'post_types' => ['opinions'],
            'brand' => 'Tech Unicorn',
            'audience' => 'Devs',
            'posts_per_week' => 3,
        ]);

        SocialPost::create([
            'platform' => 'x',
            'format' => 'post',
            'title' => 'Hot take on Next.js 15',
            'body' => 'Turbopack is finally fast.',
            'approval' => 'pending',
            'status' => 'draft',
            'social_agent_id' => $agent->id,
        ]);

        $step = NextStep::current();
        $this->assertNotNull($step);
        $this->assertSame('agent_approval', $step['key']);
        $this->assertStringContainsString('Review 1 post draft', $step['title']);
    }

    public function test_attach_deal_to_script_injects_sponsor_markdown(): void
    {
        $script = Script::create([
            'title' => 'Building a Local AI Agent in PHP',
            'body' => "## Introduction\nLet's build an AI assistant from scratch.",
        ]);

        $deal = Deal::create([
            'brand_name' => 'AssemblyAI',
            'stage' => 'contracted',
            'rate' => 1800,
            'coupon_code' => 'UNICORN20',
            'tracking_url' => 'https://assemblyai.com/techunicorn',
            'key_talking_points' => "- Universal-2 speech model\n- 50 lines of code streaming",
        ]);

        $res = $this->postJson("/api/scripts/{$script->id}/sponsor", [
            'deal_id' => $deal->id,
            'inject_segment' => true,
        ]);

        $res->assertOk();
        $res->assertJsonPath('script.deals.0.brand_name', 'AssemblyAI');

        $deal->refresh();
        $this->assertSame($script->id, $deal->script_id);
        $this->assertSame('production', $deal->stage);

        $script->refresh();
        $this->assertStringContainsString('[SPONSORED SEGMENT: AssemblyAI]', $script->body);
        $this->assertStringContainsString('UNICORN20', $script->body);
        $this->assertStringContainsString('https://assemblyai.com/techunicorn', $script->body);
        $this->assertStringContainsString('Universal-2 speech model', $script->body);
    }
}
