<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Status of the scheduled studio jobs, and "run now" for each.
 */
class ScheduleController extends Controller
{
    public const TASKS = [
        'scan' => [
            'command' => 'studio:scan',
            'label' => 'Breakout scan',
            'when' => 'Daily at 07:00',
        ],
        'feeds' => [
            'command' => 'studio:feeds',
            'label' => 'Refresh news feeds',
            'when' => 'Every 3 hours',
        ],
        'checkin' => [
            'command' => 'studio:checkin',
            'label' => 'Weekly check-in & mentor verdict',
            'when' => 'Mondays at 08:00',
        ],
        'pipeline' => [
            'command' => 'studio:pipeline',
            'label' => 'Call prep briefs & morning follow-ups',
            'when' => 'Daily at 08:15',
        ],
        'memory' => [
            'command' => 'studio:memory',
            'label' => 'Update Memory index',
            'when' => 'Daily at 03:30',
        ],
        'clips' => [
            'command' => 'studio:clips',
            'label' => 'Collect finished Shorts (Vizard)',
            'when' => 'Every 10 min while any are processing',
        ],
        'broll' => [
            'command' => 'studio:broll',
            'label' => 'Collect finished b-roll (fal.ai)',
            'when' => 'Every 2 min while any are rendering',
        ],
        'stats' => [
            'command' => 'studio:stats',
            'label' => 'Record your videos’ daily numbers',
            'when' => 'Daily at 06:30',
        ],
        'landing' => [
            'command' => 'studio:landing',
            'label' => 'Refresh your landing page content feed',
            'when' => 'Hourly',
        ],
        'social' => [
            'command' => 'studio:social-sync',
            'label' => 'Sync connected social accounts',
            'when' => 'Hourly (and when you open the studio)',
        ],
        'agents' => [
            'command' => 'studio:agents',
            'label' => 'Agents plan the coming week',
            'when' => 'Hourly, for agents that are due (once a day each)',
        ],
        'publish' => [
            'command' => 'studio:publish',
            'label' => 'Publish approved agent posts',
            'when' => 'Every 5 min while any are scheduled',
        ],
        'learn' => [
            'command' => 'studio:agents-learn',
            'label' => 'Agents learn from results & your edits',
            'when' => 'Sundays at 20:00',
        ],
        'engage' => [
            'command' => 'studio:engage',
            'label' => 'Check comments & DMs, run automations',
            'when' => 'Every 10 min while Instagram, Threads or YouTube is connected',
        ],
        'workflows' => [
            'command' => 'studio:workflows',
            'label' => 'Run workflows (schedules, waits)',
            'when' => 'Every minute while any workflow is on',
        ],
        'youtube' => [
            'command' => 'studio:youtube',
            'label' => 'YouTube uploads & title tests',
            'when' => 'Every minute while any are running',
        ],
        'backup' => [
            'command' => 'studio:backup',
            'label' => 'Back up the studio',
            'when' => 'Daily at 03:00 (always, even when paused)',
        ],
        'prune' => [
            'command' => 'studio:prune',
            'label' => 'Clean up old logs & uploads',
            'when' => 'Daily at 04:00',
        ],
    ];

    public function index(): JsonResponse
    {
        $tasks = [];
        foreach (self::TASKS as $key => $t) {
            $tasks[] = [
                'key' => $key,
                'label' => $t['label'],
                'when' => $t['when'],
                'command' => $t['command'],
                'last_run' => Setting::get("last_run_{$key}"),
                'last_error' => Setting::get("last_error_{$key}"),
            ];
        }

        return response()->json([
            'enabled' => Setting::get('schedule_enabled', '1') === '1',
            'timezone' => Setting::get('timezone'),
            'tasks' => $tasks,
            // The scheduler writes a heartbeat every minute it runs (see routes/console.php).
            'scheduler_seen' => Setting::get('scheduler_heartbeat'),
            // One command that starts the scheduler at every Windows sign-in (she runs it once).
            'autostart' => PHP_OS_FAMILY === 'Windows'
                ? 'schtasks /Create /TN "Creator Studio" /SC ONLOGON /RL LIMITED /F /TR "\\"' . PHP_BINARY . '\\" \\"' . base_path('artisan') . '\\" schedule:work"'
                : '@reboot cd ' . base_path() . ' && ' . PHP_BINARY . ' artisan schedule:work >/dev/null 2>&1',
        ]);
    }

    public function run(string $task): JsonResponse
    {
        if (!isset(self::TASKS[$task])) {
            return response()->json([
                'error' => 'Unknown task.',
            ], 404);
        }
        @set_time_limit(600);

        try {
            $code = Artisan::call(self::TASKS[$task]['command']);
            $output = trim(Artisan::output());
        } catch (Throwable $e) {
            [$code, $output] = [1, $e->getMessage()];
        }
        if ($code !== 0) {
            return response()->json([
                'error' => $output ?: 'The job failed.',
            ] + $this->index()->getData(true), 422);
        }

        return response()->json([
            'message' => $output ?: 'Done.',
        ] + $this->index()->getData(true));
    }
}
