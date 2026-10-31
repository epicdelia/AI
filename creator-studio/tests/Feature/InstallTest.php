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

    public function test_registration_creates_user_and_logs_in(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Delia Lazarescu',
            'email' => 'delia@techunicorn.test',
            'password' => 'supersecret123',
            'password_confirmation' => 'supersecret123',
        ])->assertCreated();

        $this->assertAuthenticated();
        $this->assertSame('delia@techunicorn.test', Auth::user()->email);
        $this->assertSame('Delia Lazarescu', Setting::get('creator_name'));
        $this->assertDatabaseHas('users', ['email' => 'delia@techunicorn.test']);
    }

    public function test_registration_validates_password_confirmation(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Delia Lazarescu',
            'email' => 'delia@techunicorn.test',
            'password' => 'supersecret123',
            'password_confirmation' => 'mismatchedpass',
        ])->assertStatus(422);
    }
}
