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
        config(['app.url' => 'https://studio.test']);
        User::create(['name' => 'Delia Lazarescu', 'email' => 'delia@example.com', 'password' => 'secret123']);
    }

    public function test_status_shows_only_first_name_love_and_google_before_sign_in(): void
    {
        $r = $this->getJson('/api/auth/status')->assertOk();
        $this->assertSame(['name' => 'Delia', 'love' => true, 'google' => false], $r->json('welcome'));
        $this->assertNull($r->json('love'));
        $this->assertStringNotContainsString('delia@example.com', $r->getContent());

        Setting::put('youtube_client_id', 'gid');
        Setting::put('youtube_client_secret', 'gsecret');
        Setting::put('love_notes', '0');
        $this->assertSame(['name' => 'Delia', 'love' => false, 'google' => true], $this->getJson('/api/auth/status')->json('welcome'));
    }

    public function test_remember_me_is_respected(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'delia@example.com', 'password' => 'secret123', 'remember' => false])->assertOk();
        $this->assertNull(Auth::user()->remember_token);
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => 'delia@example.com', 'password' => 'secret123', 'remember' => true])->assertOk();
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
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'AT']),
            'openidconnect.googleapis.com/*' => Http::sequence()
                ->push(['email' => 'someone@else.com', 'email_verified' => true])
                ->push(['email' => 'Delia@Example.com', 'email_verified' => true]),
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
}
