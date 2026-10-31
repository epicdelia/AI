<?php

namespace App\Http\Controllers\Api;

use App\Domains\Engine\Stream\StreamService;
use App\Domains\Memory\Memory;
use App\Domains\Search\FactChecker;
use App\Domains\Search\WebSearch;
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
    public function draft(Script $script, Llm $llm, StreamService $sse, Memory $memory): JsonResponse|StreamedResponse
    {
        $idea = $script->idea;
        if (! $idea) {
            return response()->json(['error' => 'This script has no idea attached to draft from.'], 422);
        }
        if (trim((string) $script->body) !== '') {
            return response()->json(['error' => 'This script already has text. Clear it first to draft a new version.'], 422);
        }

        // Memory: how she actually talked about related topics before, and what not to repeat.
        $past = $memory->context($idea->title.' '.$idea->premise, 3, ['transcript', 'script'], [['script', 'transcript'], $script->id]);
        $user = "The greenlit video idea:\n\n".$idea->asMarkdown()
            .($past ? "\n\nFrom her past videos on related topics (match her real phrasing; don't reuse the same stories, jokes or examples):\n\n{$past}" : '');

        return $sse->respond(function (StreamService $sse) use ($script, $user, $llm) {
            $body = $llm->as('script')->stream(Prompts::scriptSystem(), [['role' => 'user', 'content' => $user]], $sse->chunk(...));
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

    /** Check the script's claims against live web results (Serper or Perplexity). */
    public function factCheck(Script $script, FactChecker $checker, StreamService $sse): JsonResponse|StreamedResponse
    {
        if (! WebSearch::available()) {
            return response()->json(['error' => 'Fact-checking needs web search. Add a Serper key (free tier) or a Perplexity key in Settings.'], 422);
        }
        if (trim((string) $script->body) === '') {
            return response()->json(['error' => 'Write the script first.'], 422);
        }

        return $sse->respond(function (StreamService $sse) use ($script, $checker) {
            $result = $checker->run(
                $script->body,
                fn (array $status) => $sse->emit('status', json_encode($status, JSON_UNESCAPED_UNICODE)),
                $sse->chunk(...),
            );
            $script->update(['fact_check' => $result['report'], 'fact_checked_at' => now()]);

            return ['fact_check' => $result['report'], 'claims' => count($result['claims']), 'sources' => $result['sources'], 'script' => $script->fresh()];
        }, 600);
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
