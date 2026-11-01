<?php

namespace App\Domains\Clients;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Security\Audit;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Viral B2B Partner Referral & Credit Flywheel.
 * Attributes partner referrals, tracks reward balances, and enables
 * clients to redeem credits toward upcoming sponsorships or retainers.
 */
class PartnerReferrals
{
    public const REWARD_FIXED_AMOUNT = 1000.00;
    public const REWARD_PERCENT = 0.10;

    public static function codeFor(User $user): string
    {
        return self::ensureCode($user);
    }

    public static function ensureCode(User $user): string
    {
        if ($user->referral_code) {
            return $user->referral_code;
        }

        $clean = preg_replace('/[^a-z0-9]/', '', strtolower($user->company ?: $user->name));
        $prefix = substr($clean ?: 'partner', 0, 10);
        $code = $prefix.'-'.strtolower(Str::random(6));

        $user->forceFill(['referral_code' => $code])->saveQuietly();
        return $code;
    }

    public static function linkFor(User $user): string
    {
        $code = self::ensureCode($user);
        return url('/?ref=partner-'.$code);
    }

    public static function summary(User $user): array
    {
        $code = self::ensureCode($user);
        $ownerId = $user->teamOwnerId();

        $referredDeals = Deal::where('referral_partner_id', $ownerId)
            ->latest()
            ->get()
            ->map(fn (Deal $d) => [
                'id' => $d->id,
                'brand_name' => $d->brand_name,
                'status' => $d->payment_status === 'paid' ? 'Completed' : 'In Progress',
                'credit_earned' => (float) $d->referral_credit_earned,
                'created_at' => $d->created_at?->toIso8601String(),
            ])
            ->values();

        $totalEarned = (float) Deal::where('referral_partner_id', $ownerId)->sum('referral_credit_earned');
        $availableBalance = (float) ($user->referral_credits ?? 0.00);

        return [
            'referral_code' => $code,
            'partner_link' => self::linkFor($user),
            'incentive_text' => 'Give $500 off to partner brands & earn $1,000 credit (or 10%) on every completed sponsorship.',
            'available_credits' => $availableBalance,
            'total_credits_earned' => $totalEarned,
            'referred_count' => $referredDeals->count(),
            'referrals' => $referredDeals,
        ];
    }

    public static function attributePayment(Deal $deal): void
    {
        if (! $deal->referral_partner_id || (float) $deal->referral_credit_earned > 0) {
            return;
        }

        $referrer = User::find($deal->referral_partner_id);
        if (! $referrer) {
            return;
        }

        $credit = max(self::REWARD_FIXED_AMOUNT, round((float) $deal->rate * self::REWARD_PERCENT, 2));

        $deal->update(['referral_credit_earned' => $credit]);
        $referrer->increment('referral_credits', $credit);

        Audit::log('deal_linked', $referrer, $deal, [
            'referral_reward' => $credit,
            'brand' => $deal->brand_name,
        ]);
        AgentEvent::log('lead', "Partner reward unlocked: {$referrer->name} earned $".number_format($credit)." credit from {$deal->brand_name}.");
    }
}
