<?php

namespace App\Domains\Copilot;

use App\Domains\Copilot\Models\CopilotThread;
use App\Models\Setting;
use RuntimeException;

/**
 * The morning briefing: a scheduled Copilot run that looks at her studio and leaves
 * "3 things today" (with proposals she can Apply) in a briefing thread and on the dashboard.
 */
class CopilotBriefing
{
    public const PROMPT = 'Good morning! Write my briefing for today. Look at my studio first (get_studio_overview, then whatever else you need: breakouts, ideas, the script in progress). '
        . 'Then give me exactly "3 things today": each a bold one-line task, why it matters this week, and how long it should take. '
        . 'Where one of them is a concrete change (save an idea, an edit, setting the week\'s video), propose it so I can Apply it. Keep it short, warm and practical.';

    public function __construct(
        private CopilotAgent $agent
    ) {
    }

    public function run(): CopilotThread
    {
        $tz = Setting::get('timezone') ?: config('app.timezone');
        $thread = CopilotThread::create([
            'title' => 'Briefing · ' . now($tz)->format('D j M'),
            'mode' => 'agent',
            'kind' => 'briefing',
            'model' => Setting::get('copilot_model') ?: null,
        ]);

        try {
            $this->agent->run($thread, self::PROMPT, [
                'path' => '/',
            ], [], new SilentStream());
        } catch (\Throwable $e) {
            $thread->delete();

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }
        Setting::put('copilot_briefing_thread', (string)$thread->id);

        return $thread;
    }

    /**
     * The latest briefing for the dashboard card, or null.
     */
    public static function latest(): ?array
    {
        $thread = CopilotThread::where('kind', 'briefing')->latest('id')->first();
        if (!$thread) {
            return null;
        }
        $answer = $thread->messages()->where('role', 'assistant')->reorder()->orderByDesc('id')->first();

        return [
            'thread_id' => $thread->id,
            'title' => $thread->title,
            'content' => (string)$answer?->content,
            'pending' => $thread->actions()->where('status', 'pending')->count(),
            'created_at' => $thread->created_at,
        ];
    }
}
