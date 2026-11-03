<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MobileTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
    }

    public function test_token_sign_in_with_two_factor_and_role_limits(): void
    {
        $admin = $this->admin();
        $this->postJson('/api/auth/mobile-token', ['email' => $admin->email, 'password' => 'wrong'])->assertStatus(401);
        $this->postJson('/api/auth/mobile-token', ['email' => $admin->email, 'password' => 'secret123'])->assertStatus(202)->assertJsonPath('two_factor', true);
        $this->postJson('/api/auth/mobile-token', ['email' => $admin->email, 'password' => 'secret123', 'code' => '000000'])->assertStatus(401);

        $staff = $this->staff();
        $token = $this->postJson('/api/auth/mobile-token', ['email' => $staff->email, 'password' => 'staff-pass-123', 'device_name' => 'Pixel'])
            ->assertCreated()->json('token');
        $this->assertSame(hash('sha256', $token), DB::table('mobile_tokens')->value('token_hash'));

        $this->app['auth']->forgetGuards();
        $h = ['Authorization' => "Bearer {$token}"];
        $this->getJson('/api/team', $h)->assertOk();
        $this->getJson('/api/backups', $h)->assertForbidden();
        $this->getJson('/api/account/devices', $h)->assertOk()->assertJsonPath('devices.0.name', 'Pixel');

        $this->deleteJson('/api/auth/mobile-token', [], $h)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/team', $h)->assertStatus(401);
        $this->getJson('/api/team', ['Authorization' => 'Bearer ' . str_repeat('x', 64)])->assertStatus(401);
    }

    public function test_unverified_clients_and_expired_tokens_are_refused(): void
    {
        $c = $this->client([], false);
        $this->postJson('/api/auth/mobile-token', ['email' => $c->email, 'password' => 'client-pass-123'])->assertForbidden();

        $m = $this->member();
        $token = $this->postJson('/api/auth/mobile-token', ['email' => $m->email, 'password' => 'member-pass-123'])->assertCreated()->json('token');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/portal/community/hub', ['Authorization' => "Bearer {$token}"])->assertOk();
        DB::table('mobile_tokens')->update(['expires_at' => now()->subDay()]);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/portal/community/hub', ['Authorization' => "Bearer {$token}"])->assertStatus(401);
    }
}
