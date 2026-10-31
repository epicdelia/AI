<?php

namespace Tests\Feature;

use App\Domains\Agents\AgentLearner;
use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Agents\Publisher;
use App\Domains\Social\Models\SocialAccount;
use App\Domains\Social\Models\SocialPost;
use App\Models\Setting;
use App\Models\User;
use App\Services\Llm;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Setting::put('timezone', 'UTC');
        Setting::put('llm_api_key', 'test');
        config(['app.url' => 'https://studio.test']);
        $this->travelTo(CarbonImmutable::parse('2026-10-28 06:00', 'UTC'));
        $this->actingAs(User::create(['name' => 'Delia', 'email' => 'd@example.com', 'password' => 'secret123']));
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        parent::tearDown();
    }

    private function agent(array $attrs = []): SocialAgent
    {
        $id = $this->postJson('/api/agents', $attrs + [
            'name' => 'Tech Unicorn agent', 'platforms' => ['linkedin', 'threads'], 'brand' => 'Delia makes videos about AI tools and productivity for women in tech.',
            'post_types' => ['informative', 'tips_and_tricks'], 'posts_per_week' => 2, 'approval' => 'review',
            'options' => ['hashtags' => true, 'hashtag_count' => 2, 'emoji' => false],
        ])->assertCreated()->json('agent.id');

        return SocialAgent::find($id);
    }

    private function fakePosts(): void
    {
        Llm::$fake = function (string $system, string $user) {
            $posts = [];
            foreach (['linkedin', 'linkedin', 'threads', 'threads'] as $i => $p) {
                $posts[] = ['platform' => $p, 'title' => "Idea {$i}", 'body' => "Post {$i} about AI tools for {$p}.", 'parts' => [], 'why' => 'Fresh angle'];
            }

            return json_encode(['posts' => $posts]);
        };
    }

    public function test_create_validates_and_logs(): void
    {
        $this->postJson('/api/agents', ['name' => 'x'])->assertStatus(422);
        $this->postJson('/api/agents', ['name' => 'Bad', 'platforms' => ['myspace'], 'brand' => str_repeat('a', 30), 'post_types' => ['informative'], 'posts_per_week' => 2, 'approval' => 'review'])->assertStatus(422);
        $agent = $this->agent();
        $this->assertSame(['linkedin', 'threads'], $agent->platforms);
        $this->assertSame(1, AgentEvent::where('type', 'created')->count());
        $this->getJson('/api/agents')->assertOk()->assertJsonPath('agents.0.name', 'Tech Unicorn agent')->assertJsonPath('agents.0.publishes.linkedin', 'manual');
    }

    public function test_run_tops_up_week_into_pending_approvals_with_slots_and_lessons_in_prompt(): void
    {
        $agent = $this->agent();
        $agent->update(['lessons' => [['text' => 'Open with a concrete result', 'evidence' => 'top 3 posts']]]);
        $seen = null;
        $this->fakePosts();
        $inner = Llm::$fake;
        Llm::$fake = function ($s, $u) use (&$seen, $inner) { $seen = $s; return $inner($s, $u); };

        $this->postJson("/api/agents/{$agent->id}/run")->assertOk()->assertJsonPath('message', '4 new post(s) planned.');
        $this->assertStringContainsString('Open with a concrete result', $seen);
        $this->assertStringContainsString('at most 2', $seen);

        $posts = SocialPost::where('social_agent_id', $agent->id)->get();
        $this->assertCount(4, $posts);
        $this->assertTrue($posts->every(fn ($p) => $p->approval === 'pending' && $p->status === 'draft' && $p->scheduled_for && $p->agent_original));
        // one per day per network: the two LinkedIn posts land on different days
        $li = $posts->where('platform', 'linkedin')->pluck('scheduled_for')->map->toDateString()->unique();
        $this->assertCount(2, $li);

        // the week is full now: a second run writes nothing
        $this->postJson("/api/agents/{$agent->id}/run")->assertOk()->assertJsonPath('message', 'The next 7 days are already full for this agent.');
        $this->assertSame(1, AgentEvent::where('type', 'drafted')->count());
    }

    public function test_auto_agent_schedules_and_publisher_posts_to_connected_linkedin_only(): void
    {
        $agent = $this->agent(['approval' => 'auto']);
        $this->fakePosts();
        $this->postJson("/api/agents/{$agent->id}/run")->assertOk();
        $this->assertSame(4, SocialPost::where('status', 'scheduled')->where('approval', 'approved')->count());

        SocialAccount::create(['platform' => 'linkedin', 'access_token' => 'AT', 'external_id' => 'abc', 'expires_at' => now()->addDays(50)]);
        Setting::put('linkedin_client_id', 'i');
        Setting::put('linkedin_client_secret', 's');
        Http::fake(['api.linkedin.com/rest/posts' => Http::response('', 201, ['x-restli-id' => 'urn:li:share:1'])]);

        $this->travel(8)->days();
        Publisher::due(); // nothing: those times are over 6 hours stale
        $this->assertSame(0, SocialPost::where('status', 'posted')->count());
        $this->travelBack();
        $this->travelTo(CarbonImmutable::parse('2026-10-28 06:00', 'UTC'));

        $first = SocialPost::where('platform', 'linkedin')->orderBy('scheduled_for')->first();
        $this->travelTo($first->scheduled_for->addMinute());
        $r = Publisher::due();
        $this->assertSame(1, $r['published']);
        $first->refresh();
        $this->assertSame('posted', $first->status);
        $this->assertSame('https://www.linkedin.com/feed/update/urn:li:share:1/', $first->url);
        Http::assertSent(fn ($req) => $req['author'] === 'urn:li:person:abc' && str_contains($req['commentary'], 'Post'));
        // Threads isn't connected: its posts wait for her in the due queue
        $this->assertSame(0, SocialPost::where('platform', 'threads')->where('status', 'posted')->count());
    }

    public function test_failed_publish_goes_back_to_approvals_without_retry_loop(): void
    {
        $agent = $this->agent(['approval' => 'auto', 'platforms' => ['threads']]);
        SocialAccount::create(['platform' => 'threads', 'access_token' => 'AT', 'external_id' => '1', 'expires_at' => now()->addDays(50)]);
        $post = SocialPost::create(['platform' => 'threads', 'format' => 'post', 'body' => 'Hi', 'status' => 'scheduled', 'scheduled_for' => now()->subMinute(),
            'approval' => 'approved', 'social_agent_id' => $agent->id]);
        Http::fake(['graph.threads.net/*' => Http::response(['error' => ['message' => 'Rate limited']], 400)]);
        $this->assertSame(['published' => 0, 'failed' => 1], Publisher::due());
        $post->refresh();
        $this->assertSame(['draft', 'pending'], [$post->status, $post->approval]);
        $this->assertStringContainsString('Rate limited', $post->publish_error);
        $this->assertSame(['published' => 0, 'failed' => 0], Publisher::due());
    }

    public function test_approve_with_edit_reject_with_reason_and_notifications(): void
    {
        $agent = $this->agent();
        $this->fakePosts();
        $this->postJson("/api/agents/{$agent->id}/run");
        [$a, $b] = SocialPost::where('social_agent_id', $agent->id)->orderBy('id')->take(2)->get()->all();

        $this->postJson("/api/agents/posts/{$a->id}/approve", ['body' => 'Completely different words written by her own hand today.'])->assertOk();
        $a->refresh();
        $this->assertSame(['scheduled', 'approved'], [$a->status, $a->approval]);
        $this->assertStringContainsString('edited', AgentEvent::where('type', 'approved')->value('message'));

        $this->postJson("/api/agents/posts/{$b->id}/reject", ['reason' => 'Too salesy'])->assertOk();
        $this->assertNull(SocialPost::find($b->id));
        $this->assertStringContainsString('Too salesy', AgentEvent::where('type', 'rejected')->value('message'));

        $n = $this->getJson('/api/agents/notifications')->assertOk();
        $this->assertGreaterThan(0, $n->json('unread'));
        $this->assertSame(2, $n->json('pending'));
        $this->postJson('/api/agents/notifications/read')->assertOk();
        $this->assertSame(0, $this->getJson('/api/agents/notifications')->json('unread'));
    }

    public function test_learning_needs_signals_then_updates_lessons_from_results_edits_and_rejections(): void
    {
        $agent = $this->agent();
        $this->postJson("/api/agents/{$agent->id}/learn")->assertStatus(422)->assertJsonFragment(['error' => 'Not enough to learn from yet: it needs at least 4 posts with numbers, edits or rejections (has 0).']);

        foreach ([[2000, 200], [1500, 30], [1000, 12], [800, 90]] as $i => [$imp, $likes]) {
            SocialPost::create(['platform' => 'linkedin', 'format' => 'post', 'body' => "Measured {$i}", 'status' => 'posted', 'posted_at' => now()->subDays($i + 1),
                'social_agent_id' => $agent->id, 'approval' => 'approved', 'agent_original' => "Measured {$i}", 'metrics' => ['impressions' => $imp, 'likes' => $likes]]);
        }
        $prompt = null;
        Llm::$fake = function ($s, $u) use (&$prompt) {
            $prompt = $u;

            return json_encode(['lessons' => [['text' => 'Lead with a number', 'evidence' => 'best post 10% rate'], ['text' => '', 'evidence' => 'dropped']], 'summary' => 'Numbers first.']);
        };
        $this->postJson("/api/agents/{$agent->id}/learn")->assertOk()->assertJsonPath('lessons.0.text', 'Lead with a number');
        $this->assertStringContainsString('Measured 3', $prompt); // 11.25% rate: best
        $this->assertSame(1, count($agent->fresh()->lessons));
        $this->assertStringContainsString('Numbers first.', AgentEvent::where('type', 'learned')->value('message'));

        $detail = $this->getJson("/api/agents/{$agent->id}")->assertOk();
        $this->assertSame(4, $detail->json('signals.measured'));
        $this->assertCount(4, $detail->json('trend'));
        $this->assertSame(4, collect($detail->json('trend'))->sum('posts'));
    }

    public function test_similarity_and_scheduled_commands(): void
    {
        $this->assertSame(1.0, AgentLearner::similarity('a b c', 'a b c'));
        $this->assertLessThan(0.5, AgentLearner::similarity('one two three four', 'five six seven eight'));

        $agent = $this->agent(['approval' => 'auto']);
        $this->fakePosts();
        Artisan::call('studio:agents');
        $this->assertSame(4, SocialPost::where('social_agent_id', $agent->id)->count());
        $this->assertTrue($agent->fresh()->next_run_at->isFuture());
        Artisan::call('studio:agents');
        $this->assertStringContainsString('No agent was due', Artisan::output());
    }

    public function test_wizard_helpers_refuse_private_addresses_and_parse_audiences(): void
    {
        $this->postJson('/api/agents/scrape', ['url' => 'http://127.0.0.1/admin'])->assertStatus(422)->assertJsonFragment(['error' => 'That address points to a private network.']);
        Llm::$fake = fn () => json_encode(['audiences' => [['name' => 'Women in tech', 'description' => 'Engineers looking for tools'], ['name' => '', 'description' => 'x']]]);
        $this->postJson('/api/agents/audiences', ['brand' => 'AI tools and productivity for women in tech careers.'])
            ->assertOk()->assertJsonCount(1, 'audiences')->assertJsonPath('audiences.0.name', 'Women in tech');
    }

    public function test_delete_removes_unapproved_drafts_but_keeps_approved(): void
    {
        $agent = $this->agent();
        $this->fakePosts();
        $this->postJson("/api/agents/{$agent->id}/run");
        $keep = SocialPost::where('social_agent_id', $agent->id)->first();
        $this->postJson("/api/agents/posts/{$keep->id}/approve")->assertOk();
        $this->deleteJson("/api/agents/{$agent->id}")->assertOk();
        $this->assertSame(1, SocialPost::count());
        $this->assertNull($keep->fresh()->social_agent_id);
    }
}
