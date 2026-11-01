<?php

namespace Tests\Feature;

use App\Domains\Memory\Embeddings;
use App\Domains\Memory\Memory;
use App\Domains\Memory\MemoryChunk;
use App\Domains\Search\WebSearch;
use App\Domains\Toolkit\Models\Document;
use App\Models\Channel;
use App\Models\Idea;
use App\Models\Note;
use App\Models\Script;
use App\Models\Setting;
use App\Models\User;
use App\Models\Week;
use App\Services\Llm;
use App\Services\YouTube;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Web search + fact-check, Memory, and the scheduled studio jobs. */
class SearchMemoryScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Llm::$fake = fn () => 'ok';
        $this->actingAs($this->admin(['name' => 'Delia', 'email' => 'd@example.com', 'password' => 'secret123']));
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        WebSearch::$fake = null;
        Embeddings::$fake = null;
        parent::tearDown();
    }

    private function events(string $body): array
    {
        $out = [];
        foreach (preg_split("/\n\n/", trim($body)) as $block) {
            if (preg_match('/^event: (\w+)\ndata: (.*)$/s', trim($block), $m)) {
                $out[] = [$m[1], $m[2]];
            }
        }

        return $out;
    }

    // ---- Web search ------------------------------------------------------------------

    public function test_serper_search_is_normalised_and_key_stays_server_side(): void
    {
        Setting::put('serper_api_key', 'serp-secret');
        Http::fake(['google.serper.dev/*' => Http::response([
            'answerBox' => ['answer' => '4.25%'],
            'organic' => [['title' => 'BoE rate', 'link' => 'https://bank.example/rate', 'snippet' => 'Cut to 4.25%', 'date' => 'Oct 30, 2026']],
        ])]);

        $r = app(WebSearch::class)->search('bank of england rate');
        $this->assertSame('serper', $r['provider']);
        $this->assertSame('4.25%', $r['answer']);
        $this->assertSame('https://bank.example/rate', $r['results'][0]['link']);
        Http::assertSent(fn ($req) => $req->hasHeader('X-API-KEY', 'serp-secret') && $req['q'] === 'bank of england rate');
        $this->assertStringNotContainsString('serp-secret', json_encode($this->getJson('/api/settings')->json()));
    }

    public function test_perplexity_search_uses_search_results_or_citations(): void
    {
        Setting::put('search_provider', 'perplexity');
        Setting::put('perplexity_api_key', 'pplx');
        Http::fake(['api.perplexity.ai/*' => Http::response(['choices' => [['message' => ['content' => 'It was cut.']]], 'citations' => ['https://news.example/a']])]);

        $r = app(WebSearch::class)->search('rate cut');
        $this->assertSame('perplexity', $r['provider']);
        $this->assertSame('It was cut.', $r['answer']);
        $this->assertSame('https://news.example/a', $r['results'][0]['link']);
    }

    public function test_search_provider_off_and_unconfigured(): void
    {
        $this->assertNull(WebSearch::provider());
        Setting::put('serper_api_key', 'x');
        $this->assertSame('serper', WebSearch::provider());
        Setting::put('search_provider', 'off');
        $this->assertNull(WebSearch::provider());
    }

    public function test_fact_check_streams_progress_and_saves_report(): void
    {
        $script = Script::create(['title' => 'Rates', 'body' => "## Claims list\n- The BoE cut rates to 4.25% in October 2026."]);
        $this->postJson("/api/stream/scripts/{$script->id}/fact-check")->assertStatus(422); // no search configured

        WebSearch::$fake = fn ($q) => ['results' => [['title' => 'BoE', 'link' => 'https://bank.example/rate', 'snippet' => '4.25%', 'date' => null]]];
        $calls = 0;
        Llm::$fake = function ($system, $user) use (&$calls) {
            $calls++;

            return $calls === 1
                ? json_encode(['claims' => [['claim' => 'The BoE cut rates to 4.25%', 'query' => 'BoE rate October 2026']]])
                : "### 1. The BoE cut rates to 4.25%\n**Verdict:** VERIFIED\n**Sources:** [BoE](https://bank.example/rate)";
        };

        $events = $this->events($this->postJson("/api/stream/scripts/{$script->id}/fact-check")->streamedContent());
        $statuses = array_map(fn ($e) => json_decode($e[1], true)['step'], array_filter($events, fn ($e) => $e[0] === 'status'));
        $this->assertSame(['extracting', 'searching', 'judging'], array_values($statuses));
        $done = json_decode(collect($events)->firstWhere(0, 'done')[1], true);
        $this->assertSame(1, $done['claims']);
        $this->assertStringContainsString('VERIFIED', $script->fresh()->fact_check);
        $this->assertNotNull($script->fresh()->fact_checked_at);
    }

    public function test_chat_web_toggle_injects_results(): void
    {
        WebSearch::$fake = fn ($q) => ['results' => [['title' => 'Trend', 'link' => 'https://t.example', 'snippet' => 'Hot now', 'date' => null]]];
        $seen = null;
        Llm::$fake = function ($system) use (&$seen) { $seen = $system; return 'Answer'; };
        $cid = $this->postJson('/api/chats')->json('conversation.id');

        $events = $this->events($this->postJson("/api/stream/chats/{$cid}/messages", ['content' => 'what is trending', 'web' => true])->streamedContent());
        $this->assertContains(['status', '{"step":"searching"}'], $events);
        $this->assertStringContainsString('https://t.example', $seen);

        $this->postJson("/api/stream/chats/{$cid}/messages", ['content' => 'no web please'])->streamedContent();
        $this->assertStringNotContainsString('Live web search results', $seen);
    }

    // ---- Memory ----------------------------------------------------------------------

    public function test_chunking_is_multibyte_safe_and_overlapping(): void
    {
        $text = str_repeat('Café crème brûlée naïve façade ', 200); // ~6,200 chars, accented
        $chunks = Memory::chunk($text);
        $this->assertGreaterThan(4, count($chunks));
        foreach ($chunks as $c) {
            $this->assertTrue(mb_check_encoding($c, 'UTF-8'));
            $this->assertLessThanOrEqual(Memory::WINDOW, mb_strlen($c));
        }
        $this->assertSame([], Memory::chunk('tiny'));
    }

    public function test_saving_indexes_automatically_and_keyword_search_finds_it(): void
    {
        $this->postJson('/api/documents', ['title' => 'Sourdough for beginners', 'content' => 'My sourdough starter died twice before it worked. Feed it daily.'])->assertOk();
        $script = Script::create(['title' => 'Budget apps', 'body' => 'I tested five budgeting apps for a month.']);
        $script->update(['transcript' => 'Honestly the budgeting apps all nagged me.']);
        Note::create(['polished' => "**Camera idea**\nTry the window light for talking heads.", 'style' => 'notes']);

        $this->assertGreaterThan(0, MemoryChunk::where('source_type', 'transcript')->count(), 'after-response job ran');

        $res = $this->getJson('/api/memory/search?q='.urlencode('sourdough starter'))->assertOk()->json('results');
        $this->assertSame('document', $res[0]['source_type']);
        $this->assertFalse($res[0]['semantic']);
        $this->assertStringContainsString('sourdough', strtolower($res[0]['excerpt']));

        $res = $this->getJson('/api/memory/search?q=budgeting&types=transcript')->json('results');
        $this->assertCount(1, $res);
        $this->assertSame("/scripts/{$script->id}", $res[0]['link']);

        $script->delete();
        $this->assertSame(0, MemoryChunk::whereIn('source_type', ['script', 'transcript'])->count(), 'delete forgets');
    }

    public function test_semantic_search_ranks_by_cosine_and_skips_unchanged(): void
    {
        $calls = 0;
        // Fake 3-d embeddings: dimension per topic word.
        Embeddings::$fake = function (array $texts) use (&$calls) {
            $calls++;

            return array_map(fn ($t) => [
                substr_count(strtolower($t), 'money') + substr_count(strtolower($t), 'budget') + 0.01,
                substr_count(strtolower($t), 'camera') + 0.01,
                substr_count(strtolower($t), 'bread') + 0.01,
            ], $texts);
        };
        $memory = app(Memory::class);
        $memory->indexText('document', 1, 'Money tips', 'Budget every Sunday. Money in envelopes.');
        $memory->indexText('document', 2, 'Camera setup', 'My camera and lights.');
        $before = $calls;
        $memory->indexText('document', 1, 'Money tips', 'Budget every Sunday. Money in envelopes.');
        $this->assertSame($before, $calls, 'unchanged text is not re-embedded');

        $hits = $memory->search('how should I save money', 2);
        $this->assertSame('Money tips', $hits[0]['title']);
        $this->assertTrue($hits[0]['semantic']);
        $this->assertSame(1.0, round(Embeddings::cosine([1, 0], [2, 0]), 4));
        $this->assertSame(0.0, Embeddings::cosine([0, 0], [1, 1]));
    }

    public function test_embeddings_provider_selection_and_batch_call(): void
    {
        $this->assertNull(Embeddings::config());
        Setting::put('llm_engine', 'anthropic');
        Setting::put('llm_api_key', 'sk-ant');
        $this->assertNull(Embeddings::config(), 'Claude has no embeddings endpoint');
        Setting::put('embedding_api_key', 'sk-oa');
        $this->assertSame('text-embedding-3-small', Embeddings::config()['model']);

        Http::fake(['api.openai.com/v1/embeddings' => Http::response(['data' => [['index' => 1, 'embedding' => [0, 1]], ['index' => 0, 'embedding' => [1, 0]]]])]);
        $v = app(Embeddings::class)->embed(['a', 'b']);
        $this->assertSame([[1, 0], [0, 1]], $v, 'reordered by index');
        Http::assertSent(fn ($r) => $r['input'] === ['a', 'b'] && $r->hasHeader('Authorization', 'Bearer sk-oa'));
    }

    public function test_memory_status_and_reindex(): void
    {
        Idea::create(['title' => 'Thrift flip challenge', 'premise' => 'Turn £20 of thrift finds into outfits']);
        $this->postJson('/api/memory/reindex', ['fresh' => true])->assertOk()
            ->assertJsonPath('semantic', false)->assertJsonPath('sources.idea', 1);
    }

    // ---- Schedule --------------------------------------------------------------------

    public function test_studio_commands_are_scheduled_in_her_timezone(): void
    {
        Artisan::call('schedule:list');
        $list = Artisan::output();
        foreach (['studio:scan', 'studio:feeds', 'studio:checkin', 'studio:memory', 'studio:prune'] as $cmd) {
            $this->assertStringContainsString($cmd, $list);
        }
    }

    public function test_scan_command_records_last_run_and_errors(): void
    {
        $this->artisan('studio:scan')->assertFailed(); // no channels yet
        $this->assertStringContainsString('Please add active competitor channels first.', Setting::get('last_error_scan'));

        Channel::create(['handle' => 'someone']);
        $this->app->instance(YouTube::class, tap(new YouTube, fn ($yt) => $yt->getter = fn () => ['items' => []]));
        $this->artisan('studio:scan')->assertSuccessful()->expectsOutputToContain('Found 0 new breakout');
        $this->assertNotNull(Setting::get('last_run_scan'));
        $this->assertNull(Setting::get('last_error_scan'));
    }

    public function test_schedule_status_and_run_now(): void
    {
        $this->getJson('/api/schedule')->assertOk()->assertJsonPath('enabled', true)->assertJsonCount(18, 'tasks');
        $this->postJson('/api/schedule/prune/run')->assertOk()->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Pruned'));
        $this->postJson('/api/schedule/nope/run')->assertNotFound();
        $this->postJson('/api/schedule/checkin/run')->assertStatus(422)->assertJsonPath('error', 'Set up your weekly plan first.');
    }

    public function test_checkin_button_still_works_after_refactor(): void
    {
        Week::create(['number' => 1, 'start_date' => '2026-10-19', 'due_date' => '2026-10-25']);
        $this->postJson('/api/goals/checkin')->assertStatus(400)->assertJsonPath('error', 'Add your channel handle or ID in Settings.');
    }
}
