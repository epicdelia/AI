<?php

namespace App\Http\Controllers\Api;

use App\Domains\Engine\Stream\StreamService;
use App\Domains\Memory\Memory;
use App\Domains\Search\FactChecker;
use App\Domains\Search\WebSearch;
use App\Http\Controllers\Controller;
use App\Models\Script;
use App\Models\Setting;
use App\Services\AiTellsLinter;
use App\Services\ChapterAligner;
use Illuminate\Support\Facades\Cache;
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
        $plan = $script->outline;
        if (! $idea && ! $plan) {
            return response()->json(['error' => 'This script has no idea or plan to draft from.'], 422);
        }
        if (trim((string) $script->body) !== '') {
            return response()->json(['error' => 'This script already has text. Clear it first to draft a new version.'], 422);
        }

        // Memory: how she actually talked about related topics before, and what not to repeat.
        $about = $idea ? $idea->title.' '.$idea->premise : $script->title.' '.($plan['topic'] ?? '');
        $past = $memory->context($about, 3, ['transcript', 'script'], [['script', 'transcript'], $script->id]);
        $user = ($idea ? "The greenlit video idea:\n\n".$idea->asMarkdown() : "The video: {$script->title}")
            .($plan ? "\n\n".ScriptWizardController::planText($plan) : '')
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

    /** Real chapter times (no AI): from the timestamped transcript, or estimated from the script. */
    public function chapters(Script $script): JsonResponse
    {
        return response()->json(ChapterAligner::align((string) $script->body, $script->transcript));
    }

    /** Title, description with chapters, tags, pinned comment and end screen, ready to paste into YouTube Studio. */
    public function publishKit(Script $script, Llm $llm, StreamService $sse): JsonResponse|StreamedResponse
    {
        if (trim((string) $script->body) === '' && trim((string) $script->transcript) === '') {
            return response()->json(['error' => 'Write the script (or add the transcript) first.'], 422);
        }
        $chapters = ChapterAligner::align((string) $script->body, $script->transcript);
        $channel = Setting::get('channel_id');
        $report = $channel ? Cache::get(AudienceController::cacheKey($channel)) : null;
        $own = collect($report['videos'] ?? [])->where('early', false)->whereNotNull('score')->sortByDesc('score')->take(8)
            ->map(fn ($v) => "- \"{$v['title']}\" ({$v['url']}), {$v['score']}x her usual")->implode("\n");
        $source = $script->transcript ? "What she said on camera:\n".mb_substr($script->transcript, 0, 12000) : "The script:\n".mb_substr((string) $script->body, 0, 12000);
        $user = "Title: {$script->title}\n\n"
            .($chapters['chapters'] ? 'Chapters ('.($chapters['source'] === 'transcript' ? 'times from the recording' : 'estimated from the script; tell her to check them').", paste exactly):\n{$chapters['chapters']}\n\n" : "No chapters (too few sections).\n\n")
            .($own ? "Her own videos that did well:\n{$own}\n\n" : '')
            .$source;

        return $sse->respond(function (StreamService $sse) use ($script, $llm, $user) {
            $kit = $llm->as('publish_kit')->stream(Prompts::publishKitSystem(), [['role' => 'user', 'content' => $user]], $sse->chunk(...));
            $script->update(['publish_kit' => $kit]);

            return ['publish_kit' => $kit];
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
