<?php

namespace Tests\Feature;

use App\Domains\Audience\VideoStats;
use App\Domains\Backup\Backup;
use App\Http\Controllers\Api\AudienceController;
use App\Models\Script;
use App\Models\Setting;
use App\Models\User;
use App\Services\ChapterAligner;
use App\Services\Llm;
use App\Services\YouTube;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Backups, daily video numbers, the publish kit (chapters) and comment reply drafts. */
class OpsTest extends TestCase
{
    use RefreshDatabase;

    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        $this->storage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'studio-test-'.uniqid();
        mkdir($this->storage.'/app/private/media/thumbnail', 0777, true);
        $this->app->useStoragePath($this->storage);
        $this->actingAs($this->admin(['name' => 'Delia', 'email' => 'd@example.com', 'password' => 'secret123']));
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    // ---------------------------------------------------------------- daily numbers

    private function fakeYouTube(array $videos, array $comments = []): void
    {
        $yt = new YouTube;
        $yt->getter = function (string $endpoint, array $params) use ($videos, $comments) {
            return match ($endpoint) {
                'channels' => ['items' => [['contentDetails' => ['relatedPlaylists' => ['uploads' => 'UUmine000000000000000001']], 'snippet' => ['title' => 'Delia Builds']]]],
                'playlistItems' => ['items' => array_map(fn ($v) => ['contentDetails' => ['videoId' => $v['id']]], $videos)],
                'videos' => ['items' => array_map(fn ($v) => [
                    'id' => $v['id'], 'snippet' => ['title' => $v['title'] ?? $v['id'], 'publishedAt' => $v['published']],
                    'statistics' => ['viewCount' => $v['views'], 'likeCount' => $v['likes'] ?? 0, 'commentCount' => 1],
                    'contentDetails' => ['duration' => 'PT10M'],
                ], isset($params['id']) && ! str_contains($params['id'], ',') ? array_values(array_filter($videos, fn ($v) => $v['id'] === $params['id'])) : $videos)],
                'commentThreads' => ['items' => $comments],
            };
        };
        $this->app->instance(YouTube::class, $yt);
    }

    public function test_daily_snapshots_give_same_age_comparisons(): void
    {
        Setting::put('channel_id', 'deliabuilds');
        Setting::put('youtube_api_key', 'yt-key');
        $start = CarbonImmutable::parse('2026-10-01 12:00', 'UTC');
        $this->travelTo($start);
        $videos = [
            ['id' => 'vidOLD00001', 'published' => $start->subDays(3)->toIso8601String(), 'views' => 1000, 'likes' => 50],
            ['id' => 'vidOLD00002', 'published' => $start->subDays(3)->toIso8601String(), 'views' => 2000],
            ['id' => 'vidOLD00003', 'published' => $start->subDays(3)->toIso8601String(), 'views' => 3000],
            ['id' => 'vidNEW00001', 'published' => $start->subDays(3)->toIso8601String(), 'views' => 6000],
        ];
        $this->fakeYouTube($videos);

        $this->artisan('studio:stats')->assertSuccessful();
        $this->assertSame(4, DB::table('video_stats')->count());
        $this->assertSame(50, (int) DB::table('video_stats')->where('video_id', 'vidOLD00001')->value('likes'));
        $this->assertNotNull(Cache::get(AudienceController::cacheKey('deliabuilds')), 'My channel cache refreshed too');

        // Same day again updates rather than duplicates.
        app(VideoStats::class)->snapshot();
        $this->assertSame(4, DB::table('video_stats')->count());

        $this->assertSame(1000, VideoStats::viewsAt('vidOLD00001', 3));
        $this->assertNull(VideoStats::viewsAt('vidOLD00001', 7), 'no snapshot near day 7 yet');
        $this->assertEquals(2500, VideoStats::usualAt(3), 'median of the four');

        $cmp = VideoStats::compare('vidNEW00001');
        $this->assertEquals(['day' => 3, 'ratio' => 3.0], $cmp['pace'], '6000 vs the median of the other three (2000)');
        $this->assertCount(1, $cmp['series']);

        // Four days later, day 7 exists.
        $this->travelTo($start->addDays(4));
        $this->fakeYouTube(array_map(fn ($v) => ['views' => $v['views'] * 2] + $v, $videos));
        app(VideoStats::class)->snapshot();
        $this->assertSame(12000, VideoStats::viewsAt('vidNEW00001', 7));

        $res = $this->getJson('/api/audience?refresh=1')->assertOk()->json();
        $this->assertEquals(3.0, $res['same_age']['vidNEW00001']['pace']['ratio']);
        $this->assertSame(7, $res['same_age']['vidNEW00001']['pace']['day']);
        $this->assertNotNull($res['tracking_since']);
    }

    public function test_snapshot_needs_a_channel(): void
    {
        $this->postJson('/api/audience/snapshot')->assertStatus(422)->assertJsonPath('error', fn ($e) => str_contains($e, 'channel'));
        $this->artisan('studio:stats')->assertFailed();
    }

    // ---------------------------------------------------------------- chapters & publish kit

    private const SCRIPT = "## Cold open\nOkay so I handed my entire inbox to an assistant for seven days.\n\n"
        ."## The rules\nFirst I wrote three strict rules about family, meetings and exclamation marks.\n\n"
        ."## What broke\nWednesday morning everything collapsed when it archived my landlord completely.\n\n"
        ."## The verdict\nWould I recommend this experiment? Honestly only with careful weekly checking.";

    public function test_chapters_come_from_the_recording_when_it_has_timestamps(): void
    {
        $transcript = "[00:00] okay so I handed my entire inbox to an assistant for seven days and here we go\n"
            ."[00:30] so first I wrote three strict rules about family meetings and exclamation marks which seemed smart\n"
            ."[01:00] it went fine for two days honestly nothing much happened at all\n"
            ."[01:30] then wednesday morning everything collapsed when it archived my landlord completely\n"
            ."[02:00] lots of chaos followed and I had to apologise to a lot of people\n"
            ."[02:30] would I recommend this experiment honestly only with careful weekly checking";
        $r = ChapterAligner::align(self::SCRIPT, $transcript);
        $this->assertSame('transcript', $r['source']);
        $this->assertSame(3, $r['matched']);
        $lines = explode("\n", $r['chapters']);
        $this->assertSame('0:00 Cold open', $lines[0]);
        $this->assertMatchesRegularExpression('/^0:3\d The rules$/', $lines[1], 'inside the 0:30 block');
        $this->assertSame('1:30 What broke', $lines[2]);
        $this->assertSame('2:30 The verdict', $lines[3]);
    }

    public function test_chapters_fall_back_to_the_estimate_and_stream_a_publish_kit(): void
    {
        $body = implode("\n", ['## Cold open', str_repeat('word ', 60), '## Setup', str_repeat('word ', 150), '## Test', str_repeat('word ', 150), '## Results', str_repeat('word ', 150)]);
        $r = ChapterAligner::align($body, 'plain transcript without any timestamps at all');
        $this->assertSame('estimate', $r['source']);
        $this->assertStringStartsWith('0:00 Cold open', $r['chapters']);

        $script = Script::create(['title' => 'Inbox week', 'body' => $body]);
        $this->getJson("/api/scripts/{$script->id}/chapters")->assertOk()->assertJsonPath('source', 'estimate');

        $seen = null;
        Llm::$fake = function ($system, $user) use (&$seen) {
            $seen = [$system, $user];

            return "## Title\nInbox week\n## Description\nHook.";
        };
        $this->postJson("/api/stream/scripts/{$script->id}/publish-kit")->assertOk()->streamedContent();
        $this->assertStringContainsString('## Tags', $seen[0]);
        $this->assertStringContainsString('estimated from the script', $seen[1]);
        $this->assertStringContainsString('0:00 Cold open', $seen[1]);
        $this->assertStringContainsString('## Title', $script->fresh()->publish_kit);

        $this->postJson('/api/stream/scripts/'.Script::create(['title' => 'Empty'])->id.'/publish-kit')->assertStatus(422);
    }

    // ---------------------------------------------------------------- reply drafts

    public function test_reply_drafts_skip_her_own_answered_and_spam_comments(): void
    {
        Setting::put('channel_id', 'deliabuilds');
        Setting::put('youtube_api_key', 'yt-key');
        $c = fn ($id, $text, $author = 'UCsomeone', $replies = 0) => ['id' => $id, 'snippet' => [
            'topLevelComment' => ['snippet' => ['textDisplay' => $text, 'likeCount' => 2, 'authorChannelId' => ['value' => $author], 'publishedAt' => '2026-10-30T10:00:00Z']],
            'totalReplyCount' => $replies,
        ]];
        $this->fakeYouTube([['id' => 'vidNEW00001', 'title' => 'Inbox week', 'published' => '2026-10-30T09:00:00Z', 'views' => 100]], [
            $c('c1', 'Does this work with Outlook?'),
            $c('c2', 'Thanks everyone for watching!', 'UCmine000000000000000001'), // her own (uploads UU… → channel UC…)
            $c('c3', 'Already answered one', 'UCother', 2),
            $c('c4', 'Earn $$$ fast https://spam.example'),
            $c('c5', 'Loved the landlord bit'),
        ]);

        $seen = null;
        Llm::$fake = function ($system, $user) use (&$seen) {
            $seen = $user;

            return json_encode(['replies' => [['index' => 0, 'reply' => 'It should! I tested Gmail, Outlook is next.'], ['index' => 1, 'reply' => 'Ha, poor landlord.'], ['index' => 9, 'reply' => 'ghost']]]);
        };
        $res = $this->postJson('/api/audience/replies', ['url' => 'https://youtu.be/vidNEW00001'])->assertOk()->json();

        $this->assertStringContainsString('Outlook', $seen);
        $this->assertStringNotContainsString('Thanks everyone', $seen, 'her own comment skipped');
        $this->assertStringNotContainsString('Already answered', $seen);
        $this->assertStringNotContainsString('spam.example', $seen);
        $this->assertSame(['c1', 'c5'], array_column($res['replies'], 'id'), 'unknown indexes are dropped');
        $this->assertSame('Ha, poor landlord.', $res['replies'][1]['reply']);

        $this->postJson('/api/audience/replies', ['url' => 'nope'])->assertStatus(422);
    }
}
