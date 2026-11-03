<?php

namespace Tests\Feature;

use App\Domains\Auth\TwoFactor;
use App\Enums\Role;
use App\Models\Deal;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        $this->admin();
    }

    public function test_roles_enum_is_the_single_source_of_abilities(): void
    {
        $this->assertTrue(Role::Admin->allows(Role::STUDIO));
        $this->assertTrue(Role::Admin->allows(Role::CLIENTS_MANAGE));
        $this->assertFalse(Role::Client->allows(Role::STUDIO));
        $this->assertFalse(Role::Client->allows(Role::CLIENTS_MANAGE));
        $this->assertTrue(Role::Client->allows(Role::PORTAL_REQUESTS));
        $this->assertFalse(Role::Admin->requiresTwoFactor());
        $this->assertFalse(Role::Client->requiresTwoFactor());
        $this->assertSame(Role::Client, Role::forSignUp());
    }

    /**
     * Every route of the studio (and of her Clients page), present and future, refuses a client.
     */
    public function test_a_client_gets_403_on_every_studio_and_admin_route(): void
    {
        $this->actingAs($this->client());
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            $mw = $route->gatherMiddleware();
            if (!in_array('ability:studio', $mw, true) && !in_array('ability:clients.manage', $mw, true)) {
                continue;
            }
            $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD');
            $uri = preg_replace_callback('/\{(\w+)\??\}/', function ($m) use ($route) {
                $pattern = $route->wheres[$m[1]] ?? null;
                foreach (['1', 'x', str_repeat('a', 32)] as $sample) {
                    if (!$pattern || preg_match('/^' . $pattern . '$/', $sample)) {
                        return $sample;
                    }
                }

                return '1';
            }, $route->uri());

            $status = $this->json($method, '/' . $uri)->status();
            $this->assertSame(403, $status, "{$method} /{$uri} answered {$status} to a client");
            $checked++;
        }
        $this->assertGreaterThan(300, $checked);
    }

    public function test_two_factor_is_optional_for_admin_and_can_be_enabled_and_disabled(): void
    {
        $admin = User::first();
        $admin->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        $this->actingAs($admin);

        // 2FA is optional: admin can access the studio without it
        $this->getJson('/api/dashboard')->assertOk();

        // Admin can optionally enable 2FA
        $secret = str_replace(' ', '', $this->postJson('/api/account/two-factor/start')->assertOk()->json('secret'));
        $this->postJson('/api/account/two-factor/confirm', [
            'code' => '000000',
        ])->assertStatus(422);
        $codes = $this->postJson('/api/account/two-factor/confirm', [
            'code' => TwoFactor::at($secret, TwoFactor::currentStep()),
        ])
            ->assertOk()->json('recovery_codes');
        $this->assertCount(8, $codes);

        $this->getJson('/api/dashboard')->assertOk();

        // Since it's optional, admin can also disable it
        $this->deleteJson('/api/account/two-factor', [
            'current_password' => 'secret123',
        ])->assertOk();
    }

    public function test_admin_sign_in_needs_the_authenticator_code_once(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'delia@example.com',
            'password' => 'secret123',
        ])
            ->assertOk()->assertJsonPath('two_factor', true);
        $this->assertGuest();

        $this->postJson('/api/auth/two-factor', [
            'code' => '123456',
        ])->assertStatus(422);
        $this->assertGuest();

        $code = TwoFactor::at(self::TWO_FACTOR_SECRET, TwoFactor::currentStep());
        $this->postJson('/api/auth/two-factor', [
            'code' => $code,
        ])->assertOk()->assertJsonPath('user.role', 'admin');
        $this->assertAuthenticated();
        $this->getJson('/api/dashboard')->assertOk();

        // The same code can't be replayed for a second sign-in.
        $this->postJson('/api/auth/logout');
        $this->postJson('/api/auth/login', [
            'email' => 'delia@example.com',
            'password' => 'secret123',
        ])->assertOk();
        $this->postJson('/api/auth/two-factor', [
            'code' => $code,
        ])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_recovery_codes_work_once_and_a_lost_window_resets(): void
    {
        $admin = User::first();
        $codes = TwoFactor::newRecoveryCodes($admin);

        $this->postJson('/api/auth/two-factor', [
            'code' => $codes[0],
        ])->assertStatus(401); // no password step yet

        $this->postJson('/api/auth/login', [
            'email' => 'delia@example.com',
            'password' => 'secret123',
        ])->assertOk();
        $this->postJson('/api/auth/two-factor', [
            'code' => strtoupper($codes[0]),
        ])->assertOk();
        $this->assertSame(7, TwoFactor::recoveryCodesLeft($admin->fresh()));

        $this->postJson('/api/auth/logout');
        $this->postJson('/api/auth/login', [
            'email' => 'delia@example.com',
            'password' => 'secret123',
        ])->assertOk();
        $this->postJson('/api/auth/two-factor', [
            'code' => $codes[0],
        ])->assertStatus(422);
    }

    public function test_sign_up_only_ever_makes_an_unconfirmed_client_and_never_says_who_exists(): void
    {
        Notification::fake();
        $body = [
            'name' => 'Bob Buyer',
            'email' => 'Bob@Brand.test',
            'company' => 'Brand Co',
            'terms' => true,
            'password' => 'buyer-pass-123',
            'password_confirmation' => 'buyer-pass-123',
            'role' => 'admin',
        ];

        $first = $this->postJson('/api/auth/register', $body)->assertStatus(202);
        $this->assertGuest();
        $bob = User::where('email', 'bob@brand.test')->firstOrFail();
        $this->assertSame(Role::Client, $bob->role);
        $this->assertFalse($bob->hasVerifiedEmail());
        Notification::assertSentTo($bob, VerifyEmail::class);

        // Her own email: the same answer, and no new account.
        $again = $this->postJson('/api/auth/register', [
            'email' => 'delia@example.com',
        ] + $body)->assertStatus(202);
        $this->assertSame(str_replace('bob@brand.test', 'X', $first->json('message')), str_replace('delia@example.com', 'X', $again->json('message')));
        $this->assertSame(2, User::count());

        $this->postJson('/api/auth/register', [
            'terms' => false,
            'email' => 'c@c.test',
        ] + $body)->assertStatus(422);
        $this->postJson('/api/auth/register', [
            'password' => 'short1',
            'password_confirmation' => 'short1',
            'email' => 'd@d.test',
        ] + $body)->assertStatus(422);
    }

    public function test_confirming_the_email_links_their_requests_and_unlocks_the_dashboard(): void
    {
        $deal = Deal::create([
            'brand_name' => 'Brand Co',
            'contact_email' => 'BOB@brand.test',
            'stage' => 'inquiry',
            'rate' => 5000,
        ]);
        $prospect = Deal::create([
            'brand_name' => 'Brand Co',
            'contact_email' => 'bob@brand.test',
            'stage' => 'prospect',
        ]);
        $bob = $this->client([
            'email' => 'bob@brand.test',
        ], verified: false);
        $this->actingAs($bob);

        $this->getJson('/api/portal/requests')->assertForbidden(); // email not confirmed yet

        $this->get('/email/verify/' . $bob->id . '/' . sha1('someone@else.test'))->assertForbidden(); // unsigned
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $bob->id,
            'hash' => sha1('wrong@email.test'),
        ]);
        $this->get($url)->assertRedirectContains('login_error=');
        $this->assertFalse($bob->fresh()->hasVerifiedEmail());

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $bob->id,
            'hash' => sha1('bob@brand.test'),
        ]);
        $this->get($url)->assertRedirect('/login?verified=1');
        $this->assertTrue($bob->fresh()->hasVerifiedEmail());
        $this->assertSame($bob->id, $deal->fresh()->user_id);
        $this->assertNull($prospect->fresh()->user_id); // a brand she might pitch is never handed over

        $this->actingAs($bob->fresh());
        $this->getJson('/api/portal/requests')->assertOk()->assertJsonCount(1, 'requests');

        // New website bookings from that email land on their dashboard by themselves.
        $later = Deal::create([
            'brand_name' => 'Brand Co',
            'contact_email' => 'bob@brand.test',
            'stage' => 'inquiry',
        ]);
        $this->assertSame($bob->id, $later->user_id);
    }

    public function test_a_client_sees_only_their_own_requests_and_only_client_safe_fields(): void
    {
        $bob = $this->client([
            'email' => 'bob@brand.test',
        ]);
        $eve = $this->client([
            'email' => 'eve@rival.test',
        ]);
        $mine = Deal::create([
            'brand_name' => 'Brand Co',
            'contact_email' => 'bob@brand.test',
            'stage' => 'inquiry',
            'rate' => 12000,
            'notes' => 'SECRET-NOTE',
            'key_talking_points' => 'SECRET-TALK',
            'do_nots' => 'SECRET-DONT',
            'deliverables' => [[
                'platform' => 'meeting',
                'format' => 'Intro Call: MVP Build',
            ]],
        ]);
        $theirs = Deal::create([
            'brand_name' => 'Rival',
            'contact_email' => 'eve@rival.test',
            'stage' => 'production',
        ]);
        $this->assertSame($bob->id, $mine->user_id);
        $this->assertSame($eve->id, $theirs->user_id);

        $this->actingAs($bob);
        $r = $this->getJson("/api/portal/requests/{$mine->id}")->assertOk();
        $this->assertSame('MVP Build', $r->json('request.title'));
        $this->assertSame('Requested', $r->json('request.status.label'));
        $this->assertNull($r->json('request.amount')); // her internal estimate isn't a price
        foreach (['SECRET-NOTE', 'SECRET-TALK', 'SECRET-DONT', 'lead_score', 'attribution', 'room_token', '12000'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $r->getContent());
        }

        $this->getJson("/api/portal/requests/{$theirs->id}")->assertNotFound();
        $this->getJson("/api/portal/requests/{$theirs->id}/messages")->assertNotFound();
        $this->postJson("/api/portal/requests/{$theirs->id}/messages", [
            'body' => 'hi',
        ])->assertNotFound();
        $this->postJson("/api/portal/requests/{$theirs->id}/accept", [
            'name' => 'Bob',
            'agree' => true,
        ])->assertNotFound();
        $this->getJson('/api/clients')->assertForbidden();
        $this->getJson("/api/deals/{$theirs->id}/messages")->assertForbidden();
    }

    public function test_requests_proposals_and_messages_between_client_and_her(): void
    {
        $bob = $this->client([
            'email' => 'bob@brand.test',
            'company' => 'Brand Co',
        ]);
        $this->actingAs($bob);

        $id = $this->postJson('/api/portal/requests', [
            'service' => 'mvp_build',
            'details' => 'We need an MVP for our booking app.',
            'budget' => '10k',
        ])
            ->assertCreated()->json('request.id');
        $deal = Deal::findOrFail($id);
        $this->assertSame($bob->id, $deal->user_id);
        $this->assertSame('inquiry', $deal->stage);
        $this->assertSame('portal', $deal->source);

        // Her: sees the request and its first message, answers, and sends a proposal.
        $this->actingAs(User::first());
        $this->getJson('/api/clients')->assertOk()->assertJsonPath('unread_total', 1);
        $this->getJson("/api/deals/{$id}/messages")->assertOk()->assertJsonCount(1, 'messages');
        $this->postJson("/api/deals/{$id}/messages", [
            'body' => 'Lovely! Proposal coming tomorrow.',
        ])->assertCreated();
        $deal->update([
            'proposal' => [
                'title' => 'Booking MVP',
                'summary' => 'Four weeks.',
                'investment' => [
                    'amount' => 11000,
                    'currency' => 'USD',
                ],
            ],
            'proposal_at' => now(),
            'stage' => 'pitching',
        ]);

        // Client: one unread message, a proposal to accept, then it's accepted.
        $this->actingAs($bob);
        $o = $this->getJson('/api/portal/overview')->assertOk();
        $this->assertSame(1, $o->json('counts.unread'));
        $this->assertSame('Review and accept the proposal', $o->json('needs_you.0.what'));
        $d = $this->getJson("/api/portal/requests/{$id}")->assertOk();
        $this->assertSame(11000, (int)$d->json('request.amount'));
        $this->assertTrue($d->json('request.can_accept'));
        $this->getJson("/api/portal/requests/{$id}/messages")->assertOk()->assertJsonCount(2, 'messages');
        $this->postJson("/api/portal/requests/{$id}/accept", [
            'name' => 'Bob Buyer',
            'agree' => true,
        ])->assertOk();
        $this->assertSame('contracted', $deal->fresh()->stage);
        $this->assertSame('Accepted', $this->getJson("/api/portal/requests/{$id}")->json('request.status.label'));
        $this->postJson("/api/portal/requests/{$id}/pay")->assertStatus(409); // Stripe isn't set up in tests
    }

    public function test_disabled_clients_are_signed_out_and_cannot_sign_in(): void
    {
        $bob = $this->client([
            'email' => 'bob@brand.test',
        ]);
        $this->actingAs(User::first());
        $this->patchJson("/api/clients/{$bob->id}", [
            'disabled' => true,
        ])->assertOk();
        $this->patchJson('/api/clients/' . User::first()->id, [
            'disabled' => true,
        ])->assertNotFound(); // never an admin

        $this->actingAs($bob->fresh());
        $this->getJson('/api/portal/overview')->assertUnauthorized();

        $this->postJson('/api/auth/login', [
            'email' => 'bob@brand.test',
            'password' => 'client-pass-123',
        ])->assertForbidden();
        $this->assertGuest();
    }

    public function test_clients_manage_their_own_account_and_can_delete_it(): void
    {
        $bob = $this->client([
            'email' => 'bob@brand.test',
        ]);
        $deal = Deal::create([
            'brand_name' => 'Brand Co',
            'contact_email' => 'bob@brand.test',
            'stage' => 'production',
        ]);
        $this->actingAs($bob);

        $this->getJson('/api/account')->assertOk()->assertJsonPath('user.role', 'client')->assertJsonPath('user.can_delete', true);
        $this->patchJson('/api/account', [
            'name' => 'Bob B',
            'company' => 'Brand Co',
            'role' => 'admin',
        ])->assertOk();
        $this->assertSame(Role::Client, $bob->fresh()->role);

        $this->putJson('/api/account/password', [
            'current_password' => 'nope',
            'password' => 'new-pass-1234',
            'password_confirmation' => 'new-pass-1234',
        ])->assertStatus(422);
        $this->putJson('/api/account/password', [
            'current_password' => 'client-pass-123',
            'password' => 'new-pass-1234',
            'password_confirmation' => 'new-pass-1234',
        ])->assertOk();

        $this->deleteJson('/api/account', [
            'current_password' => 'new-pass-1234',
        ])->assertOk();
        $this->assertGuest();
        $this->assertNull(User::find($bob->id));
        $this->assertNull($deal->fresh()->user_id); // her record of the work stays

        $this->actingAs(User::first());
        $this->deleteJson('/api/account', [
            'current_password' => 'secret123',
        ])->assertForbidden();
    }

    public function test_demo_client_login_is_off_unless_enabled_and_is_only_ever_a_client(): void
    {
        $this->postJson('/api/auth/demo-client')->assertNotFound();
        $this->assertFalse($this->getJson('/api/auth/status')->json('welcome.demo_client'));

        config([
            'services.demo_client.enabled' => true,
        ]);
        $this->assertTrue($this->getJson('/api/auth/status')->json('welcome.demo_client'));
        $this->postJson('/api/auth/demo-client')->assertOk()->assertJsonPath('user.role', 'client')->assertJsonPath('user.email_verified', true);
        $this->assertAuthenticated();

        // Sample requests in every state, its own only.
        $r = $this->getJson('/api/portal/requests')->assertOk();
        $this->assertCount(3, $r->json('requests'));
        $this->assertSame(['Proposal', 'In progress', 'Paid'], collect($r->json('requests'))->sortBy('id')->pluck('status.label')->values()->all());
        $this->assertCount(1, $this->getJson('/api/portal/billing')->json('payments'));
        $this->getJson('/api/dashboard')->assertForbidden();

        // A second click reuses it (no duplicate data), even if it was switched off.
        \App\Domains\Clients\DemoClient::account()->forceFill([
            'disabled_at' => now(),
        ])->save();
        $this->postJson('/api/auth/logout');
        $this->postJson('/api/auth/demo-client')->assertOk();
        $this->assertSame(1, User::where('email', \App\Domains\Clients\DemoClient::EMAIL)->count());
        $this->assertSame(3, Deal::where('source', 'demo')->count());
    }

    public function test_status_tells_the_dashboard_the_role_and_keeps_her_settings_from_clients(): void
    {
        Setting::put('motto', 'her private motto');
        $this->actingAs($this->client([
            'email' => 'bob@brand.test',
        ]));
        $s = $this->getJson('/api/auth/status')->assertOk();
        $this->assertSame('client', $s->json('user.role'));
        $this->assertSame(Role::Client->abilities(), $s->json('user.abilities'));
        $this->assertNull($s->json('prefs'));
        $this->assertNull($s->json('love'));
        $this->assertStringNotContainsString('her private motto', $s->getContent());
    }
}
