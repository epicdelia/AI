<?php

namespace App\Services;

use App\Domains\Deals\FollowUps;
use App\Domains\Social\Models\SocialPost;
use App\Models\Deal;
use App\Models\Idea;
use App\Models\Script;
use App\Models\Setting;
use App\Models\Week;
use Carbon\CarbonImmutable;

/**
 * The one thing to do next for this week's video and high-priority studio tasks,
 * so the dashboard (and the Copilot) can say "fill the 2 [YOUR STORY] gaps" or
 * flag urgent brand deliverables. Walks the priority pipeline:
 * urgent brand deliverable → a lead or invoice gone quiet → script pipeline → agent approvals.
 */
class NextStep
{
    /**
     * @return array{key: string, title: string, detail: string, action: string, link: string, script_id: ?int}|null
     */
    public static function current(): ?array
    {
        // 1. Highest financial urgency: Urgent brand sponsorship deliverables due in <= 3 days or overdue
        $urgentDeal = self::urgentDeal();
        if ($urgentDeal) {
            return $urgentDeal;
        }

        // 2. Money waiting on her: a website lead, proposal or invoice that's gone quiet.
        if ($followUp = self::followUp()) {
            return $followUp;
        }

        $week = Week::current()?->load('script');
        if ($week && $week->status === 'shipped') {
            $next = Week::where('number', $week->number + 1)->first();

            if ($next && !$next->video_title) {
                return self::step('pick', "Week {$week->number} shipped. Pick next week’s video", 'Choose it now, while the momentum’s there.', 'Open Ideas', '/ideas');
            }

            return self::pendingAgentDraft();
        }

        // This week's script, or failing that the one she's been working on.
        $script = $week?->script ?? Script::whereIn('status', ['draft', 'reviewed', 'filmed'])->latest('updated_at')->first();
        $due = $week ? self::due($week) : '';

        if (!$script) {
            if (Idea::where('status', 'picked')->exists()) {
                return self::step('pick', 'Pick this week’s video and draft it', trim("You have picked ideas waiting. {$due}"), 'Open Ideas', '/ideas');
            }

            if (Week::exists() || !Idea::exists()) {
                return self::step('ideas', 'Get ideas for this week’s video', trim("Nothing is picked yet. {$due}"), 'Get fresh ideas', '/ideas');
            }

            return self::pendingAgentDraft();
        }

        $body = (string)$script->body;
        $link = "/scripts/{$script->id}";
        $name = "“{$script->title}”";

        if ($script->status === 'published') {
            return $week && $week->status !== 'shipped'
                ? self::step('checkin', "{$name} is published. Score the week", 'Run the check-in so it counts on your scoreboard.', 'Open Weekly goals', '/goals', $script->id)
                : self::pendingAgentDraft();
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
            return self::step('film', "Film {$name}", trim('About ' . ScriptStructure::clock(ScriptStructure::analyse($body)['total_seconds']) . " to read. The teleprompter is next to the script’s tabs. {$due}"), 'Open the script', $link, $script->id);
        }

        // Filmed.
        return self::step('publish', "Package and publish {$name}", trim("Titles, thumbnail and description are on the Package tab; add the link on Publish. {$due}"), 'Package it', "{$link}?tab=package", $script->id);
    }

    private static function urgentDeal(): ?array
    {
        $deals = Deal::whereIn('stage', ['contracted', 'production', 'in_review'])->get();

        foreach ($deals as $deal) {
            if (!is_array($deal->deliverables)) {
                continue;
            }

            foreach ($deal->deliverables as $deliv) {
                if (($deliv['status'] ?? 'pending') === 'done' || empty($deliv['due_date'])) {
                    continue;
                }

                $dueDate = rescue(fn () => CarbonImmutable::parse($deliv['due_date']), null);
                if (!$dueDate) {
                    continue;
                }

                $days = (int)now(Setting::get('timezone'))->startOfDay()->diffInDays($dueDate->startOfDay(), false);
                if ($days <= 3) {
                    $platform = ucfirst($deliv['platform'] ?? 'video');
                    $format = $deliv['format'] ?? 'deliverable';
                    $when = match (true) {
                        $days > 1 => "Due in {$days} days.",
                        $days === 1 => 'Due tomorrow.',
                        $days === 0 => 'Due today.',
                        default => 'Past due by ' . abs($days) . ' day(s).',
                    };

                    $link = $deal->script_id ? "/scripts/{$deal->script_id}" : '/deals';

                    return self::step(
                        'deal_deliverable',
                        "Sponsorship: deliver {$deal->brand_name} ({$platform} {$format})",
                        "Sponsored deliverable {$when} Key talking points and guidelines are in Deals.",
                        $deal->script_id ? 'Open the script' : 'Open Deal',
                        $link,
                        $deal->script_id
                    );
                }
            }
        }

        return null;
    }

    private static function followUp(): ?array
    {
        $due = rescue(fn () => FollowUps::due(), collect(), false);
        $top = $due->first();
        if (!$top) {
            return null;
        }
        $deal = $top['deal'];
        $more = $due->count() > 1 ? ' ' . ($due->count() - 1) . ' more in Deals.' : '';
        $quiet = $top['kind'] === 'kit_opened' ? "{$top['reason']}." : "{$top['reason']}: {$top['days']} days quiet.";

        return self::step('follow_up', "Follow up with {$deal->brand_name}", trim("{$quiet} The studio drafts it, you send it.{$more}"), 'Draft the follow-up', "/deals?follow-up={$deal->id}");
    }

    private static function pendingAgentDraft(): ?array
    {
        $count = rescue(fn () => SocialPost::whereNotNull('social_agent_id')->where('approval', 'pending')->count(), 0);
        if ($count > 0) {
            return self::step(
                'agent_approval',
                "Review {$count} post draft" . ($count === 1 ? '' : 's') . ' from your AI agent',
                'Your autonomous agent generated fresh drafts based on breakout signals. Approve or tweak them.',
                'Review drafts',
                '/agents'
            );
        }

        return null;
    }

    private static function due(Week $week): string
    {
        $days = (int)now(Setting::get('timezone'))->startOfDay()->diffInDays($week->due_date->copy()->startOfDay(), false);

        return match (true) {
            $days > 1 => "Due in {$days} days.",
            $days === 1 => 'Due tomorrow.',
            $days === 0 => 'Due today.',
            default => 'Past due by ' . abs($days) . ' day(s).',
        };
    }

    private static function step(string $key, string $title, string $detail, string $action, string $link, ?int $scriptId = null): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'detail' => $detail,
            'action' => $action,
            'link' => $link,
            'script_id' => $scriptId,
        ];
    }
}
