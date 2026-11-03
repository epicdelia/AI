<?php

namespace App\Http\Controllers\Api;

use App\Domains\Team\Models\TeamTask;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Two team guard rails:
 * - Stage checklists: her checklist per stage ("Editing: captions burned in, loudness −14…");
 *   a task only moves past a stage once its list is ticked (she can override).
 * - Workload: this week's open work per person against their weekly hours, from each task's
 *   estimate (or what that person usually logs), so nobody gets overloaded by accident.
 */
class TeamOpsController extends Controller
{
    public const DEFAULT_TASK_MINUTES = 120;

    public static function checklists(): array
    {
        return json_decode((string)Setting::get('stage_checklists', ''), true) ?: [];
    }

    /**
     * @return array<int, array{key: string, label: string, done: bool}>
     */
    public static function itemsFor(TeamTask $task): array
    {
        $done = (array)($task->checklist ?? []);

        return array_map(fn ($i) => $i + ['done' => isset($done[$task->stage . ':' . $i['key']])], self::checklists()[$task->stage] ?? []);
    }

    /**
     * Unticked items of the stage the task is leaving (empty = it may move on).
     *
     * @return array<int, string>
     */
    public static function missing(TeamTask $task): array
    {
        return array_values(array_map(fn ($i) => $i['label'], array_filter(self::itemsFor($task), fn ($i) => !$i['done'])));
    }

    public function index(): JsonResponse
    {
        return response()->json(['checklists' => (object)self::checklists(), 'stages' => TeamTask::STAGES]);
    }

    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'stage' => 'required|in:' . implode(',', TeamTask::STAGES),
            'items' => 'present|array|max:15',
            'items.*' => 'string|max:150',
        ]);
        $all = self::checklists();
        $items = [];
        foreach (array_values(array_filter(array_map('trim', $data['items']))) as $i => $label) {
            $items[] = ['key' => 'c' . ($i + 1) . '_' . substr(md5($label), 0, 6), 'label' => $label];
        }
        if ($items) {
            $all[$data['stage']] = $items;
        } else {
            unset($all[$data['stage']]);
        }
        Setting::put('stage_checklists', json_encode($all));

        return $this->index();
    }

    public function tick(Request $request, int $id, string $key): JsonResponse
    {
        $task = TeamTask::findOrFail($id);
        abort_unless(collect(self::itemsFor($task))->firstWhere('key', $key), 404);
        $data = $request->validate(['done' => 'required|boolean']);
        $done = (array)($task->checklist ?? []);
        $k = $task->stage . ':' . $key;
        if ($data['done']) {
            $done[$k] = now()->toIso8601String();
        } else {
            unset($done[$k]);
        }
        $task->forceFill(['checklist' => $done])->save();

        return response()->json(['items' => self::itemsFor($task->fresh())]);
    }

    // ---- workload ---------------------------------------------------------------------------

    public function workload(): JsonResponse
    {
        return response()->json(['people' => self::loads()]);
    }

    /**
     * @return array<int, array{id: int, name: string, tasks: int, minutes: int, capacity_minutes: int, over: bool}>
     */
    public static function loads(): array
    {
        $hours = DB::table('team_rates')->pluck('weekly_hours', 'user_id');
        $open = TeamTask::whereNull('completed_at')->whereNotNull('assignee_id')
            ->where(fn ($q) => $q->whereNull('due_on')->orWhereDate('due_on', '<=', now()->addDays(7)->toDateString()))->get();
        $usual = TeamTask::whereNotNull('completed_at')->where('minutes_logged', '>', 0)->where('completed_at', '>=', now()->subDays(90))
            ->select('assignee_id', 'role_label', DB::raw('AVG(minutes_logged) as m'))->groupBy('assignee_id', 'role_label')->get()
            ->mapWithKeys(fn ($r) => [$r->assignee_id . '|' . $r->role_label => (int)$r->m]);

        return User::whereIn('role', [Role::Admin->value, Role::Staff->value])->whereNull('disabled_at')->orderBy('name')->get(['id', 'name'])
            ->map(function ($u) use ($open, $usual, $hours) {
                $mine = $open->where('assignee_id', $u->id);
                $minutes = (int)$mine->sum(fn (TeamTask $t) => max(0, ($t->estimate_minutes ?: ($usual[$u->id . '|' . $t->role_label] ?? self::DEFAULT_TASK_MINUTES)) - (int)$t->minutes_logged));
                $cap = (int)($hours[$u->id] ?? 40) * 60;

                return ['id' => $u->id, 'name' => $u->name, 'tasks' => $mine->count(), 'minutes' => $minutes, 'capacity_minutes' => $cap, 'over' => $cap > 0 && $minutes > $cap];
            })->values()->all();
    }

    public static function warningFor(?int $userId): ?string
    {
        if (!$userId) {
            return null;
        }
        $p = collect(self::loads())->firstWhere('id', $userId);

        return $p && $p['over'] ? "{$p['name']} has about " . round($p['minutes'] / 60) . 'h of work this week for ' . round($p['capacity_minutes'] / 60) . 'h available.' : null;
    }

    public function saveHours(Request $request, int $user): JsonResponse
    {
        $data = $request->validate(['weekly_hours' => 'required|integer|min:0|max:80']);
        User::whereIn('role', [Role::Admin->value, Role::Staff->value])->findOrFail($user);
        DB::table('team_rates')->updateOrInsert(['user_id' => $user], ['weekly_hours' => $data['weekly_hours'], 'updated_at' => now(), 'created_at' => now()]);

        return $this->workload();
    }
}
