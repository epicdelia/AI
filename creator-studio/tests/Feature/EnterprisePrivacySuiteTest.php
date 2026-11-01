<?php

namespace Tests\Feature;

use App\Domains\Privacy\DataProcessingAgreement;
use App\Domains\Privacy\DataRetentionPurger;
use App\Domains\Privacy\PiiRedactor;
use App\Domains\Privacy\PrivacyAccessLogger;
use App\Domains\Privacy\SubProcessorRegistry;
use App\Domains\Privacy\ZeroDataRetention;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnterprisePrivacySuiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_pii_and_secret_redaction_gateway_masks_sensitive_credentials(): void
    {
        // Fake credentials are assembled at runtime so no key-shaped literal lives in the repo
        // (GitHub push protection / secret scanners would otherwise flag this fixture).
        $stripeKey = implode('', ['sk', '_live_', str_repeat('Ab1', 12)]);
        $openAiKey = implode('', ['sk-', 'proj-', str_repeat('a1b2', 8)]);
        $githubToken = implode('', ['gh', 'p_', str_repeat('x9', 18)]);
        $awsKey = implode('', ['AK', 'IA', 'IOSFODNN7EXAMPLE']);
        $rawText = "Please use our test Stripe key {$stripeKey} and OpenAI {$openAiKey}. Also GitHub token {$githubToken} and AWS {$awsKey}.";

        $this->assertTrue(PiiRedactor::hasSecrets($rawText));

        $res = PiiRedactor::redact($rawText);

        $this->assertGreaterThanOrEqual(4, $res['redacted_count']);
        $this->assertContains('STRIPE_LIVE_KEY', $res['found_types']);
        $this->assertContains('OPENAI_API_KEY', $res['found_types']);
        $this->assertContains('GITHUB_TOKEN', $res['found_types']);
        $this->assertContains('AWS_ACCESS_KEY', $res['found_types']);

        $this->assertStringNotContainsString($stripeKey, $res['sanitized']);
        $this->assertStringNotContainsString($githubToken, $res['sanitized']);
        $this->assertStringNotContainsString($awsKey, $res['sanitized']);
        $this->assertStringContainsString('[REDACTED_STRIPE_LIVE_KEY:', $res['sanitized']);
        $this->assertStringContainsString('[REDACTED_AWS_ACCESS_KEY:', $res['sanitized']);

        // Test API endpoint
        $response = $this->postJson('/api/privacy/redact', ['text' => $rawText]);
        $response->assertOk()
            ->assertJsonPath('redacted_count', $res['redacted_count']);
    }

    public function test_subprocessor_registry_directory_and_30_day_change_subscription(): void
    {
        $response = $this->getJson('/api/privacy/subprocessors');
        $response->assertOk()
            ->assertJsonPath('title', 'Authorized Sub-Processors Directory')
            ->assertJsonStructure(['subprocessors' => [['name', 'purpose', 'entity_location', 'safeguard', 'certifications']]]);

        $this->assertStringContainsString('Stripe Payments Europe', $response->json('markdown'));
        $this->assertStringContainsString('OpenAI, L.L.C.', $response->json('markdown'));
        $this->assertStringContainsString('Cloudflare, Inc.', $response->json('markdown'));

        // Test subscribing corporate email to change notifications
        $subResponse = $this->postJson('/api/privacy/subprocessors/subscribe', [
            'email' => 'privacy-officer@enterprise-partner.com',
            'company' => 'Enterprise Partner SE',
        ]);

        $subResponse->assertOk()
            ->assertJson(['ok' => true, 'email' => 'privacy-officer@enterprise-partner.com']);

        $this->assertDatabaseHas('sub_processor_subscribers', [
            'email' => 'privacy-officer@enterprise-partner.com',
            'company_name' => 'Enterprise Partner SE',
        ]);
    }

    public function test_deal_room_dpa_generation_and_cryptographic_signing(): void
    {
        $token = Str::random(32);
        $deal = Deal::create([
            'brand_name' => 'Acme Corp EU',
            'contact_name' => 'Jean Dupont',
            'contact_email' => 'jean@acme-corp.eu',
            'stage' => 'negotiating',
            'rate' => 7500,
            'currency' => 'EUR',
            'room_token' => $token,
        ]);

        // 1. Fetch DPA in Deal Room
        $dpaRes = $this->getJson("/api/room/{$token}/dpa");
        $dpaRes->assertOk()
            ->assertJsonPath('brand', 'Acme Corp EU')
            ->assertJsonPath('signed', false);

        $this->assertStringContainsString('Data Processing Agreement (GDPR Article 28 & EU SCCs)', $dpaRes->json('markdown'));
        $this->assertStringContainsString('Decision 2021/914/EU', $dpaRes->json('markdown'));

        // 2. Electronically execute DPA
        $signRes = $this->postJson("/api/room/{$token}/dpa/sign", [
            'name' => 'Jean Dupont',
            'email' => 'jean@acme-corp.eu',
            'agree' => true,
        ]);

        $signRes->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('signed', true)
            ->assertJsonPath('signer_name', 'Jean Dupont');

        $signatureHash = $signRes->json('signature_hash');
        $this->assertNotEmpty($signatureHash);

        $deal->refresh();
        $this->assertNotNull($deal->dpa_signed_at);
        $this->assertSame('Jean Dupont', $deal->dpa_signer_name);
        $this->assertSame('jean@acme-corp.eu', $deal->dpa_signer_email);
        $this->assertSame($signatureHash, $deal->dpa_signature_hash);

        // Fetch again, should reflect signed state
        $dpaResAfter = $this->getJson("/api/room/{$token}/dpa");
        $dpaResAfter->assertOk()
            ->assertJsonPath('signed', true)
            ->assertJsonPath('signature_hash', $signatureHash);
    }

    public function test_authenticated_client_portal_dpa_and_zdr_certification(): void
    {
        $client = User::factory()->create([
            'name' => 'Alice Compliance',
            'email' => 'alice@client.eu',
            'email_verified_at' => now(),
            'role' => 'client',
            'company' => 'FinTech Europe B.V.',
        ]);

        $deal = Deal::create([
            'user_id' => $client->id,
            'brand_name' => 'FinTech Europe B.V.',
            'contact_name' => 'Alice Compliance',
            'contact_email' => 'alice@client.eu',
            'stage' => 'contracted',
            'rate' => 12000,
            'currency' => 'EUR',
        ]);

        // Client views DPA (logs to privacy access log)
        $dpaRes = $this->actingAs($client)->getJson("/api/portal/requests/{$deal->id}/dpa");
        $dpaRes->assertOk()
            ->assertJsonPath('signed', false);

        // Client signs DPA
        $signRes = $this->actingAs($client)->postJson("/api/portal/requests/{$deal->id}/dpa/sign", [
            'name' => 'Alice Compliance',
            'email' => 'alice@client.eu',
            'agree' => true,
        ]);
        $signRes->assertOk()
            ->assertJsonPath('signed', true);

        // Client views ZDR Certificate
        $zdrRes = $this->actingAs($client)->getJson("/api/portal/requests/{$deal->id}/zdr-certificate");
        $zdrRes->assertOk()
            ->assertJsonPath('certified', true)
            ->assertJsonStructure(['certificate_id', 'certified_at', 'guarantees' => ['zero_training', 'ephemeral_memory']]);

        $certId = $zdrRes->json('certificate_id');
        $this->assertStringStartsWith('ZDR-', $certId);

        $deal->refresh();
        $this->assertTrue($deal->zdr_certified);
        $this->assertSame($certId, $deal->zdr_certificate_id);
    }

    public function test_privacy_access_logger_subnets_and_retrieves_audit_log(): void
    {
        // Test IP subnet masking
        $this->assertSame('198.51.100.0', PrivacyAccessLogger::maskSubnet('198.51.100.42'));
        $this->assertSame('2001:db8:85a3::0', PrivacyAccessLogger::maskSubnet('2001:db8:85a3:0000:0000:8a2e:0370:7334'));

        $client = User::factory()->create([
            'name' => 'Bob Security',
            'email' => 'bob@security.test',
            'email_verified_at' => now(),
            'role' => 'client',
        ]);

        $deal = Deal::create([
            'user_id' => $client->id,
            'brand_name' => 'Secure Org',
            'contact_name' => 'Bob Security',
            'contact_email' => 'bob@security.test',
            'stage' => 'in_review',
            'rate' => 5000,
        ]);

        PrivacyAccessLogger::log($client, 'VAULT_ACCESS', $deal, 'Verification of brand guidelines', null, '203.0.113.19');

        $logRes = $this->actingAs($client)->getJson('/api/portal/privacy/access-log');
        $logRes->assertOk()
            ->assertJsonPath('title', 'Data Access & Processing Transparency Log');

        $logs = $logRes->json('logs');
        $this->assertNotEmpty($logs);
        $this->assertSame('203.0.113.0', $logs[0]['ip_subnet']);
        $this->assertSame('VAULT_ACCESS', $logs[0]['action']);
    }

    public function test_data_retention_purger_deletes_ephemeral_files_and_preserves_records(): void
    {
        Storage::fake('local');

        $client = User::factory()->create([
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        $deal = Deal::create([
            'user_id' => $client->id,
            'brand_name' => 'Ephemeral Media Co',
            'contact_name' => 'Emma Editor',
            'contact_email' => 'emma@ephemeral.test',
            'stage' => 'paid',
            'payment_status' => 'paid',
            'paid_at' => now()->subDays(65),
            'rate' => 4500,
        ]);

        // Upload ephemeral files to deal
        $file = UploadedFile::fake()->create('rough_cut_v1.mp4', 1024, 'video/mp4');
        $path = $file->store('deals/' . $deal->id, 'local');

        $dealFile = $deal->files()->create([
            'user_id' => $client->id,
            'name' => 'rough_cut_v1.mp4',
            'path' => $path,
            'size' => 1024,
            'mime_type' => 'video/mp4',
        ]);

        // Add annotations
        $deal->annotations()->create([
            'user_id' => $client->id,
            'body' => 'Cut the first 3 seconds of intro hook',
            'status' => 'open',
        ]);

        $this->assertTrue(Storage::disk('local')->exists($path));
        $this->assertDatabaseHas('deal_files', ['id' => $dealFile->id]);
        $this->assertDatabaseCount('deliverable_annotations', 1);

        // Execute purge
        $purgeRes = $this->actingAs($client)->postJson("/api/portal/requests/{$deal->id}/purge");
        $purgeRes->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('statutory_records_preserved', true);

        // Ephemeral files and annotations are purged
        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->assertDatabaseMissing('deal_files', ['id' => $dealFile->id]);
        $this->assertDatabaseCount('deliverable_annotations', 0);

        // Deal and statutory transaction record are preserved
        $deal->refresh();
        $this->assertNotNull($deal->retention_purged_at);
        $this->assertSame('paid', $deal->payment_status);
        $this->assertSame(4500.0, (float) $deal->rate);
    }

    public function test_brand_vault_persists_enterprise_privacy_and_retention_settings(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'email_verified_at' => now(),
            'company' => 'Enterprise SA',
        ]);

        $saveRes = $this->actingAs($client)->putJson('/api/portal/brand-vault', [
            'brand_name' => 'Enterprise SA',
            'retention_days' => 45,
            'zdr_enabled' => true,
            'auto_redact_secrets' => true,
        ]);

        $saveRes->assertOk()
            ->assertJsonPath('vault.retention_days', 45)
            ->assertJsonPath('vault.zdr_enabled', true)
            ->assertJsonPath('vault.auto_redact_secrets', true);

        $this->assertDatabaseHas('client_brand_vaults', [
            'user_id' => $client->id,
            'retention_days' => 45,
            'zdr_enabled' => 1,
            'auto_redact_secrets' => 1,
        ]);
    }
}
