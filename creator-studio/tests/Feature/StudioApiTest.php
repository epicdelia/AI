<?php

namespace Tests\Feature;

use App\Domains\Engine\EngineManager;
use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Usage\AiUsage;
use App\Models\Idea;
use App\Models\Setting;
use App\Models\User;
use App\Services\Llm;
use App\Services\YouTubeTranscript;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StudioApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Llm::$fake = fn (string $system, string $user) => "## Option 1\nA fake answer for: ".mb_substr($user, 0, 40);
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        YouTubeTranscript::$fake = null;
        parent::tearDown();
    }

    private function login(): User
    {
        $user = User::create(['name' => 'Delia', 'email' => 'd@example.com', 'password' => 'secret123']);
        $this->actingAs($user);

        return $user;
    }

    public function test_spa_shell_is_served_for_any_page(): void
    {
        foreach (['/', '/scripts/12', '/tools/yt_titles'] as $path) {
            $file = $this->get($path)->assertOk()->baseResponse->getFile();
            $this->assertSame(realpath(public_path('index.html')), $file->getRealPath(), $path);
        }
        $this->assertStringContainsString('type="module" src="/js/app.js', file_get_contents(public_path('index.html')));
    }

    public function test_api_requires_login_and_returns_json_401(): void
    {
        $this->getJson('/api/dashboard')->assertStatus(401);
        $this->getJson('/api/tools')->assertStatus(401);
    }

    public function test_first_run_setup_then_login(): void
    {
        $this->getJson('/api/auth/status')->assertJson(['initialized' => false, 'authenticated' => false]);
        $this->postJson('/api/auth/setup', [
            'name' => 'Delia', 'email' => 'd@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertOk();
        $this->postJson('/api/auth/setup', [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertStatus(403);
        $this->postJson('/api/auth/logout')->assertOk();
        $this->postJson('/api/auth/login', ['email' => 'd@example.com', 'password' => 'wrong-pass'])->assertStatus(401);
        $this->postJson('/api/auth/login', ['email' => 'd@example.com', 'password' => 'secret123'])->assertOk();
    }

    public function test_dashboard_loads(): void
    {
        $this->login();
        $this->getJson('/api/dashboard')->assertOk()->assertJsonStructure(['creator_name', 'stats', 'setup_checks', 'weeks']);
    }

    public function test_idea_update_can_edit_and_clear_fields(): void
    {
        $this->login();
        $idea = Idea::create(['title' => 'Old', 'notes' => 'keep?', 'status' => 'new']);
        $this->patchJson("/api/ideas/{$idea->id}", ['title' => 'New', 'angle' => 'My angle', 'notes' => null, 'status' => 'picked'])->assertOk();
        $idea->refresh();
        $this->assertSame('New', $idea->title);
        $this->assertSame('My angle', $idea->angle);
        $this->assertNull($idea->notes);
        $this->assertSame('picked', $idea->status);
    }

    public function test_generate_ideas_reports_real_count_and_logs_usage(): void
    {
        $this->login();
        Llm::$fake = fn () => json_encode(['summary' => 'Weak signal.', 'ideas' => [['title' => 'One'], ['title' => 'Two'], ['title' => 'Three']]]);
        $this->postJson('/api/ideas/generate')->assertOk()->assertJsonPath('message', '3 new idea(s) generated from your niche.');
        $this->assertSame(3, Idea::count());
    }

    public function test_script_from_idea_lint_and_review_gate(): void
    {
        $this->login();
        $idea = Idea::create(['title' => 'Budget apps', 'status' => 'picked']);
        $id = $this->postJson('/api/scripts', ['idea_id' => $idea->id])->assertOk()->json('script.id');
        $this->assertSame('scripted', $idea->fresh()->status);

        $this->patchJson("/api/scripts/{$id}", ['body' => 'This is a game-changer — trust me.'])->assertOk();
        $this->getJson("/api/scripts/{$id}")->assertOk()->assertJsonPath('has_hard', true);
        $this->postJson("/api/scripts/{$id}/review")->assertStatus(422);

        $this->patchJson("/api/scripts/{$id}", ['body' => "I tried five apps. Here's what stuck."])->assertOk();
        $this->postJson("/api/scripts/{$id}/review")->assertOk()->assertJsonPath('script.status', 'reviewed');
    }

    public function test_note_saves_with_null_style(): void
    {
        $this->login();
        $this->postJson('/api/notes', ['polished' => '**Idea**', 'style' => null])->assertOk()->assertJsonPath('note.style', 'auto');
    }

    public function test_toolkit_lists_runs_and_validates(): void
    {
        $this->login();
        $tools = $this->getJson('/api/tools')->assertOk()->json('tools');
        $this->assertContains('yt_titles', array_column($tools, 'key'));

        $this->postJson('/api/tools/run', ['tool' => 'yt_titles', 'inputs' => []])->assertStatus(422);
        $this->postJson('/api/tools/run', ['tool' => 'nope'])->assertStatus(404);

        $seen = null;
        Llm::$fake = function ($system, $user) use (&$seen) { $seen = [$system, $user]; return 'Titles!'; };
        Setting::put('tone_of_voice', 'cheeky');
        $this->postJson('/api/tools/run', ['tool' => 'yt_titles', 'inputs' => ['topic' => 'student budgets'], 'results' => 3])
            ->assertOk()->assertJsonPath('output', 'Titles!');
        $this->assertStringContainsString('student budgets', $seen[1]);
        $this->assertStringContainsString('Give 3 different options', $seen[1]);
        $this->assertStringContainsString('Tone of voice: cheeky', $seen[0]);
    }

    public function test_custom_template_crud_and_run(): void
    {
        $this->login();
        $id = $this->postJson('/api/tools/custom', [
            'name' => 'Pinned comment', 'prompt' => 'Pinned comment ideas for {topic}',
            'fields' => [['name' => 'Topic!', 'label' => 'Topic', 'type' => 'textarea', 'required' => true], ['name' => '']],
        ])->assertOk()->json('tool.id');

        $tools = collect($this->getJson('/api/tools')->json('tools'));
        $custom = $tools->firstWhere('key', "custom_{$id}");
        $this->assertSame([['name' => 'topic', 'label' => 'Topic', 'type' => 'textarea', 'required' => true]], $custom['fields']);

        $this->postJson('/api/tools/run', ['tool' => "custom_{$id}", 'inputs' => ['topic' => 'my vlog']])->assertOk();
        $this->postJson('/api/tools/favorite', ['key' => "custom_{$id}"])->assertJsonPath('favorites', ["custom_{$id}"]);
        $this->postJson('/api/tools/favorite', ['key' => "custom_{$id}"])->assertJsonPath('favorites', []);
        $this->deleteJson("/api/tools/custom/{$id}")->assertOk();
    }

    public function test_library_crud_search_and_favorites(): void
    {
        $this->login();
        $id = $this->postJson('/api/documents', ['title' => 'Titles for budgets', 'tool' => 'yt_titles', 'content' => 'Option 1'])->assertOk()->json('document.id');
        $this->postJson('/api/documents', ['title' => 'Other', 'content' => 'zzz']);
        $this->getJson('/api/documents?q=budget')->assertOk()->assertJsonCount(1, 'documents.data');
        $this->patchJson("/api/documents/{$id}", ['favorite' => true])->assertJsonPath('document.favorite', true);
        $this->getJson('/api/documents?favorite=1')->assertJsonCount(1, 'documents.data');
        $this->deleteJson("/api/documents/{$id}")->assertOk();
    }

    public function test_chat_conversation_flow(): void
    {
        $this->login();
        $cid = $this->postJson('/api/chats')->assertOk()->json('conversation.id');
        $this->postJson("/api/chats/{$cid}/messages", ['content' => 'Give me title ideas for my budgeting video'])
            ->assertOk()->assertJsonPath('message.role', 'assistant')
            ->assertJsonPath('conversation.title', 'Give me title ideas for my budgeting video');
        $this->getJson("/api/chats/{$cid}")->assertJsonCount(2, 'messages');
        $this->deleteJson("/api/chats/{$cid}")->assertOk();
        $this->getJson("/api/chats/{$cid}")->assertNotFound();
    }

    public function test_youtube_analyze_with_link_and_pasted_transcript(): void
    {
        $this->login();
        YouTubeTranscript::$fake = fn ($id) => ['text' => 'hello world', 'segments' => [['start' => 0, 'text' => 'hello world']]];
        $this->postJson('/api/youtube/analyze', ['action' => 'summary', 'url' => 'https://youtu.be/dQw4w9WgXcQ'])
            ->assertOk()->assertJsonPath('transcript', 'hello world');
        $this->postJson('/api/youtube/analyze', ['action' => 'tldr', 'transcript' => 'pasted words'])->assertOk();
        $this->postJson('/api/youtube/analyze', ['action' => 'tldr'])->assertStatus(422);
        $this->postJson('/api/youtube/transcript', ['url' => 'not a link'])->assertStatus(422);
    }

    public function test_video_id_parsing(): void
    {
        foreach (['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=3', 'https://youtu.be/dQw4w9WgXcQ', 'https://youtube.com/shorts/dQw4w9WgXcQ', 'dQw4w9WgXcQ'] as $u) {
            $this->assertSame('dQw4w9WgXcQ', YouTubeTranscript::videoId($u), $u);
        }
        $this->assertNull(YouTubeTranscript::videoId('https://example.com'));
    }

    public function test_engine_selection_and_drivers_hit_the_right_api(): void
    {
        $this->login();
        Llm::$fake = null;
        Http::fake([
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'hi from claude']], 'usage' => ['input_tokens' => 5, 'output_tokens' => 3], 'model' => 'claude-sonnet-5-5']),
            'generativelanguage.googleapis.com/*' => Http::response(['choices' => [['message' => ['content' => 'hi from gemini']]], 'usage' => ['prompt_tokens' => 4, 'completion_tokens' => 2]]),
        ]);

        $this->putJson('/api/settings', ['llm_engine' => 'anthropic', 'llm_api_key' => 'sk-ant-test'])->assertOk();
        $this->assertSame(EngineEnum::ANTHROPIC, app(EngineManager::class)->current());
        $this->assertSame('hi from claude', app(Llm::class)->as('test')->chat('sys', 'hello'));
        Http::assertSent(fn ($r) => $r->url() === 'https://api.anthropic.com/v1/messages' && $r->hasHeader('x-api-key', 'sk-ant-test') && $r['model'] === 'claude-sonnet-5-5');

        $this->putJson('/api/settings', ['llm_engine' => 'gemini', 'llm_model' => 'gemini-2.5-pro'])->assertOk();
        $this->assertSame('hi from gemini', app(Llm::class)->chat('sys', 'hello'));
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions') && $r['model'] === 'gemini-2.5-pro');

        $this->assertSame(2, AiUsage::count());
        $this->assertSame(5, AiUsage::where('engine', 'anthropic')->value('input_tokens'));
        $this->getJson('/api/usage')->assertOk()->assertJsonPath('calls', 2);
        $this->getJson('/api/settings')->assertOk()->assertJsonPath('current_engine', 'gemini')->assertJsonPath('secrets.llm_api_key', true);
    }

    public function test_legacy_base_url_install_maps_to_engine(): void
    {
        Setting::put('llm_base_url', 'https://openrouter.ai/api/v1');
        $this->assertSame(EngineEnum::OPENROUTER, app(EngineManager::class)->current());
        Setting::put('llm_base_url', 'https://llm-gateway.assemblyai.com/v1');
        $this->assertSame(EngineEnum::ASSEMBLYAI, app(EngineManager::class)->current());
    }
}
