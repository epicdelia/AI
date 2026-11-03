<?php

namespace Tests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * The authenticator secret every test admin has (see admin()).
     */
    protected const TWO_FACTOR_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    protected function setUp(): void
    {
        parent::setUp();
        // No test may reach a real API: anything not matched by Http::fake() throws.
        Http::preventStrayRequests();
    }

    /**
     * Her account as the studio really has it: an admin (App\Enums\Role) with a confirmed email
     * and two-factor sign-in on. The same email twice returns the same account.
     */
    protected function admin(array $attributes = [], bool $twoFactor = true): User
    {
        $email = $attributes['email'] ?? 'delia@example.com';
        if ($existing = User::where('email', $email)->first()) {
            return $existing;
        }
        $user = new User([
            'email' => $email,
        ] + $attributes + [
            'name' => 'Delia',
            'password' => 'secret123',
        ]);
        $user->forceFill([
            'role' => Role::Admin,
            'email_verified_at' => now(),
        ]
            + ($twoFactor ? [
                'two_factor_secret' => self::TWO_FACTOR_SECRET,
                'two_factor_confirmed_at' => now(),
            ] : []))->save();

        return $user;
    }

    /**
     * A client who signed up on her website (email confirmed unless $verified is false).
     */
    protected function client(array $attributes = [], bool $verified = true): User
    {
        $user = new User($attributes + [
            'name' => 'Acme Buyer',
            'email' => 'buyer@acme.test',
            'password' => 'client-pass-123',
        ]);
        $user->forceFill([
            'role' => Role::Client,
            'email_verified_at' => $verified ? now() : null,
        ])->save();

        return $user;
    }

    /**
     * A team member (editor, designer…) she provisioned: content studio, no private side.
     */
    protected function staff(array $attributes = []): User
    {
        if ($existing = User::where('email', $attributes['email'] ?? 'alex@studio.test')->first()) {
            return $existing;
        }
        $user = new User($attributes + [
            'name' => 'Alex Editor',
            'email' => 'alex@studio.test',
            'password' => 'staff-pass-123',
        ]);
        $user->forceFill([
            'role' => Role::Staff,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    /**
     * A follower who signed up for the community.
     */
    protected function member(array $attributes = []): User
    {
        if ($existing = User::where('email', $attributes['email'] ?? 'fan@follow.test')->first()) {
            return $existing;
        }
        $user = new User($attributes + [
            'name' => 'Fan Follower',
            'email' => 'fan@follow.test',
            'password' => 'member-pass-123',
        ]);
        $user->forceFill([
            'role' => Role::Member,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }
}
