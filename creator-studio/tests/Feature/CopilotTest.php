<?php

namespace Tests\Feature;

use App\Domains\Copilot\CopilotTools;
use App\Domains\Copilot\Models\CopilotAction;
use App\Domains\Copilot\Models\CopilotMessage;
use App\Domains\Copilot\Models\CopilotThread;
use App\Domains\Engine\Catalog\ModelCatalog;
use App\Domains\Engine\Data\ToolTurn;
use App\Domains\Engine\Drivers\AnthropicDriver;
use App\Domains\Engine\Drivers\OpenAiCompatibleDriver;
use App\Domains\Engine\EngineKeys;
use App\Domains\Engine\Enums\EngineEnum;
use App\Models\Idea;
use App\Models\Script;
use App\Models\Setting;
use App\Models\User;
use App\Services\Llm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The agentic Copilot: tool loop, proposals (apply/undo), threads, models across providers. */
class CopilotTest extends TestCase
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
        Llm::$toolFake = null;
        Llm::$fake = null;
        parent::tearDown();
    }

    private function events(string $body): array
    {
        $out = [];
        foreach (preg_split("/\n\n/", trim($body)) as $block) {
            if (preg_match('/^event: (\w+)\ndata: (.*)$/s', trim($block), $m)) {
                $out[] = [$m[1], json_decode($m[2], true) ?? $m[2]];
            }
        }

        return $out;
    }

    /** Script a sequence of model turns; record what the agent sent each time. */
    private function scriptedTurns(array $turns, ?array &$seen = null): void
    {
        $seen = [];
        $i = 0;
        Llm::$toolFake = function ($system, $messages, $tools) use (&$i, $turns, &$seen) {
            $seen[] = compact('system', 'messages', 'tools');

            return $turns[min($i++, count($turns) - 1)];
        };
    }

    public function test_tool_definitions_depend_on_mode(): void
    {
        $tools = app(CopilotTools::class);
        $agent = array_column($tools->definitions('agent'), 'name');
        $ask = array_column($tools->definitions('ask'), 'name');
        $this->assertContains('edit_script', $agent);
        $this->assertNotContains('edit_script', $ask, 'Ask mode never gets write tools');
        $this->assertContains('get_script', $ask);
        $this->assertNotContains('search_web', $agent, 'web search only when a provider is configured');
        foreach ($tools->definitions('agent') as $t) {
            $this->assertSame('object', $t['parameters']['type'], $t['name']);
        }
    }

    public function test_agent_reads_then_proposes_an_edit_and_she_applies_and_undoes_it(): void
    {
        $script = Script::create(['title' => 'Budget apps', 'body' => "## Cold open\nSo today we look at apps.\n\nMore text."]);
        $this->scriptedTurns([
            new ToolTurn('', [['id' => 'c1', 'name' => 'get_script', 'args' => ['script_id' => $script->id]]]),
            new ToolTurn('Sharpening the opening.', [
                [
                    'id' => 'c2',
                    'name' => 'edit_script',
                    'args' => [
                        'script_id' => $script->id,
                        'operation' => 'replace_text',
                        'find' => 'So today we look at apps.',
                        'text' => 'Five apps. One month. £300 I didn’t know I was wasting.',
                        'reason' => 'payoff first',
                    ]
                ]
            ]),
            new ToolTurn('I proposed a punchier cold open: hit **Apply** if you like it.'),
        ], $seen);

        $thread = $this->postJson('/api/copilot/threads', ['mode' => 'agent'])->json('thread');
        $res = $this->postJson("/api/stream/copilot/threads/{$thread['id']}/messages", [
            'content' => 'Make my cold open punchier',
            'context' => ['path' => "/scripts/{$script->id}", 'script_id' => $script->id, 'title' => 'Budget apps', 'selection' => 'So today we look at apps.'],
        ])->assertOk();
        $events = $this->events($res->streamedContent());

        // Step 2 saw the get_script result; the system prompt knows the page and selection.
        $this->assertSame('tool', $seen[1]['messages'][2]['role']);
        $this->assertStringContainsString('So today we look at apps.', $seen[1]['messages'][2]['content']);
        $this->assertStringContainsString("script #{$script->id}", $seen[0]['system']);
        $this->assertStringContainsString('She has selected this text', $seen[0]['system']);

        $kinds = array_column($events, 0);
        $this->assertContains('action', $kinds);
        $this->assertContains('done', $kinds);
        $tools = array_values(array_filter($events, fn($e) => $e[0] === 'tool' && $e[1]['status'] !== 'running'));
        $this->assertSame(['done', 'proposed'], array_column(array_column($tools, 1), 'status'));

        $this->assertStringContainsString('So today', $script->fresh()->body, 'nothing changes before Apply');
        $action = CopilotAction::firstOrFail();
        $this->assertSame('pending', $action->status);
        $this->assertSame('So today we look at apps.', $action->result['preview']['before']);

        $this->postJson("/api/copilot/actions/{$action->id}/apply")->assertOk()->assertJsonPath('script.id', $script->id);
        $this->assertStringStartsWith("## Cold open\nFive apps. One month.", $script->fresh()->body);
        $this->postJson("/api/copilot/actions/{$action->id}/apply")->assertStatus(409);

        $this->postJson("/api/copilot/actions/{$action->id}/undo")->assertOk();
        $this->assertStringContainsString('So today we look at apps.', $script->fresh()->body);
        $this->assertSame('undone', $action->fresh()->status);

        // The transcript records what she did, so the agent knows next turn.
        $this->assertTrue(CopilotMessage::where('content', 'like', '[Studio note] She applied change%')->exists());
        $this->assertTrue(CopilotMessage::where('content', 'like', '[Studio note] She undid change%')->exists());
    }

    public function test_bad_proposals_are_rejected_back_to_the_model(): void
    {
        $script = Script::create(['title' => 'S', 'body' => 'Hello world']);
        $this->scriptedTurns([
            new ToolTurn('', [['id' => 'c1', 'name' => 'edit_script', 'args' => ['script_id' => $script->id, 'operation' => 'replace_text', 'find' => 'not in script', 'text' => 'x']]]),
            new ToolTurn('Sorry, let me read it first.'),
        ], $seen);
        $thread = CopilotThread::create(['mode' => 'agent']);
        $this->postJson("/api/stream/copilot/threads/{$thread->id}/messages", ['content' => 'edit it'])->streamedContent();

        $this->assertStringContainsString('ERROR: The "find" text is not in the script', $seen[1]['messages'][2]['content']);
        $this->assertSame(0, CopilotAction::count());
    }

    public function test_ask_mode_cannot_change_anything(): void
    {
        $this->scriptedTurns([
            new ToolTurn('', [['id' => 'c1', 'name' => 'create_idea', 'args' => ['title' => 'Sneaky']]]),
            new ToolTurn('In Ask mode I can only suggest: save “Sneaky” as an idea.'),
        ], $seen);
        $thread = CopilotThread::create(['mode' => 'ask']);
        $this->postJson("/api/stream/copilot/threads/{$thread->id}/messages", ['content' => 'save an idea', 'mode' => 'ask'])->streamedContent();

        $this->assertNotContains('create_idea', array_column($seen[0]['tools'], 'name'));
        $this->assertStringContainsString('Ask mode can’t change anything', $seen[1]['messages'][2]['content']);
        $this->assertSame(0, CopilotAction::count());
        $this->assertSame(0, Idea::count());
    }

    public function test_open_page_is_a_client_event_and_ideas_can_be_created(): void
    {
        $this->scriptedTurns([
            new ToolTurn('', [
                ['id' => 'c1', 'name' => 'create_idea', 'args' => ['title' => 'Thrift flip', 'premise' => '£20 challenge']],
                ['id' => 'c2', 'name' => 'open_page', 'args' => ['path' => '/ideas']],
            ]),
            new ToolTurn('Done: proposed the idea and opened Ideas.'),
        ]);
        $thread = CopilotThread::create(['mode' => 'agent']);
        $events = $this->events($this->postJson("/api/stream/copilot/threads/{$thread->id}/messages", ['content' => 'idea please'])->streamedContent());

        $client = collect($events)->firstWhere(0, 'client');
        $this->assertSame(['name' => 'open_page', 'args' => ['path' => '/ideas']], $client[1]);
        $action = CopilotAction::firstOrFail();
        $this->postJson("/api/copilot/actions/{$action->id}/apply")->assertOk()->assertJsonPath('link', '/ideas');
        $this->assertSame('picked', Idea::where('title', 'Thrift flip')->value('status'));
        $this->postJson("/api/copilot/actions/{$action->id}/undo")->assertOk();
        $this->assertSame(0, Idea::count());
    }

    public function test_stale_edit_fails_cleanly_at_apply_time(): void
    {
        $script = Script::create(['title' => 'S', 'body' => 'Alpha beta']);
        $action = app(CopilotTools::class)->propose(CopilotThread::create(), 'edit_script', ['script_id' => $script->id, 'operation' => 'replace_text', 'find' => 'Alpha', 'text' => 'Gamma']);
        $script->update(['body' => 'Rewritten meanwhile']);
        $this->postJson("/api/copilot/actions/{$action->id}/apply")->assertStatus(422)->assertJsonPath('action.status', 'failed');
        $this->assertSame('Rewritten meanwhile', $script->fresh()->body);
    }

    public function test_threads_history_projects_and_feedback(): void
    {
        $a = $this->postJson('/api/copilot/threads', ['project' => 'Budget series'])->json('thread');
        $this->postJson('/api/copilot/threads', [])->assertOk();
        $this->getJson('/api/copilot/threads')->assertJsonCount(2, 'threads');
        $this->getJson('/api/copilot/threads?project=Budget+series')->assertJsonCount(1, 'threads');
        $this->getJson('/api/copilot/meta')->assertJsonPath('projects', ['Budget series'])->assertJsonPath('commands.0.cmd', '/rewrite');

        $this->patchJson("/api/copilot/threads/{$a['id']}", ['title' => 'Renamed', 'mode' => 'ask'])->assertJsonPath('thread.mode', 'ask');
        $this->postJson('/api/copilot/feedback', [])->assertStatus(422);
        $this->postJson('/api/copilot/feedback', ['thread_id' => $a['id'], 'rating' => 'up', 'text' => 'love it'])->assertOk();
        $this->deleteJson("/api/copilot/threads/{$a['id']}")->assertOk();
        $this->getJson("/api/copilot/threads/{$a['id']}")->assertNotFound();
    }

    public function test_show_hides_tool_rows_and_returns_steps(): void
    {
        $this->scriptedTurns([new ToolTurn('', [['id' => 'c1', 'name' => 'list_ideas', 'args' => []]]), new ToolTurn('You have no ideas yet.')]);
        $thread = CopilotThread::create(['mode' => 'agent']);
        $this->postJson("/api/stream/copilot/threads/{$thread->id}/messages", ['content' => 'my ideas?'])->streamedContent();
        $msgs = $this->getJson("/api/copilot/threads/{$thread->id}")->json('messages');
        $this->assertSame(['user', 'steps', 'assistant'], array_column($msgs, 'role'));
        $this->assertSame('Looking through ideas', $msgs[1]['steps'][0]['label']);
        $this->assertSame('my ideas?', CopilotThread::find($thread->id)->title);
    }

    // ---- models across providers (ported catalog) -------------------------------------

    public function test_model_catalog_includes_magicai_models_and_curated_rules(): void
    {
        $openai = ModelCatalog::all(EngineEnum::OPENAI);
        $this->assertGreaterThan(30, count($openai), 'ported from MagicAI EntityEnum');
        $this->assertArrayHasKey('gpt-4.1-mini', $openai);
        $this->assertSame(['none', 'low', 'medium', 'high', 'xhigh'], $openai['gpt-5.5'][1], 'curated effort ladder wins');
        $this->assertArrayHasKey('claude-opus-5-5', ModelCatalog::all(EngineEnum::ANTHROPIC));
        $this->assertNotEmpty(ModelCatalog::all(EngineEnum::OPENROUTER));
        $this->assertTrue(ModelCatalog::isReasoning(EngineEnum::OPENAI, 'gpt-5-nano'));
    }

    public function test_picker_lists_every_provider_with_a_key_and_routes_to_it(): void
    {
        Setting::put('llm_engine', 'openai');
        Setting::put('llm_api_key', 'sk-main');
        Setting::put('extra_llm_keys', 'anthropic=sk-ant-x; gemini=AIza-y');
        $groups = collect($this->getJson('/api/copilot/meta')->json('groups'))->keyBy('engine');
        $this->assertEqualsCanonicalizing(['openai', 'anthropic', 'gemini'], $groups->keys()->all());
        $this->assertTrue($groups['openai']['current']);
        $this->assertStringStartsWith('anthropic|', $groups['anthropic']['models'][0]['value']);

        [$e, $m] = EngineKeys::parse('anthropic|claude-sonnet-5-5');
        $this->assertSame([EngineEnum::ANTHROPIC, 'claude-sonnet-5-5'], [$e, $m]);
        $this->assertSame([EngineEnum::OPENAI, 'gpt-4.1'], EngineKeys::parse('gpt-4.1'));
        $this->assertSame([EngineEnum::OPENAI, null], EngineKeys::parse('xai|grok-4'), 'no xAI key → stays on Settings engine with default model');
        $this->assertSame([EngineEnum::OPENAI, null], EngineKeys::parse('openai|gemini'), 'malformed engine-model pair falls back to default');
        $this->assertSame([EngineEnum::OPENAI, null], EngineKeys::parse('openai'), 'bare engine falls back to default model');

        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'hi']], 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]])]);
        app(Llm::class)->model('anthropic|claude-sonnet-5-5')->agentTurn('s', [['role' => 'user', 'content' => 'x']], []);
        Http::assertSent(fn($r) => str_contains($r->url(), 'anthropic.com') && $r->hasHeader('x-api-key', 'sk-ant-x') && $r['model'] === 'claude-sonnet-5-5');
    }

    // ---- native tool calling wire formats ---------------------------------------------

    public function test_openai_tool_calls_round_trip(): void
    {
        Setting::put('llm_api_key', 'sk');
        Setting::put('llm_model', 'gpt-4.1');
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => null,
                            'tool_calls' => [
                                ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'get_script', 'arguments' => '{"script_id":4}']],
                            ]
                        ]
                    ]
                ]
            ])
        ]);
        $msgs = [
            ['role' => 'user', 'content' => 'hi'],
            ['role' => 'assistant', 'content' => '', 'tool_calls' => [['id' => 'call_0', 'name' => 'list_scripts', 'args' => []]]],
            ['role' => 'tool', 'tool_call_id' => 'call_0', 'name' => 'list_scripts', 'content' => '[]'],
        ];
        $turn = (new OpenAiCompatibleDriver(EngineEnum::OPENAI))->toolChat('sys', $msgs, [['name' => 'get_script', 'description' => 'd', 'parameters' => ['type' => 'object', 'properties' => (object) []]]]);

        $this->assertSame([['id' => 'call_1', 'name' => 'get_script', 'args' => ['script_id' => 4]]], $turn->toolCalls);
        Http::assertSent(fn($r) => $r['tools'][0]['type'] === 'function' && $r['tools'][0]['function']['name'] === 'get_script'
            && $r['messages'][2]['tool_calls'][0]['function']['arguments'] === '{}'
            && $r['messages'][3] === ['role' => 'tool', 'tool_call_id' => 'call_0', 'content' => '[]']);
    }

    public function test_anthropic_tool_use_round_trip_merges_results(): void
    {
        Setting::put('llm_engine', 'anthropic');
        Setting::put('llm_api_key', 'sk-ant');
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'Let me look.'],
                    ['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'list_ideas', 'input' => ['status' => 'picked']],
                ]
            ])
        ]);
        $msgs = [
            ['role' => 'user', 'content' => 'hi'],
            ['role' => 'assistant', 'content' => '', 'tool_calls' => [['id' => 'a', 'name' => 'x', 'args' => []], ['id' => 'b', 'name' => 'y', 'args' => ['q' => 1]]]],
            ['role' => 'tool', 'tool_call_id' => 'a', 'name' => 'x', 'content' => 'A'],
            ['role' => 'tool', 'tool_call_id' => 'b', 'name' => 'y', 'content' => 'B'],
        ];
        $turn = (new AnthropicDriver(EngineEnum::ANTHROPIC))->toolChat('sys', $msgs, [['name' => 'list_ideas', 'description' => 'd', 'parameters' => ['type' => 'object', 'properties' => (object) []]]]);

        $this->assertSame('Let me look.', $turn->text);
        $this->assertSame([['id' => 'tu_1', 'name' => 'list_ideas', 'args' => ['status' => 'picked']]], $turn->toolCalls);
        Http::assertSent(function ($r) {
            $m = $r['messages'];

            return $r['tools'][0]['input_schema']['type'] === 'object'
                && $m[1]['content'][1]['type'] === 'tool_use' && $m[1]['content'][1]['id'] === 'b'
                && count($m) === 3 && count($m[2]['content']) === 2 && $m[2]['content'][1] === ['type' => 'tool_result', 'tool_use_id' => 'b', 'content' => 'B'];
        });
    }

    public function test_provider_without_tools_falls_back_to_plain_answer(): void
    {
        Setting::put('llm_api_key', 'sk');
        Setting::put('llm_model', 'gpt-4.1');
        $calls = 0;
        Http::fake([
            'api.openai.com/*' => function ($req) use (&$calls) {
                $calls++;

                return isset($req['tools']) ? Http::response(['error' => ['message' => 'tools are not supported for this model']], 400) : Http::response(['choices' => [['message' => ['content' => 'plain answer']]]]);
            }
        ]);
        $turn = (new OpenAiCompatibleDriver(EngineEnum::OPENAI))->toolChat('s', [['role' => 'user', 'content' => 'x']], [['name' => 't', 'description' => 'd', 'parameters' => ['type' => 'object', 'properties' => (object) []]]]);
        $this->assertSame(['plain answer', 2], [$turn->text, $calls]);
    }

    public function test_gemini_model_sanitization_removes_invalid_prefixes(): void
    {
        Setting::put('llm_engine', 'gemini');
        Setting::put('llm_api_key', 'AIza-test');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'ok']]],
            ])
        ]);

        $driver = new OpenAiCompatibleDriver(EngineEnum::GEMINI);

        // Test with "gemini|gemini-2.5-flash"
        $driver->chat('sys', [['role' => 'user', 'content' => 'hi']], ['model' => 'gemini|gemini-2.5-flash']);
        Http::assertSent(fn($r) => $r['model'] === 'gemini-2.5-flash');

        // Test with "models/gemini-2.5-pro"
        $driver->chat('sys', [['role' => 'user', 'content' => 'hi']], ['model' => 'models/gemini-2.5-pro']);
        Http::assertSent(fn($r) => $r['model'] === 'gemini-2.5-pro');

        // Test with bare "gemini" -> falls back to default model
        $driver->chat('sys', [['role' => 'user', 'content' => 'hi']], ['model' => 'gemini']);
        Http::assertSent(fn($r) => $r['model'] === EngineEnum::GEMINI->defaultModel());
    }
}
