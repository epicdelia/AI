<?php

namespace App\Http\Controllers;

use App\Models\Idea;
use App\Models\IdeaBrief;
use App\Models\Outlier;
use App\Services\Llm;
use App\Services\Prompts;
use Illuminate\Http\Request;
use Throwable;

class IdeaController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $ideas = Idea::query()
            ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->whereIn('status', ['new', 'picked']))
            ->orderByRaw("CASE status WHEN 'picked' THEN 0 WHEN 'new' THEN 1 ELSE 2 END")
            ->latest()->paginate(20)->withQueryString();

        return view('ideas.index', [
            'ideas' => $ideas,
            'status' => $status,
            'latestBrief' => IdeaBrief::latest()->first(),
            'freshSignals' => Outlier::where('used', false)->count(),
            'counts' => Idea::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    /** Stage 1: turn fresh breakout signals into 5 ideas (prompts/01_daily_ideas.md). */
    public function generate(Request $request, Llm $llm)
    {
        $signals = Outlier::where('used', false)->orderByDesc('outlier_score')->take(10)->get();
        $extra = trim((string) $request->input('extra'));
        $user = $signals->isEmpty()
            ? "No breakout signals today.\n"
            : "Long-form breakouts (competitor videos at 3x+ their channel median), one per line:\n".$signals->map->compactLine()->implode("\n")."\n";
        if ($extra !== '') {
            $user .= "\nExtra signals or thoughts from her (treat as a strong signal):\n{$extra}\n";
        }
        $recent = Idea::latest()->take(15)->pluck('title');
        if ($recent->isNotEmpty()) {
            $user .= "\nAvoid repeating these recent ideas:\n- ".$recent->implode("\n- ");
        }

        try {
            $data = $llm->json(Prompts::ideasSystem(), $user);
        } catch (Throwable $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }

        $brief = IdeaBrief::create(['summary' => $data['summary'] ?? null, 'signals' => $signals->map->compactLine()->implode("\n")]);
        $made = 0;
        foreach (array_slice($data['ideas'] ?? [], 0, 5) as $i) {
            if (empty($i['title'])) {
                continue;
            }
            $brief->ideas()->create(collect($i)->only([
                'title', 'thumbnail_text', 'premise', 'why_now', 'source_url', 'angle', 'format', 'effort', 'shelf_life', 'risk',
            ])->map(fn ($v) => is_scalar($v) ? mb_substr((string) $v, 0, 2000) : null)->all());
            $made++;
        }
        Outlier::whereIn('id', $signals->pluck('id'))->update(['used' => true]);

        return redirect()->route('ideas.index')->with('status', "{$made} new ideas are ready. Pick the ones you'd film.");
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'premise' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:5000',
            'source_url' => 'nullable|url|max:500',
        ]);
        Idea::create($data + ['status' => 'picked']);

        return back()->with('status', 'Idea saved to your picked list.');
    }

    public function update(Request $request, Idea $idea)
    {
        $data = $request->validate([
            'status' => 'nullable|in:'.implode(',', Idea::STATUSES),
            'title' => 'nullable|string|max:200',
            'notes' => 'nullable|string|max:5000',
        ]);
        $idea->update(array_filter($data, fn ($v) => $v !== null));

        return back();
    }

    public function destroy(Idea $idea)
    {
        $idea->delete();

        return back()->with('status', 'Idea deleted.');
    }
}

