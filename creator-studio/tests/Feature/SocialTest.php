<?php

namespace Tests\Feature;

use App\Domains\Copilot\CopilotTools;
use App\Domains\Copilot\Models\CopilotThread;
use App\Domains\Social\Models\SocialPost;
use App\Domains\Social\Platforms;
use App\Models\Script;
use App\Models\Setting;
use App\Models\User;
use App\Models\Week;
use App\Services\Llm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        $this->actingAs($this->admin(['name' => 'Delia', 'email' => 'd@example.com', 'password' => 'secret123']));
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        parent::tearDown();
    }

    private static function messages(array $checks): array
    {
        return array_column($checks, 'message');
    }

    // ---------------------------------------------------------------- platform checks

    public function test_x_limits_count_links_as_23_and_premium_lifts_them(): void
    {
        $this->assertSame(23, Platforms::xLength('https://example.com/a/very/long/path/that/goes/on'));
        $long = str_repeat('a', 270).' https://example.com/x';
        $this->assertTrue(Platforms::hasHard(Platforms::check('x', 'post', $long)), '270 + space + 23 > 280');
        $this->assertFalse(Platforms::hasHard(Platforms::check('x', 'post', str_repeat('a', 280))));

        $thread = Platforms::check('x', 'thread', '', ['fine', str_repeat('b', 300)]);
        $this->assertSame(1, collect($thread)->firstWhere('severity', 'HARD')['part']);

        Setting::put('x_premium', '1');
        $this->assertFalse(Platforms::hasHard(Platforms::check('x', 'post', str_repeat('a', 2000))));
    }

    public function test_linkedin_and_instagram_checks(): void
    {
        $li = self::messages(Platforms::check('linkedin', 'post', str_repeat('Long hook ', 20)."\nRead it: https://example.com"));
        $this->assertTrue(collect($li)->contains(fn ($m) => str_contains($m, 'see more')));
        $this->assertTrue(collect($li)->contains(fn ($m) => str_contains($m, 'link')));
        $this->assertTrue(Platforms::hasHard(Platforms::check('linkedin', 'post', str_repeat('a', 3001))));

        $six = 'Hook'."\n\n#one #two #three #four #five #six";
        $ig = Platforms::check('instagram', 'post', $six);
        $this->assertFalse(Platforms::hasHard($ig));
        $this->assertTrue(collect(self::messages($ig))->contains(fn ($m) => str_contains($m, '6 hashtags')));
        $this->assertTrue(Platforms::hasHard(Platforms::check('instagram', 'post', implode(' ', array_map(fn ($i) => "#t{$i}", range(1, 31))))));
        $this->assertSame(0, Platforms::hashtags('Email me at a#b or see &#39; and C#'), 'not hashtags');

        $slides = Platforms::check('instagram', 'carousel', 'Caption', array_fill(0, 21, 'Slide'));
        $this->assertTrue(Platforms::hasHard($slides));
        $one = self::messages(Platforms::check('instagram', 'carousel', 'Caption', ['Only one']));
        $this->assertContains('A carousel needs at least 2 slides.', $one);
    }

    public function test_threads_and_tiktok_checks(): void
    {
        $this->assertTrue(Platforms::hasHard(Platforms::check('threads', 'post', str_repeat('a', 501))));
        $this->assertFalse(Platforms::hasHard(Platforms::check('threads', 'post', str_repeat('a', 500))));
        $thread = Platforms::check('threads', 'thread', '', ['Short one.', str_repeat('b', 520)]);
        $this->assertSame(1, collect($thread)->firstWhere('severity', 'HARD')['part']);

        $this->assertTrue(Platforms::hasHard(Platforms::check('tiktok', 'photo', 'Caption', array_fill(0, 36, 'Slide'))), '35 max');
        $this->assertFalse(Platforms::hasHard(Platforms::check('tiktok', 'photo', 'Caption', array_fill(0, 30, 'Slide'))), 'more than Instagram allows is fine on TikTok');
        $this->assertTrue(collect(self::messages(Platforms::check('tiktok', 'video', str_repeat('Long caption hook ', 6), ['Beat one', 'Beat two'])))
            ->contains(fn ($m) => str_contains($m, 'first ~70 characters')));

        $this->getJson('/api/social/meta')->assertOk()
            ->assertJsonPath('platforms.threads.limit', 500)
            ->assertJsonPath('platforms.tiktok.formats.photo.parts', 'Slides');
    }

    public function test_ai_tells_are_checked_on_body_and_parts(): void
    {
        $checks = Platforms::check('x', 'thread', '', ['Fine post.', 'Let’s delve into it — now.']);
        $hard = collect($checks)->where('severity', 'HARD');
        $this->assertCount(2, $hard, 'banned phrase and em dash');
        $this->assertSame([1], $hard->pluck('part')->unique()->values()->all());
    }

    // ---------------------------------------------------------------- posts API

    public function test_create_update_schedule_post_and_log_numbers(): void
    {
        $post = $this->postJson('/api/social/posts', ['platform' => 'linkedin', 'format' => 'post', 'body' => 'Short hook'])->assertOk()->json('post');
        $this->assertSame('draft', $post['status']);
        $this->postJson('/api/social/posts', ['platform' => 'x', 'format' => 'carousel'])->assertStatus(422);

        // Scheduling without a time stays a draft; with one it's scheduled.
        $this->patchJson("/api/social/posts/{$post['id']}", ['status' => 'scheduled'])->assertJsonPath('post.status', 'draft');
        $this->patchJson("/api/social/posts/{$post['id']}", ['status' => 'scheduled', 'scheduled_for' => '2026-11-03T09:30:00+01:00'])
            ->assertJsonPath('post.status', 'scheduled')
            ->assertJsonPath('post.scheduled_for', '2026-11-03T08:30:00.000000Z');

        $res = $this->patchJson("/api/social/posts/{$post['id']}", [
            'status' => 'posted', 'url' => 'https://www.linkedin.com/feed/update/1',
            'metrics' => ['impressions' => 2000, 'likes' => 80, 'comments' => 15, 'shares' => 5, 'bogus' => 9],
        ])->assertOk()->json('post');
        $this->assertNotNull($res['posted_at'], 'posting stamps the time');
        $this->assertArrayNotHasKey('bogus', $res['metrics']);
        $this->assertEquals(['total' => 100, 'rate' => 5.0], $res['engagement']);

        $this->patchJson("/api/social/posts/{$post['id']}", ['format' => 'thread'])->assertStatus(422);
        $this->getJson('/api/social/posts?platform=linkedin')->assertOk()->assertJsonPath('counts.posted', 1);
        $this->deleteJson("/api/social/posts/{$post['id']}")->assertOk();
        $this->assertSame(0, SocialPost::count());
    }

    public function test_duplicate_to_another_format_folds_parts_into_the_text(): void
    {
        $thread = SocialPost::create(['platform' => 'x', 'format' => 'thread', 'body' => '', 'parts' => ['One.', 'Two.'], 'status' => 'posted', 'url' => 'https://x.com/a/status/1']);
        $copy = $this->postJson("/api/social/posts/{$thread->id}/duplicate", ['platform' => 'linkedin', 'format' => 'post'])->assertOk()->json('post');
        $this->assertSame("One.\n\nTwo.", $copy['body']);
        $this->assertSame([], $copy['parts']);
        $this->assertSame('draft', $copy['status']);
        $this->assertNull($copy['url']);
    }

    public function test_live_check_endpoint(): void
    {
        $this->postJson('/api/social/check', ['platform' => 'x', 'format' => 'post', 'body' => str_repeat('a', 300)])
            ->assertOk()->assertJsonPath('checks.0.severity', 'HARD');
        $this->postJson('/api/social/check', ['platform' => 'x', 'format' => 'reel'])->assertStatus(422);
    }

    // ---------------------------------------------------------------- repurpose

    public function test_repurpose_a_script_into_native_drafts(): void
    {
        $script = Script::create(['title' => 'Inbox week', 'body' => str_repeat('I let an AI answer my email for a week and it archived my landlord. ', 5), 'transcript' => 'What I actually said on camera, at length, about the inbox experiment and the landlord email.']);
        $seen = null;
        Llm::$fake = function ($system, $user) use (&$seen) {
            $seen = [$system, $user];

            return json_encode(['posts' => [
                ['platform' => 'linkedin', 'format' => 'post', 'title' => 'Inbox lesson', 'body' => "I gave my inbox to an AI.\n\nHere's what broke.", 'parts' => ['ignored']],
                ['platform' => 'x', 'format' => 'thread', 'body' => '', 'parts' => ['Hook post', '', 'Second post', ['nested']]],
                ['platform' => 'myspace', 'format' => 'video', 'body' => 'dropped'],
            ]]);
        };

        $res = $this->postJson('/api/social/repurpose', ['source' => 'transcript', 'id' => $script->id, 'extra' => 'Keep it funny',
            'targets' => [['platform' => 'linkedin', 'format' => 'post'], ['platform' => 'x', 'format' => 'thread'], ['platform' => 'x', 'format' => 'nope']]])
            ->assertOk()->json();

        $this->assertStringContainsString('linkedin.post:', $seen[0]);
        $this->assertStringContainsString('x.thread:', $seen[0]);
        $this->assertStringNotContainsString('x.nope', $seen[0]);
        $this->assertStringContainsString('What I actually said on camera', $seen[1], 'transcript preferred');
        $this->assertStringContainsString('Keep it funny', $seen[1]);

        $this->assertCount(2, $res['posts'], 'unknown platforms are dropped');
        $this->assertSame([], $res['posts'][0]['parts'], 'a text post has no parts');
        $this->assertSame(['Hook post', 'Second post'], $res['posts'][1]['parts']);
        $this->assertArrayHasKey('checks', $res['posts'][0]);
        $this->assertSame([$script->id], SocialPost::pluck('script_id')->unique()->all());
        $this->assertSame(['repurpose'], SocialPost::pluck('source')->unique()->all());
    }

    public function test_composer_turns_a_short_prompt_and_images_into_drafts(): void
    {
        $this->getJson('/api/social/composer')->assertOk()->assertJsonStructure(['model', 'engine', 'ai_ready', 'targets']);

        $calls = [];
        Llm::$fake = function ($system, $user) use (&$calls) {
            $calls[] = [$system, $user];
            if (str_contains($system, 'Describe each image')) {
                return 'A tidy desk with one monitor and a plant.';
            }

            return json_encode(['posts' => [
                ['platform' => 'tiktok', 'format' => 'video', 'title' => 'Desk', 'body' => 'Desk reset 👇', 'parts' => ['Hook', 'Beat 2']],
                ['platform' => 'threads', 'format' => 'post', 'title' => 'Desk q', 'body' => 'What stays on your desk?'],
            ]]);
        };
        $png = 'data:image/png;base64,'.base64_encode('fake-png');
        $res = $this->postJson('/api/social/compose', [
            'prompt' => 'my new desk setup', 'extra' => 'playful',
            'targets' => [['platform' => 'tiktok', 'format' => 'video'], ['platform' => 'threads', 'format' => 'post']],
            'images' => [$png, 'not-a-data-uri'],
        ])->assertOk()->json();
        $this->assertCount(2, $res['posts']);
        $this->assertNull($res['note']);
        $this->assertStringContainsString('my new desk setup', $calls[1][1], 'short prompts are fine here');
        $this->assertStringContainsString('A tidy desk with one monitor', $calls[1][1], 'image description reaches the writer');
        $this->assertStringContainsString('playful', $calls[1][1]);

        // Without a vision-capable model the drafts are still written, with a note.
        Llm::$fake = function ($system) {
            if (str_contains($system, 'Describe each image')) {
                throw new \RuntimeException('This model can’t look at images.');
            }

            return json_encode(['posts' => [['platform' => 'x', 'format' => 'post', 'body' => 'Desk post']]]);
        };
        $this->postJson('/api/social/compose', ['prompt' => 'desk', 'targets' => [['platform' => 'x', 'format' => 'post']], 'images' => [$png]])
            ->assertOk()->assertJsonPath('note', fn ($n) => str_contains($n, 'weren’t described'));
        $this->postJson('/api/social/compose', ['prompt' => 'hi', 'targets' => [['platform' => 'x', 'format' => 'post']]])->assertStatus(422);
    }

    public function test_demo_posts_load_once_cover_every_network_and_remove_cleanly(): void
    {
        $real = SocialPost::create(['platform' => 'x', 'format' => 'post', 'body' => 'Mine', 'status' => 'draft']);
        \Illuminate\Support\Facades\DB::table('follower_snapshots')->insert(['platform' => 'instagram', 'count' => 999, 'recorded_on' => now()->toDateString(), 'source' => 'manual', 'created_at' => now(), 'updated_at' => now()]);

        $this->postJson('/api/social/demo')->assertOk()->assertJsonPath('demo', 20);
        $this->postJson('/api/social/demo')->assertOk()->assertJsonPath('message', 'Demo posts are already loaded.');
        $demo = SocialPost::where('source', 'demo')->get();
        $this->assertEqualsCanonicalizing(['linkedin', 'instagram', 'x', 'tiktok', 'threads', 'youtube'], $demo->pluck('platform')->unique()->values()->all());
        $this->assertEqualsCanonicalizing(['posted', 'scheduled', 'draft', 'idea'], $demo->pluck('status')->unique()->values()->all());
        $this->assertTrue($demo->every(fn ($p) => str_starts_with($p->title, 'Demo · ')));
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('follower_snapshots')->where('platform', 'instagram')->where('source', 'demo')->count(), 'her real counts are left alone');
        $this->getJson('/api/social/meta')->assertJsonPath('demo', 20);

        $this->deleteJson('/api/social/demo')->assertOk()->assertJsonPath('demo', 0);
        $this->assertSame([$real->id], SocialPost::pluck('id')->all());
        $this->assertSame(['manual'], \Illuminate\Support\Facades\DB::table('follower_snapshots')->pluck('source')->all());
    }

    public function test_repurpose_refuses_thin_sources_and_no_targets(): void
    {
        $this->postJson('/api/social/repurpose', ['source' => 'text', 'text' => 'too short', 'targets' => [['platform' => 'x', 'format' => 'post']]])->assertStatus(422);
        $this->postJson('/api/social/repurpose', ['source' => 'text', 'text' => str_repeat('enough words here ', 20), 'targets' => [['platform' => 'x', 'format' => 'reel']]])
            ->assertStatus(422)->assertJsonPath('error', 'Pick at least one platform and format.');
    }

    // ---------------------------------------------------------------- calendar, progress, stats

    public function test_calendar_progress_and_stats(): void
    {
        Setting::put('cadence_linkedin', '3');
        $monday = now('Europe/London')->startOfWeek();
        $posted = SocialPost::create(['platform' => 'linkedin', 'format' => 'post', 'body' => 'a', 'status' => 'posted', 'posted_at' => $monday->copy()->addDay()->setTime(9, 0)->utc(),
            'metrics' => ['impressions' => 1000, 'likes' => 40, 'comments' => 10]]);
        SocialPost::create(['platform' => 'linkedin', 'format' => 'post', 'body' => 'b', 'status' => 'scheduled', 'scheduled_for' => $monday->copy()->addDays(3)->setTime(9, 0)->utc()]);
        SocialPost::create(['platform' => 'instagram', 'format' => 'reel', 'body' => 'c', 'status' => 'draft']);
        $script = Script::create(['title' => 'Inbox week', 'status' => 'published', 'published_on' => $monday->copy()->addDays(2)->toDateString()]);
        Week::create(['number' => 1, 'start_date' => $monday->toDateString(), 'due_date' => $monday->copy()->addDays(6)->toDateString(), 'script_id' => $script->id, 'video_title' => 'Inbox week']);

        $from = $monday->copy()->utc()->toIso8601String();
        $to = $monday->copy()->addWeek()->utc()->toIso8601String();
        $cal = $this->getJson('/api/social/calendar?'.http_build_query(['from' => $from, 'to' => $to]))->assertOk()->json();
        $this->assertCount(2, $cal['posts']);
        $this->assertCount(1, $cal['unscheduled']);
        $this->assertEqualsCanonicalizing(['due', 'published'], array_column($cal['youtube'], 'kind'));
        $this->assertSame(['label' => 'LinkedIn', 'target' => 3, 'posted' => 1, 'scheduled' => 1], $cal['progress']['linkedin']);

        $stats = $this->getJson('/api/social/stats?platform=linkedin')->assertOk()->json();
        $this->assertSame(1, $stats['logged']);
        $this->assertEquals(5.0, $stats['avg_rate']);
        $this->assertSame($posted->id, $stats['top'][0]['id']);

        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('social.linkedin.posted', 1);
        $this->getJson('/api/social/meta')->assertOk()->assertJsonPath('platforms.x.formats.thread.parts', 'Posts');
    }

    // ---------------------------------------------------------------- copilot

    public function test_copilot_proposes_a_post_she_applies_and_undoes(): void
    {
        $tools = app(CopilotTools::class);
        $this->assertContains('create_social_post', array_column($tools->definitions('agent'), 'name'));
        $this->assertNotContains('create_social_post', array_column($tools->definitions('ask'), 'name'));

        $thread = CopilotThread::create(['mode' => 'agent']);
        $action = $tools->propose($thread, 'create_social_post', ['platform' => 'x', 'format' => 'thread', 'body' => '', 'parts' => ['One', 'Two']]);
        $this->assertSame(0, SocialPost::count(), 'nothing happens until she applies');

        $this->postJson("/api/copilot/actions/{$action->id}/apply")->assertOk();
        $post = SocialPost::firstOrFail();
        $this->assertSame(['One', 'Two'], $post->parts);
        $this->assertSame('copilot', $post->source);
        $this->assertStringContainsString('"status": "draft"', $tools->read('list_social_posts', ['platform' => 'x']));

        $this->postJson("/api/copilot/actions/{$action->id}/undo")->assertOk();
        $this->assertSame(0, SocialPost::count());

        $this->expectException(\RuntimeException::class);
        $tools->propose($thread, 'create_social_post', ['platform' => 'x', 'format' => 'reel', 'body' => 'x']);
    }
}
