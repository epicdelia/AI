<?php

namespace App\Services;

use App\Models\Idea;
use App\Models\Script;
use App\Models\Setting;
use App\Models\Week;

/**
 * The one thing to do next for this week's video, so the dashboard (and the Copilot) can say
 * "fill the 2 [YOUR STORY] gaps" instead of just "2 days left". Walks the pipeline in order:
 * pick → draft → fix hard AI tells → fill gaps → review → film → publish → check in.
 */
class NextStep
{
    /** @return array{key: string, title: string, detail: string, action: string, link: string, script_id: ?int}|null */
    public static function current(): ?array
    {
        $week = Week::current()?->load('script');
        if ($week && $week->status === 'shipped') {
            $next = Week::where('number', $week->number + 1)->first();

            return $next && ! $next->video_title
                ? self::step('pick', "Week {$week->number} shipped. Pick next week’s video", 'Choose it now, while the momentum’s there.', 'Open Ideas', '/ideas')
                : null;
        }

        // This week's script, or failing that the one she's been working on.
        $script = $week?->script ?? Script::whereIn('status', ['draft', 'reviewed', 'filmed'])->latest('updated_at')->first();
        $due = $week ? self::due($week) : '';

        if (! $script) {
            return Idea::where('status', 'picked')->exists()
                ? self::step('pick', 'Pick this week’s video and draft it', trim("You have picked ideas waiting. {$due}"), 'Open Ideas', '/ideas')
                : self::step('ideas', 'Get ideas for this week’s video', trim("Nothing is picked yet. {$due}"), 'Get fresh ideas', '/ideas');
        }

        $body = (string) $script->body;
        $link = "/scripts/{$script->id}";
        $name = "“{$script->title}”";

        if ($script->status === 'published') {
            return $week && $week->status !== 'shipped'
                ? self::step('checkin', "{$name} is published. Score the week", 'Run the check-in so it counts on your scoreboard.', 'Open Weekly goals', '/goals', $script->id)
                : null;
        }
        if (str_word_count($body) < 150) {
            return self::step('draft', "Write the draft of {$name}", trim("Dictate it, use the script wizard, or start typing. {$due}"), 'Open the script', $link, $script->id);
        }

        $hard = collect(AiTellsLinter::lint($body))->where('severity', AiTellsLinter::HARD)->count();
        if ($hard) {
            return self::step('lint', "Fix {$hard} hard AI tell(s) in {$name}", 'Em dashes and banned phrases block the review. They’re listed next to the editor.', 'Fix them', $link, $script->id);
        }

        if ($script->status === 'draft') {
            return self::step('review', "Run the AI review on {$name}", trim("Voice, retention, facts and hostile comments, as line edits. {$due}"), 'Review it', "{$link}?tab=review", $script->id);
        }

        $gaps = ScriptStructure::analyse($body)['placeholders'];
        if ($gaps['story'] + $gaps['verify'] > 0 && $script->status === 'reviewed') {
            $what = implode(' and ', array_filter([$gaps['story'] ? "{$gaps['story']} [YOUR STORY]" : null, $gaps['verify'] ? "{$gaps['verify']} [VERIFY]" : null]));

            return self::step('gaps', "Fill {$what} in {$name}", 'Only you can write those. Then it’s ready to film.', 'Fill them', $link, $script->id);
        }
        if ($script->status === 'reviewed') {
            return self::step('film', "Film {$name}", trim('About '.ScriptStructure::clock(ScriptStructure::analyse($body)['total_seconds'])." to read. The teleprompter is next to the script’s tabs. {$due}"), 'Open the script', $link, $script->id);
        }

        // Filmed.
        return self::step('publish', "Package and publish {$name}", trim("Titles, thumbnail and description are on the Package tab; add the link on Publish. {$due}"), 'Package it', "{$link}?tab=package", $script->id);
    }

    private static function due(Week $week): string
    {
        $days = (int) now(Setting::get('timezone'))->startOfDay()->diffInDays($week->due_date->copy()->startOfDay(), false);

        return match (true) {
            $days > 1 => "Due in {$days} days.",
            $days === 1 => 'Due tomorrow.',
            $days === 0 => 'Due today.',
            default => 'Past due by '.abs($days).' day(s).',
        };
    }

    private static function step(string $key, string $title, string $detail, string $action, string $link, ?int $scriptId = null): array
    {
        return ['key' => $key, 'title' => $title, 'detail' => $detail, 'action' => $action, 'link' => $link, 'script_id' => $scriptId];
    }
}
