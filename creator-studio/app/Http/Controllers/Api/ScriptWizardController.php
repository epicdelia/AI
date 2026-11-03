<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Idea;
use App\Models\Script;
use App\Services\Llm;
use App\Services\Prompts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Step-by-step script planning, ported from MagicAI's AIArticleWizardController
 * (generateTitles → generateOutlines → generateArticle), reshaped for YouTube:
 * pick a title, pick and edit a chapter plan, then the draft streams into the editor
 * following that plan (ScriptStreamController::draft reads scripts.outline).
 */
class ScriptWizardController extends Controller
{
    public function titles(Request $request, Llm $llm): JsonResponse
    {
        $data = $request->validate([
            'idea_id' => 'nullable|exists:ideas,id',
            'topic' => 'nullable|string|max:3000',
            'count' => 'nullable|integer|min:3|max:8',
        ]);
        $about = $this->about($data);
        if ($about === '') {
            return response()->json([
                'error' => 'Pick an idea or describe the video first.',
            ], 422);
        }
        $count = $data['count'] ?? 5;

        try {
            $out = $llm->as('wizard:titles')->json(
                'You title YouTube videos for ' . Prompts::name() . ".\n\n" . Prompts::profile() . "\n\n"
                . "Write {$count} title options, each under 60 characters, mixing tension, curiosity and outcome styles. "
                . "No clickbait the video can't pay off; no em dashes.\n"
                . 'JSON: {"titles": [{"title": "...", "style": "tension|curiosity|outcome", "why": "one short reason it would get clicked"}]}',
                "The video:\n{$about}"
            );
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'titles' => collect($out['titles'] ?? [])->filter(fn ($t) => !empty($t['title']))
                ->map(fn ($t) => [
                    'title' => mb_substr((string)$t['title'], 0, 120),
                    'style' => $t['style'] ?? null,
                    'why' => $t['why'] ?? null,
                ])
                ->take(8)->values(),
        ]);
    }

    public function outlines(Request $request, Llm $llm): JsonResponse
    {
        $data = $request->validate([
            'idea_id' => 'nullable|exists:ideas,id',
            'topic' => 'nullable|string|max:3000',
            'title' => 'required|string|max:200',
            'minutes' => 'nullable|integer|min:3|max:40',
            'count' => 'nullable|integer|min:1|max:4',
        ]);
        $minutes = $data['minutes'] ?? 10;
        $count = $data['count'] ?? 3;

        try {
            $out = $llm->as('wizard:outlines')->json(
                'You plan YouTube videos for ' . Prompts::name() . ".\n\n" . Prompts::profile() . "\n\n"
                . "Write {$count} genuinely different chapter plans for a {$minutes}-minute video (different structures, not reworded copies). "
                . 'Each starts with a cold open that pays off the title in the first 30 seconds and ends with a payoff and short CTA; '
                . "3 to 6 body chapters, each with a one-line beat that says what happens and the open loop into the next.\n"
                . 'JSON: {"outlines": [{"name": "short label for the structure", "chapters": [{"title": "chapter title", "beat": "what happens"}]}]}',
                "Title: {$data['title']}\nThe video:\n" . $this->about($data)
            );
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 502);
        }

        $outlines = collect($out['outlines'] ?? [])->map(fn ($o) => [
            'name' => mb_substr((string)($o['name'] ?? 'Plan'), 0, 80),
            'chapters' => collect($o['chapters'] ?? [])->filter(fn ($c) => !empty($c['title']))
                ->map(fn ($c) => [
                    'title' => mb_substr((string)$c['title'], 0, 120),
                    'beat' => mb_substr((string)($c['beat'] ?? ''), 0, 400),
                ])
                ->take(10)->values()->all(),
        ])->filter(fn ($o) => count($o['chapters']) > 1)->take(4)->values();

        return response()->json([
            'outlines' => $outlines,
        ]);
    }

    /**
     * Save the approved plan as a new script; the editor then streams the draft.
     */
    public function create(Request $request): JsonResponse
    {
        $data = $request->validate([
            'idea_id' => 'nullable|exists:ideas,id',
            'topic' => 'nullable|string|max:3000',
            'title' => 'required|string|max:200',
            'minutes' => 'nullable|integer|min:3|max:40',
            'chapters' => 'required|array|min:2|max:12',
            'chapters.*.title' => 'required|string|max:120',
            'chapters.*.beat' => 'nullable|string|max:400',
        ]);

        $script = Script::create([
            'idea_id' => $data['idea_id'] ?? null,
            'title' => $data['title'],
            'body' => '',
            'outline' => [
                'topic' => $data['topic'] ?? null,
                'title' => $data['title'],
                'minutes' => $data['minutes'] ?? 10,
                'chapters' => array_values(array_map(fn ($c) => [
                    'title' => $c['title'],
                    'beat' => $c['beat'] ?? '',
                ], $data['chapters'])),
            ],
        ]);
        if (!empty($data['idea_id'])) {
            Idea::whereKey($data['idea_id'])->update([
                'status' => 'scripted',
            ]);
        }

        return response()->json([
            'script' => $script,
            'message' => 'Plan saved. Writing the draft…',
        ]);
    }

    /**
     * The approved plan as prompt text (used by the streamed draft).
     */
    public static function planText(array $outline): string
    {
        $minutes = (int)($outline['minutes'] ?? 10);
        $lines = ["Approved title: {$outline['title']}", "Target length: about {$minutes} minutes (~" . ($minutes * 150) . ' spoken words).', 'Approved chapter plan (follow it in this order):'];
        foreach ($outline['chapters'] ?? [] as $i => $c) {
            $lines[] = ($i + 1) . ". {$c['title']}" . (!empty($c['beat']) ? ": {$c['beat']}" : '');
        }
        if (!empty($outline['topic'])) {
            $lines[] = "\nWhat she said about the video: {$outline['topic']}";
        }

        return implode("\n", $lines);
    }

    private function about(array $data): string
    {
        $idea = !empty($data['idea_id']) ? Idea::find($data['idea_id']) : null;

        return trim(($idea ? $idea->asMarkdown() : '') . "\n" . ($data['topic'] ?? ''));
    }
}
