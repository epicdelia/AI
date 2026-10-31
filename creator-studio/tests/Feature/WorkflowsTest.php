<?php

namespace Tests\Feature;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Engagement\Engagement;
use App\Domains\Engagement\Models\EngagementItem;
use App\Domains\Engagement\Models\EngagementRule;
use App\Domains\Social\Models\SocialAccount;
use App\Domains\Social\Models\SocialPost;
use App\Domains\Workflows\Engine;
use App\Domains\Workflows\Models\Workflow;
use App\Domains\Workflows\Models\WorkflowRun;
use App\Models\Setting;
use App\Models\User;
use App\Services\Llm;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkflowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Setting::put('timezone', 'UTC');
        Setting::put('llm_api_key', 'test');
        Setting::put('instagram_client_id', 'i');
        Setting::put('instagram_client_secret', 's');
        config(['app.url' => 'https://studio.test']);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 07:00', 'UTC')); // a Monday
        $this->actingAs(User::create(['name' => 'Delia', 'email' => 'd@example.com', 'password' => 'secret123']));
        SocialAccount::create(['platform' => 'instagram', 'access_token' => 'AT', 'external_id' => '178', 'handle' => 'tech.unicorn', 'expires_at' => now()->addDays(50)]);
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        parent::tearDown();
    }

    private function workflow(array $nodes, bool $active = true): Workflow
    {
        $id = $this->postJson('/api/workflows', ['name' => 'Test'])->assertCreated()->json('workflow.id');
        $this->putJson("/api/workflows/{$id}", ['nodes' => $nodes, 'active' => $active])->assertOk();

        return Workflow::find($id);
    }

    private function fakeInstagram(array $comments): void
    {
        Http::fake([
            'graph.instagram.com/v21.0/me/media*' => Http::response(['data' => [['id' => 'm1', 'timestamp' => '2026-11-01T10:00:00+0000', 'comments_count' => count($comments)]]]),
            'graph.instagram.com/v21.0/m1/comments*' => Http::response(['data' => $comments]),
            'graph.instagram.com/v21.0/me/conversations*' => Http::response(['data' => []]),
            'graph.instagram.com/v21.0/*/replies' => Http::response(['id' => 'r1']),
            'graph.instagram.com/v21.0/me/messages' => Http::response(['message_id' => 'mid1']),
        ]);
    }

    public function test_templates_create_readable_nodes_and_turning_on_requires_setup(): void
    {
        $r = $this->getJson('/api/workflows')->assertOk();
        $this->assertGreaterThanOrEqual(5, count($r->json('templates')));
        $this->assertArrayHasKey('any_comment', $r->json('catalog'));

        $wf = $this->postJson('/api/workflows', ['template' => 'keyword_dm'])->assertCreated()->json('workflow');
        $this->assertSame(['trigger_1', 'action_1', 'action_2'], array_column($wf['nodes'], 'key'));
        $this->assertSame([], $wf['problems']);

        $blank = $this->postJson('/api/workflows', [])->json('workflow.id');
        $this->putJson("/api/workflows/{$blank}", ['active' => true])->assertStatus(422)->assertJsonFragment(['problems' => ['Add at least one step after the trigger.']]);
        $this->putJson("/api/workflows/{$blank}", ['nodes' => [['type' => 'any_comment'], ['type' => 'send_dm', 'config' => ['mode' => 'template']]], 'active' => true])
            ->assertStatus(422)->assertJsonPath('problems.0', '“Send DM” isn’t set up yet.');
        $this->putJson("/api/workflows/{$blank}", ['nodes' => [['type' => 'send_dm']]])->assertStatus(422)->assertJsonFragment(['error' => 'A workflow starts with a trigger.']);
        $this->putJson("/api/workflows/{$blank}", ['nodes' => [['type' => 'schedule', 'config' => ['every' => 'day', 'time' => '09:00']], ['type' => 'reply_comment', 'config' => ['mode' => 'ai']]], 'active' => true])
            ->assertStatus(422)->assertJsonPath('problems.0', '“Reply to comment” needs a comment or DM trigger.');
    }

    public function test_keyword_comment_runs_reply_then_dm_once_and_rules_stand_aside(): void
    {
        EngagementRule::create(['name' => 'Rule', 'platforms' => ['instagram'], 'trigger' => 'any_comment', 'reply_mode' => 'template', 'reply_template' => 'RULE', 'auto_send' => true]);
        $this->workflow([
            ['type' => 'comment_keyword', 'config' => ['keywords' => ['guide'], 'platforms' => ['instagram']]],
            ['type' => 'reply_comment', 'config' => ['mode' => 'template', 'text' => 'Check your DMs {name}!']],
            ['type' => 'send_dm', 'config' => ['mode' => 'template', 'text' => 'Here: delia.link/guide']],
        ]);
        $this->fakeInstagram([['id' => 'c1', 'text' => 'GUIDE pls', 'timestamp' => '2026-11-02T06:00:00+0000', 'username' => 'ana', 'from' => ['id' => 'u1']]]);

        app(Engagement::class)->poll();

        $item = EngagementItem::where('external_id', 'c1')->first();
        $this->assertSame('replied', $item->status);
        $this->assertSame('workflow', $item->replied_by);
        $this->assertSame("Check your DMs @ana!\n\n[DM] Here: delia.link/guide", $item->reply);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'c1/replies') && $r['message'] === 'Check your DMs @ana!');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'me/messages') && $r['recipient'] === ['comment_id' => 'c1']);
        Http::assertNotSent(fn ($r) => ($r['message'] ?? null) === 'RULE');

        $run = WorkflowRun::first();
        $this->assertSame('done', $run->status);
        $this->assertCount(3, $run->log);
        $this->assertSame(1, Workflow::first()->runs_count);
        // the same comment never fires twice
        $this->assertSame(0, Engine::dispatch('comment', Engine::itemContext($item), "item:{$item->id}"));
    }

    public function test_condition_stops_and_wait_resumes_later(): void
    {
        $wf = $this->workflow([
            ['type' => 'any_comment', 'config' => []],
            ['type' => 'condition', 'config' => ['field' => 'intent', 'op' => 'is', 'value' => 'question']],
            ['type' => 'delay', 'config' => ['amount' => 10, 'unit' => 'minutes']],
            ['type' => 'notify', 'config' => ['message' => '{author} asked: {text}']],
        ]);
        $this->fakeInstagram([
            ['id' => 'q1', 'text' => 'Which mic is that?', 'timestamp' => '2026-11-02T06:00:00+0000', 'username' => 'ben'],
            ['id' => 'p1', 'text' => 'So good', 'timestamp' => '2026-11-02T06:01:00+0000', 'username' => 'cy'],
        ]);
        app(Engagement::class)->poll();

        $runs = WorkflowRun::orderBy('id')->get();
        $this->assertSame(['waiting', 'stopped'], $runs->pluck('status')->all());
        $this->assertSame(0, AgentEvent::where('type', 'workflow')->count());

        $this->travel(11)->minutes();
        Artisan::call('studio:workflows');
        $this->assertSame('done', $runs[0]->fresh()->status);
        $this->assertStringContainsString('Test: ben asked: Which mic is that?', AgentEvent::where('type', 'workflow')->value('message'));
        $this->assertSame(2, $wf->fresh()->runs_count); // the resumed run isn't counted twice
    }

    public function test_test_mode_never_sends_and_uses_sample_data(): void
    {
        $wf = $this->workflow([
            ['type' => 'comment_keyword', 'config' => ['keywords' => ['guide']]],
            ['type' => 'reply_comment', 'config' => ['mode' => 'template', 'text' => 'Sent {name}']],
            ['type' => 'delay', 'config' => ['amount' => 1, 'unit' => 'days']],
            ['type' => 'send_dm', 'config' => ['mode' => 'template', 'text' => 'Link']],
        ], false);
        Http::fake();
        $run = $this->postJson("/api/workflows/{$wf->id}/run", ['dry' => true])->assertOk()->json('run');
        $this->assertSame('done', $run['status']);
        $this->assertTrue($run['dry_run']);
        $this->assertStringContainsString('Would reply: Sent @sample_user', $run['log'][1]['message']);
        $this->assertStringContainsString('skipped in a test', $run['log'][2]['message']);
        $this->assertStringContainsString('Would DM', $run['log'][3]['message']);
        Http::assertNothingSent();
        $this->assertSame(0, $wf->fresh()->runs_count);

        $step = $this->postJson("/api/workflows/{$wf->id}/test-step", ['key' => 'action_1'])->assertOk();
        $this->assertTrue($step->json('ok'));
        $this->postJson("/api/workflows/{$wf->id}/run", ['dry' => false])->assertStatus(422);
    }

    public function test_post_published_repurposes_and_schedules_for_publisher(): void
    {
        $this->workflow([
            ['type' => 'post_published', 'config' => ['platforms' => ['linkedin']]],
            ['type' => 'repurpose', 'config' => ['platforms' => ['threads', 'linkedin'], 'status' => 'scheduled']],
        ]);
        Llm::$fake = fn () => json_encode(['posts' => [['platform' => 'threads', 'format' => 'post', 'title' => 'T', 'body' => 'Shorter take for Threads.']]]);
        $post = SocialPost::create(['platform' => 'linkedin', 'format' => 'post', 'body' => 'My long LinkedIn story about AI tools and what I learned.', 'status' => 'draft']);
        $post->update(['status' => 'posted']);
        app(Engine::class)->drain();

        $made = SocialPost::where('platform', 'threads')->first();
        $this->assertNotNull($made);
        $this->assertSame(['scheduled', 'approved', 'workflow', $post->id], [$made->status, $made->approval, $made->source, $made->source_post_id]);
        $this->assertTrue($made->scheduled_for->isFuture());
        $this->assertSame('done', WorkflowRun::first()->status);
    }

    public function test_post_takes_off_fires_once_above_multiplier(): void
    {
        $this->workflow([
            ['type' => 'post_performs', 'config' => ['multiplier' => 2]],
            ['type' => 'notify', 'config' => ['message' => '{platform} post at {text}']],
        ]);
        foreach ([1, 1.2, 0.9, 1.1, 1.0, 1.05] as $i => $rate) {
            SocialPost::create(['platform' => 'instagram', 'format' => 'post', 'body' => "Normal {$i}", 'status' => 'posted', 'source' => 'demo', 'posted_at' => now()->subDays(10 + $i),
                'metrics' => ['impressions' => 1000, 'likes' => (int) ($rate * 10)]]);
        }
        $hit = SocialPost::create(['platform' => 'instagram', 'format' => 'post', 'body' => 'The big one', 'status' => 'posted', 'source' => 'demo', 'posted_at' => now()->subDay(),
            'metrics' => ['impressions' => 1000, 'likes' => 40]]);
        $this->assertSame(1, Engine::checkPerformance('instagram'));
        $this->assertSame(0, Engine::checkPerformance('instagram'));
        app(Engine::class)->drain();
        $this->assertSame("perf:{$hit->id}", WorkflowRun::first()->trigger_ref);
        $this->assertStringContainsString('The big one', AgentEvent::where('type', 'workflow')->value('message'));
    }

    public function test_schedule_fires_once_per_day_and_failures_notify(): void
    {
        $this->workflow([
            ['type' => 'schedule', 'config' => ['every' => 'week', 'weekday' => '1', 'time' => '06:30']],
            ['type' => 'run_agent', 'config' => ['agent_id' => 999]],
        ]);
        $this->assertSame(1, Engine::tick());
        $this->assertSame(0, Engine::tick());
        app(Engine::class)->drain();
        $run = WorkflowRun::first();
        $this->assertSame('failed', $run->status);
        $this->assertSame('That agent no longer exists.', $run->error);
        $this->assertStringContainsString('stopped at a step', AgentEvent::where('type', 'failed')->value('message'));
        $this->travel(1)->days();
        $this->assertSame(0, Engine::tick()); // Tuesday: not the day
    }

    public function test_inactive_and_rate_limited_workflows_dont_fire(): void
    {
        $wf = $this->workflow([['type' => 'any_comment', 'config' => []], ['type' => 'notify', 'config' => ['message' => 'x']]], false);
        $this->assertSame(0, Engine::dispatch('comment', ['platform' => 'instagram', 'text' => 'hi', 'kind' => 'comment'], 'item:1'));
        $wf->update(['active' => true]);
        for ($i = 0; $i < Engine::MAX_RUNS_PER_HOUR; $i++) {
            Engine::dispatch('comment', ['platform' => 'instagram', 'text' => 'hi', 'kind' => 'comment'], "item:x{$i}");
        }
        $this->assertSame(0, Engine::dispatch('comment', ['platform' => 'instagram', 'text' => 'hi', 'kind' => 'comment'], 'item:over'));
    }
}
