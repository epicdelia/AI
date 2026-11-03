<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        config([
            'app.url' => 'https://studio.test',
        ]);
        // Right after install: her admin account, before she has turned on two-factor.
        $this->admin([
            'name' => 'Delia Lazarescu',
            'email' => 'delia@example.com',
        ], twoFactor: false);
    }

    public function test_status_shows_only_first_name_love_and_google_before_sign_in(): void
    {
        $r = $this->getJson('/api/auth/status')->assertOk();
        $this->assertSame([
            'name' => 'Delia',
            'accent' => 'rose',
            'love' => true,
            'google' => false,
            'demo_client' => false,
        ], $r->json('welcome'));
        $this->assertNull($r->json('love'));
        $this->assertStringNotContainsString('delia@example.com', $r->getContent());

        Setting::put('youtube_client_id', 'gid');
        Setting::put('youtube_client_secret', 'gsecret');
        Setting::put('love_notes', '0');
        $this->assertSame([
            'name' => 'Delia',
            'accent' => 'rose',
            'love' => false,
            'google' => true,
            'demo_client' => false,
        ], $this->getJson('/api/auth/status')->json('welcome'));
    }

    public function test_remember_me_is_respected(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'delia@example.com',
            'password' => 'secret123',
            'remember' => false,
        ])->assertOk();
        $this->assertNull(Auth::user()->remember_token);
        Auth::logout();
        $this->postJson('/api/auth/login', [
            'email' => 'delia@example.com',
            'password' => 'secret123',
            'remember' => true,
        ])->assertOk();
        $this->assertNotNull(User::first()->remember_token);
    }

    public function test_google_login_only_for_her_verified_email(): void
    {
        $this->get('/auth/google/redirect')->assertRedirectContains('login_error=');
        Setting::put('youtube_client_id', 'gid');
        Setting::put('youtube_client_secret', 'gsecret');

        $go = $this->get('/auth/google/redirect?remember=1');
        $go->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth');
        parse_str(parse_url($go->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertSame('https://studio.test/auth/google/callback', $q['redirect_uri']);
        $this->assertSame('openid email', $q['scope']);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'AT',
            ]),
            'openidconnect.googleapis.com/*' => Http::sequence()
                ->push([
                    'email' => 'someone@else.com',
                    'email_verified' => true,
                ])
                ->push([
                    'email' => 'Delia@Example.com',
                    'email_verified' => true,
                ]),
        ]);
        // wrong account
        $this->get("/auth/google/callback?state={$q['state']}&code=C")->assertRedirectContains('login_error=');
        $this->assertGuest();
        // forged state
        $this->get('/auth/google/redirect');
        $this->get('/auth/google/callback?state=forged&code=C')->assertRedirectContains('expired');
        $this->assertGuest();
        // her account
        $go = $this->get('/auth/google/redirect');
        parse_str(parse_url($go->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->get("/auth/google/callback?state={$q['state']}&code=C")->assertRedirect('/');
        $this->assertAuthenticatedAs(User::first());
    }

    public function test_password_reset_command(): void
    {
        $this->artisan('studio:password')
            ->expectsQuestion('New password for delia@example.com (at least 8 characters)', 'brand-new-pass')
            ->expectsQuestion('Type it again', 'brand-new-pass')
            ->assertExitCode(0);
        $this->assertTrue(Hash::check('brand-new-pass', User::first()->password));
        $this->artisan('studio:password')
            ->expectsQuestion('New password for delia@example.com (at least 8 characters)', 'short')
            ->assertExitCode(1); // too short: rejected before asking again
    }

    public function test_in_app_password_reset_flow(): void
    {
        // 1. Request code: it goes out by email, never back in the response
        $forgot = $this->postJson('/api/auth/forgot-password', [
            'email' => 'Delia@Example.com',
        ])->assertOk();
        $this->assertNull($forgot->json('code'));
        $code = $this->sentResetCode();
        $this->assertStringNotContainsString($code, $forgot->getContent());

        // 2. Reset password
        $this->postJson('/api/auth/reset-password', [
            'email' => 'delia@example.com',
            'code' => $code,
            'password' => 'new-secret-password-123',
            'password_confirmation' => 'new-secret-password-123',
        ])->assertOk();

        // A reset never signs anyone in (that would skip two-factor): she signs in again.
        $this->assertGuest();
        $this->assertTrue(Hash::check('new-secret-password-123', User::first()->fresh()->password));
        $this->postJson('/api/auth/login', [
            'email' => 'delia@example.com',
            'password' => 'new-secret-password-123',
        ])->assertOk();
        $this->assertAuthenticated();
    }

    public function test_reset_for_an_unknown_email_never_falls_back_to_her_account(): void
    {
        $forgot = $this->postJson('/api/auth/forgot-password', [
            'email' => 'attacker@evil.test',
        ])->assertOk();
        $this->assertStringNotContainsString('delia@example.com', $forgot->getContent());
        $this->assertCount(0, app('mailer')->getSymfonyTransport()->messages());
        $this->assertDatabaseCount('password_reset_tokens', 0);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'attacker@evil.test',
            'code' => '123456',
            'password' => 'taken-over-1234',
            'password_confirmation' => 'taken-over-1234',
        ])->assertStatus(400);
        $this->assertGuest();
    }

    public function test_five_wrong_codes_burn_the_reset_code(): void
    {
        $this->postJson('/api/auth/forgot-password', [
            'email' => 'delia@example.com',
        ])->assertOk();
        $code = $this->sentResetCode();
        $wrong = $code === '111111' ? '222222' : '111111';
        $body = [
            'email' => 'delia@example.com',
            'password' => 'new-secret-1234',
            'password_confirmation' => 'new-secret-1234',
        ];

        foreach (range(1, 4) as $_) {
            $this->postJson('/api/auth/reset-password', $body + [
                'code' => $wrong,
            ])->assertStatus(422);
        }
        $this->postJson('/api/auth/reset-password', $body + [
            'code' => $wrong,
        ])->assertStatus(429);
        $this->postJson('/api/auth/reset-password', $body + [
            'code' => $code,
        ])->assertStatus(400);
        $this->assertGuest();
    }

    public function test_install_endpoints_are_locked_once_installed(): void
    {
        $this->postJson('/api/install/test-db', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ])->assertForbidden();
        $this->postJson('/api/install/setup-db', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ])->assertForbidden();
        $this->postJson('/api/install/run-migrations')->assertForbidden();
    }

    private function sentResetCode(): string
    {
        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame('delia@example.com', $messages->first()->getEnvelope()->getRecipients()[0]->getAddress());
        $this->assertMatchesRegularExpression('/code is (\d{6})/', $messages->first()->toString());
        preg_match('/code is (\d{6})/', $messages->first()->toString(), $m);

        return $m[1];
    }
}
