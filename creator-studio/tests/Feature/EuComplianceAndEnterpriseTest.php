<?php

namespace Tests\Feature;

use App\Domains\Clients\ClientComplianceAuditor;
use App\Domains\Clients\Models\ClientBrandVault;
use App\Domains\Clients\PartnerReferrals;
use App\Domains\Clients\TaxCompliance;
use App\Domains\Deals\ProposalTiers;
use App\Models\Deal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EuComplianceAndEnterpriseTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_privacy_policy_endpoint_returns_gdpr_markdown(): void
    {
        $response = $this->getJson('/api/legal/privacy');

        $response->assertOk()
            ->assertJsonStructure([
                'title',
                'markdown',
                'effective_date',
                'contact_email',
            ]);

        $content = $response->json('markdown');
        $this->assertStringContainsString('General Data Protection Regulation', $content);
        $this->assertStringContainsString('Article 6', $content);
        $this->assertStringContainsString('Article 20', $content);
        $this->assertStringContainsString('ePrivacy Directive', $content);
    }

    public function test_legal_terms_and_conditions_endpoint_returns_directive_compliant_terms(): void
    {
        $response = $this->getJson('/api/legal/terms');

        $response->assertOk()
            ->assertJsonStructure([
                'title',
                'markdown',
                'effective_date',
            ]);

        $content = $response->json('markdown');
        $this->assertStringContainsString('Council Directive 2006/112/EC', $content);
        $this->assertStringContainsString('2011/7/EU', $content);
        $this->assertStringContainsStringIgnoringCase('Reverse Charge', $content);
    }

    public function test_tax_compliance_validates_eu_vat_and_applies_reverse_charge(): void
    {
        // Valid German VAT
        $this->assertTrue(TaxCompliance::isValidVat('DE123456789'));
        // Valid French VAT
        $this->assertTrue(TaxCompliance::isValidVat('FR12345678901'));
        // Invalid VAT format
        $this->assertFalse(TaxCompliance::isValidVat('DE12'));
        $this->assertFalse(TaxCompliance::isValidVat('123456789'));

        // Reverse charge notice calculation
        $notice = TaxCompliance::reverseChargeNotice('DE', 'DE123456789');
        $this->assertNotNull($notice);
        $this->assertStringContainsString('Article 196', $notice);
        $this->assertStringContainsStringIgnoringCase('Reverse Charge', $notice);

        // US client should not get reverse-charge notice
        $usNotice = TaxCompliance::reverseChargeNotice('US', '12-3456789');
        $this->assertNull($usNotice);

        // Billing header with VAT
        $header = TaxCompliance::commercialBillingHeader([
            'company' => 'Enterprise GmbH',
            'tax_id' => 'DE123456789',
            'billing_address' => 'Alexanderplatz 1, Berlin',
            'country_code' => 'DE',
        ]);

        $this->assertSame('Enterprise GmbH', $header['company']);
        $this->assertTrue($header['tax_exempt']);
        $this->assertStringContainsStringIgnoringCase('Reverse Charge', $header['tax_liability_notice']);
    }

    public function test_gdpr_article_20_data_export_produces_structured_json(): void
    {
        $client = $this->client([
            'company' => 'Acme EU Corp',
        ]);
        $deal = Deal::create([
            'brand_name' => 'Acme EU Corp',
            'rate' => 7500,
            'currency' => 'EUR',
            'stage' => 'in_review',
            'user_id' => $client->id,
            'selected_tier' => 'growth',
        ]);

        ClientBrandVault::create([
            'user_id' => $client->id,
            'brand_name' => 'Acme EU',
            'tax_id' => 'FR99887766554',
            'country_code' => 'FR',
            'billing_address' => '10 Champs-Élysées, Paris',
        ]);

        $response = $this->actingAs($client)->getJson('/api/account/export');

        $response->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="gdpr-data-export-user-' . $client->id . '.json"')
            ->assertJsonStructure([
                'export_metadata' => ['standard', 'regulation', 'exported_at', 'user_id', 'data_controller'],
                'profile' => ['id', 'name', 'email', 'company', 'role'],
                'brand_vault' => ['brand_name', 'tax_id', 'country_code', 'billing_address'],
                'deals',
                'audit_log',
            ]);

        $data = $response->json();
        $this->assertSame('GDPR Article 20 (Right to Data Portability)', $data['export_metadata']['standard']);
        $this->assertSame('Acme EU Corp', $data['deals'][0]['brand_name']);
        $this->assertSame('FR99887766554', $data['brand_vault']['tax_id']);
    }

    public function test_multi_tier_proposal_selection_via_deal_room(): void
    {
        $deal = Deal::create([
            'brand_name' => 'Vercel Partner',
            'rate' => 6000,
            'stage' => 'inquiry',
            'room_token' => 'roomtoken12345678901234567890',
        ]);

        // Fetch proposal tiers
        $tiers = ProposalTiers::for($deal);
        $this->assertCount(3, $tiers);
        $this->assertSame('essential', $tiers[0]['key']);
        $this->assertSame('growth', $tiers[1]['key']);
        $this->assertSame('enterprise', $tiers[2]['key']);

        // Select Enterprise tier via Deal Room endpoint
        $response = $this->postJson('/api/room/roomtoken12345678901234567890/select-tier', [
            'tier' => 'enterprise',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('selected_tier', 'enterprise');

        $deal->refresh();
        $this->assertSame('enterprise', $deal->selected_tier);
        $this->assertEquals($tiers[2]['price'], $deal->rate);
    }

    public function test_compliance_auditor_verifies_ftc_and_generates_executive_wrap_report(): void
    {
        $client = $this->client();
        $deal = Deal::create([
            'brand_name' => 'Supercharge AI',
            'rate' => 9000,
            'stage' => 'scheduled',
            'user_id' => $client->id,
            'key_talking_points' => 'Zero-latency inference, FTC disclosure, #ad included',
            'published_at' => now()->subDay(),
        ]);

        $audit = ClientComplianceAuditor::audit($deal);
        $this->assertTrue($audit['compliant']);
        $this->assertTrue($audit['ftc_disclosure']['passed']);
        $this->assertTrue($audit['exclusivity_clearance']['passed']);
        $this->assertNotEmpty($audit['certificate_id']);

        // Test API endpoint for compliance
        $response = $this->actingAs($client)->getJson("/api/portal/requests/{$deal->id}/compliance");
        $response->assertOk()
            ->assertJsonPath('compliant', true)
            ->assertJsonPath('ftc_disclosure.passed', true);

        // Test API endpoint for Executive Wrap Report
        $reportResponse = $this->actingAs($client)->getJson("/api/portal/requests/{$deal->id}/compliance/report");
        $reportResponse->assertOk()
            ->assertJsonStructure(['deal_id', 'brand_name', 'markdown', 'generated_at']);

        $this->assertStringContainsString('Executive Campaign Wrap & Compliance Report', $reportResponse->json('markdown'));
    }

    public function test_slack_webhook_ping_dispatcher(): void
    {
        $client = $this->client();
        $deal = Deal::create([
            'brand_name' => 'Tech Corp',
            'rate' => 4500,
            'stage' => 'inquiry',
            'user_id' => $client->id,
        ]);

        Http::fake([
            'https://hooks.slack.com/*' => Http::response([
                'ok' => true,
            ], 200),
        ]);

        $response = $this->actingAs($client)->postJson('/api/portal/webhook/test', [
            'url' => 'https://hooks.slack.com/services/T0000/B0000/XXXX',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Test message sent to Slack successfully!');
    }

    public function test_partner_referrals_generation_and_credit_attribution(): void
    {
        $referrer = $this->client([
            'email' => 'partner@agency.test',
            'name' => 'Agency Partner',
        ]);
        $referredUser = $this->client([
            'email' => 'client@startup.test',
            'name' => 'Startup Buyer',
        ]);

        // Verify referral code generation
        $code = PartnerReferrals::codeFor($referrer);
        $this->assertNotEmpty($code);
        $referrer->refresh();
        $this->assertSame($code, $referrer->referral_code);

        // Create deal attributed to referrer
        $deal = Deal::create([
            'brand_name' => 'Startup Buyer',
            'rate' => 5000,
            'stage' => 'invoiced',
            'payment_status' => 'pending',
            'user_id' => $referredUser->id,
            'referral_partner_id' => $referrer->id,
        ]);

        // Referrer credits start at 0
        $this->assertSame(0, (int)$referrer->referral_credits);

        // Deal is marked as paid, which triggers PartnerReferrals::attributePayment via Model booted hook
        $deal->payment_status = 'paid';
        $deal->paid_at = now();
        $deal->save();

        $referrer->refresh();
        $deal->refresh();

        // Fixed minimum $1,000 credit awarded
        $this->assertEquals(1000, (int)$deal->referral_credit_earned);
        $this->assertEquals(1000, (int)$referrer->referral_credits);

        // Test API endpoint for partner referrals
        $portalResponse = $this->actingAs($referrer)->getJson('/api/portal/referrals');
        $portalResponse->assertOk()
            ->assertJsonPath('referral_code', $code)
            ->assertJsonPath('available_credits', 1000)
            ->assertJsonPath('total_credits_earned', 1000);
    }
}
