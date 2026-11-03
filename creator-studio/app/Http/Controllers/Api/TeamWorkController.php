<?php

namespace App\Http\Controllers\Api;

use App\Domains\Clients\DealFiles;
use App\Domains\Team\Models\TeamTask;
use App\Domains\Team\TeamTasks;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Services\ScriptStructure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The working side of a production task:
 * - brief templates per role (her own text with {placeholders}, filled from the task);
 * - time tracking: a start/stop timer or hours by hand; tasks can be billed hourly at the
 *   person's rate (she sets rates; only she and that person see them);
 * - files on a task (the cut) and her notes at exact seconds, then "send back for changes".
 */
class TeamWorkController extends Controller
{
    public const PLACEHOLDERS = ['title', 'script_title', 'script_link', 'chapters', 'notes', 'due', 'video_url', 'brand_kit', 'assignee'];

    public const FILE_TYPES = ['mp4', 'mov', 'webm', 'png', 'jpg', 'jpeg', 'webp', 'pdf', 'zip', 'psd', 'txt', 'md', 'srt'];

    // ---- Brief templates ----------------------------------------------------------------

    public static function templates(): array
    {
        return json_decode((string)Setting::get('brief_templates', ''), true) ?: [];
    }

    public function briefTemplates(): JsonResponse
    {
        return response()->json([
            'templates' => (object)self::templates(),
            'roles' => TeamController::ROLES,
            'placeholders' => self::PLACEHOLDERS,
        ]);
    }

    public function saveBriefTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role' => 'required|in:' . implode(',', TeamController::ROLES),
            'template' => 'nullable|string|max:8000',
        ]);
        $all = self::templates();
        if (trim((string)$data['template']) === '') {
            unset($all[$data['role']]);
        } else {
            $all[$data['role']] = $data['template'];
        }
        Setting::put('brief_templates', json_encode($all));

        return $this->briefTemplates();
    }

    /**
     * Fill the role's template for this task ({placeholder} → value; unknown ones stay).
     */
    public static function renderBrief(TeamTask $task, ?string $videoUrl = null): ?string
    {
        $tpl = self::templates()[$task->role_label ?? ''] ?? null;
        if (!$tpl) {
            return null;
        }
        $script = $task->script;
        $chapters = $script ? collect(ScriptStructure::analyse((string)$script->body)['chapters'] ?? [])
            ->map(fn ($c) => is_array($c) ? (($c['time'] ?? $c['at'] ?? '') . ' ' . ($c['title'] ?? '')) : (string)$c)->implode("\n") : '';
        $values = [
            'title' => $task->title,
            'script_title' => $script?->title ?? '',
            'script_link' => $script ? url('/scripts/' . $script->id) : '',
            'chapters' => trim($chapters),
            'notes' => (string)$task->notes,
            'due' => $task->due_on?->toFormattedDateString() ?? '',
            'video_url' => (string)($videoUrl ?? $script?->youtube_url ?? ''),
            'brand_kit' => url('/brand'),
            'assignee' => $task->assignee?->name ?? '',
        ];

        return preg_replace_callback('/\{(\w+)\}/', fn ($m) => array_key_exists($m[1], $values) ? $values[$m[1]] : $m[0], $tpl);
    }

    public function applyBrief(int $id): JsonResponse
    {
        $task = TeamTask::with(['script', 'assignee'])->findOrFail($id);
        $brief = self::renderBrief($task);
        abort_unless($brief !== null, 422, 'There’s no brief template for this role yet.');
        $task->update(['brief' => $brief]);

        return response()->json(['brief' => $brief]);
    }

    public function saveBrief(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['brief' => 'nullable|string|max:20000']);
        $task = TeamTask::findOrFail($id);
        $task->update(['brief' => $data['brief']]);

        return response()->json(['brief' => $task->brief]);
    }

    // ---- Time -----------------------------------------------------------------------------

    public function timer(Request $request, int $id): JsonResponse
    {
        $task = TeamTask::findOrFail($id);
        $me = $request->user();
        abort_unless($me->isAdmin() || $task->assignee_id === $me->id, 403, 'Only the person on this task tracks its time.');
        if ($task->timer_started_at) {
            $minutes = max(1, (int)round($task->timer_started_at->diffInSeconds(now()) / 60));
            $minutes = min($minutes, 12 * 60); // a forgotten timer counts at most 12 hours
            $this->log($task, $me, $minutes, 'Timer');
            $task->update(['timer_started_at' => null]);
        } else {
            $task->update(['timer_started_at' => now()]);
        }

        return response()->json($this->timePayload($task->fresh(), $me));
    }

    public function addTime(Request $request, int $id): JsonResponse
    {
        $task = TeamTask::findOrFail($id);
        $me = $request->user();
        abort_unless($me->isAdmin() || $task->assignee_id === $me->id, 403, 'Only the person on this task logs its time.');
        $data = $request->validate([
            'minutes' => 'required|integer|min:-600|max:1440',
            'note' => 'nullable|string|max:200',
        ]);
        abort_if($data['minutes'] < 0 && $task->minutes_logged + $data['minutes'] < 0, 422, 'That would make the time negative.');
        abort_if($task->payout_status === 'paid', 422, 'This task is paid already.');
        $this->log($task, $me, (int)$data['minutes'], $data['note'] ?? null);

        return response()->json($this->timePayload($task->fresh(), $me));
    }

    public function time(Request $request, int $id): JsonResponse
    {
        return response()->json($this->timePayload(TeamTask::findOrFail($id), $request->user()));
    }

    public function rates(): JsonResponse
    {
        $rates = DB::table('team_rates')->pluck('hourly_rate', 'user_id');

        return response()->json([
            'members' => User::whereIn('role', ['admin', 'staff'])->whereNull('disabled_at')->orderBy('name')->get(['id', 'name'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'hourly_rate' => (float)($rates[$u->id] ?? 0)]),
        ]);
    }

    public function saveRate(Request $request, int $user): JsonResponse
    {
        $data = $request->validate([
            'hourly_rate' => 'required|numeric|min:0|max:10000',
            'currency' => 'nullable|string|size:3',
        ]);
        User::whereIn('role', ['admin', 'staff'])->findOrFail($user);
        DB::table('team_rates')->updateOrInsert(['user_id' => $user], [
            'hourly_rate' => $data['hourly_rate'],
            'currency' => strtoupper($data['currency'] ?? 'USD'),
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        return $this->rates();
    }

    /**
     * When an hourly task is finished, its payout is hours × the person's rate.
     */
    public static function settleHourly(TeamTask $task): void
    {
        if (!$task->hourly || !$task->assignee_id || $task->payout_status === 'paid') {
            return;
        }
        $rate = (float)(DB::table('team_rates')->where('user_id', $task->assignee_id)->value('hourly_rate') ?? 0);
        $task->hourly_rate = $rate;
        $task->payout_amount = round($task->minutes_logged / 60 * $rate, 2);
    }

    // ---- Files & review notes ----------------------------------------------------------

    public function files(int $id): JsonResponse
    {
        $task = TeamTask::findOrFail($id);

        return response()->json([
            'files' => DB::table('team_task_files')->leftJoin('users', 'users.id', '=', 'team_task_files.user_id')
                ->where('task_id', $task->id)->latest('team_task_files.id')
                ->get(['team_task_files.id', 'team_task_files.name', 'team_task_files.size', 'team_task_files.created_at', 'users.name as by'])
                ->map(fn ($f) => (array)$f + ['is_video' => in_array(strtolower(pathinfo($f->name, PATHINFO_EXTENSION)), ['mp4', 'webm', 'mov'], true)]),
        ]);
    }

    public function upload(Request $request, int $id): JsonResponse
    {
        $task = TeamTask::findOrFail($id);
        $request->validate([
            'file' => 'required|file|extensions:' . implode(',', self::FILE_TYPES) . '|max:' . (DealFiles::maxMb() * 1024),
        ]);
        $file = $request->file('file');
        DealFiles::verifySafeUpload($file);
        $name = mb_substr(preg_replace('/[^\w.\- ]+/u', '_', $file->getClientOriginalName()), 0, 150);
        $path = $file->storeAs('team-files/' . $task->id, bin2hex(random_bytes(8)) . '.' . strtolower($file->getClientOriginalExtension()), 'local');
        $fid = DB::table('team_task_files')->insertGetId([
            'task_id' => $task->id, 'user_id' => $request->user()->id, 'name' => $name, 'path' => $path,
            'size' => $file->getSize(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($task->stage === 'editing' && $task->assignee_id === $request->user()->id) {
            $task->update(['stage' => 'review']);
            PortalController::tellAdmins("Ready for review: {$task->title}", "{$request->user()->name} uploaded “{$name}” for review.\n\n" . url('/team'));
        }

        return response()->json(['id' => $fid, 'name' => $name], 201);
    }

    public function stream(int $id, int $file): \Symfony\Component\HttpFoundation\Response
    {
        $f = DB::table('team_task_files')->where('task_id', $id)->where('id', $file)->first();
        abort_unless($f && Storage::disk('local')->exists($f->path), 404);
        $ext = strtolower(pathinfo($f->name, PATHINFO_EXTENSION));
        $types = ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime'];
        if (isset($types[$ext])) {
            return response()->file(Storage::disk('local')->path($f->path), ['Content-Type' => $types[$ext], 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
        }

        return Storage::disk('local')->download($f->path, $f->name, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function notes(int $id): JsonResponse
    {
        TeamTask::findOrFail($id);

        return response()->json(['annotations' => $this->noteRows($id)]);
    }

    /**
     * Same shape as client revision notes, so the review player works for both.
     */
    public function addNote(Request $request, int $id): JsonResponse
    {
        TeamTask::findOrFail($id);
        $data = $request->validate([
            'deliverable_index' => 'nullable|integer',
            'timecode_seconds' => 'nullable|numeric|min:0',
            'body' => 'required|string|min:2|max:3000',
        ]);
        $fileId = isset($data['deliverable_index']) && DB::table('team_task_files')->where('task_id', $id)->where('id', $data['deliverable_index'])->exists()
            ? (int)$data['deliverable_index'] : null;
        $nid = DB::table('team_task_notes')->insertGetId([
            'task_id' => $id, 'file_id' => $fileId, 'user_id' => $request->user()->id,
            'timecode_seconds' => $data['timecode_seconds'] ?? null, 'body' => trim($data['body']),
            'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(['annotation' => collect($this->noteRows($id))->firstWhere('id', $nid)], 201);
    }

    public function resolveNote(int $id, int $note): JsonResponse
    {
        DB::table('team_task_notes')->where('task_id', $id)->where('id', $note)->update(['status' => 'resolved', 'updated_at' => now()]);

        return response()->json(['annotation' => collect($this->noteRows($id))->firstWhere('id', $note)]);
    }

    /**
     * Her review is done: back to editing with the open notes, and the editor hears about it.
     */
    public function sendBack(Request $request, int $id): JsonResponse
    {
        $task = TeamTask::with('assignee')->findOrFail($id);
        $open = collect($this->noteRows($id))->where('status', 'open');
        abort_if($open->isEmpty(), 422, 'Add at least one note first.');
        $task->update(['stage' => 'editing', 'completed_at' => null]);
        if ($task->assignee && ($task->assignee->notify_email ?? true)) {
            $lines = $open->map(fn ($n) => '• ' . ($n['timecode_seconds'] !== null ? gmdate($n['timecode_seconds'] >= 3600 ? 'H:i:s' : 'i:s', (int)$n['timecode_seconds']) . ' ' : '') . $n['body'])->implode("\n");
            AuthController::mail($task->assignee->email, "Changes on: {$task->title}", "Hi {$task->assignee->name},\n\n{$request->user()->name} reviewed your upload and asked for changes:\n\n{$lines}\n\nOpen the task: " . url('/team'));
        }

        return response()->json(['ok' => true, 'notes' => $open->count()]);
    }

    // ==========================================================================================

    private function log(TeamTask $task, User $me, int $minutes, ?string $note): void
    {
        DB::table('team_time_entries')->insert([
            'task_id' => $task->id, 'user_id' => $me->id, 'minutes' => $minutes, 'note' => $note,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $task->increment('minutes_logged', $minutes);
    }

    private function timePayload(TeamTask $task, User $me): array
    {
        $rate = ($me->isAdmin() || $task->assignee_id === $me->id)
            ? (float)(DB::table('team_rates')->where('user_id', $task->assignee_id)->value('hourly_rate') ?? 0) : null;

        return [
            'minutes_logged' => (int)$task->minutes_logged,
            'timer_started_at' => $task->timer_started_at?->toIso8601String(),
            'hourly' => (bool)$task->hourly,
            'hourly_rate' => $rate,
            'estimate' => $rate !== null && $task->hourly ? round($task->minutes_logged / 60 * $rate, 2) : null,
            'entries' => DB::table('team_time_entries')->leftJoin('users', 'users.id', '=', 'team_time_entries.user_id')
                ->where('task_id', $task->id)->latest('team_time_entries.id')->take(50)
                ->get(['team_time_entries.minutes', 'team_time_entries.note', 'team_time_entries.created_at', 'users.name']),
        ];
    }

    private function noteRows(int $taskId): array
    {
        return DB::table('team_task_notes')->leftJoin('users', 'users.id', '=', 'team_task_notes.user_id')
            ->where('task_id', $taskId)->orderBy('timecode_seconds')->orderBy('team_task_notes.id')
            ->get(['team_task_notes.*', 'users.name as author_name', 'users.role as author_role'])
            ->map(fn ($n) => [
                'id' => (int)$n->id,
                'kind' => 'video',
                'deliverable_index' => $n->file_id !== null ? (int)$n->file_id : null,
                'timecode_seconds' => $n->timecode_seconds !== null ? (float)$n->timecode_seconds : null,
                'body' => $n->body,
                'status' => $n->status,
                'author' => $n->author_name ?? 'Team',
                'from_admin' => $n->author_role === 'admin',
                'created_at' => $n->created_at,
            ])->all();
    }
}
