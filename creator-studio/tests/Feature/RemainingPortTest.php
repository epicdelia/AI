<?php

namespace Tests\Feature;

use App\Domains\Chat\Models\Conversation;
use App\Domains\Media\BrollGenerator;
use App\Domains\Media\MediaFile;
use App\Domains\Media\VideoJob;
use App\Domains\Toolkit\Models\Document;
use App\Models\Script;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/** B-roll (fal queue), audio clean-up (ElevenLabs isolation), exports and the tour. */
class RemainingPortTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Storage::fake('local');
        $this->actingAs($this->admin(['name' => 'Delia', 'email' => 'd@example.com', 'password' => 'secret123']));
    }

    protected function tearDown(): void
    {
        BrollGenerator::$fake = null;
        parent::tearDown();
    }

    // ---- B-roll ----------------------------------------------------------------------

    public function test_broll_submit_poll_and_download_via_fal_queue(): void
    {
        $this->postJson('/api/broll', ['prompt' => 'desk'])->assertStatus(422)->assertJsonPath('error', fn ($e) => str_contains($e, 'fal.ai key'));

        Setting::put('fal_api_key', 'fal-k');
        $polls = 0;
        Http::fake([
            'queue.fal.run/fal-ai/kling-video/v3/standard/text-to-video' => Http::response([
                'request_id' => 'req-1',
                'status_url' => 'https://queue.fal.run/fal-ai/kling-video/requests/req-1/status',
                'response_url' => 'https://queue.fal.run/fal-ai/kling-video/requests/req-1',
            ]),
            'queue.fal.run/fal-ai/kling-video/requests/req-1/status' => function () use (&$polls) {
                return Http::response(['status' => ++$polls === 1 ? 'IN_PROGRESS' : 'COMPLETED']);
            },
            'queue.fal.run/fal-ai/kling-video/requests/req-1' => Http::response(['video' => ['url' => 'https://v3.fal.media/out.mp4']]),
            'v3.fal.media/*' => Http::response('MP4BYTES'),
        ]);
        $script = Script::create(['title' => 'S', 'body' => 'x [B-ROLL: receipts falling] y']);

        $id = $this->postJson('/api/broll', ['prompt' => 'Receipts falling in slow motion', 'script_id' => $script->id, 'duration' => '10', 'aspect' => '9:16'])
            ->assertOk()->json('job.id');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v3/standard/text-to-video') && $r->hasHeader('Authorization', 'Key fal-k')
            && $r['duration'] === '10' && $r['aspect_ratio'] === '9:16' && ! isset($r['image_url']));
        $this->assertArrayNotHasKey('status_url', $this->getJson('/api/broll')->json('jobs.0'), 'internal URLs stay server-side');

        $this->postJson("/api/broll/{$id}/refresh")->assertJsonPath('job.status', 'running');
        $this->artisan('studio:broll')->assertSuccessful()->expectsOutputToContain('1 finished');
        $job = VideoJob::with('media')->find($id);
        $this->assertSame('done', $job->status);
        $this->assertSame('MP4BYTES', $job->media->contents());
        $this->assertSame('video/mp4', $job->media->mime);
    }

    public function test_broll_from_a_mockup_uses_image_to_video_with_data_uri(): void
    {
        Setting::put('fal_api_key', 'k');
        $png = "\x89PNG\r\n\x1a\nxx";
        $mock = MediaFile::store('thumbnail', $png, 'png', 'image/png');
        $sent = null;
        BrollGenerator::$fake = function ($method, $url, $body) use (&$sent) {
            $sent = [$url, $body];

            return ['request_id' => 'r2'];
        };
        $this->postJson('/api/broll', ['prompt' => 'Bring it to life', 'model' => 'veo3.1-fast', 'media_id' => $mock->id])->assertOk();
        $this->assertSame('https://queue.fal.run/fal-ai/veo3.1/fast/image-to-video', $sent[0]);
        $this->assertSame('data:image/png;base64,'.base64_encode($png), $sent[1]['image_url']);
        $this->assertSame('6s', $sent[1]['duration']);
        $this->assertFalse($sent[1]['generate_audio']);
    }

    public function test_broll_failure_and_limit(): void
    {
        Setting::put('fal_api_key', 'k');
        BrollGenerator::$fake = fn ($m, $url) => str_ends_with($url, '/status') ? ['status' => 'FAILED'] : ['request_id' => 'r3'];
        $id = $this->postJson('/api/broll', ['prompt' => 'x'])->json('job.id');
        $this->postJson("/api/broll/{$id}/refresh")->assertJsonPath('job.status', 'failed')->assertJsonPath('job.error', 'fal.ai status: FAILED');

        Setting::put('monthly_video_limit', '1');
        $this->postJson('/api/broll', ['prompt' => 'y'])->assertStatus(422)->assertJsonPath('error', fn ($e) => str_contains($e, 'b-roll clips (1 of 1)'));
    }

    // ---- Audio clean-up --------------------------------------------------------------

    public function test_audio_cleanup_posts_multipart_to_elevenlabs_isolation(): void
    {
        $this->post('/api/audio/clean', ['file' => UploadedFile::fake()->create('take.mp3', 50, 'audio/mpeg')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('error', fn ($e) => str_contains($e, 'ElevenLabs key'));

        Setting::put('elevenlabs_api_key', 'xi');
        Http::fake(['api.elevenlabs.io/v1/audio-isolation' => Http::response('CLEANMP3')]);
        $res = $this->post('/api/audio/clean', ['file' => UploadedFile::fake()->create('noisy take.m4a', 50, 'audio/mp4')], ['Accept' => 'application/json'])->assertOk();
        Http::assertSent(fn ($r) => $r->hasHeader('xi-api-key', 'xi') && $r->isMultipart() && collect($r->data())->contains(fn ($p) => ($p['name'] ?? '') === 'audio'));

        $audio = MediaFile::find($res->json('audio.id'));
        $this->assertSame(['CLEANMP3', 'Cleaned: noisy take'], [$audio->contents(), $audio->title]);
        $this->post('/api/audio/clean', ['file' => UploadedFile::fake()->create('doc.pdf', 5, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(422);
    }

    // ---- Exports ---------------------------------------------------------------------

    public function test_script_exports_as_word_markdown_and_text(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('zip extension not loaded');
        }
        $script = Script::create(['title' => 'Budget apps', 'body' => "## Cold open\n[VISUAL: phones]\nI tried **five** apps & one *failed*.\n- Point one", 'review' => 'Voice: 8/10',
            'outline' => ['title' => 'Budget apps', 'chapters' => [['title' => 'Cold open', 'beat' => 'the £300']]]]);

        $res = $this->get("/api/export/scripts/{$script->id}?parts=outline,body,review&format=docx")->assertOk();
        $this->assertStringContainsString('attachment; filename="budget-apps.docx"', $res->headers->get('Content-Disposition'));
        $path = tempnam(sys_get_temp_dir(), 't');
        file_put_contents($path, $res->getContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'a valid zip/docx');
        $xml = $zip->getFromName('word/document.xml');
        $this->assertStringContainsString('<w:pStyle w:val="Heading1"/>', $xml); // "# Plan" section header
        $this->assertStringContainsString('<w:b/></w:rPr><w:t xml:space="preserve">five</w:t>', $xml);
        $this->assertStringContainsString('&amp; one', $xml, 'XML-escaped');
        $this->assertStringContainsString('[VISUAL: phones]', $xml);
        $this->assertNotFalse($zip->getFromName('word/styles.xml'));
        $zip->close();

        $md = $this->get("/api/export/scripts/{$script->id}?parts=body&format=md")->assertOk()->getContent();
        $this->assertStringStartsWith("# Budget apps\n\n## Cold open", $md);
        $txt = $this->get("/api/export/scripts/{$script->id}?parts=body&format=txt")->getContent();
        $this->assertStringContainsString('I tried five apps & one failed.', $txt);
        $this->assertStringNotContainsString('**', $txt);
    }

    public function test_chat_and_document_exports(): void
    {
        $c = Conversation::create(['title' => 'Title brainstorm']);
        $c->messages()->create(['role' => 'user', 'content' => 'Ideas?']);
        $c->messages()->create(['role' => 'assistant', 'content' => '1. One']);
        $md = $this->get("/api/export/chats/{$c->id}?format=md")->assertOk()->getContent();
        $this->assertStringContainsString("**Delia:**\n\nIdeas?", $md);
        $this->assertStringContainsString("**Producer:**\n\n1. One", $md);

        $d = Document::create(['title' => 'Sponsor brief', 'content' => 'Mention DELIA20']);
        $this->get("/api/export/documents/{$d->id}?format=txt")->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    // ---- Tour ------------------------------------------------------------------------

    public function test_tour_flag(): void
    {
        $this->getJson('/api/auth/status')->assertJsonPath('tour_done', false);
        $this->postJson('/api/tour/done')->assertOk();
        $this->getJson('/api/auth/status')->assertJsonPath('tour_done', true);
    }
}
