<?php

namespace App\Http\Controllers;

use App\Models\Idea;
use App\Models\Script;
use App\Services\AiTellsLinter;
use App\Services\Llm;
use App\Services\Prompts;
use Illuminate\Http\Request;
use Throwable;

class ScriptController extends Controller
{
    public function index()
    {
        return view('scripts.index', ['scripts' => Script::with('idea')->latest('updated_at')->paginate(20)]);
    }

    /** Stage 2: create a script, written by AI from a picked idea, or blank. */
    public function store(Request $request, Llm $llm)
    {
        $data = $request->validate(['idea_id' => 'nullable|exists:ideas,id', 'title' => 'nullable|string|max:200', 'blank' => 'nullable|boolean']);
        $idea = isset($data['idea_id']) ? Idea::find($data['idea_id']) : null;
        $title = $data['title'] ?? $idea?->title ?? 'Untitled script';
        $body = '';

        if ($idea && ! $request->boolean('blank')) {
            try {
                $body = $llm->chat(Prompts::scriptSystem(), "The greenlit idea:\n\n".$idea->asMarkdown());
            } catch (Throwable $e) {
                return back()->withErrors(['ai' => $e->getMessage()]);
            }
        }
        $script = Script::create(['idea_id' => $idea?->id, 'title' => $title, 'body' => $body]);
        $idea?->update(['status' => 'scripted']);

        return redirect()->route('scripts.show', $script)->with('status', $body ? 'Draft written. Check the linter, then run the AI review.' : 'Blank script created.');
    }

    public function show(Script $script)
    {
        $findings = AiTellsLinter::lint((string) $script->body);

        return view('scripts.show', [
            'script' => $script,
            'findings' => $findings,
            'hard' => AiTellsLinter::hasHard($findings),
            'tab' => request('tab', 'script'),
        ]);
    }

    public function update(Request $request, Script $script)
    {
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:200',
            'body' => 'sometimes|nullable|string',
            'transcript' => 'sometimes|nullable|string',
            'substack' => 'sometimes|nullable|string',
            'edit_brief' => 'sometimes|nullable|string',
            'status' => 'sometimes|in:'.implode(',', Script::STATUSES),
            'youtube_url' => 'sometimes|nullable|url|max:300',
            'published_on' => 'sometimes|nullable|date',
        ]);
        $script->update($data);

        return back()->with('status', 'Saved.');
    }

    /** Stage 3: linter gate first (free), then one combined AI review call. */
    public function review(Script $script, Llm $llm)
    {
        $findings = AiTellsLinter::lint((string) $script->body);
        if (AiTellsLinter::hasHard($findings)) {
            return back()->withErrors(['ai' => 'Fix the red (HARD) linter findings first. The AI review only runs on a script that passes the free check.']);
        }
        $warns = collect($findings)->map(fn ($f) => "line {$f['line']}: {$f['rule']}: \"{$f['match']}\"")->implode("\n");
        $user = "The draft:\n\n{$script->body}".($warns ? "\n\nLinter warnings (hints, not blockers):\n{$warns}" : '');
        try {
            $review = $llm->chat(Prompts::reviewSystem(), $user);
        } catch (Throwable $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }
        $script->update(['review' => $review, 'status' => $script->status === 'draft' ? 'reviewed' : $script->status]);

        return redirect()->route('scripts.show', [$script, 'tab' => 'review'])->with('status', 'Review done. Apply the edits you agree with, then read it out loud once.');
    }

    /** Stage 4: Substack post from the real transcript (or the script if there's none yet). */
    public function substack(Script $script, Llm $llm)
    {
        $source = $script->transcript
            ? "Real transcript of the video:\n\n{$script->transcript}\n\nThe planned script, for reference:\n\n{$script->body}"
            : "No transcript yet, so work from the script:\n\n{$script->body}";
        try {
            $post = $llm->chat(Prompts::substackSystem(), "Video title: {$script->title}\n\n{$source}");
        } catch (Throwable $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }
        $script->update(['substack' => $post]);

        return redirect()->route('scripts.show', [$script, 'tab' => 'substack'])->with('status', 'Substack draft ready.');
    }

    public function editBrief(Script $script, Llm $llm)
    {
        try {
            $brief = $llm->chat(Prompts::editBriefSystem(), "Title: {$script->title}\n\n{$script->body}");
        } catch (Throwable $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }
        $script->update(['edit_brief' => $brief]);

        return redirect()->route('scripts.show', [$script, 'tab' => 'brief'])->with('status', 'Edit brief ready for your editor.');
    }

    public function lint(Request $request)
    {
        return response()->json(AiTellsLinter::lint((string) $request->input('text')));
    }

    public function destroy(Script $script)
    {
        $script->delete();

        return redirect()->route('scripts.index')->with('status', 'Script deleted.');
    }
}

