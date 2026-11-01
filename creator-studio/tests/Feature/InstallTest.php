<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class InstallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
    }

    public function test_install_status_returns_system_info(): void
    {
        $response = $this->getJson('/api/install/status')->assertOk();

        $response->assertJsonStructure([
            'installed',
            'database' => ['connected', 'default'],
            'server' => [
                'php_version',
                'php_ok',
                'extensions',
                'storage_writable',
                'cache_writable',
                'has_app_key',
            ],
        ]);
        $this->assertTrue($response->json('database.connected'));
        $this->assertTrue($response->json('server.php_ok'));
    }

    public function test_test_database_connection_sqlite(): void
    {
        $response = $this->postJson('/api/install/test-db', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ])->assertOk();

        $this->assertTrue($response->json('ok'));
    }

    public function test_first_account_is_the_admin_and_sign_up_waits_for_it(): void
    {
        // No studio yet: public sign-up can't be used to grab the first (admin) account.
        $this->postJson('/api/auth/register', [
            'name' => 'Stranger', 'email' => 'stranger@evil.test', 'terms' => true,
            'password' => 'supersecret123', 'password_confirmation' => 'supersecret123',
        ])->assertStatus(409);

        $this->postJson('/api/auth/setup', [
            'name' => 'Delia Lazarescu', 'email' => 'Delia@TechUnicorn.test',
            'password' => 'supersecret123', 'password_confirmation' => 'supersecret123',
        ])->assertOk()->assertJsonPath('user.role', 'admin')->assertJsonPath('user.two_factor.required', true);

        $this->assertAuthenticated();
        $this->assertSame('delia@techunicorn.test', Auth::user()->email);
        $this->assertSame('Delia Lazarescu', Setting::get('creator_name'));
        $this->assertTrue(Auth::user()->isAdmin());
    }

    public function test_setup_validates_password_strength_and_confirmation(): void
    {
        $base = ['name' => 'Delia Lazarescu', 'email' => 'delia@techunicorn.test'];
        $this->postJson('/api/auth/setup', $base + ['password' => 'supersecret123', 'password_confirmation' => 'mismatchedpass1'])->assertStatus(422);
        $this->postJson('/api/auth/setup', $base + ['password' => 'onlyletters', 'password_confirmation' => 'onlyletters'])->assertStatus(422);
        $this->assertDatabaseCount('users', 0);
    }
}
