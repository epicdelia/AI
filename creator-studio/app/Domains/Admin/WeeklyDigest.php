<?php

namespace App\Domains\Admin;

use App\Domains\Community\Models\CommunityMembership;
use App\Domains\Crm\Models\CrmContact;
use App\Domains\Deals\Billing;
use App\Domains\Deals\DealRight;
use App\Domains\Team\Models\TeamTask;
use App\Enums\Role;
use App\Http\Controllers\Api\AuthController;
use App\Models\Deal;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;

/**
 * Monday's one-email summary of the business for her: money in, money owed, what's late,
 * new leads, the community and the team. Real numbers from the last 7 days only.
 * Off with Setting weekly_digest = 0.
 */
class WeeklyDigest
{
    public static function build(): array
    {
        $since = now()->subDays(7);
        $paidDeals = Deal::where('payment_status', 'paid')->where('paid_at', '>=', $since)->get(['id', 'rate', 'deposit_amount']);
        // A deal paid in full counts once (minus a deposit already counted when it came in); other payments count as they arrive.
        $paidIn = (float)$paidDeals->sum(fn ($d) => (float)$d->rate - (float)($d->deposit_amount ?? 0))
            + (float)Payment::whereIn('kind', ['renewal', 'deposit', 'rights'])->where('paid_at', '>=', $since)->sum('amount');
        $open = Deal::whereNotIn('stage', ['lost', 'paid', 'prospect'])->where('payment_status', '!=', 'paid')->whereNotNull('invoiced_at')->get();
        $overdue = $open->filter(fn (Deal $d) => Billing::isOverdue($d));

        return [
            'period' => $since->toFormattedDateString() . ' – ' . now()->toFormattedDateString(),
            'paid_in' => round($paidIn, 2),
            'invoiced_open' => round((float)$open->sum(fn ($d) => Billing::nextPayment($d)['amount']), 2),
            'overdue_count' => $overdue->count(),
            'overdue_total' => round((float)$overdue->sum(fn ($d) => Billing::nextPayment($d)['amount']), 2),
            'late_reviews' => Deal::where('stage', 'in_review')->whereNotNull('review_due_on')->whereDate('review_due_on', '<', now()->toDateString())->count(),
            'rights_ending' => DealRight::where('status', 'active')->whereNotNull('ends_on')->whereDate('ends_on', '<=', now()->addDays(14)->toDateString())->count(),
            'new_inquiries' => Deal::where('stage', 'inquiry')->where('created_at', '>=', $since)->count(),
            'new_contacts' => CrmContact::where('created_at', '>=', $since)->count(),
            'follow_ups_due' => CrmContact::whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now()->endOfDay())->count(),
            'new_members' => User::where('role', Role::Member->value)->where('created_at', '>=', $since)->count(),
            'pro_active' => CommunityMembership::whereIn('status', ['active', 'comped'])->count(),
            'pro_new' => CommunityMembership::where('status', 'active')->where('created_at', '>=', $since)->count(),
            'payouts_to_approve' => round((float)TeamTask::where('payout_status', 'pending')->sum('payout_amount'), 2),
            'payouts_to_pay' => round((float)TeamTask::where('payout_status', 'approved')->sum('payout_amount'), 2),
            'tasks_overdue' => TeamTask::whereNull('completed_at')->whereNotNull('due_on')->whereDate('due_on', '<', now()->toDateString())->count(),
        ];
    }

    public static function text(array $d): string
    {
        $m = fn ($v) => '$' . number_format((float)$v, 2);
        $lines = [
            "Your week ({$d['period']})",
            '',
            'Money',
            "• Paid in: {$m($d['paid_in'])}",
            "• Invoiced, not paid yet: {$m($d['invoiced_open'])}" . ($d['overdue_count'] ? " ({$d['overdue_count']} overdue, {$m($d['overdue_total'])})" : ''),
            $d['rights_ending'] ? "• Usage rights ending in the next 14 days: {$d['rights_ending']} (renewal chance)" : null,
            '',
            'Clients & leads',
            "• New inquiries: {$d['new_inquiries']} · new contacts: {$d['new_contacts']}",
            $d['follow_ups_due'] ? "• Follow-ups due: {$d['follow_ups_due']}" : null,
            $d['late_reviews'] ? "• Client reviews past their due date: {$d['late_reviews']}" : null,
            '',
            'Community',
            "• New members: {$d['new_members']} · Pro members: {$d['pro_active']}" . ($d['pro_new'] ? " (+{$d['pro_new']} this week)" : ''),
            '',
            'Team',
            "• Payouts to approve: {$m($d['payouts_to_approve'])} · approved, to pay: {$m($d['payouts_to_pay'])}",
            $d['tasks_overdue'] ? "• Overdue tasks: {$d['tasks_overdue']}" : null,
            '',
            'Everything waiting on you: ' . url('/needs-you'),
        ];

        return implode("\n", array_filter($lines, fn ($l) => $l !== null));
    }

    public static function send(): int
    {
        if (Setting::get('weekly_digest', '1') === '0') {
            return 0;
        }
        $body = self::text(self::build());
        $sent = 0;
        foreach (User::where('role', Role::Admin->value)->whereNull('disabled_at')->get() as $admin) {
            if (AuthController::mail($admin->email, 'Your week in the studio', "Hi {$admin->name},\n\n{$body}")) {
                $sent++;
            }
        }

        return $sent;
    }
}
