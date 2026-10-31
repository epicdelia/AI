<?php

namespace App\Http\Controllers\Api;

use App\Domains\Engine\Stream\StreamService;
use App\Http\Controllers\Controller;
use App\Models\Script;
use App\Services\AiTellsLinter;
use App\Services\Llm;
use App\Services\Prompts;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streaming versions of the script AI actions (same prompts as ApiController), so the
 * draft, review, Substack post and edit brief appear as they're written.
 */
class ScriptStreamController extends Controller
{
    /** Draft the body from the script's idea, straight into the editor. */
    public function draft(Script $script, Llm $llm, StreamService $sse): JsonResponse|StreamedResponse
    {
        $idea = $script->idea;
        if (! $idea) {
            return response()->json(['error' => 'This script has no idea attached to draft from.'], 422);
        }
        if (trim((string) $script->body) !== '') {
            return response()->json(['error' => 'This script already has text. Clear it first to draft a new version.'], 422);
        }

        return $sse->respond(function (StreamService $sse) use ($script, $idea, $llm) {
            $body = $llm->as('script')->stream(Prompts::scriptSystem(),
                [['role' => 'user', 'content' => "The greenlit video idea:\n\n".$idea->asMarkdown()]], $sse->chunk(...));
            $script->update(['body' => $body]);

            return ['script' => $script->fresh(), 'findings' => AiTellsLinter::lint($body)];
        });
    }

    public function review(Script $script, Llm $llm, StreamService $sse): JsonResponse|StreamedResponse
    {
        $findings = AiTellsLinter::lint((string) $script->body);
        if (AiTellsLinter::hasHard($findings)) {
            return response()->json([
                'error' => 'Please resolve HARD linter errors (like banned AI cliches or em-dashes) before running AI review.',
                'findings' => $findings,
            ], 422);
        }
        $warns = collect($findings)->map(fn ($f) => "line {$f['line']}: {$f['rule']}: \"{$f['match']}\"")->implode("\n");
        $user = "Draft script:\n\n{$script->body}".($warns ? "\n\nLinter warnings (hints):\n{$warns}" : '');

        return $sse->respond(function (StreamService $sse) use ($script, $llm, $user) {
            $review = $llm->as('review')->stream(Prompts::reviewSystem(), [['role' => 'user', 'content' => $user]], $sse->chunk(...));
            $script->update(['review' => $review, 'status' => $script->status === 'draft' ? 'reviewed' : $script->status]);

            return ['review' => $review, 'script' => $script->fresh()];
        });
    }

    public function substack(Script $script, Llm $llm, StreamService $sse): StreamedResponse
    {
        $source = $script->transcript
            ? "Real recording transcript:\n\n{$script->transcript}\n\nPlanned script:\n\n{$script->body}"
            : "Planned script:\n\n{$script->body}";

        return $sse->respond(function (StreamService $sse) use ($script, $llm, $source) {
            $post = $llm->as('substack')->stream(Prompts::substackSystem(),
                [['role' => 'user', 'content' => "Video title: {$script->title}\n\n{$source}"]], $sse->chunk(...));
            $script->update(['substack' => $post]);

            return ['substack' => $post];
        });
    }

    public function editBrief(Script $script, Llm $llm, StreamService $sse): StreamedResponse
    {
        return $sse->respond(function (StreamService $sse) use ($script, $llm) {
            $brief = $llm->as('edit_brief')->stream(Prompts::editBriefSystem(),
                [['role' => 'user', 'content' => "Title: {$script->title}\n\n{$script->body}"]], $sse->chunk(...));
            $script->update(['edit_brief' => $brief]);

            return ['edit_brief' => $brief];
        });
    }
}
