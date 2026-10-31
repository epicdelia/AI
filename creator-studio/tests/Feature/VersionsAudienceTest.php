<?php

namespace Tests\Feature;

use App\Domains\Audience\CommentMiner;
use App\Domains\Audience\Models\AudienceInsight;
use App\Http\Controllers\Api\AudienceController;
use App\Models\Script;
use App\Models\ScriptVersion;
use App\Models\Setting;
use App\Models\User;
use App\Services\Llm;
use App\Services\YouTube;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class VersionsAudienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        $this->actingAs(User::create(['name' => 'Delia', 'email' => 'd@example.com', 'password' => 'secret123']));
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        parent::tearDown();
    }

    private static function paragraphs(int $n, string $word = 'line'): string
    {
        return implode("\n\n", array_map(fn ($i) => "Paragraph {$i} has a {$word} of its own here.", range(1, $n)));
    }

    // ---------------------------------------------------------------- versions

    public function test_autosave_keeps_a_version_at_most_every_ten_minutes(): void
    {
        $script = Script::create(['title' => 'Inbox week', 'body' => self::paragraphs(6)]);

        $this->patchJson("/api/scripts/{$script->id}", ['body' => self::paragraphs(6).' One more word.'])->assertOk();
        $this->assertSame(1, $script->versions()->count(), 'first edit keeps the original');
        $this->assertSame(self::paragraphs(6), $script->versions()->first()->body);

        $this->patchJson("/api/scripts/{$script->id}", ['body' => self::paragraphs(6).' Two more words.'])->assertOk();
        $this->assertSame(1, $script->versions()->count(), 'typing inside the window adds nothing');

        $this->travel(11)->minutes();
        $this->patchJson("/api/scripts/{$script->id}", ['body' => self::paragraphs(6).' Three.'])->assertOk();
        $this->assertSame(2, $script->versions()->count());

        // Title-only changes never snapshot.
        $this->patchJson("/api/scripts/{$script->id}", ['title' => 'Renamed'])->assertOk();
        $this->assertSame(2, $script->versions()->count());
    }

    public function test_a_large_rewrite_is_always_kept(): void
    {
        $script = Script::create(['title' => 'Inbox week', 'body' => self::paragraphs(8)]);
        $script->update(['body' => self::paragraphs(8).' tweak']);
        $this->assertSame(1, $script->versions()->count());

        // A Copilot-style whole rewrite a minute later.
        $this->travel(1)->minutes();
        $script->update(['body' => self::paragraphs(8, 'different thought')]);
        $this->assertSame(2, $script->versions()->count());
        $latest = $script->versions()->latest('id')->first();
        $this->assertSame('Before a large edit', $latest->label);
        $this->assertStringContainsString('tweak', $latest->body);

        $this->assertTrue(ScriptVersion::isLarge(str_repeat('word ', 400), str_repeat('word ', 100)), 'big delete');
        $this->assertFalse(ScriptVersion::isLarge(self::paragraphs(8), self::paragraphs(8).' small'));
    }

    public function test_manual_save_restore_and_compare(): void
    {
        $script = Script::create(['title' => 'Inbox week', 'body' => "Cold open.\n\nThe setup."]);

        $v1 = $this->postJson("/api/scripts/{$script->id}/versions", ['label' => 'Before filming'])->assertOk()->json('version');
        $this->assertSame('manual', $v1['source']);
        // Saving the same text again names the same version instead of duplicating it.
        $this->postJson("/api/scripts/{$script->id}/versions", ['label' => 'Final'])->assertOk()->assertJsonPath('version.id', $v1['id']);
        $this->assertSame(1, $script->versions()->count());

        $script->update(['body' => "Cold open.\n\nA brand new setup."]);

        $this->getJson("/api/scripts/{$script->id}/versions/{$v1['id']}")->assertOk()
            ->assertJsonPath('version.body', "Cold open.\n\nThe setup.")
            ->assertJsonPath('hunks.1.type', 'change')
            ->assertJsonPath('hunks.1.after.0', 'A brand new setup.');

        $this->postJson("/api/scripts/{$script->id}/versions/{$v1['id']}/restore")->assertOk()
            ->assertJsonPath('script.body', "Cold open.\n\nThe setup.");
        $kept = $script->versions()->latest('id')->first();
        $this->assertSame('restore', $kept->source);
        $this->assertSame("Cold open.\n\nA brand new setup.", $kept->body, 'the text she restored over is kept');

        $list = $this->getJson("/api/scripts/{$script->id}/versions")->assertOk()->json('versions');
        $this->assertCount(2, $list);
        $this->assertArrayNotHasKey('body', $list[0], 'the list stays light');
    }

    public function test_versions_are_scoped_to_their_script_and_deleted_with_it(): void
    {
        $a = Script::create(['title' => 'A', 'body' => 'Some text for A.']);
        $b = Script::create(['title' => 'B', 'body' => 'Some text for B.']);
        $va = ScriptVersion::keep($a);

        $this->getJson("/api/scripts/{$b->id}/versions/{$va->id}")->assertNotFound();
        $this->postJson("/api/scripts/{$b->id}/versions/{$va->id}/restore")->assertNotFound();
        $this->assertSame('Some text for B.', $b->fresh()->body);

        $this->postJson('/api/scripts/'.Script::create(['title' => 'Empty'])->id.'/versions')->assertStatus(422);

        $this->deleteJson("/api/scripts/{$a->id}")->assertOk();
        $this->assertSame(0, ScriptVersion::count());
    }

    public function test_old_automatic_versions_are_pruned_but_manual_ones_stay(): void
    {
        $script = Script::create(['title' => 'Long one', 'body' => 'v0']);
        ScriptVersion::keep($script, 'hand saved', source: 'manual');
        for ($i = 1; $i <= ScriptVersion::KEEP + 5; $i++) {
            ScriptVersion::keep($script, "auto {$i}", source: 'auto');
        }
        $this->assertSame(ScriptVersion::KEEP, $script->versions()->where('source', 'auto')->count());
        $this->assertSame(1, $script->versions()->where('source', 'manual')->count());
        $this->assertFalse($script->versions()->where('body', 'auto 1')->exists());
    }

    // ---------------------------------------------------------------- my channel

    private function fakeYouTube(array $comments = [], ?string $error = null): void
    {
        $now = CarbonImmutable::now('UTC');
        $videos = [
            ['id' => 'vidAAAAAAA1', 'title' => 'Old steady', 'days' => 30, 'views' => 10000],
            ['id' => 'vidAAAAAAA2', 'title' => 'Big hit', 'days' => 20, 'views' => 40000],
            ['id' => 'vidAAAAAAA3', 'title' => 'Flop', 'days' => 14, 'views' => 4000],
            ['id' => 'vidAAAAAAA4', 'title' => 'Average', 'days' => 10, 'views' => 12000],
            ['id' => 'vidAAAAAAA5', 'title' => 'Brand new', 'days' => 2, 'views' => 9000],
            ['id' => 'vidShort001', 'title' => 'A short', 'days' => 5, 'views' => 90000, 'seconds' => 40],
        ];
        $yt = new YouTube;
        $yt->getter = function (string $endpoint, array $params) use ($videos, $now, $comments, $error) {
            return match ($endpoint) {
                'channels' => ['items' => [['contentDetails' => ['relatedPlaylists' => ['uploads' => 'UUme']], 'snippet' => ['title' => 'Delia Builds']]]],
                'playlistItems' => ['items' => array_map(fn ($v) => ['contentDetails' => ['videoId' => $v['id']]], $videos)],
                'videos' => ['items' => array_map(fn ($v) => [
                    'id' => $v['id'],
                    'snippet' => ['title' => $v['title'], 'publishedAt' => $now->subDays($v['days'])->toIso8601String()],
                    'statistics' => ['viewCount' => $v['views'], 'commentCount' => 3],
                    'contentDetails' => ['duration' => 'PT'.($v['seconds'] ?? 600).'S'],
                ], isset($params['id']) && ! str_contains($params['id'], ',') ? array_values(array_filter($videos, fn ($v) => $v['id'] === $params['id'])) : $videos)],
                'commentThreads' => $error ? throw new RuntimeException($error) : ['items' => array_map(fn ($c) => [
                    'snippet' => ['topLevelComment' => ['snippet' => ['textDisplay' => $c[0], 'likeCount' => $c[1]]], 'totalReplyCount' => 0],
                ], $comments)],
            };
        };
        $this->app->instance(YouTube::class, $yt);
    }

    public function test_channel_report_scores_her_videos_against_her_own_median(): void
    {
        Setting::put('channel_id', 'deliabuilds');
        Setting::put('youtube_api_key', 'yt-key');
        $this->fakeYouTube();

        $report = $this->getJson('/api/audience')->assertOk()->assertJsonPath('error', null)->json('report');
        $this->assertSame('Delia Builds', $report['title']);
        // Median of mature long-form videos (10k, 40k, 4k, 12k) = 11k; Shorts and the 2-day-old video are left out.
        $this->assertEquals(11000, $report['median']);
        $byTitle = collect($report['videos'])->keyBy('title');
        $this->assertFalse($byTitle->has('A short'));
        $this->assertEquals(3.64, $byTitle['Big hit']['score']);
        $this->assertTrue($byTitle['Brand new']['early']);
        $this->assertSame('Brand new', $report['videos'][0]['title'], 'newest first');
        $this->assertNotNull(Cache::get(AudienceController::cacheKey('deliabuilds')));
    }

    public function test_my_channel_explains_what_is_missing(): void
    {
        $this->getJson('/api/audience')->assertOk()->assertJsonPath('report', null)
            ->assertJsonPath('error', fn ($e) => str_contains($e, 'channel'));
        $this->postJson('/api/audience/comments', ['url' => 'https://youtu.be/vidAAAAAAA2'])->assertStatus(422);
        Setting::put('youtube_api_key', 'yt-key');
        $this->postJson('/api/audience/comments', ['url' => 'not a link'])->assertStatus(422);
    }

    public function test_comments_become_viewer_needs_with_only_real_quotes(): void
    {
        Setting::put('channel_id', 'deliabuilds');
        Setting::put('youtube_api_key', 'yt-key');
        $this->fakeYouTube([
            ['Can you do a video on setting this up on Windows?', 40],
            ['Please do Windows next!!', 12],
            ['I got lost at the part about API keys’ scopes', 3],
            ['great video', 1],
        ]);
        $this->getJson('/api/audience')->assertOk(); // caches the report, so her own video is recognised

        $seen = null;
        Llm::$fake = function ($system, $user) use (&$seen) {
            $seen = $user;

            return json_encode(['summary' => 'They want Windows.', 'items' => [
                ['type' => 'request', 'text' => 'A Windows setup walkthrough', 'mentions' => 2, 'quote' => 'Can you do a video on setting this up on Windows?', 'idea' => 'I set this up on Windows so you don\'t have to'],
                ['type' => 'confusion', 'text' => 'API key scopes were unclear', 'mentions' => 99, 'quote' => "I got lost at the part about API keys' scopes", 'idea' => 'null'],
                ['type' => 'question', 'text' => 'Does it work on Linux?', 'mentions' => 1, 'quote' => 'Does it work on Linux though?', 'idea' => null],
                ['type' => 'hype', 'text' => 'Not a real type'],
            ]]);
        };

        $insight = $this->postJson('/api/audience/comments', ['url' => 'https://www.youtube.com/watch?v=vidAAAAAAA2'])->assertOk()->json('insight');
        $this->assertStringContainsString('[40 likes] Can you do a video', $seen);
        $this->assertStringContainsString('Big hit', $seen, 'title looked up when not given');
        $this->assertTrue($insight['own']);
        $this->assertSame(4, $insight['comment_count']);
        $this->assertCount(3, $insight['items'], 'unknown types are dropped');
        [$first, $second, $third] = $insight['items'];
        $this->assertSame('confusion', $first['type'], 'sorted by mentions');
        $this->assertSame(4, $first['mentions'], 'mentions capped at the number of comments');
        $this->assertNotNull($first['quote'], 'curly apostrophe still matches');
        $this->assertNull($first['idea']);
        $this->assertSame('Can you do a video on setting this up on Windows?', $second['quote']);
        $this->assertNull($third['quote'], 'a quote nobody wrote is dropped');

        // Analysing again updates the same row.
        $this->postJson('/api/audience/comments', ['url' => 'https://youtu.be/vidAAAAAAA2', 'title' => 'Big hit'])->assertOk();
        $this->assertSame(1, AudienceInsight::count());
    }

    public function test_comment_errors_are_reported_plainly(): void
    {
        Setting::put('youtube_api_key', 'yt-key');
        $this->fakeYouTube([['only one', 0]]);
        $this->postJson('/api/audience/comments', ['url' => 'https://youtu.be/vidAAAAAAA2'])->assertStatus(422)
            ->assertJsonPath('error', fn ($e) => str_contains($e, 'too few comments'));

        $this->fakeYouTube([], 'YouTube API error (403): The video identified by the videoId parameter has disabled comments.');
        $this->postJson('/api/audience/comments', ['url' => 'https://youtu.be/vidAAAAAAA2'])->assertStatus(422)
            ->assertJsonPath('error', 'Comments are turned off on this video.');
    }

    public function test_viewer_needs_and_her_winners_feed_the_idea_generator(): void
    {
        Setting::put('channel_id', 'deliabuilds');
        Setting::put('youtube_api_key', 'yt-key');
        $this->fakeYouTube();
        $this->getJson('/api/audience')->assertOk();
        AudienceInsight::create(['video_id' => 'vidAAAAAAA2', 'video_title' => 'Big hit', 'url' => 'https://youtu.be/vidAAAAAAA2', 'own' => true,
            'items' => [['type' => 'request', 'text' => 'A Windows setup walkthrough', 'mentions' => 7], ['type' => 'praise', 'text' => 'Loved the pacing', 'mentions' => 3]]]);

        $seen = null;
        Llm::$fake = function ($system, $user) use (&$seen) {
            $seen = $user;

            return json_encode(['summary' => 'ok', 'ideas' => [['title' => 'Windows setup, done right', 'source_url' => 'https://youtu.be/vidAAAAAAA2']]]);
        };
        $this->postJson('/api/ideas/generate')->assertOk()->assertJsonPath('message', fn ($m) => str_contains($m, '1 viewer request'));

        $this->assertStringContainsString('A Windows setup walkthrough', $seen);
        $this->assertStringNotContainsString('Loved the pacing', $seen, 'praise is not an idea signal');
        $this->assertStringContainsString('"Big hit": 3.64x her median', $seen);
        $this->assertTrue(AudienceInsight::first()->used);

        // Used insights aren't sent twice.
        $this->postJson('/api/ideas/generate')->assertOk();
        $this->assertStringNotContainsString('A Windows setup walkthrough', $seen);
    }

    public function test_copilot_and_search_know_about_my_channel(): void
    {
        AudienceInsight::create(['video_id' => 'vidAAAAAAA2', 'video_title' => 'Big hit', 'url' => 'https://youtu.be/vidAAAAAAA2', 'own' => true,
            'summary' => 'They want Windows.', 'items' => [['type' => 'request', 'text' => 'A Windows setup walkthrough', 'mentions' => 7]]]);

        $tools = app(\App\Domains\Copilot\CopilotTools::class);
        $this->assertContains('list_viewer_requests', array_column($tools->definitions('ask'), 'name'));
        $this->assertSame('read', \App\Domains\Copilot\CopilotTools::kind('list_viewer_requests'));
        $out = $tools->read('list_viewer_requests', []);
        $this->assertStringContainsString('A Windows setup walkthrough', $out);

        $this->getJson('/api/search?q=comments')->assertOk()->assertJsonFragment(['url' => '/channel']);
    }

    public function test_clean_ignores_garbage(): void
    {
        $this->assertSame([], CommentMiner::clean('nope', []));
        $this->assertSame([], CommentMiner::clean([['type' => 'question', 'text' => '']], [['text' => 'x']]));
    }
}
