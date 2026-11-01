<?php

namespace App\Domains\Security;

use App\Domains\Security\Models\AuditLog;
use App\Http\Controllers\Api\AuthController;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * The security audit log: sign-ins, two-factor and password changes, client access changes,
 * invites, key changes, database downloads. Never blocks the action it records. Sign-ins carry
 * a device fingerprint (browser + network); a sign-in from a device not seen before emails the
 * account owner (newDeviceCheck).
 */
class Audit
{
    public const LABELS = [
        'login' => 'Signed in',
        'login_failed' => 'Wrong password',
        'two_factor_failed' => 'Wrong two-factor code',
        'two_factor_on' => 'Two-factor turned on',
        'two_factor_off' => 'Two-factor turned off',
        'recovery_codes' => 'New recovery codes',
        'password_changed' => 'Password changed',
        'password_reset' => 'Password reset by email code',
        'email_changed' => 'Email changed',
        'sessions_ended' => 'Signed out other devices',
        'account_deleted' => 'Account deleted',
        'client_disabled' => 'Client access switched off',
        'client_enabled' => 'Client access switched on',
        'client_verified' => 'Client email confirmed by hand',
        'deal_linked' => 'Deal linked to a client',
        'deal_unlinked' => 'Deal unlinked from a client',
        'client_invited' => 'Client invited to the dashboard',
        'keys_changed' => 'API keys changed',
        'backup_downloaded' => 'Database backup downloaded',
        'subscribers_exported' => 'Subscribers exported',
        'share_link_revoked' => 'Share link revoked',
        'automations_paused' => 'Automations paused',
        'automations_resumed' => 'Automations resumed',
        'team_member_added' => 'Teammate joined',
        'team_member_removed' => 'Teammate removed',
        'contract_signed' => 'Agreement signed & verified',
        'deliverable_annotated' => 'Deliverable feedback added',
        'deliverable_resolved' => 'Deliverable revision resolved',
        'brand_vault_updated' => 'Brand assets updated',
    ];

    public static function log(string $action, ?User $user = null, ?Model $subject = null, array $meta = []): void
    {
        rescue(function () use ($action, $user, $subject, $meta) {
            $request = request();
            AuditLog::create([
                'user_id' => ($user ?? $request?->user())?->id,
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'meta' => $meta ?: null,
                'ip' => $request?->ip(),
                'user_agent' => mb_substr((string) $request?->userAgent(), 0, 255),
                'device' => $request ? self::device($request) : null,
            ]);
        }, null, true);
    }

    /** Browser family + OS + the network (/24 for IPv4, /48 for IPv6): stable enough, not a tracker. */
    public static function device(Request $request): string
    {
        $ip = (string) $request->ip();
        $net = str_contains($ip, ':') ? implode(':', array_slice(explode(':', $ip), 0, 3)) : implode('.', array_slice(explode('.', $ip), 0, 3));

        return hash('sha256', self::browser((string) $request->userAgent()).'|'.$net);
    }

    /** "Chrome on Windows"-style, from the user agent. */
    public static function browser(string $ua): string
    {
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'A browser',
        };
        $os = match (true) {
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'an unknown system',
        };

        return "{$browser} on {$os}";
    }

    /** Record a sign-in; email the owner when it's from a device this account hasn't used before. */
    public static function signedIn(User $user, Request $request): void
    {
        $device = self::device($request);
        $known = AuditLog::where('user_id', $user->id)->where('action', 'login')->where('device', $device)->exists();
        $first = ! AuditLog::where('user_id', $user->id)->where('action', 'login')->exists();
        self::log('login', $user);

        if (! $known && ! $first) {
            $when = now()->toDayDateTimeString();
            AuthController::mail($user->email, 'New sign-in to your account',
                "Your account was just signed in from a new device:\n\n".self::browser((string) $request->userAgent())."\nIP {$request->ip()}\n{$when} (server time)\n\n"
                ."If this was you, there's nothing to do.\n\nIf it wasn't: reset your password at ".url('/login')." (Forgot password?), then use \"Sign out other devices\" in Account & security.");
        }
    }
}
