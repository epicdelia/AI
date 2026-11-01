<?php

namespace App\Domains\Clients;

use App\Enums\Role;
use App\Models\Deal;
use App\Models\User;

/**
 * Which deals belong to which client. Matching is by email, but only ever against a client
 * whose email is verified, and only once: after that the deal's `user_id` decides (so changing
 * an email later never hands a deal to someone else).
 */
class ClientLink
{
    /** On email confirmation: unclaimed deals sent from that email become theirs. */
    public static function claim(User $user): int
    {
        if ($user->role() !== Role::Client || ! $user->hasVerifiedEmail()) {
            return 0;
        }

        return Deal::whereNull('user_id')->whereRaw('lower(contact_email) = ?', [strtolower($user->email)])
            ->where('stage', '!=', 'prospect')
            ->update(['user_id' => $user->id]);
    }

    /** A new deal (website booking, checkout, her own entry) from a verified client's email. */
    public static function ownerFor(?string $email): ?int
    {
        if (! $email) {
            return null;
        }

        return User::where('role', Role::Client->value)->whereNotNull('email_verified_at')->whereNull('disabled_at')
            ->whereRaw('lower(email) = ?', [strtolower(trim($email))])->value('id');
    }
}
