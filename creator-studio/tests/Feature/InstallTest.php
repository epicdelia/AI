<?php

namespace Tests\Feature;

use App\Models\Setting;
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
        if (file_exists(storage_path('installed'))) {
            @unlink(storage_path('installed'));
        }
    }

    protected function tearDown(): void
    {
        if (file_exists(storage_path('installed'))) {
            @unlink(storage_path('installed'));
        }
        parent::tearDown();
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
            'name' => 'Stranger',
            'email' => 'stranger@evil.test',
            'terms' => true,
            'password' => 'supersecret123',
            'password_confirmation' => 'supersecret123',
        ])->assertStatus(409);

        $this->postJson('/api/auth/setup', [
            'name' => 'Delia Lazarescu',
            'email' => 'Delia@TechUnicorn.test',
            'password' => 'supersecret123',
            'password_confirmation' => 'supersecret123',
        ])->assertOk()->assertJsonPath('user.role', 'admin')->assertJsonPath('user.two_factor.required', false);

        $this->assertAuthenticated();
        $this->assertSame('delia@techunicorn.test', Auth::user()->email);
        $this->assertSame('Delia Lazarescu', Setting::get('creator_name'));
        $this->assertTrue(Auth::user()->isAdmin());
    }

    public function test_setup_validates_password_strength_and_confirmation(): void
    {
        $base = [
            'name' => 'Delia Lazarescu',
            'email' => 'delia@techunicorn.test',
        ];
        $this->postJson('/api/auth/setup', $base + [
            'password' => 'supersecret123',
            'password_confirmation' => 'mismatchedpass1',
        ])->assertStatus(422);
        $this->postJson('/api/auth/setup', $base + [
            'password' => 'onlyletters',
            'password_confirmation' => 'onlyletters',
        ])->assertStatus(422);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_nginx_snippet_generator_returns_tailored_config(): void
    {
        $res = $this->getJson('/api/install/nginx?app_url=https://studio.thetechunicorn.com')->assertOk();
        $res->assertJsonPath('ok', true);
        $res->assertJsonPath('host', 'studio.thetechunicorn.com');
        $this->assertStringContainsString('server_name studio.thetechunicorn.com;', $res->json('config'));
        $this->assertStringContainsString('fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;', $res->json('config'));
        $this->assertStringContainsString('add_header Cache-Control', $res->json('config'));
    }

    public function test_vps_install_seeds_initial_staff_roles(): void
    {
        $payload = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'app_url' => 'https://studio.test',
            'admin_name' => 'Delia Lazarescu',
            'admin_email' => 'delia@thetechunicorn.com',
            'admin_password' => 'MasterPass2026!',
            'admin_password_confirmation' => 'MasterPass2026!',
            'timezone' => 'Europe/Berlin',
            'currency' => 'EUR',
            'brand_accent' => 'rose',
            'seed_staff' => true,
        ];

        $res = $this->postJson('/api/install/vps', $payload)->assertOk();
        $res->assertJsonPath('ok', true);
        $this->assertAuthenticated();
        $this->assertSame('delia@thetechunicorn.com', Auth::user()->email);
        $this->assertSame('rose', Setting::get('accent'));
        $this->assertSame('Europe/Berlin', Setting::get('timezone'));

        // Staff members seeded
        $staffCount = \App\Models\User::where('role', \App\Enums\Role::Staff)->count();
        $this->assertGreaterThanOrEqual(3, $staffCount);
    }

    public function test_install_page_loads_with_200_ok_when_sessions_and_settings_tables_do_not_exist(): void
    {
        \Illuminate\Support\Facades\Schema::dropIfExists('sessions');
        \Illuminate\Support\Facades\Schema::dropIfExists('settings');
        config(['session.driver' => 'database']);
        \App\Http\Middleware\EnsureSessionDriverAvailable::resetTableCheck();
        Setting::flush();

        $response = $this->get('/install');
        $response->assertOk();
    }

    public function test_auth_status_returns_cleanly_when_sessions_and_settings_tables_do_not_exist(): void
    {
        \Illuminate\Support\Facades\Schema::dropIfExists('sessions');
        \Illuminate\Support\Facades\Schema::dropIfExists('settings');
        \Illuminate\Support\Facades\Schema::dropIfExists('users');
        config(['session.driver' => 'database']);
        \App\Http\Middleware\EnsureSessionDriverAvailable::resetTableCheck();
        Setting::flush();

        $response = $this->getJson('/api/auth/status');
        $response->assertOk();
        $response->assertJson([
            'initialized' => false,
            'authenticated' => false,
        ]);
    }

    public function test_vps_install_creates_installed_file_and_dispatches_vendor_event(): void
    {
        \Illuminate\Support\Facades\Event::fake([
            \RachidLaasri\LaravelInstaller\Events\LaravelInstallerFinished::class,
        ]);

        $payload = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'app_url' => 'https://studio.test',
            'admin_name' => 'Delia Lazarescu',
            'admin_email' => 'delia@thetechunicorn.com',
            'admin_password' => 'MasterPass2026!',
            'admin_password_confirmation' => 'MasterPass2026!',
            'timezone' => 'Europe/Berlin',
            'currency' => 'EUR',
            'brand_accent' => 'rose',
            'seed_staff' => false,
        ];

        $res = $this->postJson('/api/install/vps', $payload)->assertOk();
        $res->assertJsonPath('ok', true);

        $this->assertFileExists(storage_path('installed'));
        \Illuminate\Support\Facades\Event::assertDispatched(\RachidLaasri\LaravelInstaller\Events\LaravelInstallerFinished::class);

        // After installation, repeated calls to install endpoints should be rejected with 403
        $this->postJson('/api/install/vps', $payload)->assertStatus(403);
    }

    public function test_helper_adapter_provides_vendor_compatibility(): void
    {
        $this->assertTrue(\App\Helpers\Classes\Helper::dbConnectionStatus());
        $this->assertNull(\App\Helpers\Classes\Helper::settingTwo('non_existent_key'));
    }
}
