<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Week;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

/**
 * Score the weekly plan from her real uploads, then ask the mentor for a verdict.
 * Used by the Check in button and the Monday-morning schedule.
 */
class WeeklyCheckin
{
    public function __construct(private YouTube $yt, private Llm $llm) {}

    /** @return array{verdict: ?string} */
    public function run(): array
    {
        $weeks = Week::orderBy('number')->get();
        if ($weeks->isEmpty()) {
            throw new RuntimeException('Set up your weekly plan first.');
        }

        $channel = Setting::get('channel_id');
        if (! $channel) {
            throw new RuntimeException('Add your channel handle or ID in Settings.');
        }

        $tz = Setting::get('timezone', 'Europe/London');
        $today = CarbonImmutable::now($tz)->toDateString();

        try {
            $uploads = collect($this->yt->uploadsSince($channel, CarbonImmutable::parse($weeks->first()->start_date)->subDays(7)))
                ->where('long_form', true)->values();
        } catch (Throwable $e) {
            throw new RuntimeException("Couldn't fetch YouTube uploads: {$e->getMessage()}", 0, $e);
        }

        $claimed = $weeks->pluck('youtube_url')->filter()->all();
        foreach ($weeks as $week) {
            $start = $week->start_date->toDateString();
            $due = $week->due_date->toDateString();

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
            $nextLine = $next ? ($next->video_title ? "Next week's video: \"{$next->video_title}\"" : "Next week's video: NOT PICKED YET") : 'This was the final scheduled week.';
            $recent = $uploads->map(fn ($v) => "{$v['published']}: \"{$v['title']}\", {$v['views']} views after {$v['age_days']} days")->implode("\n");

            try {
                $verdict = $this->llm->as('mentor')->chat(Prompts::mentorSystem(), "Today: {$today}\nScoring week {$scoredWeek->number}.\n\nScoreboard:\n{$board}\n\n{$nextLine}\n\nRecent long-form uploads:\n".($recent ?: 'none'));
                $scoredWeek->update(['verdict' => $verdict]);
            } catch (Throwable) {
                // The scoreboard is updated even if the mentor call fails.
            }
        }

        return ['verdict' => $verdict];
    }
}
