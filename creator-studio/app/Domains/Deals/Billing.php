<?php

namespace App\Domains\Deals;

use App\Domains\Clients\ClientNotifier;
use App\Models\Deal;
use App\Models\Setting;
use Carbon\CarbonImmutable;

/**
 * Getting paid on time:
 * - payment plans: on receipt, net 7, net 15, net 30 → the invoice's due date;
 * - an optional deposit (a percentage she sets per deal) paid up front, the balance once
 *   the work is live;
 * - polite overdue reminders on day 1, 7 and 14, mentioning a late fee only when she has
 *   set one (Setting late_fee_percent, off by default; it's never charged automatically).
 */
class Billing
{
    public const PLANS = ['on_receipt' => 0, 'net7' => 7, 'net15' => 15, 'net30' => 30];

    /**
     * Days overdue on which a reminder goes out.
     */
    public const REMINDER_DAYS = [1, 7, 14];

    public static function dueDate(Deal $deal): ?string
    {
        $plan = $deal->payment_plan ?: (Setting::get('default_payment_plan') ?: null);
        if (!$plan || !array_key_exists($plan, self::PLANS) || !$deal->invoiced_at) {
            return null;
        }

        return CarbonImmutable::parse($deal->invoiced_at)->addDays(self::PLANS[$plan])->toDateString();
    }

    public static function depositDue(Deal $deal): bool
    {
        return $deal->deposit_percent > 0 && !$deal->deposit_paid_at && $deal->payment_status !== 'paid';
    }

    /**
     * What the client pays next, and which part it is (deposit / balance / full).
     *
     * @return array{amount: float, part: string}
     */
    public static function nextPayment(Deal $deal): array
    {
        $rate = (float)$deal->rate;
        if (self::depositDue($deal)) {
            return ['amount' => round($rate * $deal->deposit_percent / 100, 2), 'part' => 'deposit'];
        }
        if ($deal->deposit_paid_at) {
            return ['amount' => max(0, round($rate - (float)$deal->deposit_amount, 2)), 'part' => 'balance'];
        }

        return ['amount' => $rate, 'part' => 'full'];
    }

    /**
     * The balance after a deposit waits until the work is live (scheduled/invoiced).
     */
    public static function balanceReady(Deal $deal): bool
    {
        return !$deal->deposit_paid_at || in_array($deal->stage, ['scheduled', 'invoiced'], true);
    }

    public static function isOverdue(Deal $deal): bool
    {
        return $deal->invoice_due_on !== null && $deal->payment_status !== 'paid' && $deal->stage !== 'lost'
            && $deal->invoice_due_on->copy()->endOfDay()->isPast();
    }

    public static function daysOverdue(Deal $deal): int
    {
        return self::isOverdue($deal) ? (int)$deal->invoice_due_on->copy()->startOfDay()->diffInDays(now()->startOfDay()) : 0;
    }

    public static function lateFeePercent(): float
    {
        return max(0, min(10, (float)Setting::get('late_fee_percent', 0)));
    }

    /**
     * For the client's request page and her deal view.
     */
    public static function summary(Deal $deal): array
    {
        $next = self::nextPayment($deal);

        return [
            'payment_plan' => $deal->payment_plan,
            'deposit_percent' => (int)$deal->deposit_percent,
            'deposit_amount' => $deal->deposit_amount !== null ? (float)$deal->deposit_amount : null,
            'deposit_paid_at' => $deal->deposit_paid_at?->toIso8601String(),
            'next_amount' => $next['amount'],
            'next_part' => $next['part'],
            'balance_ready' => self::balanceReady($deal),
            'invoice_due_on' => $deal->invoice_due_on?->toDateString(),
            'overdue' => self::isOverdue($deal),
            'days_overdue' => self::daysOverdue($deal),
            'late_fee_percent' => self::lateFeePercent() ?: null,
        ];
    }

    /**
     * The scheduler's daily pass. Returns how many reminders went out.
     */
    public static function remindOverdue(): int
    {
        $sent = 0;
        Deal::whereNotNull('invoice_due_on')->whereDate('invoice_due_on', '<', now()->toDateString())
            ->where('payment_status', '!=', 'paid')->where('stage', '!=', 'lost')
            ->where('pay_reminders', '<', count(self::REMINDER_DAYS))
            ->get()->each(function (Deal $deal) use (&$sent) {
                $days = self::daysOverdue($deal);
                $nextAt = self::REMINDER_DAYS[$deal->pay_reminders] ?? null;
                if ($nextAt === null || $days < $nextAt || $deal->pay_reminded_on?->isToday()) {
                    return;
                }
                $next = self::nextPayment($deal);
                $fee = self::lateFeePercent();
                $amount = number_format($next['amount'], 2) . ' ' . strtoupper($deal->currency ?: 'USD');
                ClientNotifier::email($deal, "Payment reminder: {$deal->brand_name}",
                    "A friendly reminder: the invoice of {$amount} was due on " . $deal->invoice_due_on->toFormattedDateString()
                    . " ({$days} day" . ($days === 1 ? '' : 's') . ' ago). You can pay online from your dashboard, or reply if anything is unclear.'
                    . ($fee > 0 ? "\n\nAs agreed in our terms, invoices more than 30 days late may carry a {$fee}% monthly late fee." : ''));
                $deal->forceFill(['pay_reminders' => $deal->pay_reminders + 1, 'pay_reminded_on' => now()->toDateString()])->saveQuietly();
                $sent++;
            });

        return $sent;
    }
}
