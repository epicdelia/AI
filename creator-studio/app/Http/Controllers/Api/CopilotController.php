<?php

namespace App\Http\Controllers\Api;

use App\Domains\Copilot\CopilotAgent;
use App\Domains\Copilot\CopilotTools;
use App\Domains\Copilot\Models\CopilotAction;
use App\Domains\Copilot\Models\CopilotFeedback;
use App\Domains\Copilot\Models\CopilotMessage;
use App\Domains\Copilot\Models\CopilotThread;
use App\Domains\Engine\Catalog\ModelCatalog;
use App\Domains\Engine\EngineKeys;
use App\Domains\Engine\EngineManager;
use App\Domains\Engine\Stream\StreamService;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** The Copilot panel: threads, projects, the streamed agent turn, proposals, feedback. */
class CopilotController extends Controller
{
    public const COMMANDS = [
        ['cmd' => '/rewrite', 'hint' => 'Rewrite the selection in my voice', 'prompt' => 'Rewrite the selected text in my voice: punchier, spoken, same meaning. Propose it as an edit.'],
        ['cmd' => '/shorten', 'hint' => 'Make it about half as long', 'prompt' => 'Make the selected text about half as long without losing anything important. Propose it as an edit.'],
        ['cmd' => '/explain', 'hint' => 'Explain or critique what I’m looking at', 'prompt' => 'Explain what works and what doesn’t in what I’m looking at, in a few bullets.'],
        ['cmd' => '/hook', 'hint' => 'Stronger cold open', 'prompt' => 'Write 3 stronger cold opens for this script (first 15 seconds, spoken, with [VISUAL] notes). Propose the best one as an edit to the opening.'],
        ['cmd' => '/titles', 'hint' => 'Title options', 'prompt' => 'Give me 5 title options for this video (tension, curiosity, outcome). Under 60 characters.'],
        ['cmd' => '/thumbnail', 'hint' => 'Thumbnail ideas', 'prompt' => 'Give me 3 thumbnail concepts for this video: text (3-5 words), expression, main visual.'],
        ['cmd' => '/factcheck', 'hint' => 'Find claims to verify', 'prompt' => 'List the factual claims in this script that I should verify before filming, and check the important ones on the web if you can.'],
        ['cmd' => '/idea', 'hint' => 'Save an idea', 'prompt' => 'Turn this into a video idea (title, premise, angle) and propose saving it.'],
        ['cmd' => '/plan', 'hint' => 'Plan my week', 'prompt' => 'Look at my studio and tell me the 3 most useful things to do this week to ship my video.'],
        ['cmd' => '/breakouts', 'hint' => 'What’s working right now', 'prompt' => 'What are the top breakouts in my niche telling me? Give me 3 ideas with my angle.'],
    ];

    public function meta(): JsonResponse
    {
        $engine = app(EngineManager::class)->current();
        $default = Setting::get('llm_model') ?: $engine->defaultModel();

        // Every provider she has a key for, with the full model catalog (ported from MagicAI's EntityEnum).
        $groups = collect(EngineKeys::available())->map(fn ($e) => [
            'engine' => $e->value,
            'label' => $e->label(),
            'current' => $e === $engine,
            'models' => collect(ModelCatalog::all($e))->reject(fn ($m) => $m[3])
                ->map(fn ($m, $id) => ['value' => "{$e->value}|{$id}", 'id' => $id, 'label' => $m[0], 'description' => $m[4] ?? $m[0], 'reasoning' => $m[1] !== [], 'vision' => $m[2]])
                ->values(),
        ])->values();

        return response()->json([
            'engine' => ['id' => $engine->value, 'label' => $engine->label()],
            'default_model' => "{$engine->value}|{$default}",
            'default_label' => ModelCatalog::find($engine, $default)['label'] ?? $default,
            'groups' => $groups,
            'commands' => self::COMMANDS,
            'projects' => CopilotThread::whereNotNull('project')->distinct()->orderBy('project')->pluck('project'),
            'max_chars' => 2000,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $q = CopilotThread::query()->withCount('messages')->latest('updated_at');
        if ($request->filled('project')) {
            $q->where('project', $request->query('project'));
        }

        return response()->json(['threads' => $q->take(60)->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        return response()->json(['thread' => CopilotThread::create($data + ['title' => 'New chat'])]);
    }

    public function show(CopilotThread $thread): JsonResponse
    {
        return response()->json([
            'thread' => $thread,
            'messages' => $thread->messages()->get()->map(fn (CopilotMessage $m) => self::present($m))->filter()->values(),
            'actions' => $thread->actions()->get(),
        ]);
    }

    public function update(Request $request, CopilotThread $thread): JsonResponse
    {
        $thread->update($this->validated($request) + $request->validate(['title' => 'sometimes|required|string|max:200']));

        return response()->json(['thread' => $thread]);
    }

    public function destroy(CopilotThread $thread): JsonResponse
    {
        $thread->delete();

        return response()->json(['message' => 'Chat deleted.']);
    }

    /** One user turn, streamed: status / tool / action / client / note / data / done events. */
    public function send(Request $request, CopilotThread $thread, CopilotAgent $agent, StreamService $sse): StreamedResponse
    {
        $data = $request->validate([
            'content' => 'required|string|max:2000',
            'mode' => 'nullable|in:agent,ask',
            'model' => 'nullable|string|max:120',
            'context' => 'nullable|array',
            'context.path' => 'nullable|string|max:200',
            'context.title' => 'nullable|string|max:200',
            'context.script_id' => 'nullable|integer',
            'context.selection' => 'nullable|string|max:20000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*.name' => 'required|string|max:200',
            'attachments.*.text' => 'required|string|max:200000',
        ]);
        $thread->update(array_filter(['mode' => $data['mode'] ?? null], fn ($v) => $v !== null) + ['model' => $data['model'] ?? $thread->model]);

        return $sse->respond(function (StreamService $sse) use ($agent, $thread, $data) {
            $r = $agent->run($thread->fresh(), $data['content'], $data['context'] ?? [], $data['attachments'] ?? [], $sse);

            return ['message' => self::present($r['message']), 'actions' => $r['actions'], 'thread' => $thread->fresh()];
        }, 600);
    }

    public function apply(CopilotAction $action, CopilotTools $tools): JsonResponse
    {
        if ($action->status !== 'pending') {
            return response()->json(['error' => "This change is already {$action->status}."], 409);
        }
        try {
            $result = $tools->apply($action);
        } catch (Throwable $e) {
            $action->update(['status' => 'failed', 'result' => ($action->result ?? []) + ['error' => $e->getMessage()]]);

            return response()->json(['error' => $e->getMessage(), 'action' => $action->fresh()], 422);
        }
        $action->update(['status' => 'applied', 'result' => ($action->result ?? []) + $result]);
        $this->note($action, "She applied change #{$action->id}: {$action->summary}.");

        return response()->json(['action' => $action->fresh(), 'message' => $result['message'] ?? 'Applied.', 'link' => $result['link'] ?? null, 'script' => $result['script'] ?? null]);
    }

    public function dismiss(CopilotAction $action): JsonResponse
    {
        if ($action->status === 'pending') {
            $action->update(['status' => 'dismissed']);
            $this->note($action, "She dismissed change #{$action->id}: {$action->summary}.");
        }

        return response()->json(['action' => $action->fresh()]);
    }

    public function undo(CopilotAction $action, CopilotTools $tools): JsonResponse
    {
        if ($action->status !== 'applied') {
            return response()->json(['error' => 'Only applied changes can be undone.'], 409);
        }
        try {
            $message = $tools->undo($action);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        $action->update(['status' => 'undone']);
        $this->note($action, "She undid change #{$action->id}: {$action->summary}.");

        return response()->json(['action' => $action->fresh(), 'message' => $message, 'script' => $action->tool === 'edit_script' ? \App\Models\Script::find($action->args['script_id']) : null]);
    }

    public function feedback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'thread_id' => 'nullable|exists:copilot_threads,id',
            'message_id' => 'nullable|exists:copilot_messages,id',
            'rating' => 'nullable|in:up,down',
            'text' => 'nullable|string|max:3000',
        ]);
        if (empty($data['rating']) && empty($data['text'])) {
            return response()->json(['error' => 'Write a few words or pick thumbs up or down.'], 422);
        }
        CopilotFeedback::create(['copilot_thread_id' => $data['thread_id'] ?? null, 'copilot_message_id' => $data['message_id'] ?? null,
            'rating' => $data['rating'] ?? null, 'text' => $data['text'] ?? null]);

        return response()->json(['message' => 'Thanks for the feedback.']);
    }

    /** Record apply/dismiss/undo in the transcript so the agent knows what happened. */
    private function note(CopilotAction $action, string $text): bool
    {
        CopilotMessage::create(['copilot_thread_id' => $action->copilot_thread_id, 'role' => 'user', 'content' => "[Studio note] {$text}", 'meta' => ['system_note' => true]]);

        return true;
    }

    private function validated(Request $request): array
    {
        return array_filter($request->validate([
            'project' => 'sometimes|nullable|string|max:80',
            'mode' => 'sometimes|in:agent,ask',
            'model' => 'sometimes|nullable|string|max:120',
        ]), fn ($v) => $v !== null) + ($request->has('project') && $request->input('project') === null ? ['project' => null] : []);
    }

    /** What the panel shows for a stored row (hides tool rows and studio notes). */
    public static function present(CopilotMessage $m): ?array
    {
        if ($m->role === 'tool' || ! empty($m->meta['system_note'])) {
            return null;
        }
        if ($m->role === 'assistant' && ! empty($m->meta['tool_calls'])) {
            return ['id' => $m->id, 'role' => 'steps', 'content' => $m->content,
                'steps' => array_map(fn ($c) => ['name' => $c['name'], 'label' => CopilotTools::label($c['name'], $c['args'])], $m->meta['tool_calls'])];
        }

        return ['id' => $m->id, 'role' => $m->role, 'content' => $m->role === 'user' ? ($m->meta['shown'] ?? $m->content) : $m->content,
            'attachments' => $m->meta['attachments'] ?? [], 'context' => $m->meta['context'] ?? null, 'created_at' => $m->created_at];
    }
}
