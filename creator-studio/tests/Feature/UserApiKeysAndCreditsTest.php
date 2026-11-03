<?php

namespace Tests\Feature;

use App\Domains\Engine\EngineKeys;
use App\Domains\Engine\Enums\EngineEnum;
use App\Enums\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApiKeysAndCreditsTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): User
    {
        return User::factory()->create([
            'role' => Role::Admin,
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => 'test-secret',
            'credit_balance' => 100.0,
        ]);
    }

    public function test_user_can_save_and_retrieve_encrypted_api_key(): void
    {
        $user = $this->createAdminUser();

        $keyRecord = $user->setApiKeyFor('gemini', 'AIzaSyTestGeminiKey12345');

        $this->assertInstanceOf(UserApiKey::class, $keyRecord);
        $this->assertEquals('gemini', $keyRecord->provider);
        $this->assertNotEquals('AIzaSyTestGeminiKey12345', $keyRecord->getRawOriginal('api_key')); // Must be encrypted
        $this->assertEquals('AIzaSyTestGeminiKey12345', $user->getApiKeyFor('gemini'));
        $this->assertEquals('AIzaSyTestGeminiKey12345', $user->getApiKeyFor(EngineEnum::GEMINI));
        $this->assertTrue($user->hasApiKeyFor('gemini'));
        $this->assertTrue($user->hasApiKeyFor(EngineEnum::GEMINI));
        $this->assertStringContainsString('••••••••', $keyRecord->maskedKey());
    }

    public function test_engine_keys_prioritizes_user_database_key_over_global_setting(): void
    {
        $user = $this->createAdminUser();
        Setting::put('llm_api_key', 'global-platform-key');

        $this->actingAs($user);

        // Before setting user key, uses global setting
        $resolvedKey = EngineKeys::for(EngineEnum::GEMINI, $user);
        $this->assertEquals('global-platform-key', $resolvedKey);

        // After setting user key, user key takes precedence
        $user->setApiKeyFor('gemini', 'delia-personal-gemini-key');
        $resolvedKey = EngineKeys::for(EngineEnum::GEMINI, $user);
        $this->assertEquals('delia-personal-gemini-key', $resolvedKey);
    }

    public function test_multi_tenant_isolation_between_users(): void
    {
        $user1 = $this->createAdminUser();
        $user2 = User::factory()->create([
            'role' => Role::Client,
            'email_verified_at' => now(),
        ]);

        $user1->setApiKeyFor('openai', 'sk-proj-user1-secret');

        $this->assertTrue($user1->hasApiKeyFor('openai'));
        $this->assertFalse($user2->hasApiKeyFor('openai'));
        $this->assertEquals('sk-proj-user1-secret', $user1->getApiKeyFor('openai'));
        $this->assertNull($user2->getApiKeyFor('openai'));
    }

    public function test_api_endpoints_for_user_keys(): void
    {
        $user = $this->createAdminUser();

        // 1. GET /api/user/keys
        $response = $this->actingAs($user)->getJson('/api/user/keys');
        $response->assertOk()
            ->assertJsonStructure([
                'credit_balance',
                'providers' => [
                    '*' => [
                        'provider',
                        'label',
                        'models',
                        'doc_url',
                        'placeholder',
                        'configured',
                        'masked_key',
                    ],
                ],
            ]);

        // 2. POST /api/user/keys
        $postResponse = $this->actingAs($user)->postJson('/api/user/keys', [
            'provider' => 'anthropic',
            'api_key' => 'sk-ant-test-anthropic-key-789',
        ]);
        $postResponse->assertOk()
            ->assertJson([
                'provider' => 'anthropic',
            ]);

        $this->assertTrue($user->fresh()->hasApiKeyFor('anthropic'));

        // 3. DELETE /api/user/keys/{provider}
        $delResponse = $this->actingAs($user)->deleteJson('/api/user/keys/anthropic');
        $delResponse->assertOk();

        $this->assertFalse($user->fresh()->hasApiKeyFor('anthropic'));
    }

    public function test_dashboard_returns_ai_engine_hub_with_default_gemini(): void
    {
        $user = $this->createAdminUser();
        $user->setApiKeyFor('gemini', 'AIzaSyPersonal123');

        $response = $this->actingAs($user)->getJson('/api/dashboard');
        $response->assertOk()
            ->assertJsonPath('ai_engine_hub.default_engine', 'gemini')
            ->assertJsonPath('ai_engine_hub.default_model', 'gemini-4-argon')
            ->assertJsonPath('ai_engine_hub.user_keys_configured', 1)
            ->assertJsonPath('ai_engine_hub.credit_balance', 100);
    }

    public function test_user_credit_deduction(): void
    {
        $user = $this->createAdminUser();
        $this->assertEquals(100.0, $user->creditBalance());

        $user->deductCredits(15.5);
        $this->assertEquals(84.5, $user->fresh()->creditBalance());

        $this->assertFalse($user->deductCredits(1000.0)); // Cannot deduct more than balance
    }
}
