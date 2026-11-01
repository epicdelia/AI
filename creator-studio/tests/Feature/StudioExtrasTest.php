<?php

namespace Tests\Feature;

use App\Domains\Social\Models\SocialAccount;
use App\Domains\YouTube\TitleTest;
use App\Domains\YouTube\VideoUpload;
use App\Domains\YouTube\YouTubeStudio;
use App\Models\Setting;
use App\Models\User;
use App\Services\Llm;
use App\Services\Prompts;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudioExtrasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Setting::put('timezone', 'UTC');
        Setting::put('youtube_client_id', 'g');
        Setting::put('youtube_client_secret', 's');
        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-11-04 08:00', 'UTC'));
        $this->actingAs($this->admin(['name' => 'Delia', 'email' => 'd@example.com', 'password' => 'secret123']));
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        parent::tearDown();
    }

    private function connect(): void
    {
        SocialAccount::create(['platform' => 'youtube', 'access_token' => 'AT', 'refresh_token' => 'RT', 'external_id' => 'UC1', 'expires_at' => now()->addHour()]);
    }

    public function test_upload_needs_youtube_then_receives_chunks_in_order_and_sends_resumably(): void
    {
        $meta = ['title' => 'My video', 'description' => 'Chapters…', 'tags' => ['ai'], 'privacy' => 'scheduled', 'publish_at' => '2026-11-06T17:00:00Z', 'bytes' => 10, 'mime' => 'video/mp4'];
        $this->postJson('/api/youtube/uploads', $meta)->assertStatus(422);
        $this->connect();
        $id = $this->postJson('/api/youtube/uploads', $meta)->assertCreated()->json('upload.id');

        $this->call('POST', "/api/youtube/uploads/{$id}/chunk?offset=4", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], 'xxxx')->assertStatus(409);
        $this->call('POST', "/api/youtube/uploads/{$id}/chunk?offset=0", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], 'abcdef')->assertOk()->assertJsonPath('upload.status', 'receiving');
        $this->call('POST', "/api/youtube/uploads/{$id}/chunk?offset=6", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], 'ghij')->assertOk()->assertJsonPath('upload.status', 'queued');

        Http::fake([
            'www.googleapis.com/upload/youtube/v3/videos*' => Http::response('', 200, ['Location' => 'https://upload.example/session1']),
            'upload.example/session1' => Http::response(['id' => 'vid123'], 200),
        ]);
        $this->postJson('/api/youtube/uploads/drain')->assertOk();
        $u = VideoUpload::find($id);
        $this->assertSame(['done', 'vid123', 100], [$u->status, $u->video_id, $u->progress]);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'uploadType=resumable') && $r['status']['privacyStatus'] === 'private' && $r['status']['publishAt'] === '2026-11-06T17:00:00Z');
        Http::assertSent(fn ($r) => $r->url() === 'https://upload.example/session1' && $r->header('Content-Range')[0] === 'bytes 0-9/10' && $r->body() === 'abcdefghij');
        Storage::disk('local')->assertMissing($u->path);
    }

    public function test_upload_resumes_after_308_and_fails_cleanly_on_refusal(): void
    {
        $this->connect();
        Storage::disk('local')->put('youtube-uploads/a.bin', str_repeat('z', 20));
        $u = VideoUpload::create(['title' => 'T', 'privacy' => 'private', 'path' => 'youtube-uploads/a.bin', 'bytes' => 20, 'received' => 20, 'status' => 'queued', 'upload_url' => 'https://upload.example/s', 'sent' => 0]);
        Http::fake(['upload.example/s' => Http::sequence()->push('', 308, ['Range' => 'bytes=0-9'])->push(['id' => 'v9'], 201)]);
        YouTubeStudio::push($u, microtime(true) + 5);
        $this->assertSame('done', $u->fresh()->status);

        Storage::disk('local')->put('youtube-uploads/b.bin', 'zz');
        $b = VideoUpload::create(['title' => 'B', 'privacy' => 'public', 'path' => 'youtube-uploads/b.bin', 'bytes' => 2, 'received' => 2, 'status' => 'queued', 'upload_url' => 'https://upload.example/b']);
        Http::fake(['upload.example/b' => Http::response(['error' => ['message' => 'quotaExceeded']], 403)]);
        YouTubeStudio::drainUploads(5);
        $this->assertSame('failed', $b->fresh()->status);
        $this->assertStringContainsString('quotaExceeded', $b->fresh()->error);
    }

    public function test_title_test_rotates_measures_and_applies_winner(): void
    {
        $this->connect();
        $views = 1000;
        $title = 'Original';
        Http::fake(function ($request) use (&$views, &$title) {
            if ($request->method() === 'PUT') {
                $title = $request['snippet']['title'];

                return Http::response(['id' => 'v1']);
            }

            return Http::response(['items' => [['snippet' => ['title' => $title, 'categoryId' => '28', 'description' => 'd'], 'statistics' => ['viewCount' => (string) $views]]]]);
        });
        $t = $this->postJson('/api/youtube/tests', ['video_id' => 'v1', 'titles' => ['Better title'], 'hours' => 24, 'rounds' => 1])->assertCreated()->json('test');
        $this->assertSame(['Original', 'Better title'], $t['titles']);

        $this->travel(25)->hours(); $views = 1100; // Original: 100 views
        YouTubeStudio::tickTests();
        $this->assertSame('Better title', $title);
        $this->travel(25)->hours(); $views = 1400; // Better: 300 views
        YouTubeStudio::tickTests();
        $test = TitleTest::first();
        $this->assertSame(['done', 1], [$test->status, $test->winner]);
        $this->assertSame('Better title', $title);
        $this->assertGreaterThan($test->results[0]['views_per_hour'], $test->results[1]['views_per_hour']);
        $this->postJson('/api/youtube/tests', ['video_id' => 'v1', 'titles' => [], 'hours' => 24, 'rounds' => 1])->assertStatus(422);
    }

    public function test_brand_kit_rules_reach_prompts_and_post_score(): void
    {
        $this->putJson('/api/brand', ['color' => '#112233', 'color_2' => '#445566', 'font' => 'Poppins', 'banned_words' => "synergy, hustle\nguru", 'cta' => 'Subscribe for weekly AI tools'])->assertOk();
        $this->putJson('/api/brand', ['color' => 'red', 'color_2' => '#445566', 'font' => 'Comic'])->assertStatus(422);
        $rules = Prompts::writingRules();
        $this->assertStringContainsString('synergy, hustle, guru', $rules);
        $this->assertStringContainsString('Subscribe for weekly AI tools', $rules);
        $checks = $this->postJson('/api/social/score', ['platform' => 'linkedin', 'body' => 'Pure hustle and synergy today.'])->json('checks');
        $this->assertStringContainsString('synergy, hustle', collect($checks)->firstWhere('source', 'your brand kit')['text']);
    }

    public function test_link_in_bio_page_tracks_clicks_and_never_redirects_elsewhere(): void
    {
        $r = $this->putJson('/api/bio', ['title' => 'Delia ✨', 'text' => 'AI tools <b>for</b> women in tech', 'links' => [
            ['title' => 'YouTube', 'url' => 'https://youtube.com/@Tech.Unicorn'], ['title' => 'Newsletter', 'url' => 'https://example.com/news', 'active' => false],
        ]])->assertOk();
        $this->putJson('/api/bio', ['links' => [['title' => 'Bad', 'url' => 'javascript:alert(1)']]])->assertStatus(422);
        $yt = collect($r->json('links'))->firstWhere('title', 'YouTube')['id'];
        $news = collect($r->json('links'))->firstWhere('title', 'Newsletter')['id'];

        auth()->logout();
        $page = $this->get('/links')->assertOk();
        $page->assertSee('Delia ✨')->assertSee('YouTube')->assertDontSee('Newsletter');
        $page->assertSee('AI tools &lt;b&gt;for&lt;/b&gt;', false);
        $this->get("/links/go/{$yt}")->assertRedirect('https://youtube.com/@Tech.Unicorn');
        $this->get("/links/go/{$yt}")->assertRedirect();
        $this->get("/links/go/{$news}")->assertNotFound();
        $this->get('/links/go/999')->assertNotFound();
        $this->assertSame(2, (int) DB::table('bio_clicks')->where('bio_link_id', $yt)->value('count'));
        $this->actingAs(User::first());
        $this->assertSame(2, collect($this->getJson('/api/bio')->json('links'))->firstWhere('id', $yt)['clicks_30d']);
    }

    public function test_memory_jar_question_of_the_day_and_answers(): void
    {
        Setting::put('nickname', 'Delia');
        Setting::put('love_from', 'Dempsey');
        $j = $this->getJson('/api/love/jar')->assertOk();
        $this->assertNotEmpty($j->json('today.question'));
        $this->assertSame(['Delia', 'Dempsey'], [$j->json('her'), $j->json('him')]);
        $id = $j->json('today.id');
        $this->putJson("/api/love/jar/{$id}", ['who' => 'her', 'answer' => 'Our coffee walk'])->assertOk()->assertJsonPath('today.her_answer', 'Our coffee walk');
        $this->assertSame($j->json('today.question'), $this->getJson('/api/love/jar')->json('today.question')); // same all day
        $this->travel(1)->days();
        $this->assertCount(1, $this->getJson('/api/love/jar')->json('past'));
    }

    public function test_ask_your_numbers_uses_only_her_data(): void
    {
        Setting::put('llm_api_key', 'k');
        $seen = null;
        Llm::$fake = function ($s, $u) use (&$seen) { $seen = $s.$u; return 'Your best network is **Instagram**.'; };
        $this->postJson('/api/social/ask', ['question' => 'What works best?'])->assertOk()->assertJsonPath('answer', 'Your best network is **Instagram**.');
        $this->assertStringContainsString('ONLY the JSON data', $seen);
        $this->assertStringContainsString('"per_network_4_weeks"', $seen);
    }
}
