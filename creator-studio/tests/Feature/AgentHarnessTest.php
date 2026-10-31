<?php

namespace Tests\Feature;

use App\Domains\Agents\Harness\AgentHarness;
use App\Domains\Agents\Harness\BenchmarkHarness;
use App\Domains\Agents\Harness\QualityEvaluator;
use App\Domains\Agents\Harness\ToolRegistry;
use App\Domains\Agents\Models\AgentEvalReport;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Audience\Models\AudienceInsight;
use App\Domains\Feeds\Models\Feed;
use App\Domains\Feeds\Models\FeedItem;
use App\Models\Channel;
use App\Models\Deal;
use App\Models\Outlier;
use App\Models\Script;
use App\Models\Setting;
use App\Models\User;
use App\Services\Llm;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentHarnessTest extends TestCase
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

        $this->user = User::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'password' => 'secret123']);
        $this->actingAs($this->user);

        $this->agent = SocialAgent::create([
            'name' => 'Agent Harness Testbot',
            'platforms' => ['linkedin', 'x'],
            'post_types' => ['informative', 'opinions'],
            'posts_per_week' => 3,
            'approval' => 'auto',
            'brand' => 'Engineering wisdom without buzzwords or fluff.',
            'audience' => 'Senior engineers and engineering leaders.',
        ]);
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        parent::tearDown();
    }

    public function test_tool_registry_executes_sanitized_tools(): void
    {
        $registry = new ToolRegistry();
        $tools = $registry->listTools();
        $this->assertCount(6, $tools);

        // Seed sample data for each tool
        $channel = Channel::create(['handle' => '@codemaster', 'title' => 'Code Master']);
        Outlier::create([
            'channel_id' => $channel->id,
            'video_id' => 'abc_123',
            'title' => 'Why PHP 8.4 is Fast',
            'url' => 'https://youtube.com/watch?v=abc_123',
            'views' => 150000,
            'median_views' => 20000,
            'outlier_score' => 7.5,
            'why_it_worked' => 'Massive speed jump demo',
            'published_at' => now()->subDay(),
        ]);

        $feed = Feed::create(['title' => 'Dev News', 'url' => 'https://example.com/rss']);
        FeedItem::create([
            'feed_id' => $feed->id,
            'guid' => 'item-harness-1',
            'title' => 'JIT Compilers Deep Dive',
            'url' => 'https://example.com/rss/item-harness-1',
            'source' => 'Dev News',
            'published_at' => now()->subHours(2),
        ]);

        Script::create([
            'title' => 'Building Autonomous AI Systems',
            'status' => 'published',
            'outline' => ['Design tools', 'Build rubrics'],
        ]);

        AudienceInsight::create([
            'video_id' => 'vid-harness-1',
            'video_title' => 'Video Comments Analysis',
            'url' => 'https://youtube.com/watch?v=vid-harness-1',
            'summary' => 'Audience asking about testing agent quality in production.',
            'items' => [
                ['kind' => 'question', 'text' => 'How do you test agent quality in production?'],
            ],
        ]);

        Deal::create([
            'brand_name' => 'Supabase',
            'stage' => 'contracted',
            'deliverables' => [
                ['platform' => 'linkedin', 'format' => 'post', 'status' => 'pending'],
            ],
            'key_talking_points' => 'Open source Firebase alternative with Postgres.',
        ]);

        $breakouts = $registry->executeTool('fetch_breakouts', ['limit' => 5]);
        $this->assertCount(1, $breakouts);
        $this->assertEquals('Why PHP 8.4 is Fast', $breakouts[0]->title);

        $rss = $registry->executeTool('fetch_news_feeds', ['limit' => 5]);
        $this->assertCount(1, $rss);
        $this->assertEquals('JIT Compilers Deep Dive', $rss[0]->title);

        $scripts = $registry->executeTool('fetch_recent_scripts', ['limit' => 5]);
        $this->assertCount(1, $scripts);
        $this->assertEquals('Building Autonomous AI Systems', $scripts[0]->title);

        $asks = $registry->executeTool('fetch_audience_asks', ['limit' => 5]);
        $this->assertCount(1, $asks);
        $this->assertEquals('vid-harness-1', $asks[0]->video_id);

        $sponsors = $registry->executeTool('fetch_active_sponsors');
        $this->assertCount(1, $sponsors);
        $this->assertEquals('Supabase', $sponsors[0]->brand_name);

        $slot = $registry->executeTool('find_optimal_slot', ['platform' => 'linkedin']);
        $this->assertIsArray($slot);
    }

    public function test_quality_evaluator_penalizes_hard_tells_and_cliches(): void
    {
        $evaluator = new QualityEvaluator();

        // Draft containing AI cliches and hard tells
        $clicheDraft = "Let's delve into this game-changer in the tech landscape — it is crucial to leverage cutting-edge paradigms for your journey.";
        $badEval = $evaluator->evaluate($clicheDraft, 'linkedin');

        $this->assertLessThan(75, $badEval['overall_score']);
        $this->assertLessThan(60, $badEval['rubric']['cliche_freedom']);
        $this->assertContains('delve', $badEval['hard_tells_caught']);
        $this->assertContains('game-changer', $badEval['hard_tells_caught']);
        $this->assertContains('landscape', $badEval['hard_tells_caught']);

        // Clean, direct, human draft
        $cleanDraft = "Most caching layers break under stampedes because developers cache the result instead of mutex-locking the compute key.\n\nHere is how we reduced our p99 latency from 450ms to 12ms using singleflight locks in Go.";
        $goodEval = $evaluator->evaluate($cleanDraft, 'linkedin');

        $this->assertGreaterThanOrEqual(80, $goodEval['overall_score']);
        $this->assertEmpty($goodEval['hard_tells_caught']);
    }

    public function test_benchmark_endpoint_runs_standardized_test_suite(): void
    {
        Llm::$fake = function (string $system, string $user) {
            return "Here is a crisp, direct engineering takeaway with zero corporate jargon and clear actionable architecture points.";
        };

        $response = $this->postJson("/api/agents/{$this->agent->id}/harness/benchmark");
        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'overall_average',
            'reports' => [
                '*' => ['scenario', 'rubric', 'overall_score', 'feedback', 'output_sample'],
            ],
        ]);

        $this->assertCount(4, $response->json('reports'));
        $this->assertEquals(4, AgentEvalReport::where('social_agent_id', $this->agent->id)->count());

        // Verify evals query endpoint
        $evalsRes = $this->getJson("/api/agents/{$this->agent->id}/evals");
        $evalsRes->assertOk();
        $evalsRes->assertJsonStructure([
            'average',
            'reports',
            'scenarios',
        ]);
        $this->assertCount(4, $evalsRes->json('reports'));
    }

    public function test_run_harness_executes_autonomous_loop(): void
    {
        Llm::$fake = function (string $system, string $user) {
            return json_encode([
                'posts' => [
                    [
                        'platform' => 'linkedin',
                        'post_type' => 'informative',
                        'body' => "We benchmarked three database connection pooling strategies under 10k concurrent WebSockets.\n\nStatic sizing won by 3x throughput over dynamic allocation.",
                        'parts' => [],
                    ],
                ],
            ]);
        };

        $response = $this->postJson("/api/agents/{$this->agent->id}/harness/run");
        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'trace' => [
                '*' => ['phase', 'summary'],
            ],
            'posts',
            'evaluations',
        ]);

        $this->assertNotEmpty($response->json('posts'));
        $this->assertNotEmpty($response->json('evaluations'));
    }
}
