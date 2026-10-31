<?php

namespace App\Http\Controllers;

use App\Models\Idea;
use App\Models\Script;
use App\Models\Setting;
use App\Models\Week;
use App\Services\Llm;
use App\Services\Prompts;
use App\Services\YouTube;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Throwable;

/** The weekly commitment scoreboard and mentor check-in (MENTOR.md + channel_stats.py). */
class WeekController extends Controller
{
    public function index()
    {
        return view('weeks', [
            'weeks' => Week::with('script')->orderBy('number')->get(),
            'current' => Week::current(),
            'scripts' => Script::latest()->take(30)->get(),
            'pickedIdeas' => Idea::whereIn('status', ['picked', 'scripted'])->latest()->take(10)->get(),
        ]);
    }

    /** Lay out N weeks starting on a given Monday. Replaces the plan only if asked to. */
    public function plan(Request $request)
    {
        $data = $request->validate([
            'start' => 'required|date',
            'count' => 'required|integer|min:1|max:52',
            'replace' => 'nullable|boolean',
        ]);
        if (Week::exists() && ! $request->boolean('replace')) {
            return back()->withErrors(['plan' => 'You already have a plan. Tick "replace my current plan" to start over.']);
        }
        Week::query()->delete();
        $start = CarbonImmutable::parse($data['start']);
        for ($i = 0; $i < $data['count']; $i++) {
            Week::create([
                'number' => $i + 1,
                'start_date' => $start->addWeeks($i),
                'due_date' => $start->addWeeks($i)->addDays(6),
            ]);
        }

        return back()->with('status', "Your {$data['count']}-week plan is set. One video a week. You've got this.");
    }

    public function update(Request $request, Week $week)
    {
        $data = $request->validate([
            'status' => 'sometimes|in:'.implode(',', Week::STATUSES),
            'script_id' => 'sometimes|nullable|exists:scripts,id',
            'video_title' => 'sometimes|nullable|string|max:200',
            'youtube_url' => 'sometimes|nullable|url|max:300',
            'published_on' => 'sometimes|nullable|date',
            'views_7d' => 'sometimes|nullable|integer|min:0',
            'note' => 'sometimes|nullable|string|max:2000',
        ]);
        if (! empty($data['script_id']) && empty($data['video_title'])) {
            $data['video_title'] = Script::find($data['script_id'])->title;
        }
        $week->update($data);

        return back()->with('status', "Week {$week->number} updated.");
    }

    /** Weekly check-in: pull her uploads, score past weeks, then ask the mentor for a verdict. */
    public function checkin(YouTube $yt, Llm $llm)
    {
        $weeks = Week::orderBy('number')->get();
        if ($weeks->isEmpty()) {
            return back()->withErrors(['checkin' => 'Set up your weekly plan first.']);
        }
        $channel = Setting::get('channel_id');
        if (! $channel) {
            return back()->withErrors(['checkin' => 'Add your channel ID or handle in Settings first.']);
        }
        $tz = Setting::get('timezone');
        $today = CarbonImmutable::now($tz)->toDateString();

        try {
            $uploads = collect($yt->uploadsSince($channel, CarbonImmutable::parse($weeks->first()->start_date)->subDays(7)))
                ->where('long_form', true)->values();
        } catch (Throwable $e) {
            return back()->withErrors(['checkin' => "Couldn't fetch your uploads, so nothing was scored: {$e->getMessage()}"]);
        }

        $claimed = $weeks->pluck('youtube_url')->filter()->all();
        foreach ($weeks as $week) {
            $start = $week->start_date->toDateString();
            $due = $week->due_date->toDateString();
            // Shipped = a long-form video went public inside the week (in her timezone).
            $video = $uploads->first(function ($v) use ($start, $due, $tz, $claimed, $week) {
                $day = $v['published_at']->setTimezone($tz)->toDateString();

                return $day >= $start && $day <= $due && (! in_array($v['url'], $claimed, true) || $v['url'] === $week->youtube_url);
            });
            if ($video && in_array($week->status, ['pending', 'missed'], true)) {
                $week->fill([
                    'status' => 'shipped',
                    'video_title' => $week->video_title ?: $video['title'],
                    'youtube_url' => $video['url'],
                    'published_on' => $video['published'],
                ]);
                $claimed[] = $video['url'];
            } elseif (! $video && $week->status === 'pending' && $due < $today) {
                $week->status = 'missed';
            }
            // Views at 7 days: recorded the first time a check-in sees the video at 7+ days old.
            if ($week->youtube_url && $week->views_7d === null) {
                $match = $uploads->firstWhere('url', $week->youtube_url);
                if ($match && $match['age_days'] >= 7) {
                    $week->views_7d = $match['views'];
                }
            }
            $week->save();
        }

        $verdict = null;
        $scoredWeek = $weeks->filter(fn ($w) => $w->status !== 'pending')->sortByDesc('number')->first();
        if ($scoredWeek && Llm::configured()) {
            $next = $weeks->firstWhere('number', $scoredWeek->number + 1);
            $board = $weeks->map(fn ($w) => "Week {$w->number} ({$w->start_date->format('M j')} to {$w->due_date->format('M j')}): {$w->status}"
                .($w->video_title ? ", \"{$w->video_title}\"" : '')
                .($w->views_7d !== null ? ", {$w->views_7d} views at 7 days" : '')
                .($w->note ? ", her note: {$w->note}" : ''))->implode("\n");
            $nextLine = $next ? ($next->video_title ? "Next week's video: \"{$next->video_title}\"" : "Next week's video: NOT PICKED YET") : 'This was the last week of the plan.';
            $recent = $uploads->map(fn ($v) => "{$v['published']}: \"{$v['title']}\", {$v['views']} views after {$v['age_days']} days")->implode("\n");
            try {
                $verdict = $llm->chat(Prompts::mentorSystem(), "Today: {$today}\nScoring week {$scoredWeek->number}.\n\nScoreboard:\n{$board}\n\n{$nextLine}\n\nHer recent long-form uploads:\n".($recent ?: 'none'));
                $scoredWeek->update(['verdict' => $verdict]);
            } catch (Throwable $e) {
                return back()->with('status', 'Scoreboard updated.')->withErrors(['checkin' => "The mentor verdict failed: {$e->getMessage()}"]);
            }
        }

        return back()->with('status', 'Check-in done. Scoreboard updated'.($verdict ? ' and your mentor left a verdict.' : '.'));
    }
}
