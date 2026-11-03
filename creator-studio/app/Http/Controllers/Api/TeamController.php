<?php

namespace App\Http\Controllers\Api;

use App\Domains\Team\Models\StaffInvite;
use App\Domains\Team\Models\TeamTask;
use App\Domains\Team\TeamTasks;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Team & Production: real tasks for real team members (assignee, due date, payout), "My
 * tasks", a publishing QA checklist, invites by email and a payout ledger.
 *
 * Her (admin) and staff both open it. Staff move work forward; only she sets payouts,
 * approves and pays them, and invites people. Staff see payout amounts on their own tasks only.
 */
class TeamController extends Controller
{
    public const ROLES = ['Video Editor', 'Thumbnail Artist', 'Copywriter', 'Social Manager', 'Researcher', 'Production Lead'];

    public function index(Request $request): JsonResponse
    {
        /** @var User $me */
        $me = $request->user();
        $admin = $me->isAdmin();
        $tasks = TeamTask::with(['assignee:id,name', 'script:id,title'])
            ->orderByRaw('completed_at is not null')->orderBy('due_on')->latest('id')->take(500)->get();

        $members = User::whereIn('role', [Role::Admin->value, Role::Staff->value])->whereNull('disabled_at')
            ->orderBy('id')->get(['id', 'name', 'email', 'role'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $admin ? $u->email : null,
                'role' => $u->role()->value,
                'role_label' => $u->role()->label(),
                'open_tasks' => $tasks->where('assignee_id', $u->id)->whereNull('completed_at')->count(),
            ]);

        $open = $tasks->whereNull('completed_at');
        $doneThisMonth = $tasks->filter(fn (TeamTask $t) => $t->completed_at && $t->completed_at->isSameMonth(now()));

        return response()->json([
            'me' => ['id' => $me->id, 'admin' => $admin],
            'members' => $members,
            'tasks' => $tasks->map(fn (TeamTask $t) => $this->present($t, $me))->values(),
            'qa' => $this->getQaChecklist(),
            'metrics' => [
                'total_tasks' => $open->count(),
                'mine' => $open->where('assignee_id', $me->id)->count(),
                'overdue' => $open->filter(fn (TeamTask $t) => $t->isOverdue())->count(),
                'in_progress' => $open->whereIn('stage', ['filming', 'editing', 'review'])->count(),
                'ready_to_schedule' => $open->where('stage', 'review')->count(),
                'stage_counts' => collect(TeamTask::STAGES)->mapWithKeys(fn ($s) => [$s => $open->where('stage', $s)->count()]),
                'completed_this_month' => $doneThisMonth->count(),
                // Money: hers in full; a team member sees their own.
                'payout_pending' => (float)$tasks->whereIn('payout_status', ['pending', 'approved'])->when(!$admin, fn ($c) => $c->where('assignee_id', $me->id))->sum('payout_amount'),
                'payout_paid_month' => (float)$tasks->where('payout_status', 'paid')->filter(fn ($t) => $t->paid_at?->isSameMonth(now()))->when(!$admin, fn ($c) => $c->where('assignee_id', $me->id))->sum('payout_amount'),
            ],
            'invites' => $admin ? StaffInvite::whereNull('accepted_at')->where('expires_at', '>', now())->latest()->get(['id', 'name', 'email', 'role_label', 'expires_at']) : [],
            'roles_available' => self::ROLES,
        ]);
    }

    public function createTask(Request $request): JsonResponse
    {
        $data = $this->validated($request, true);
        $me = $request->user();
        if (!$me->isAdmin()) {
            unset($data['payout_amount'], $data['payout_currency']);
        }
        $task = TeamTask::create($data + [
            'created_by' => $me->id,
            'completed_at' => ($data['stage'] ?? 'idea') === 'published' ? now() : null,
        ]);
        TeamTasks::notifyAssigned($task->load('assignee'), $me);

        return response()->json([
            'ok' => true,
            'message' => 'Task added.',
            'task' => $this->present($task->fresh(['assignee', 'script']), $me),
        ], 201);
    }

    public function updateTask(Request $request, int $id): JsonResponse
    {
        $task = TeamTask::findOrFail($id);
        $me = $request->user();
        $data = $this->validated($request, false);
        if (!$me->isAdmin()) {
            unset($data['payout_amount'], $data['payout_currency']);
        }
        $before = $task->assignee_id;

        if (array_key_exists('completed', $data)) {
            $task->completed_at = $data['completed'] ? ($task->completed_at ?? now()) : null;
            if ($data['completed']) {
                $task->stage = 'published';
            }
            unset($data['completed']);
        }
        $task->fill($data);
        if ($task->isDirty('stage') && $task->stage === 'published' && !$task->completed_at) {
            $task->completed_at = now();
        }
        if ($task->completed_at) {
            TeamTasks::completed($task);
        }
        $task->save();
        if ($task->assignee_id && $task->assignee_id !== $before) {
            TeamTasks::notifyAssigned($task->load('assignee'), $me);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Task updated.',
            'task' => $this->present($task->fresh(['assignee', 'script']), $me),
        ]);
    }

    public function deleteTask(Request $request, int $id): JsonResponse
    {
        $task = TeamTask::findOrFail($id);
        abort_if($task->payout_status === 'paid', 422, 'A paid task stays in the ledger.');
        abort_unless($request->user()->isAdmin() || $task->created_by === $request->user()->id, 403, 'Only she or whoever created it can delete a task.');
        $task->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * One long-form video becomes four production tasks (2 Shorts, a thumbnail pack, a
     * carousel + thread), assigned to whoever on the team has that role, or unassigned.
     */
    public function autoRepurpose(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'video_url' => 'nullable|url',
            'timestamps' => 'nullable|string|max:1000',
            'script_id' => 'nullable|integer|exists:scripts,id',
        ]);
        $me = $request->user();
        $base = trim($data['title']);
        $notes = trim(($data['video_url'] ?? '') . "\n" . ($data['timestamps'] ?? ''));
        $plan = [
            ["Short #1 (hook & setup): {$base}", 'editing', 'Video Editor', 1],
            ["Short #2 (best insight): {$base}", 'editing', 'Video Editor', 2],
            ["Thumbnail pack (2 concepts): {$base}", 'review', 'Thumbnail Artist', 1],
            ["LinkedIn carousel & X thread: {$base}", 'script', 'Copywriter', 2],
        ];
        $created = [];
        foreach ($plan as [$title, $stage, $role, $days]) {
            $task = TeamTask::create([
                'title' => $title,
                'stage' => $stage,
                'role_label' => $role,
                'assignee_id' => $this->defaultAssignee($role),
                'script_id' => $data['script_id'] ?? null,
                'due_on' => now()->addDays($days)->toDateString(),
                'notes' => $notes ?: null,
                'created_by' => $me->id,
            ]);
            TeamTasks::notifyAssigned($task->load('assignee'), $me);
            $created[] = $this->present($task, $me);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Added 4 production tasks.',
            'tasks_created' => $created,
            'total_queue' => TeamTask::whereNull('completed_at')->count(),
        ], 201);
    }

    /**
     * Rule-of-thumb readability for thumbnail text on a phone (no fabricated contrast or CTR).
     */
    public function simulateThumbnail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:100',
            'has_face' => 'nullable|boolean',
            'has_text' => 'nullable|boolean',
            'text_words_count' => 'nullable|integer|min:0|max:30',
        ]);
        $words = (int)($data['text_words_count'] ?? str_word_count($data['title']));
        $readability = !($data['has_text'] ?? true) ? 100 : ($words <= 4 ? 95 : ($words <= 6 ? 75 : 50));
        $tips = [];
        if ($words > 4) {
            $tips[] = 'Cut the thumbnail text to 4 words or fewer: longer text is hard to read at phone size.';
        }
        if (!($data['has_face'] ?? false)) {
            $tips[] = 'Consider a clear face or one strong focal object; busy scenes blur at small sizes.';
        }
        $tips[] = 'Check the real image at 120px wide on a dark and a light background (Package → Feed preview).';

        return response()->json([
            'ok' => true,
            'title' => $data['title'],
            'metrics' => [
                'mobile_readability_score' => $readability,
                'text_density_verdict' => $words <= 4 ? 'Short enough for a phone' : 'Long: may be unreadable on a phone',
            ],
            'basis' => 'Rule of thumb based on word count, not a measurement of your image.',
            'recommendations' => $tips,
        ]);
    }

    public function toggleQaItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => 'required|string|max:60',
        ]);
        $qaItems = $this->getQaChecklist();
        foreach ($qaItems as &$it) {
            if ($it['key'] === $data['key']) {
                $it['passed'] = !$it['passed'];
                break;
            }
        }
        unset($it);
        Setting::put('team_qa_checklist', json_encode(array_values($qaItems)));

        return response()->json([
            'ok' => true,
            'qa' => $qaItems,
        ]);
    }

    // ---- Payouts --------------------------------------------------------------------------

    /**
     * The payout ledger for a month (YYYY-MM, default this month): hers in full, a team
     * member's own. `?format=csv` downloads it as a statement.
     */
    public function payouts(Request $request): JsonResponse|StreamedResponse
    {
        $data = $request->validate([
            'month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'format' => 'nullable|in:json,csv',
        ]);
        $me = $request->user();
        $month = $data['month'] ?? now()->format('Y-m');
        $start = \Carbon\CarbonImmutable::createFromFormat('Y-m-d', "{$month}-01")->startOfDay();
        $end = $start->endOfMonth();

        $q = TeamTask::with('assignee:id,name')->where('payout_amount', '>', 0)
            ->when(!$me->isAdmin(), fn ($q) => $q->where('assignee_id', $me->id))
            ->where(fn ($q) => $q->whereIn('payout_status', ['pending', 'approved'])
                ->orWhereBetween('paid_at', [$start, $end])
                ->orWhereBetween('completed_at', [$start, $end]))
            ->orderBy('assignee_id')->orderBy('completed_at');
        $tasks = $q->get();

        if (($data['format'] ?? 'json') === 'csv') {
            $rows = TeamTasks::statementRows($tasks);

            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                foreach ($rows as $r) {
                    // Neutralise spreadsheet formulas in free text.
                    fputcsv($out, array_map(fn ($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : $v, $r));
                }
                fclose($out);
            }, "payouts-{$month}.csv", ['Content-Type' => 'text/csv']);
        }

        $byPerson = $tasks->groupBy('assignee_id')->map(fn ($list) => [
            'name' => $list->first()->assignee?->name ?? 'Unassigned',
            'pending' => (float)$list->where('payout_status', 'pending')->sum('payout_amount'),
            'approved' => (float)$list->where('payout_status', 'approved')->sum('payout_amount'),
            'paid' => (float)$list->where('payout_status', 'paid')->sum('payout_amount'),
        ])->values();

        return response()->json([
            'month' => $month,
            'tasks' => $tasks->map(fn (TeamTask $t) => $this->present($t, $me))->values(),
            'by_person' => $byPerson,
        ]);
    }

    public function approvePayout(Request $request, int $id): JsonResponse
    {
        $task = TeamTask::findOrFail($id);
        abort_unless($task->completed_at && $task->payout_amount > 0, 422, 'Only finished tasks with a payout can be approved.');
        abort_if($task->payout_status === 'paid', 422, 'Already paid.');
        $task->update(['payout_status' => 'approved', 'approved_at' => now()]);

        return response()->json(['task' => $this->present($task->fresh('assignee'), $request->user())]);
    }

    public function markPaid(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reference' => 'nullable|string|max:120',
        ]);
        $task = TeamTask::findOrFail($id);
        abort_unless($task->payout_status === 'approved', 422, 'Approve the payout first.');
        $task->update(['payout_status' => 'paid', 'paid_at' => now(), 'paid_ref' => $data['reference'] ?? null]);
        if ($task->assignee && ($task->assignee->notify_email ?? true)) {
            AuthController::mail($task->assignee->email, 'Payout sent', "Hi {$task->assignee->name},\n\nYour payout of "
                . number_format((float)$task->payout_amount, 2) . " {$task->payout_currency} for “{$task->title}” was marked paid"
                . ($task->paid_ref ? " (reference {$task->paid_ref})" : '') . ".\n\nSee your statement: " . url('/team'));
        }

        return response()->json(['task' => $this->present($task->fresh('assignee'), $request->user())]);
    }

    // ---- Team members ---------------------------------------------------------------------

    /**
     * She invites a team member by email; they set their own password from the link.
     */
    public function inviteStaff(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:200',
            'role_label' => 'nullable|string|in:' . implode(',', self::ROLES),
        ]);
        $email = strtolower(trim($data['email']));
        abort_if(User::whereRaw('lower(email) = ?', [$email])->exists(), 422, 'Someone already has an account with that email.');

        $token = Str::random(48);
        StaffInvite::where('email', $email)->whereNull('accepted_at')->delete();
        StaffInvite::create([
            'email' => $email,
            'name' => $data['name'],
            'role_label' => $data['role_label'] ?? null,
            'token_hash' => hash('sha256', $token),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(7),
        ]);
        $link = url('/join-team/' . $token);
        $sent = AuthController::mail($email, 'You’re invited to the studio team', "Hi {$data['name']},\n\n{$request->user()->name} invited you to the studio team"
            . (!empty($data['role_label']) ? " as {$data['role_label']}" : '') . ".\n\nSet your password and sign in (the link works for 7 days):\n{$link}");

        return response()->json([
            'ok' => true,
            'message' => $sent ? "Invite sent to {$email}." : 'The invite is saved, but the email couldn’t be sent: copy the link instead.',
            'link' => $link,
        ], 201);
    }

    public function withdrawInvite(int $id): JsonResponse
    {
        StaffInvite::whereNull('accepted_at')->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Turn a team member's access off (their tasks stay; open ones become unassigned).
     */
    public function removeStaff(Request $request, int $id): JsonResponse
    {
        $u = User::where('role', Role::Staff->value)->findOrFail($id);
        DB::transaction(function () use ($u) {
            $u->forceFill(['disabled_at' => now()])->save();
            TeamTask::where('assignee_id', $u->id)->whereNull('completed_at')->update(['assignee_id' => null]);
            DB::table('sessions')->where('user_id', $u->id)->delete();
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Public: what the invite link is for (no account details beyond name and email).
     */
    public function showInvite(string $token): JsonResponse
    {
        $invite = StaffInvite::findByToken($token);
        abort_unless($invite, 404, 'This invite link isn’t active. Ask for a new one.');

        return response()->json([
            'name' => $invite->name,
            'email' => $invite->email,
            'role_label' => $invite->role_label,
        ]);
    }

    /**
     * Public: accept the invite with a password; creates the staff account.
     */
    public function acceptInvite(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', Password::min(10)],
        ]);
        $invite = StaffInvite::findByToken($token);
        abort_unless($invite, 404, 'This invite link isn’t active. Ask for a new one.');
        abort_if(User::whereRaw('lower(email) = ?', [$invite->email])->exists(), 422, 'An account with this email exists already: sign in instead.');

        DB::transaction(function () use ($invite, $data) {
            $user = new User(['name' => $invite->name, 'email' => $invite->email, 'password' => $data['password']]);
            $user->forceFill(['role' => Role::Staff, 'email_verified_at' => now()])->save();
            $invite->update(['accepted_at' => now()]);
        });

        return response()->json([
            'ok' => true,
            'message' => 'Your account is ready. Sign in with your email and new password.',
        ]);
    }

    // ==========================================================================================

    private function present(TeamTask $t, User $me): array
    {
        $out = $t->toApi();
        if (!$me->isAdmin() && $t->assignee_id !== $me->id) {
            $out['payout_amount'] = null;
            $out['paid_ref'] = null;
        }
        $out['mine'] = $t->assignee_id === $me->id;

        return $out;
    }

    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:200'],
            'stage' => 'sometimes|string|in:' . implode(',', TeamTask::STAGES),
            'role_label' => 'nullable|string|max:60',
            'role_specialty' => 'nullable|string|max:60', // old field name
            'assignee_id' => ['nullable', 'integer', function ($attr, $value, $fail) {
                if ($value && !User::whereKey($value)->whereIn('role', [Role::Admin->value, Role::Staff->value])->whereNull('disabled_at')->exists()) {
                    $fail('Pick someone on the team.');
                }
            }],
            'script_id' => 'nullable|integer|exists:scripts,id',
            'due_on' => 'nullable|date',
            'due_date' => 'nullable|date', // old field name
            'notes' => 'nullable|string|max:2000',
            'payout_amount' => 'nullable|numeric|min:0|max:100000',
            'payout_currency' => 'nullable|string|size:3',
            'completed' => 'sometimes|boolean',
        ]);
        if (array_key_exists('role_specialty', $data)) {
            $data['role_label'] ??= $data['role_specialty'];
            unset($data['role_specialty']);
        }
        if (array_key_exists('due_date', $data)) {
            $data['due_on'] ??= $data['due_date'];
            unset($data['due_date']);
        }
        if (isset($data['payout_currency'])) {
            $data['payout_currency'] = strtoupper($data['payout_currency']);
        }
        if (array_key_exists('payout_amount', $data)) {
            $data['payout_amount'] = (float)($data['payout_amount'] ?? 0);
        }

        return $data;
    }

    private function defaultAssignee(string $role): ?int
    {
        $last = TeamTask::where('role_label', $role)->whereNotNull('assignee_id')->latest('id')->value('assignee_id');

        return $last && User::whereKey($last)->whereNull('disabled_at')->exists() ? $last : null;
    }

    private function getQaChecklist(): array
    {
        $raw = Setting::get('team_qa_checklist');
        $items = $raw ? json_decode($raw, true) : null;

        return is_array($items) ? $items : [
            ['key' => 'retention_hook', 'title' => 'First 30 seconds checked', 'desc' => 'Immediate payoff or question, no dead air or slow logo intro.', 'passed' => false],
            ['key' => 'audio_norm', 'title' => 'Loudness around -14 LUFS', 'desc' => 'No clipping; matches YouTube’s loudness normalisation.', 'passed' => false],
            ['key' => 'thumbnail_contrast', 'title' => 'Thumbnail checked at phone size', 'desc' => 'Readable text and face at 120px, on dark and light backgrounds.', 'passed' => false],
            ['key' => 'sponsor_disclosure', 'title' => 'Sponsor and affiliate disclosures', 'desc' => 'Paid promotion flag on, disclosure in the video and description, tracking links work.', 'passed' => false],
            ['key' => 'chapters_timestamps', 'title' => 'Chapters in the description', 'desc' => 'Starts at 00:00, at least three chapters, clear names.', 'passed' => false],
            ['key' => 'community_repurpose', 'title' => 'Repurposing drafts ready', 'desc' => 'Shorts, carousel and thread drafted or assigned.', 'passed' => false],
        ];
    }
}
