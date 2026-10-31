<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/** Status of the scheduled studio jobs, and "run now" for each. */
class ScheduleController extends Controller
{
    public const TASKS = [
        'scan' => ['command' => 'studio:scan', 'label' => 'Breakout scan', 'when' => 'Daily at 07:00'],
        'feeds' => ['command' => 'studio:feeds', 'label' => 'Refresh news feeds', 'when' => 'Every 3 hours'],
        'checkin' => ['command' => 'studio:checkin', 'label' => 'Weekly check-in & mentor verdict', 'when' => 'Mondays at 08:00'],
        'memory' => ['command' => 'studio:memory', 'label' => 'Update Memory index', 'when' => 'Daily at 03:30'],
        'clips' => ['command' => 'studio:clips', 'label' => 'Collect finished Shorts (Vizard)', 'when' => 'Every 10 min while any are processing'],
        'broll' => ['command' => 'studio:broll', 'label' => 'Collect finished b-roll (fal.ai)', 'when' => 'Every 2 min while any are rendering'],
        'prune' => ['command' => 'studio:prune', 'label' => 'Clean up old logs & uploads', 'when' => 'Daily at 04:00'],
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
        ]);
    }

    public function run(string $task): JsonResponse
    {
        if (! isset(self::TASKS[$task])) {
            return response()->json(['error' => 'Unknown task.'], 404);
        }
        @set_time_limit(600);

        try {
            $code = Artisan::call(self::TASKS[$task]['command']);
            $output = trim(Artisan::output());
        } catch (Throwable $e) {
            [$code, $output] = [1, $e->getMessage()];
        }
        if ($code !== 0) {
            return response()->json(['error' => $output ?: 'The job failed.'] + $this->index()->getData(true), 422);
        }

        return response()->json(['message' => $output ?: 'Done.'] + $this->index()->getData(true));
    }
}
