<?php

namespace Tests\Feature;

use App\Domains\Agents\AgentPlanner;
use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Audience\Models\AudienceInsight;
use App\Domains\Feeds\Models\Feed;
use App\Domains\Feeds\Models\FeedItem;
use App\Domains\Social\Models\SocialPost;
use App\Models\Channel;
use App\Models\Outlier;
use App\Models\Script;
use App\Models\Setting;
use App\Models\User;
use App\Services\Llm;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutonomousAgentTest extends TestCase
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
        $this->travelTo(CarbonImmutable::parse('2026-10-31 09:00', 'UTC'));

        $this->user = $this->admin(['name' => 'Delia', 'email' => 'delia@example.com', 'password' => 'secret123']);
        $this->actingAs($this->user);

        $this->agent = SocialAgent::create([
            'name' => 'Autonomous Tech Bot',
            'platforms' => ['linkedin', 'x'],
            'post_types' => ['informative', 'opinions'],
            'posts_per_week' => 4,
            'approval' => 'auto',
            'brand' => 'Tech Unicorn developer tips and modern AI workflows.',
            'audience' => 'Software engineers and developers.',
        ]);
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        parent::tearDown();
    }

    public function test_gather_signals_compiles_breakouts_news_scripts_and_asks(): void
    {
        $channel = Channel::create(['handle' => '@techcreator', 'title' => 'Tech Creator']);
        Outlier::create([
            'channel_id' => $channel->id,
            'video_id' => 'v1',
            'title' => 'Why SQLite in Browser Is The Future',
            'url' => 'https://youtube.com/watch?v=v1',
            'views' => 150000,
            'median_views' => 25000,
            'outlier_score' => 6.0,
            'why_it_worked' => 'Hot architectural shift debate',
            'published_at' => now()->subDays(2),
        ]);

        $feed = Feed::create(['title' => 'Hacker News Top', 'url' => 'https://news.ycombinator.com/rss']);
        FeedItem::create([
            'feed_id' => $feed->id,
            'guid' => 'https://news.ycombinator.com/item?id=999',
            'title' => 'Python 3.14 free-threading benchmarks',
            'url' => 'https://news.ycombinator.com/item?id=999',
            'source' => 'Hacker News',
            'published_at' => now()->subHours(4),
        ]);

        Script::create([
            'title' => 'Local AI Agents on Mac',
            'body' => 'Here is how to run LLMs locally with zero cloud dependencies.',
            'status' => 'published',
        ]);

        AudienceInsight::create([
            'video_id' => 'v2',
            'video_title' => 'My Developer Setup',
            'url' => 'https://youtube.com/watch?v=v2',
            'summary' => 'Viewers asking what database to recommend for solo developer projects.',
            'items' => [
                ['kind' => 'question', 'text' => 'What database do you use for your side projects?'],
            ],
        ]);

        $res = $this->getJson('/api/agents/signals');
        $res->assertOk();
        $this->assertCount(1, $res->json('breakouts'));
        $this->assertSame('Why SQLite in Browser Is The Future', $res->json('breakouts.0.title'));
        $this->assertCount(1, $res->json('news'));
        $this->assertSame('Python 3.14 free-threading benchmarks', $res->json('news.0.title'));
        $this->assertCount(1, $res->json('scripts'));
        $this->assertSame('Local AI Agents on Mac', $res->json('scripts.0.title'));
        $this->assertCount(1, $res->json('asks'));
        $this->assertStringContainsString('Viewers asking', $res->json('asks.0.summary'));
    }

    public function test_repurpose_script_autonomously_creates_and_schedules_posts(): void
    {
        $script = Script::create([
            'title' => 'Building Fast Micro-APIs with SQLite',
            'body' => "## Intro\nSQLite handles 100k requests per second without Redis.\n\n## Benchmarks\nLatency was 0.4ms.",
            'outline' => ['Intro', 'Benchmarks', 'Takeaways'],
            'status' => 'published',
        ]);

        Llm::$fake = function (string $system, string $user) {
            return json_encode([
                'posts' => [
                    [
                        'platform' => 'linkedin',
                        'format' => 'post',
                        'title' => 'SQLite Benchmarks Breakdown',
                        'body' => 'Most people assume you need Redis for sub-millisecond API latency. Here are the real benchmarks.',
                        'parts' => [],
                    ],
                    [
                        'platform' => 'x',
                        'format' => 'post',
                        'title' => 'SQLite Latency Take',
                        'body' => 'SQLite handles 100k req/s at 0.4ms latency without Redis. Stop over-engineering.',
                        'parts' => [],
                    ],
                ],
            ]);
        };

        $res = $this->postJson("/api/agents/{$this->agent->id}/repurpose-script", [
            'script_id' => $script->id,
        ]);

        $res->assertOk();
        $res->assertJsonPath('message', '2 post(s) autonomously repurposed from “Building Fast Micro-APIs with SQLite”.');

        $posts = SocialPost::where('script_id', $script->id)->get();
        $this->assertCount(2, $posts);
        $this->assertTrue($posts->every(fn ($p) => $p->social_agent_id === $this->agent->id));
        $this->assertTrue($posts->every(fn ($p) => $p->status === 'scheduled' && $p->approval === 'approved'));

        $this->assertDatabaseHas('agent_events', [
            'social_agent_id' => $this->agent->id,
            'type' => 'drafted',
        ]);
    }

    public function test_spike_breakout_creates_trending_reaction_post(): void
    {
        $channel = Channel::create(['handle' => '@ai_frontier', 'title' => 'AI Frontier']);
        $outlier = Outlier::create([
            'channel_id' => $channel->id,
            'video_id' => 'v99',
            'title' => 'Why Everyone is Ditching Docker for MicroVMs',
            'url' => 'https://youtube.com/watch?v=v99',
            'views' => 240000,
            'median_views' => 40000,
            'outlier_score' => 6.0,
            'why_it_worked' => 'Contrarian devops hot-take',
            'published_at' => now()->subDay(),
        ]);

        Llm::$fake = function (string $system, string $user) {
            return json_encode([
                'title' => 'My Take on MicroVMs vs Docker',
                'body' => 'MicroVMs boot in 5 milliseconds. But are they actually ready for solo founders? Here is my experience.',
                'parts' => [],
                'why' => 'Contrarian reaction to the trending shift',
            ]);
        };

        $res = $this->postJson("/api/agents/{$this->agent->id}/spike-breakout", [
            'outlier_id' => $outlier->id,
            'platform' => 'linkedin',
        ]);

        $res->assertOk();
        $this->assertDatabaseHas('social_posts', [
            'platform' => 'linkedin',
            'social_agent_id' => $this->agent->id,
            'source' => 'agent_breakout',
            'status' => 'scheduled',
            'approval' => 'approved',
        ]);

        $outlier->refresh();
        $this->assertTrue($outlier->used);
    }
}
