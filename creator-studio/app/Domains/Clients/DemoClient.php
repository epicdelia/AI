<?php

namespace App\Domains\Clients;

use App\Enums\Role;
use App\Models\Deal;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * A demo client for trying the client dashboard: one click on the sign-in page logs in as it.
 * Off unless DEMO_CLIENT_LOGIN=true (dev and testing only: on a public server anyone could use
 * it). It's always a client (never an admin), and its sample requests are tagged `demo` with
 * their own email, so they never mix with real clients.
 */
class DemoClient
{
    public const EMAIL = 'demo-client@studio.test';

    public static function enabled(): bool
    {
        return (bool)config('services.demo_client.enabled');
    }

    /**
     * The demo account (created with sample requests the first time).
     */
    public static function account(): User
    {
        $user = User::where('email', self::EMAIL)->first();
        if (!$user) {
            $user = new User([
                'name' => 'Demo Client',
                'email' => self::EMAIL,
                'company' => 'Demo Client Co',
                'password' => Str::random(40),
            ]);
            $user->forceFill([
                'role' => Role::Client,
                'email_verified_at' => now(),
            ])->save();
        }
        // Whatever happened to it, the demo is a working client account.
        $user->forceFill([
            'role' => Role::Client,
            'disabled_at' => null,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        if (!$user->deals()->exists()) {
            self::seed($user);
        }

        return $user;
    }

    /**
     * Three requests that show every state: a proposal to accept, work in progress, a paid one.
     */
    private static function seed(User $user): void
    {
        $base = [
            'brand_name' => 'Demo Client Co',
            'contact_name' => 'Demo Client',
            'contact_email' => self::EMAIL,
            'currency' => 'USD',
            'source' => 'demo',
        ];

        $proposal = self::deal($user, $base + [
            'stage' => 'pitching',
            'rate' => 12000,
            'payment_status' => 'pending',
            'proposal_at' => now()->subDay(),
            'deliverables' => [[
                'platform' => 'request',
                'format' => 'MVP Build (2-4 Weeks)',
                'project_type' => 'mvp_build',
            ]],
            'proposal' => [
                'title' => 'Booking app MVP (demo)',
                'summary' => 'A four-week build of the booking flow, admin and payments.',
                'investment' => [
                    'amount' => 12000,
                    'currency' => 'USD',
                ],
            ],
            'meeting_at' => now()->addDays(3)->setTime(15, 0),
        ]);
        $proposal->messages()->create([
            'from_admin' => true,
            'body' => 'Hi! Here’s the proposal we talked about. Questions welcome right here. (Demo message)',
        ]);

        self::deal($user, $base + [
            'stage' => 'production',
            'rate' => 3500,
            'payment_status' => 'pending',
            'accepted_at' => now()->subWeek(),
            'accepted_by' => 'Demo Client',
            'deliverables' => [
                [
                    'platform' => 'request',
                    'format' => 'Advisory Retainer ($3,500/mo)',
                    'project_type' => 'retainer',
                ],
                [
                    'format' => 'Architecture review',
                    'due_date' => now()->addDays(5)->toDateString(),
                    'status' => 'in progress',
                ],
                [
                    'format' => 'Kick-off notes',
                    'status' => 'delivered',
                    'url' => 'https://example.com/demo-notes',
                ],
            ],
            'proposal' => [
                'title' => 'Advisory retainer (demo)',
                'summary' => 'Monthly architecture and AI advice.',
                'investment' => [
                    'amount' => 3500,
                    'currency' => 'USD',
                ],
            ],
        ]);

        $paid = self::deal($user, $base + [
            'stage' => 'paid',
            'rate' => 2000,
            'payment_status' => 'paid',
            'paid_at' => now()->subMonth(),
            'accepted_at' => now()->subMonths(2),
            'accepted_by' => 'Demo Client',
            'deliverables' => [[
                'platform' => 'request',
                'format' => 'Architecture & AI Technical Consulting',
                'project_type' => 'consulting',
            ]],
        ]);
        Payment::create([
            'deal_id' => $paid->id,
            'amount' => 2000,
            'currency' => 'USD',
            'kind' => 'checkout',
            'paid_at' => now()->subMonth(),
        ]);
    }

    private static function deal(User $user, array $attributes): Deal
    {
        $deal = new Deal($attributes);
        $deal->user_id = $user->id;
        $deal->save();

        return $deal;
    }
}
