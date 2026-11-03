<?php

namespace Tests\Feature;

use App\Domains\Engine\Drivers\AnthropicDriver;
use App\Domains\Engine\Drivers\OpenAiCompatibleDriver;
use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Feeds\FeedReader;
use App\Domains\Feeds\Models\Feed;
use App\Domains\Feeds\Models\FeedItem;
use App\Domains\Usage\AiUsage;
use App\Models\Idea;
use App\Models\Script;
use App\Models\Setting;
use App\Services\Llm;
use App\Services\Transcription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Streaming, recording transcription and RSS feeds (ported from MagicAI).
 */
class PortedFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private const RSS = <<<'XML'
<?xml version="1.0"?>
<rss version="2.0"><channel><title>Money News</title>
  <item><title>Rates cut again</title><link>https://news.example/rates</link><guid>r1</guid><description><![CDATA[<p>The bank cut rates &amp; more.</p>]]></description><pubDate>Fri, 30 Oct 2026 10:00:00 GMT</pubDate><enclosure url="https://news.example/r.jpg" type="image/jpeg"/></item>
  <item><title>Student loans change</title><link>https://news.example/loans</link><guid>r2</guid><description>New rules.</description><pubDate>Thu, 29 Oct 2026 10:00:00 GMT</pubDate></item>
</channel></rss>
XML;

    private const ATOM = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom"><title>Creator Blog</title>
  <entry><title>Thumbnails that work</title><id>tag:a,1</id><link rel="alternate" href="https://blog.example/thumbs"/><updated>2026-10-28T09:00:00Z</updated><summary>Faces and contrast.</summary></entry>
</feed>
XML;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Llm::$fake = fn (string $system, string $user) => "Hello there.\nSecond line.";
        Transcription::$pollSeconds = 0;
        $this->actingAs($this->admin([
            'name' => 'Delia',
            'email' => 'd@example.com',
            'password' => 'secret123',
        ]));
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        Transcription::$fake = null;
        FeedReader::$fake = null;
        parent::tearDown();
    }

    // ---- Streaming ------------------------------------------------------------------

    public function test_tool_stream_sends_chunks_then_done_then_stop(): void
    {
        $res = $this->postJson('/api/stream/tools/run', [
            'tool' => 'yt_titles',
            'inputs' => [
                'topic' => 'budgets',
            ],
        ]);
        $res->assertOk();
        $this->assertStringContainsString('text/event-stream', $res->headers->get('Content-Type'));

        $events = $this->events($res->streamedContent());
        $chunks = array_map(fn ($e) => json_decode($e[1]), array_filter($events, fn ($e) => $e[0] === 'data'));
        $this->assertSame("Hello there.\nSecond line.", implode('', $chunks), 'newlines survive JSON-encoded chunks');
        $this->assertSame('done', $events[count($events) - 2][0]);
        $this->assertSame("Hello there.\nSecond line.", json_decode($events[count($events) - 2][1], true)['output']);
        $this->assertSame(['stop', '[DONE]'], end($events));
    }

    public function test_stream_validation_errors_are_plain_json(): void
    {
        $this->postJson('/api/stream/tools/run', [
            'tool' => 'yt_titles',
            'inputs' => [],
        ])->assertStatus(422)->assertJsonStructure(['error']);
        $script = Script::create([
            'title' => 'S',
            'body' => 'A game-changer — wow.',
        ]);
        $this->postJson("/api/stream/scripts/{$script->id}/review")->assertStatus(422)->assertJsonStructure(['findings']);
    }

    public function test_ai_failure_mid_stream_becomes_an_error_event(): void
    {
        Llm::$fake = fn () => throw new RuntimeException('Quota exceeded');
        $events = $this->events($this->postJson('/api/stream/tools/run', [
            'tool' => 'yt_titles',
            'inputs' => [
                'topic' => 'x',
            ],
        ])->streamedContent());
        $this->assertContains(['error', '"Quota exceeded"'], $events);
        $this->assertSame(['stop', '[DONE]'], end($events));
    }

    public function test_chat_stream_saves_both_messages(): void
    {
        $cid = $this->postJson('/api/chats')->json('conversation.id');
        $events = $this->events($this->postJson("/api/stream/chats/{$cid}/messages", [
            'content' => 'Title ideas?',
        ])->streamedContent());
        $this->assertSame('message', $events[0][0]);
        $done = json_decode(collect($events)->firstWhere(0, 'done')[1], true);
        $this->assertSame('assistant', $done['message']['role']);
        $this->assertSame('Title ideas?', $done['conversation']['title']);
        $this->getJson("/api/chats/{$cid}")->assertJsonCount(2, 'messages');
    }

    public function test_script_draft_review_substack_and_brief_stream_and_save(): void
    {
        $idea = Idea::create([
            'title' => 'Budget apps',
            'status' => 'picked',
        ]);
        $script = Script::create([
            'idea_id' => $idea->id,
            'title' => 'Budget apps',
        ]);

        $this->postJson("/api/stream/scripts/{$script->id}/draft")->assertOk()->streamedContent();
        $this->assertSame("Hello there.\nSecond line.", $script->fresh()->body);
        $this->postJson("/api/stream/scripts/{$script->id}/draft")->assertStatus(422); // won't overwrite

        $this->postJson("/api/stream/scripts/{$script->id}/review")->streamedContent();
        $this->assertSame('reviewed', $script->fresh()->status);
        $this->postJson("/api/stream/scripts/{$script->id}/substack")->streamedContent();
        $this->postJson("/api/stream/scripts/{$script->id}/edit-brief")->streamedContent();
        $fresh = $script->fresh();
        $this->assertNotEmpty($fresh->review);
        $this->assertNotEmpty($fresh->substack);
        $this->assertNotEmpty($fresh->edit_brief);
    }

    public function test_openai_compatible_driver_streams_deltas_and_usage(): void
    {
        Setting::put('llm_api_key', 'sk-test');
        Http::fake([
            'api.openai.com/*' => Http::response(
                "data: {\"choices\":[{\"delta\":{\"role\":\"assistant\"}}]}\n\n"
                        . "data: {\"choices\":[{\"delta\":{\"content\":\"Hel\"}}]}\n\n"
                        . "data: {\"choices\":[{\"delta\":{\"content\":\"lo\"}}],\"model\":\"gpt-5-mini\"}\n\n"
                        . "data: {\"choices\":[],\"usage\":{\"prompt_tokens\":7,\"completion_tokens\":2}}\n\n"
                        . "data: [DONE]\n\n",
                200,
                [
                    'Content-Type' => 'text/event-stream',
                ]
            ),
        ]);

        $deltas = [];
        $result = (new OpenAiCompatibleDriver(EngineEnum::OPENAI))->stream('sys', [[
            'role' => 'user',
            'content' => 'hi',
        ]], function ($d) use (&$deltas) { $deltas[] = $d; });

        $this->assertSame(['Hel', 'lo'], $deltas);
        $this->assertSame('Hello', $result->text);
        $this->assertSame([7, 2], [$result->inputTokens, $result->outputTokens]);
        Http::assertSent(fn ($r) => $r['stream'] === true && $r['stream_options']['include_usage'] === true);
    }

    public function test_anthropic_driver_streams_content_block_deltas(): void
    {
        Setting::put('llm_api_key', 'sk-ant');
        Http::fake([
            'api.anthropic.com/*' => Http::response(
                "event: message_start\ndata: {\"type\":\"message_start\",\"message\":{\"model\":\"claude-sonnet-5-5\",\"usage\":{\"input_tokens\":11}}}\n\n"
                        . "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"text_delta\",\"text\":\"Hi \"}}\n\n"
                        . "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"text_delta\",\"text\":\"Delia\"}}\n\n"
                        . "event: message_delta\ndata: {\"type\":\"message_delta\",\"usage\":{\"output_tokens\":3}}\n\n"
                        . "event: message_stop\ndata: {\"type\":\"message_stop\"}\n\n",
                200,
                [
                    'Content-Type' => 'text/event-stream',
                ]
            ),
        ]);

        $deltas = [];
        $result = (new AnthropicDriver(EngineEnum::ANTHROPIC))->stream('sys', [[
            'role' => 'user',
            'content' => 'hi',
        ]], function ($d) use (&$deltas) { $deltas[] = $d; });

        $this->assertSame(['Hi ', 'Delia'], $deltas);
        $this->assertSame([11, 3, 'claude-sonnet-5-5'], [$result->inputTokens, $result->outputTokens, $result->model]);
    }

    public function test_llm_stream_logs_usage(): void
    {
        Llm::$fake = null;
        Setting::put('llm_engine', 'anthropic');
        Setting::put('llm_api_key', 'sk-ant');
        Http::fake([
            'api.anthropic.com/*' => Http::response(
                "data: {\"type\":\"content_block_delta\",\"delta\":{\"text\":\"ok\"}}\n\ndata: {\"type\":\"message_stop\"}\n\n"
            ),
        ]);

        app(Llm::class)->as('chat')->stream('s', [[
            'role' => 'user',
            'content' => 'x',
        ]], fn () => null);
        $this->assertSame('chat', AiUsage::first()->feature);
        $this->assertTrue(AiUsage::first()->ok);
    }

    // ---- Transcription ---------------------------------------------------------------

    public function test_transcribe_requires_an_engine(): void
    {
        $this->postJson('/api/transcribe', [
            'file' => UploadedFile::fake()->create('take.mp3', 50, 'audio/mpeg'),
        ])
            ->assertStatus(422)->assertJsonPath('error', fn ($e) => str_contains($e, 'AssemblyAI'));
        $this->getJson('/api/transcribe')->assertOk()->assertJsonPath('engine', null);
    }

    public function test_transcribe_saves_timestamped_transcript_to_script_and_deletes_upload(): void
    {
        Setting::put('assemblyai_api_key', 'aai');
        $seenPath = null;
        Transcription::$fake = function ($path, $engine) use (&$seenPath) {
            $seenPath = $path;
            $this->assertFileExists($path);
            $this->assertSame('assemblyai', $engine);

            return [
                'text' => 'One two. Three four.',
                'segments' => [[
                    'start' => 0.0,
                    'text' => 'One two.',
                ], [
                    'start' => 42.0,
                    'text' => 'Three four.',
                ]],
            ];
        };
        $script = Script::create([
            'title' => 'S',
        ]);

        $this->post('/api/transcribe', [
            'file' => UploadedFile::fake()->create('take.mov', 200, 'video/quicktime'),
            'script_id' => $script->id,
            'timestamps' => '1',
        ], [
            'Accept' => 'application/json',
        ])
            ->assertOk()->assertJsonPath('engine', 'assemblyai')->assertJsonPath('words', 4);

        $this->assertSame("[00:00] One two.\n[00:42] Three four.", $script->fresh()->transcript);
        $this->assertFileDoesNotExist($seenPath, 'upload is removed after transcription');
        $this->assertSame('transcription', AiUsage::first()->feature);
    }

    public function test_transcribe_rejects_unsupported_types(): void
    {
        Setting::put('assemblyai_api_key', 'aai');
        $this->post('/api/transcribe', [
            'file' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertStatus(422)->assertJsonPath('error', fn ($e) => str_contains($e, 'Unsupported file type'));
    }

    public function test_assemblyai_upload_job_and_poll(): void
    {
        Setting::put('assemblyai_api_key', 'aai');
        Http::fake([
            'api.assemblyai.com/v2/upload' => Http::response([
                'upload_url' => 'https://cdn.assemblyai.com/u/1',
            ]),
            'api.assemblyai.com/v2/transcript' => Http::response([
                'id' => 't1',
                'status' => 'queued',
            ]),
            'api.assemblyai.com/v2/transcript/t1' => Http::sequence()
                ->push([
                    'status' => 'processing',
                ])
                ->push([
                    'status' => 'completed',
                    'text' => 'Hi there. Bye.',
                    'words' => [
                        [
                            'text' => 'Hi',
                            'start' => 0,
                        ], [
                            'text' => 'there.',
                            'start' => 400,
                        ], [
                            'text' => 'Bye.',
                            'start' => 61000,
                        ],
                    ],
                ]),
        ]);

        $res = $this->post('/api/transcribe', [
            'file' => UploadedFile::fake()->create('take.m4a', 100, 'audio/mp4'),
            'timestamps' => '1',
        ], [
            'Accept' => 'application/json',
        ]);
        $res->assertOk()->assertJsonPath('transcript', "[00:00] Hi there.\n[01:01] Bye.");
        Http::assertSent(fn ($r) => $r->url() === 'https://api.assemblyai.com/v2/transcript' && $r['audio_url'] === 'https://cdn.assemblyai.com/u/1' && $r->hasHeader('Authorization', 'aai'));
    }

    public function test_whisper_path_uses_verbose_json_and_enforces_25mb(): void
    {
        Setting::put('llm_engine', 'openai');
        Setting::put('llm_api_key', 'sk-test');
        Http::fake([
            'api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'Hello world',
                'segments' => [[
                    'start' => 0,
                    'text' => ' Hello world',
                ]],
            ]),
        ]);

        $this->post('/api/transcribe', [
            'file' => UploadedFile::fake()->create('take.mp3', 100, 'audio/mpeg'),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertOk()->assertJsonPath('engine', 'openai')->assertJsonPath('transcript', 'Hello world');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/audio/transcriptions') && $r->isMultipart());

        // Checked in the service (the HTTP layer may already cap uploads lower via php.ini).
        try {
            app(Transcription::class)->transcribe(UploadedFile::fake()->create('big.mp4', 26 * 1024, 'video/mp4'));
            $this->fail('A 26 MB file was sent to Whisper');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('25 MB', $e->getMessage());
        }
    }

    // ---- Feeds -----------------------------------------------------------------------

    public function test_reader_parses_rss_and_atom(): void
    {
        FeedReader::$fake = fn ($url) => str_contains($url, 'atom') ? self::ATOM : self::RSS;

        $rss = (new FeedReader())->parse('https://news.example/rss');
        $this->assertSame('Money News', $rss['title']);
        $this->assertSame('Rates cut again', $rss['items'][0]['title']);
        $this->assertSame('The bank cut rates & more.', $rss['items'][0]['summary']);
        $this->assertSame('https://news.example/r.jpg', $rss['items'][0]['image']);
        $this->assertSame('2026-10-30', $rss['items'][0]['published_at']->toDateString());

        $atom = (new FeedReader())->parse('https://blog.example/atom');
        $this->assertSame('Creator Blog', $atom['title']);
        $this->assertSame('https://blog.example/thumbs', $atom['items'][0]['link']);
    }

    public function test_reader_rejects_bad_and_private_urls(): void
    {
        foreach (['ftp://example.com/feed', 'http://127.0.0.1/feed', 'http://192.168.1.10/rss', 'http://[::1]/rss'] as $url) {
            try {
                FeedReader::assertPublicUrl($url);
                $this->fail("Accepted {$url}");
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
        FeedReader::$fake = fn () => 'not xml at all';
        $this->postJson('/api/feeds', [
            'url' => 'https://news.example/rss',
        ])->assertStatus(422);
    }

    public function test_feed_crud_refresh_dedupes(): void
    {
        FeedReader::$fake = fn () => self::RSS;
        $this->postJson('/api/feeds', [
            'url' => 'news.example/rss',
        ])->assertOk()
            ->assertJsonPath('feeds.0.title', 'Money News')->assertJsonPath('feeds.0.url', 'https://news.example/rss')
            ->assertJsonPath('fresh_count', 2);
        $this->postJson('/api/feeds', [
            'url' => 'https://news.example/rss',
        ])->assertStatus(422);

        $this->postJson('/api/feeds/refresh')->assertOk()->assertJsonPath('message', 'Feeds refreshed: 0 new item(s).');
        $this->assertSame(2, FeedItem::count());

        $feed = Feed::first();
        $this->patchJson("/api/feeds/{$feed->id}/toggle")->assertJsonPath('feed.active', false);
        $this->deleteJson('/api/feed-items/' . FeedItem::first()->id)->assertOk();
        $this->deleteJson("/api/feeds/{$feed->id}")->assertOk();
        $this->assertSame(0, FeedItem::count());
    }

    public function test_idea_generation_uses_fresh_feed_items_and_marks_them_used(): void
    {
        $this->travelTo('2026-10-31 12:00:00');
        FeedReader::$fake = fn () => self::RSS;
        $this->postJson('/api/feeds', [
            'url' => 'https://news.example/rss',
        ])->assertOk();

        $prompt = null;
        Llm::$fake = function ($system, $user) use (&$prompt) {
            $prompt = $user;

            return json_encode([
                'summary' => 'News-led day.',
                'ideas' => [[
                    'title' => 'Rates cut: what students do now',
                    'source_url' => 'https://news.example/rates',
                ]],
            ]);
        };

        $this->postJson('/api/ideas/generate')->assertOk()->assertJsonPath('message', '1 new idea(s) generated from 2 news item(s).');
        $this->assertStringContainsString('News and blog posts in her niche', $prompt);
        $this->assertStringContainsString('https://news.example/rates', $prompt);
        $this->assertSame(0, FeedItem::where('used', false)->count());
    }

    /**
     * Parse an SSE body into [event, data] pairs.
     */
    private function events(string $body): array
    {
        $out = [];
        foreach (preg_split("/\n\n/", trim($body)) as $block) {
            $event = null;
            $data = [];
            foreach (explode("\n", $block) as $line) {
                if (str_starts_with($line, 'event: ')) {
                    $event = substr($line, 7);
                } elseif (str_starts_with($line, 'data: ')) {
                    $data[] = substr($line, 6);
                }
            }
            if ($event) {
                $out[] = [$event, implode("\n", $data)];
            }
        }

        return $out;
    }
}
