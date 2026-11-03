<?php

namespace App\Domains\Community;

use App\Domains\Community\Models\CommunityMembership;
use App\Enums\Role;
use App\Http\Controllers\Api\AuthController;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Members invite friends with a personal link. A friend who joins earns the inviter points;
 * a friend who buys Pro earns them a free month of Pro (or bonus points if they already
 * pay for Pro, since a running Stripe subscription isn't changed from here).
 * Limits: 10 rewarded sign-ups a month, never yourself, never the same network twice in 30 days.
 */
class MemberReferrals
{
    public const MONTHLY_CAP = 10;

    public const PRO_BONUS_POINTS = 50;

    public static function code(User $user): string
    {
        if (!$user->member_ref_code) {
            do {
                $code = strtolower(Str::random(8));
            } while (User::where('member_ref_code', $code)->exists());
            $user->forceFill(['member_ref_code' => $code])->save();
        }

        return $user->member_ref_code;
    }

    public static function link(User $user): string
    {
        return url('/login?signup=1&ref=' . self::code($user));
    }

    /**
     * After a member signs up with ?ref=…
     */
    public static function record(User $newUser, ?string $code, ?string $ip): bool
    {
        if (!$code || $newUser->role() !== Role::Member) {
            return false;
        }
        $referrer = User::where('member_ref_code', strtolower($code))->whereNull('disabled_at')->first();
        if (!$referrer || $referrer->id === $newUser->id || strcasecmp($referrer->email, $newUser->email) === 0) {
            return false;
        }
        $ipHash = $ip ? hash('sha256', $ip . '|' . config('app.key')) : null;
        $sameNetwork = $ipHash && DB::table('member_referrals')->where('referrer_id', $referrer->id)->where('ip_hash', $ipHash)
            ->where('created_at', '>=', now()->subDays(30))->exists();
        $thisMonth = DB::table('member_referrals')->where('referrer_id', $referrer->id)->where('created_at', '>=', now()->startOfMonth())->count();
        DB::table('member_referrals')->insertOrIgnore([
            'referrer_id' => $referrer->id, 'referred_id' => $newUser->id, 'ip_hash' => $ipHash,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($sameNetwork || $thisMonth >= self::MONTHLY_CAP) {
            return false; // recorded, not rewarded
        }
        Community::award($referrer, 'referred');

        return true;
    }

    /**
     * The referred member just bought Pro.
     */
    public static function proPurchased(User $buyer): void
    {
        $row = DB::table('member_referrals')->where('referred_id', $buyer->id)->whereNull('pro_rewarded_at')->first();
        if (!$row || !($referrer = User::find($row->referrer_id))) {
            return;
        }
        $m = CommunityMembership::where('user_id', $referrer->id)->first();
        if ($m && $m->stripe_subscription_id && $m->isActive()) {
            // A paid subscription: bonus points instead of touching their Stripe billing.
            for ($i = 0; $i < self::PRO_BONUS_POINTS / Community::POINTS['referred']; $i++) {
                Community::award($referrer, 'referred');
            }
            $gift = self::PRO_BONUS_POINTS . ' bonus points';
        } elseif ($m && $m->status === 'comped') {
            $gift = null; // already free forever
        } else {
            $from = $m && $m->isActive() && $m->current_period_end ? $m->current_period_end : now();
            CommunityMembership::updateOrCreate(['user_id' => $referrer->id], [
                'tier' => 'pro', 'status' => 'active', 'stripe_subscription_id' => null,
                'current_period_end' => $from->copy()->addDays(30),
            ]);
            $gift = 'a free month of Pro';
        }
        DB::table('member_referrals')->where('id', $row->id)->update(['pro_rewarded_at' => now()]);
        if ($gift && ($referrer->notify_email ?? true)) {
            AuthController::mail($referrer->email, 'Thanks for the referral!', "Hi {$referrer->name},\n\nSomeone you invited just joined Pro, so you get {$gift}. Thank you!\n\n" . url('/community'));
        }
    }

    public static function stats(User $user): array
    {
        return [
            'link' => self::link($user),
            'joined' => DB::table('member_referrals')->where('referrer_id', $user->id)->count(),
            'went_pro' => DB::table('member_referrals')->where('referrer_id', $user->id)->whereNotNull('pro_rewarded_at')->count(),
            'cap' => self::MONTHLY_CAP,
        ];
    }
}
