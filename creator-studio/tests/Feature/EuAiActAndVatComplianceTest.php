<?php

namespace Tests\Feature;

use App\Domains\Ai\AiTransparency;
use App\Domains\Clients\TaxCompliance;
use App\Domains\Clients\ViesClient;
use App\Models\Deal;
use App\Models\Script;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EuAiActAndVatComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_vies_client_validates_eu_vat_with_mocked_european_commission_api(): void
    {
        Cache::flush();

        Http::fake([
            'https://ec.europa.eu/taxation_customs/vies/rest-api/*' => Http::response([
                'isValid' => true,
                'requestDate' => '2026-11-01T17:00:00.000Z',
                'userError' => 'VALID',
                'name' => 'MUSTERMANN ENTERPRISE GMBH',
                'address' => 'UNTER DEN LINDEN 1, 10117 BERLIN',
                'requestIdentifier' => 'WAPIAAAA5X9ZTESTTOKEN',
                'originalVatNumber' => '123456789',
                'vatNumber' => '123456789',
            ], 200),
        ]);

        $res = ViesClient::check('DE123456789', 'DE');

        $this->assertTrue($res['valid']);
        $this->assertSame('VIES_VERIFIED_ACTIVE', $res['status']);
        $this->assertSame('MUSTERMANN ENTERPRISE GMBH', $res['name']);
        $this->assertSame('WAPIAAAA5X9ZTESTTOKEN', $res['request_identifier']);

        // Second check must be fulfilled from cache (Http fake not called again)
        Http::fake([
            'https://ec.europa.eu/taxation_customs/vies/rest-api/*' => Http::response(['error' => 'Should not hit'], 500),
        ]);

        $cachedRes = ViesClient::check('DE123456789', 'DE');
        $this->assertTrue($cachedRes['valid']);
        $this->assertSame('WAPIAAAA5X9ZTESTTOKEN', $cachedRes['request_identifier']);
    }

    public function test_vies_client_handles_outages_with_syntax_regulatory_fallback(): void
    {
        Cache::flush();

        // Simulate European Commission VIES server outage
        Http::fake([
            'https://ec.europa.eu/taxation_customs/vies/rest-api/*' => Http::response(null, 503),
        ]);

        $res = ViesClient::check('FR12345678901', 'FR');

        $this->assertTrue($res['valid']);
        $this->assertTrue($res['fallback'] ?? false);
        $this->assertSame('SYNTAX_VERIFIED_OFFLINE_PENDING_VIES', $res['status']);
        $this->assertStringStartsWith('OFFLINE-', $res['request_identifier']);
        $this->assertStringContainsString('Pending background VIES synchronization', $res['notice']);
    }

    public function test_eu_27_vat_rates_and_one_stop_shop_oss_calculation(): void
    {
        // Test standard rates across key EU member states
        $this->assertSame(19.0, TaxCompliance::rateFor('DE'));
        $this->assertSame(20.0, TaxCompliance::rateFor('FR'));
        $this->assertSame(27.0, TaxCompliance::rateFor('HU'));
        $this->assertSame(17.0, TaxCompliance::rateFor('LU'));
        $this->assertSame(25.0, TaxCompliance::rateFor('SE'));
        $this->assertSame(25.5, TaxCompliance::rateFor('FI'));
        $this->assertSame(0.0, TaxCompliance::rateFor('US'));

        // Calculate B2C One-Stop Shop (OSS) VAT for German consumer
        $calc = TaxCompliance::calculateOss(2500.00, 'DE', '84.115.1.20');
        $this->assertSame(2500.00, $calc['net_amount']);
        $this->assertSame(19.0, $calc['vat_rate']);
        $this->assertSame(475.00, $calc['vat_amount']);
        $this->assertSame(2975.00, $calc['gross_amount']);
        $this->assertTrue($calc['oss_eligible']);

        // Verify Rule of Two location evidence
        $evidence = $calc['location_evidence'];
        $this->assertTrue($evidence['rule_of_two_satisfied']);
        $this->assertSame('DE', $evidence['evidence_items']['billing_country']);
        $this->assertTrue($evidence['evidence_items']['ip_address_logged']);
    }

    public function test_ai_act_article_50_headers_and_provenance_manifest(): void
    {
        $headers = AiTransparency::headers('gpt-4o', true);
        $this->assertSame('true', $headers['X-AI-Generated']);
        $this->assertSame('Delia Creator Studio AI Engine', $headers['X-AI-System']);
        $this->assertSame('Regulation-(EU)-2024/1689-Article-50', $headers['X-AI-Regulation']);
        $this->assertSame('human-reviewed', $headers['X-AI-Editorial-Status']);

        $manifest = AiTransparency::manifest('Building Agents with Delia', 'script', 'gpt-4o', true);
        $this->assertSame('https://schema.org', $manifest['@context']);
        $this->assertSame('CreativeWork', $manifest['@type']);
        $this->assertSame('Regulation (EU) 2024/1689 (EU AI Act)', $manifest['aiTransparency']['regulation']);
        $this->assertSame('Natural Person (Delia Lazarescu)', $manifest['aiTransparency']['editorial_responsibility']);
        $this->assertTrue($manifest['aiTransparency']['human_editorial_review']);

        $watermarked = AiTransparency::embedWatermark("First draft hook.\nLine two.", 'gpt-4o', true);
        $this->assertStringContainsString('<!-- eu-ai-act-disclosure:', $watermarked);
        $this->assertStringContainsString('EU AI Act Transparency Disclosure (Article 50)', $watermarked);
        $this->assertTrue(AiTransparency::hasDisclosure($watermarked));
    }

    public function test_export_controller_attaches_ai_transparency_headers_and_watermark(): void
    {
        $admin = $this->admin();
        $script = Script::create([
            'title' => 'Autonomous Engineering AI',
            'body' => 'Here is the spoken video breakdown of agentic loops.',
            'is_ai_assisted' => true,
            'ai_model_name' => 'openai|gpt-4o',
            'human_reviewed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get("/api/export/scripts/{$script->id}?format=md");

        $response->assertOk();
        $response->assertHeader('X-AI-Generated', 'true');
        $response->assertHeader('X-AI-Regulation', 'Regulation-(EU)-2024/1689-Article-50');
        $this->assertStringContainsString('eu-ai-act-disclosure', $response->getContent());
        $this->assertStringContainsString('EU AI Act Transparency Disclosure', $response->getContent());
    }

    public function test_legal_impressum_endpoint_satisfies_ddg_and_ecommerce_directive(): void
    {
        $response = $this->getJson('/api/legal/impressum');

        $response->assertOk()
            ->assertJsonStructure([
                'title',
                'markdown',
                'effective_date',
                'contact_email',
            ]);

        $content = $response->json('markdown');
        $this->assertStringContainsString('§ 5 Digitale-Dienste-Gesetz (DDG)', $content);
        $this->assertStringContainsString('Directive 2000/31/EC', $content);
        $this->assertStringContainsString('Delia Lazarescu', $content);
        $this->assertStringContainsString('ec.europa.eu/consumers/odr', $content);
        $this->assertStringContainsString('EU AI Act Transparency Declaration', $content);
    }

    public function test_brand_vault_vies_verification_endpoint(): void
    {
        $client = $this->client();

        Http::fake([
            'https://ec.europa.eu/taxation_customs/vies/rest-api/*' => Http::response([
                'isValid' => true,
                'requestDate' => '2026-11-01T17:00:00.000Z',
                'userError' => 'VALID',
                'name' => 'KRAKEN CLOUD SERVICES GMBH',
                'address' => 'KURFÜRSTENDAMM 20, 10719 BERLIN',
                'requestIdentifier' => 'VIES-BERLIN-998877',
            ], 200),
        ]);

        $response = $this->actingAs($client)->postJson('/api/portal/brand-vault/verify-vies', [
            'tax_id' => 'DE998877665',
            'country_code' => 'DE',
        ]);

        $response->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('name', 'KRAKEN CLOUD SERVICES GMBH')
            ->assertJsonPath('request_identifier', 'VIES-BERLIN-998877');
    }
}
