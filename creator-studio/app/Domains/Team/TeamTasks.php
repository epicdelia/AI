<?php

namespace App\Domains\Team;

use App\Domains\Team\Models\TeamTask;
use App\Http\Controllers\Api\AuthController;
use App\Models\User;

/**
 * Production task rules: who hears about what, and how a payout moves from "pending" (the
 * work is done) to "approved" (she signed off) to "paid" (money sent, with a reference).
 */
class TeamTasks
{
    public static function notifyAssigned(TeamTask $task, ?User $by = null): void
    {
        $u = $task->assignee;
        if (!$u || ($by && $by->id === $u->id) || !($u->notify_email ?? true)) {
            return;
        }
        $due = $task->due_on ? ' (due ' . $task->due_on->format('D j M') . ')' : '';
        AuthController::mail(
            $u->email,
            "New task: {$task->title}",
            "Hi {$u->name},\n\n" . ($by?->name ?? 'The studio') . " assigned you a task{$due}:\n\n{$task->title}\n"
                . ($task->notes ? "\n{$task->notes}\n" : '')
                . "\nOpen it: " . url('/team')
        );
    }

    /**
     * A finished task with a payout waits for her approval.
     */
    public static function completed(TeamTask $task): void
    {
        if ($task->payout_amount > 0 && $task->payout_status === 'none') {
            $task->payout_status = 'pending';
        }
    }

    /**
     * Emails each team member their tasks due tomorrow or overdue (once a day, from the
     * scheduler). Returns how many people were emailed.
     */
    public static function remindDue(): int
    {
        $tasks = TeamTask::with('assignee')->whereNull('completed_at')->whereNotNull('assignee_id')
            ->whereNotNull('due_on')->whereDate('due_on', '<=', now()->addDay()->toDateString())
            ->get()->groupBy('assignee_id');
        $sent = 0;
        foreach ($tasks as $list) {
            $u = $list->first()->assignee;
            if (!$u || !($u->notify_email ?? true) || $u->disabled_at) {
                continue;
            }
            $lines = $list->map(fn (TeamTask $t) => '• ' . $t->title . ' (' . ($t->isOverdue() ? 'overdue since ' : 'due ') . $t->due_on->format('D j M') . ')')->implode("\n");
            if (AuthController::mail($u->email, 'Tasks due soon', "Hi {$u->name},\n\n{$lines}\n\nOpen your tasks: " . url('/team'))) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * A month's payouts as CSV rows (header first).
     *
     * @param  iterable<TeamTask>  $tasks
     * @return array<int, array<int, string>>
     */
    public static function statementRows(iterable $tasks): array
    {
        $rows = [['Task', 'Team member', 'Role', 'Completed', 'Hours', 'Amount', 'Currency', 'Status', 'Paid on', 'Reference']];
        foreach ($tasks as $t) {
            $rows[] = [
                $t->title,
                $t->assignee?->name ?? 'Unassigned',
                (string)$t->role_label,
                $t->completed_at?->toDateString() ?? '',
                number_format(((int)$t->minutes_logged) / 60, 2, '.', ''),
                number_format((float)$t->payout_amount, 2, '.', ''),
                $t->payout_currency,
                $t->payout_status,
                $t->paid_at?->toDateString() ?? '',
                (string)$t->paid_ref,
            ];
        }

        return $rows;
    }
}
