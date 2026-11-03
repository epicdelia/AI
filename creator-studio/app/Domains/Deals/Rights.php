<?php

namespace App\Domains\Deals;

use App\Domains\Clients\ClientNotifier;
use App\Http\Controllers\Api\PortalController;
use App\Models\Deal;
use App\Models\Setting;

/**
 * Usage rights, whitelisting and exclusivity as their own priced, dated line items. They
 * start when the content goes live, end on a date, and 14 days before that the client and
 * she both hear "ends soon: extend?" (the client can pay the extension online).
 */
class Rights
{
    public const KINDS = [
        'usage' => 'Paid usage rights',
        'whitelisting' => 'Whitelisting (ads from her handle)',
        'exclusivity' => 'Category exclusivity',
    ];

    public const DAYS = [30, 60, 90, 180, 365];

    public const REMIND_DAYS_BEFORE = 14;

    /**
     * Her suggested price: a percentage of the deal's fee per 30 days (Setting rights_pricing,
     * e.g. {"usage":15,"whitelisting":20,"exclusivity":10}); 0 = no suggestion.
     */
    public static function suggestedPrice(Deal $deal, string $kind, int $days): float
    {
        $pct = (float)((json_decode((string)Setting::get('rights_pricing', ''), true) ?: [])[$kind] ?? 0);

        return $pct > 0 ? round((float)$deal->rate * $pct / 100 * ($days / 30), 2) : 0.0;
    }

    public static function startAll(Deal $deal): void
    {
        DealRight::where('deal_id', $deal->id)->whereNull('starts_on')->get()->each(function (DealRight $r) use ($deal) {
            $start = $deal->published_at ?? now();
            $r->update(['starts_on' => $start->toDateString(), 'ends_on' => $start->copy()->addDays($r->days)->toDateString()]);
        });
    }

    /**
     * Extend by the same length (after the extension is paid, or by her).
     */
    public static function extend(DealRight $r): void
    {
        $from = $r->ends_on && $r->ends_on->isFuture() ? $r->ends_on : now();
        $r->update([
            'starts_on' => $r->starts_on ?? now()->toDateString(),
            'ends_on' => $from->copy()->addDays($r->days)->toDateString(),
            'status' => 'active',
            'reminded_at' => null,
            'renewals' => $r->renewals + 1,
        ]);
    }

    /**
     * Daily: remind about rights ending within 14 days; mark ended ones. Returns reminders sent.
     */
    public static function remindEnding(): int
    {
        DealRight::where('status', 'active')->whereNotNull('ends_on')->whereDate('ends_on', '<', now()->toDateString())->update(['status' => 'ended']);
        $sent = 0;
        DealRight::with('deal')->where('status', 'active')->whereNull('reminded_at')->whereNotNull('ends_on')
            ->whereDate('ends_on', '<=', now()->addDays(self::REMIND_DAYS_BEFORE)->toDateString())
            ->get()->each(function (DealRight $r) use (&$sent) {
                if (!$r->deal) {
                    return;
                }
                $label = self::KINDS[$r->kind] ?? $r->kind;
                $line = "{$label} for “{$r->deal->brand_name}” end on " . $r->ends_on->toFormattedDateString()
                    . ". To keep using the content, extend for another {$r->days} days from your dashboard.";
                ClientNotifier::email($r->deal, "Ends soon: {$label}", $line);
                PortalController::tellAdmins("Rights ending: {$r->deal->brand_name}", $line . "\n\n" . url('/deals'));
                $r->update(['reminded_at' => now()]);
                $sent++;
            });

        return $sent;
    }

    public static function row(DealRight $r): array
    {
        return [
            'id' => $r->id,
            'kind' => $r->kind,
            'label' => self::KINDS[$r->kind] ?? $r->kind,
            'scope' => $r->scope,
            'days' => $r->days,
            'price' => (float)$r->price,
            'starts_on' => $r->starts_on?->toDateString(),
            'ends_on' => $r->ends_on?->toDateString(),
            'status' => $r->status,
            'ends_soon' => $r->status === 'active' && $r->ends_on && $r->ends_on->lte(now()->addDays(self::REMIND_DAYS_BEFORE)),
            'renewals' => $r->renewals,
        ];
    }
}
