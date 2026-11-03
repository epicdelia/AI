<?php

namespace App\Http\Controllers\Api;

use App\Domains\Chat\Models\ChatMessage;
use App\Domains\Chat\Models\Conversation;
use App\Domains\Engine\Stream\StreamService;
use App\Domains\Memory\Memory;
use App\Domains\Search\WebSearch;
use App\Http\Controllers\Controller;
use App\Models\Idea;
use App\Models\Outlier;
use App\Models\Script;
use App\Models\Week;
use App\Services\Llm;
use App\Services\Prompts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Producer chat: a persistent conversation with an assistant that knows her channel.
 */
class ChatController extends Controller
{
    private const HISTORY = 30; // most recent messages sent back to the AI

    public function index(): JsonResponse
    {
        return response()->json([
            'conversations' => Conversation::withCount('messages')->orderByDesc('pinned')->latest('updated_at')->take(100)->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:200',
        ]);

        return response()->json([
            'conversation' => Conversation::create([
                'title' => $data['title'] ?? 'New chat',
            ]),
        ]);
    }

    public function show(Conversation $conversation): JsonResponse
    {
        return response()->json([
            'conversation' => $conversation,
            'messages' => $conversation->messages,
        ]);
    }

    public function update(Request $request, Conversation $conversation): JsonResponse
    {
        $conversation->update($request->validate([
            'title' => 'sometimes|required|string|max:200',
            'pinned' => 'sometimes|boolean',
        ]));

        return response()->json([
            'conversation' => $conversation,
        ]);
    }

    public function destroy(Conversation $conversation): JsonResponse
    {
        $conversation->delete();

        return response()->json([
            'message' => 'Chat deleted.',
        ]);
    }

    public function sendStream(Request $request, Conversation $conversation, Llm $llm, StreamService $sse): StreamedResponse
    {
        [$userMsg, $history] = $this->addUserMessage($request, $conversation);
        $web = $request->boolean('web');

        return $sse->respond(function (StreamService $sse) use ($llm, $history, $conversation, $userMsg, $web) {
            $sse->emit('message', (string)$userMsg->id);
            $system = $this->system($userMsg->content, $web, fn ($s) => $sse->emit('status', json_encode($s)));
            $reply = $llm->as($web ? 'chat:web' : 'chat')->stream($system, $history, $sse->chunk(...));
            $assistant = $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $reply,
            ]);

            return [
                'user_message' => $userMsg,
                'message' => $assistant,
                'conversation' => $conversation->fresh(),
            ];
        });
    }

    public function send(Request $request, Conversation $conversation, Llm $llm): JsonResponse
    {
        [$userMsg, $history] = $this->addUserMessage($request, $conversation);

        try {
            $reply = $llm->as('chat')->converse($this->system($userMsg->content, $request->boolean('web')), $history);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'user_message' => $userMsg,
                'conversation' => $conversation,
            ], 502);
        }

        $assistant = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $reply,
        ]);

        return response()->json([
            'user_message' => $userMsg,
            'message' => $assistant,
            'conversation' => $conversation,
        ]);
    }

    /**
     * The producer's system prompt: studio context, relevant past work from Memory, and
     * (when asked) live web results. The web part follows MagicAI's realtime chat: search
     * the user's message and hand the results to the model with instructions on using them.
     */
    private function system(string $question, bool $web, ?callable $onStatus = null): string
    {
        $context = $this->context();

        $past = app(Memory::class)->context($question, 3);
        if ($past !== '') {
            $context .= "\n\n## From her own past work (scripts, transcripts, saved notes; reference by title when useful)\n{$past}";
        }

        if ($web && WebSearch::available()) {
            $onStatus && $onStatus([
                'step' => 'searching',
            ]);

            try {
                $results = app(WebSearch::class)->search($question, 6);
                $context .= "\n\n## Live web search results for her latest message (" . now()->toDateString() . ")\n" . WebSearch::forPrompt($results)
                    . "\nUse these when they're relevant and cite them as markdown links. Never invent links. If they don't answer the question, say so.";
            } catch (Throwable $e) {
                $context .= "\n\n(Web search failed: {$e->getMessage()}. Say you couldn't search, and answer from what you know with that caveat.)";
            }
        }

        return Prompts::chatSystem($context);
    }

    /**
     * @return array{0: ChatMessage, 1: array} the saved user message and the history to send
     */
    private function addUserMessage(Request $request, Conversation $conversation): array
    {
        $content = trim((string)$request->validate([
            'content' => 'required|string|max:30000',
            'web' => 'nullable|boolean',
        ])['content']);

        $userMsg = $conversation->messages()->create([
            'role' => 'user',
            'content' => $content,
        ]);
        if ($conversation->title === 'New chat') {
            $conversation->title = mb_strimwidth(preg_replace('/\s+/', ' ', $content), 0, 60, '…');
        }
        $conversation->updated_at = now();
        $conversation->save();

        $history = $conversation->messages()->reorder()->orderByDesc('id')->take(self::HISTORY)->get()->reverse()
            ->map(fn ($m) => [
                'role' => $m->role,
                'content' => $m->content,
            ])->values()->all();

        return [$userMsg, $history];
    }

    /**
     * What's going on in the studio right now, so the producer doesn't have to ask.
     */
    private function context(): string
    {
        $lines = ['## Her studio right now', Prompts::profile()];
        if ($w = Week::current()) {
            $lines[] = "This week: week {$w->number}, due {$w->due_date->toDateString()}, status {$w->status}"
                . ($w->video_title ? ", video: \"{$w->video_title}\"" : ', video not picked yet');
        }
        $picked = Idea::where('status', 'picked')->latest()->take(5)->pluck('title');
        if ($picked->isNotEmpty()) {
            $lines[] = 'Picked ideas: ' . $picked->map(fn ($t) => "\"{$t}\"")->implode(', ');
        }
        $scripts = Script::latest('updated_at')->take(5)->get(['title', 'status']);
        if ($scripts->isNotEmpty()) {
            $lines[] = 'Scripts in progress: ' . $scripts->map(fn ($s) => "\"{$s->title}\" ({$s->status})")->implode(', ');
        }
        $outliers = Outlier::orderByDesc('outlier_score')->take(5)->get();
        if ($outliers->isNotEmpty()) {
            $lines[] = "Top breakouts in her niche:\n" . $outliers->map->compactLine()->implode("\n");
        }

        return implode("\n", $lines);
    }
}
