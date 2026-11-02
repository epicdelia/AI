<?php

namespace App\Domains\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Authenticator-app codes (TOTP, RFC 6238: SHA-1, 6 digits, 30 s), no package needed. Any app
 * works (Google Authenticator, 1Password, Authy…). A code is accepted once (the last used time
 * step is stored), with one step of clock drift either way. Eight one-time recovery codes, kept
 * hashed, get her in if the phone is lost.
 */
class TwoFactor
{
    private const PERIOD = 30;
    private const DIGITS = 6;
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * A fresh secret (160 bits, base32), stored on the user but not active until confirmed.
     */
    public static function start(User $user): string
    {
        $secret = '';
        foreach (str_split(random_bytes(20)) as $byte) {
            $secret .= self::BASE32[ord($byte) & 31];
        }
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_last_step' => null,
        ])->save();

        return $secret;
    }

    /**
     * otpauth:// link: tapping it on a phone, or pasting it, adds the account to the app.
     */
    public static function uri(User $user, string $secret): string
    {
        $issuer = config('app.name', 'Creator Studio');

        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $user->email) . '?' . http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * First correct code turns it on; returns the recovery codes (shown once).
     */
    public static function confirm(User $user, string $code): ?array
    {
        if (!$user->two_factor_secret || !self::verifyCode($user, $code)) {
            return null;
        }
        $codes = self::newRecoveryCodes($user);
        $user->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $codes;
    }

    /**
     * A sign-in code: an authenticator code, or one of the recovery codes (used up).
     */
    public static function check(User $user, string $code): bool
    {
        if (!$user->hasTwoFactor()) {
            return false;
        }
        $code = trim($code);
        if (preg_match('/^\d{6}$/', str_replace(' ', '', $code))) {
            return self::verifyCode($user, str_replace(' ', '', $code));
        }

        return self::useRecoveryCode($user, $code);
    }

    public static function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_last_step' => null,
        ])->save();
    }

    /**
     * @return list<string> plain codes, shown once; only hashes are kept.
     */
    public static function newRecoveryCodes(User $user): array
    {
        $codes = array_map(fn () => Str::lower(Str::random(5) . '-' . Str::random(5)), range(1, 8));
        $user->forceFill([
            'two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), $codes),
        ])->save();

        return $codes;
    }

    public static function recoveryCodesLeft(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    /**
     * The code for a secret at a time step (public for tests).
     */
    public static function at(string $secret, int $step): string
    {
        $key = self::base32Decode($secret);
        $hash = hash_hmac('sha1', pack('N*', 0, $step), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string)($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function currentStep(): int
    {
        return intdiv(time(), self::PERIOD);
    }

    private static function verifyCode(User $user, string $code): bool
    {
        $now = self::currentStep();
        foreach ([$now - 1, $now, $now + 1] as $step) {
            if ($user->two_factor_last_step !== null && $step <= (int)$user->two_factor_last_step) {
                continue; // already used: a code can't be replayed
            }
            if (hash_equals(self::at($user->two_factor_secret, $step), $code)) {
                $user->forceFill([
                    'two_factor_last_step' => $step,
                ])->save();

                return true;
            }
        }

        return false;
    }

    private static function useRecoveryCode(User $user, string $code): bool
    {
        $hashes = $user->two_factor_recovery_codes ?? [];
        foreach ($hashes as $i => $hash) {
            if (Hash::check(Str::lower($code), $hash)) {
                unset($hashes[$i]);
                $user->forceFill([
                    'two_factor_recovery_codes' => array_values($hashes),
                ])->save();

                return true;
            }
        }

        return false;
    }

    private static function base32Decode(string $secret): string
    {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($secret, '='))) as $char) {
            $pos = strpos(self::BASE32, $char);
            if ($pos !== false) {
                $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
            }
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }
}
