<?php

namespace Tests\Feature;

use App\Domains\Media\ClipProject;
use App\Domains\Media\MediaFile;
use App\Domains\Media\ShortsClipper;
use App\Domains\Media\TextToSpeech;
use App\Domains\Media\ThumbnailGenerator;
use App\Models\Script;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Read-aloud, thumbnail mockups, reference photos and Shorts (ported from MagicAI). */
class MediaTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = "\x89PNG\r\n\x1a\n0000fakepng";

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Storage::fake('local');
        $this->actingAs(User::create(['name' => 'Delia', 'email' => 'd@example.com', 'password' => 'secret123']));
    }

    protected function tearDown(): void
    {
        TextToSpeech::$fake = null;
        ThumbnailGenerator::$fake = null;
        ShortsClipper::$fake = null;
        parent::tearDown();
    }

    // ---- Read-aloud ------------------------------------------------------------------

    public function test_spoken_text_drops_cues_headings_and_trailing_sections(): void
    {
        $script = "## Cold open\n[VISUAL: me holding five phones]\nI let **five apps** run my money.\n- First point\n[YOUR STORY: the rent month]\n\n## Title options\n1. Nope";
        $this->assertSame("I let five apps run my money.\nFirst point\n(your story here)", TextToSpeech::spoken($script));
    }

    public function test_split_respects_limits_at_sentence_boundaries(): void
    {
        $para = str_repeat('This is a sentence that goes on. ', 60); // ~2,000 chars
        $parts = TextToSpeech::split($para."\n\n".$para, 1000);
        $this->assertGreaterThan(3, count($parts));
        foreach ($parts as $p) {
            $this->assertLessThanOrEqual(1000, mb_strlen($p));
            $this->assertStringEndsWith('.', $p);
        }
    }

    public function test_openai_read_aloud_splits_joins_and_stores_privately(): void
    {
        $this->postJson('/api/tts', ['text' => 'hi'])->assertStatus(422)->assertJsonPath('error', fn ($e) => str_contains($e, 'Read-aloud needs an OpenAI key or an ElevenLabs key'));

        Setting::put('embedding_api_key', 'sk-extra');
        Http::fake(['api.openai.com/v1/audio/speech' => Http::sequence()->push('MP3A')->push('MP3B')]);
        $script = Script::create(['title' => 'Budget apps', 'body' => str_repeat('Money talk. ', 400)."\n\n".str_repeat('More money talk. ', 150)]);

        $res = $this->postJson('/api/tts', ['script_id' => $script->id, 'voice' => 'nova', 'speed' => 1.1])->assertOk();
        $audio = MediaFile::findOrFail($res->json('audio.id'));
        $this->assertSame('MP3AMP3B', $audio->contents(), 'chunks concatenated in order');
        $this->assertSame('audio/mpeg', $audio->mime);
        $this->assertStringStartsWith('media/audio/', $audio->path);
        Http::assertSent(fn ($r) => $r['model'] === 'gpt-4o-mini-tts' && $r['voice'] === 'nova' && $r['speed'] === 1.1 && ! isset($r['language']) && $r->hasHeader('Authorization', 'Bearer sk-extra'));
        Http::assertSentCount(2);

        $this->get($audio->url)->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        $this->assertArrayNotHasKey('path', $res->json('audio'), 'storage path never leaves the server');
    }

    public function test_elevenlabs_uses_ported_voice_settings(): void
    {
        Setting::put('elevenlabs_api_key', 'xi');
        Http::fake(['api.elevenlabs.io/v1/text-to-speech/*' => Http::response('MP3')]);
        $this->postJson('/api/tts', ['text' => 'Hello there, friends of the channel.', 'provider' => 'elevenlabs', 'voice' => 'voice123'])->assertOk();
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/text-to-speech/voice123') && $r->hasHeader('xi-api-key', 'xi')
            && $r['model_id'] === 'eleven_multilingual_v2' && $r['voice_settings']['similarity_boost'] === 0.75 && $r['voice_settings']['stability'] === 0.95);
    }

    public function test_media_file_requires_login(): void
    {
        $m = MediaFile::store('audio', 'MP3', 'mp3', 'audio/mpeg');
        $this->get($m->url)->assertOk();
        auth()->logout();
        $this->getJson($m->url)->assertUnauthorized();
    }

    // ---- Thumbnails ------------------------------------------------------------------

    public function test_thumbnail_generation_request_and_storage(): void
    {
        Setting::put('embedding_api_key', 'sk-extra');
        Http::fake(['api.openai.com/v1/images/generations' => Http::response(['data' => [['b64_json' => base64_encode(self::PNG)]]])]);
        $script = Script::create(['title' => 'S']);

        $res = $this->postJson('/api/thumbnails', ['script_id' => $script->id, 'concept' => 'Me holding five phones', 'text' => '£300 GONE', 'style' => 'bright pop'])->assertOk();
        $img = MediaFile::findOrFail($res->json('image.id'));
        $this->assertSame(['thumbnail', 'image/png', 'gpt-image-1'], [$img->kind, $img->mime, $img->model]);
        $this->assertSame(self::PNG, $img->contents());
        Http::assertSent(fn ($r) => $r['size'] === '1536x1024' && $r['quality'] === 'medium'
            && str_contains($r['prompt'], 'exactly: "£300 GONE"') && str_contains($r['prompt'], 'bright pop style'));
        $this->getJson("/api/media?kind=thumbnail&script_id={$script->id}")->assertJsonCount(1, 'media');
    }

    public function test_photos_go_to_the_edits_endpoint_as_references(): void
    {
        Setting::put('embedding_api_key', 'sk-extra');
        $this->postJson('/api/thumbnails', ['concept' => 'x', 'use_photos' => true])->assertStatus(422)->assertJsonPath('error', fn ($e) => str_contains($e, 'Upload a photo'));

        $this->post('/api/media/photos', ['photo' => UploadedFile::fake()->createWithContent('me.png', self::PNG)], ['Accept' => 'application/json'])->assertOk();
        $this->post('/api/media/photos', ['photo' => UploadedFile::fake()->createWithContent('me.txt', 'not an image')], ['Accept' => 'application/json'])->assertStatus(422);

        Http::fake(['api.openai.com/v1/images/edits' => Http::response(['data' => [['b64_json' => base64_encode(self::PNG)]]])]);
        $this->postJson('/api/thumbnails', ['concept' => 'Shocked face', 'use_photos' => true])->assertOk()->assertJsonPath('image.meta.with_photos', 1);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/images/edits') && $r->isMultipart()
            && collect($r->data())->contains(fn ($part) => ($part['name'] ?? '') === 'image[]'));
    }

    public function test_fal_provider_downloads_the_returned_image(): void
    {
        Setting::put('fal_api_key', 'fal-k');
        Http::fake([
            'fal.run/*' => Http::response(['images' => [['url' => 'https://cdn.fal.example/a.png']]]),
            'cdn.fal.example/*' => Http::response(self::PNG),
        ]);
        $this->postJson('/api/thumbnails', ['concept' => 'Cozy desk', 'provider' => 'fal'])->assertOk()->assertJsonPath('image.provider', 'fal');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'fal.run/fal-ai/flux/dev') && $r->hasHeader('Authorization', 'Key fal-k') && $r['image_size'] === 'landscape_16_9');
    }

    public function test_non_image_response_is_rejected(): void
    {
        $this->expectExceptionMessage('isn’t an image');
        ThumbnailGenerator::sniff('<html>error</html>');
    }

    // ---- Shorts (Vizard) -------------------------------------------------------------

    public function test_submit_and_poll_vizard(): void
    {
        $this->postJson('/api/clips', ['url' => 'https://youtu.be/dQw4w9WgXcQ'])->assertStatus(422)->assertJsonPath('error', fn ($e) => str_contains($e, 'Vizard API key'));

        Setting::put('vizard_api_key', 'viz');
        $polls = 0;
        Http::fake([
            '*/project/create' => Http::response(['code' => 2000, 'projectId' => 98765]),
            '*/project/query/98765' => function () use (&$polls) {
                return Http::response(++$polls === 1 ? ['code' => 1000] : ['code' => 2000, 'videos' => [
                    ['videoId' => 1, 'title' => 'Low', 'videoUrl' => 'https://v.example/1.mp4', 'videoMsDuration' => 31000, 'viralScore' => '6'],
                    ['videoId' => 2, 'title' => 'Best moment', 'videoUrl' => 'https://v.example/2.mp4', 'videoMsDuration' => 45500, 'viralScore' => '9.1', 'clipEditorUrl' => 'https://vizard.ai/editor/2'],
                ]]);
            },
        ]);
        $script = Script::create(['title' => 'S']);

        $pid = $this->postJson('/api/clips', ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'script_id' => $script->id, 'length' => 2, 'max' => 5])->assertOk()->json('project.id');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'project/create') && $r->hasHeader('VIZARDAI_API_KEY', 'viz')
            && $r['videoType'] === 2 && $r['preferLength'] === [2] && $r['ratioOfClip'] === 1 && $r['maxClipNumber'] === 5);

        $this->postJson("/api/clips/{$pid}/refresh")->assertJsonPath('project.status', 'processing');
        $this->artisan('studio:clips')->assertSuccessful()->expectsOutputToContain('1 finished');
        $p = ClipProject::find($pid);
        $this->assertSame('done', $p->status);
        $this->assertSame('Best moment', $p->clips[0]['title'], 'sorted by viral score');
        $this->assertEquals(46, $p->clips[0]['seconds']);
        $this->getJson("/api/clips?script_id={$script->id}")->assertJsonCount(1, 'projects');
    }

    public function test_vizard_rejects_bad_links_and_reports_errors(): void
    {
        ShortsClipper::$fake = fn ($m, $path) => $path === 'project/create' ? ['code' => 4001, 'errMsg' => 'Video too short'] : [];
        $this->postJson('/api/clips', ['url' => 'https://example.com/page'])->assertStatus(422)->assertJsonPath('error', fn ($e) => str_contains($e, 'YouTube link'));
        $this->postJson('/api/clips', ['url' => 'https://cdn.example/video.mp4'])->assertStatus(422)->assertJsonPath('error', 'Vizard refused the video: Video too short');

        $p = ClipProject::create(['source_url' => 'x', 'project_id' => '1', 'status' => 'processing']);
        ShortsClipper::$fake = fn () => ['code' => 4008, 'errMsg' => 'Download failed'];
        $this->postJson("/api/clips/{$p->id}/refresh")->assertJsonPath('project.status', 'failed')->assertJsonPath('project.error', 'Download failed');
    }

    public function test_capabilities_reflect_keys(): void
    {
        $this->getJson('/api/media/capabilities')->assertOk()
            ->assertJsonPath('tts.providers', [])->assertJsonPath('images.providers', [])->assertJsonPath('clips', false);
        Setting::put('llm_engine', 'openai');
        Setting::put('llm_api_key', 'sk');
        $this->getJson('/api/media/capabilities')->assertJsonPath('tts.providers', ['openai'])->assertJsonPath('images.default', 'openai');
    }
}
