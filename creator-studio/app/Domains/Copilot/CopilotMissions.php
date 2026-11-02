<?php

namespace App\Domains\Copilot;

use App\Domains\Copilot\Models\CopilotMission;
use App\Domains\Copilot\Models\CopilotThread;
use App\Models\Week;
use RuntimeException;

/**
 * Missions: multi-step workflows the Copilot runs with her, one checkpoint at a time.
 * The state (steps done, her choices so far) lives in copilot_missions, so a mission can be
 * left half-way and resumed from the thread days later.
 */
class CopilotMissions
{
    public const KINDS = [
        'ship_week' => [
            'title' => 'Ship this week’s video',
            'steps' => [
                [
                    'key' => 'idea',
                    'label' => 'Pick the idea',
                    'how' => 'Look at picked ideas and top breakouts (list_ideas, list_breakouts). Offer 2-3 options with a one-line case each, or use the idea she already set for this week. Save her choice with update_mission data {"idea_id", "idea"}.',
                ],
                [
                    'key' => 'title',
                    'label' => 'Choose the title',
                    'how' => 'Call suggest_titles. Show the options; she picks or edits one. Save {"title"}.',
                ],
                [
                    'key' => 'plan',
                    'label' => 'Plan the chapters',
                    'how' => 'Call plan_chapters for the chosen title. Show the plans briefly; she picks one (or tweaks it). Save {"chapters"}.',
                ],
                [
                    'key' => 'draft',
                    'label' => 'Draft the script',
                    'how' => 'Propose create_script with the title and a full first draft that follows the chosen plan, in her voice. After she applies it, save {"script_id"}.',
                ],
                [
                    'key' => 'polish',
                    'label' => 'Polish: AI tells & facts',
                    'how' => 'Read the script (get_script), run check_ai_tells, propose targeted edit_script fixes, and offer fact_check_script if there are factual claims.',
                ],
                [
                    'key' => 'package',
                    'label' => 'Package it',
                    'how' => 'Run writing tools for description, tags and chapters; offer make_thumbnail with a concept. Save the useful bits with save_to_library.',
                ],
                [
                    'key' => 'schedule',
                    'label' => 'Put it on this week',
                    'how' => 'Propose set_week_video for the current week with the title and script id. Then finish the mission.',
                ],
            ],
        ],
    ];

    public static function start(CopilotThread $thread, string $kind = 'ship_week'): CopilotMission
    {
        $def = self::KINDS[$kind] ?? throw new RuntimeException('Unknown mission.');
        CopilotMission::where('copilot_thread_id', $thread->id)->where('status', 'active')->update([
            'status' => 'cancelled',
        ]);
        $week = Week::current();

        return CopilotMission::create([
            'copilot_thread_id' => $thread->id,
            'kind' => $kind,
            'title' => $def['title'],
            'status' => 'active',
            'step' => 0,
            'steps' => array_map(fn ($s) => [
                'key' => $s['key'],
                'label' => $s['label'],
                'done' => false,
            ], $def['steps']),
            'data' => $week ? [
                'week_number' => $week->number,
            ] + ($week->video_title ? [
                'week_video' => $week->video_title,
            ] : []) : [],
        ]);
    }

    public static function active(CopilotThread $thread): ?CopilotMission
    {
        return CopilotMission::where('copilot_thread_id', $thread->id)->where('status', 'active')->latest('id')->first();
    }

    /**
     * Mark a step done and/or save her choices; moves the pointer to the first open step.
     */
    public static function update(CopilotMission $m, ?string $stepDone, array $data, bool $finish = false): CopilotMission
    {
        $steps = $m->steps;
        if ($stepDone !== null && $stepDone !== '') {
            $found = false;
            foreach ($steps as &$s) {
                if ($s['key'] === $stepDone) {
                    $s['done'] = $found = true;
                }
            }
            unset($s);
            if (!$found) {
                throw new RuntimeException('Unknown step "' . $stepDone . '". Steps: ' . implode(', ', array_column($steps, 'key')));
            }
        }
        $open = array_keys(array_filter($steps, fn ($s) => !$s['done']));
        $m->update([
            'steps' => $steps,
            'data' => array_merge($m->data ?? [], array_filter($data, fn ($v) => $v !== null && $v !== '')),
            'step' => $open ? $open[0] : count($steps) - 1,
            'status' => $finish || !$open ? 'done' : 'active',
        ]);

        return $m->fresh();
    }

    /**
     * The mission block for the system prompt.
     */
    public static function prompt(CopilotMission $m): string
    {
        $def = self::KINDS[$m->kind] ?? null;
        $lines = ["MISSION in progress: \"{$m->title}\" (mission #{$m->id}). Checkpoints:"];
        foreach ($m->steps as $i => $s) {
            $mark = $s['done'] ? '[x]' : ($i === $m->step ? '[>]' : '[ ]');
            $lines[] = "{$mark} {$s['key']}: {$s['label']}";
        }
        $current = $m->steps[$m->step] ?? null;
        $how = $current ? collect($def['steps'] ?? [])->firstWhere('key', $current['key'])['how'] ?? '' : '';
        $lines[] = 'Her choices so far: ' . json_encode($m->data ?: new \stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $lines[] = "Work on the current step only. {$how}";
        $lines[] = 'Each step is a checkpoint: show her the options and stop; when she has chosen or approved, call update_mission with step_done and the data, then move on. Start your reply with the step name in bold.';

        return implode("\n", $lines);
    }
}
