<?php

namespace Tests\Feature;

use App\Domains\Clients\DealFiles;
use App\Domains\Clients\Models\ClientBrandVault;
use App\Domains\Clients\Models\DeliverableAnnotation;
use App\Domains\Deals\ContractSigner;
use App\Domains\Security\Audit;
use App\Domains\Security\Models\AuditLog;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHardeningAndRoiTest extends TestCase
{
    use RefreshDatabase;

    public function test_magic_byte_validation_accepts_valid_png_and_pdf(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $deal = Deal::create(['brand_name' => 'Acme Corp', 'rate' => 5000, 'stage' => 'inquiry', 'user_id' => $admin->id]);

        // Real valid PNG binary header
        $pngContent = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15c4";
        $filePng = UploadedFile::fake()->createWithContent('graphic.png', $pngContent);

        $savedPng = DealFiles::store($deal, $filePng, $admin, true);
        $this->assertNotNull($savedPng);
        $this->assertSame('graphic.png', $savedPng->name);

        // Real valid PDF binary header
        $pdfContent = "%PDF-1.4\n1 0 obj\n<<\n/Type /Catalog\n>>\nendobj\ntrailer\n<<\n/Root 1 0 R\n>>\n%%EOF";
        $filePdf = UploadedFile::fake()->createWithContent('contract.pdf', $pdfContent);

        $savedPdf = DealFiles::store($deal, $filePdf, $admin, true);
        $this->assertNotNull($savedPdf);
        $this->assertSame('contract.pdf', $savedPdf->name);
    }

    public function test_magic_byte_validation_rejects_disguised_php_script(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $deal = Deal::create(['brand_name' => 'Acme Corp', 'rate' => 5000, 'stage' => 'inquiry', 'user_id' => $admin->id]);

        // Disguised PHP script with a .png extension
        $fakePng = UploadedFile::fake()->createWithContent('shell.png', "<?php system(\$_GET['cmd']); ?>");

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        DealFiles::store($deal, $fakePng, $admin, true);
    }

    public function test_svg_sanitizer_blocks_script_tags(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $deal = Deal::create(['brand_name' => 'Acme Corp', 'rate' => 5000, 'stage' => 'inquiry', 'user_id' => $admin->id]);

        $maliciousSvg = UploadedFile::fake()->createWithContent('xss.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        DealFiles::store($deal, $maliciousSvg, $admin, true);
    }

    public function test_contract_signer_generates_tamper_evident_e_signature(): void
    {
        $client = $this->client();
        $deal = Deal::create([
            'brand_name' => 'Stripe Partner',
            'contact_email' => $client->email,
            'rate' => 12500,
            'currency' => 'USD',
            'stage' => 'pitching',
            'user_id' => $client->id,
            'proposal' => [
                'title' => 'Q4 Developer Tool Showcase',
                'summary' => 'Dedicated integration highlighting Stripe webhook reliability.',
                'investment' => ['amount' => 12500, 'currency' => 'USD'],
            ],
        ]);

        $this->actingAs($client);
        $res = $this->postJson("/api/portal/requests/{$deal->id}/accept", [
            'name' => 'Sarah Connor',
            'agree' => true,
        ]);

        $res->assertOk();
        $this->assertNotNull($res->json('signature_hash'));
        $this->assertSame(64, strlen($res->json('signature_hash')));

        $fresh = $deal->fresh();
        $this->assertSame('contracted', $fresh->stage);
        $this->assertSame('Sarah Connor', $fresh->accepted_by);
        $this->assertSame($res->json('signature_hash'), $fresh->signature_hash);

        // Verify immutable audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'contract_signed',
            'user_id' => $client->id,
            'subject_id' => $deal->id,
        ]);
    }

    public function test_deliverable_annotations_and_proofing_workflow(): void
    {
        $client = $this->client();
        $deal = Deal::create([
            'brand_name' => 'Supabase Corp',
            'rate' => 8000,
            'stage' => 'in_review',
            'user_id' => $client->id,
            'deliverables' => [
                ['platform' => 'youtube', 'format' => 'Integration', 'status' => 'in_review'],
            ],
        ]);

        $this->actingAs($client);

        // 1. Post timecode annotation
        $postRes = $this->postJson("/api/portal/requests/{$deal->id}/annotations", [
            'deliverable_index' => 0,
            'kind' => 'video',
            'timecode_seconds' => 75.5,
            'body' => 'At 01:15, please emphasize our self-hosting capabilities.',
        ])->assertStatus(201);

        $annotationId = $postRes->json('annotation.id');
        $this->assertNotNull($annotationId);
        $this->assertSame('open', $postRes->json('annotation.status'));

        // 2. Query annotations list
        $listRes = $this->getJson("/api/portal/requests/{$deal->id}/annotations")->assertOk();
        $this->assertCount(1, $listRes->json('annotations'));
        $this->assertSame('At 01:15, please emphasize our self-hosting capabilities.', $listRes->json('annotations.0.body'));

        // 3. Resolve annotation
        $resolveRes = $this->postJson("/api/portal/requests/{$deal->id}/annotations/{$annotationId}/resolve")->assertOk();
        $this->assertSame('resolved', $resolveRes->json('annotation.status'));
        $this->assertNotNull($resolveRes->json('annotation.resolved_at'));
    }

    public function test_client_campaign_roi_metrics(): void
    {
        $client = $this->client();
        $deal = Deal::create([
            'brand_name' => 'Datadog Tools',
            'rate' => 15000,
            'currency' => 'USD',
            'stage' => 'paid',
            'payment_status' => 'paid',
            'user_id' => $client->id,
            'deliverables' => [
                ['platform' => 'youtube', 'format' => 'Full Video', 'status' => 'completed'],
            ],
        ]);

        $this->actingAs($client);
        $res = $this->getJson("/api/portal/requests/{$deal->id}/roi")->assertOk();

        $this->assertArrayHasKey('metrics', $res->json());
        $this->assertArrayHasKey('benchmark', $res->json());
        $this->assertArrayHasKey('renewal_incentive', $res->json());

        $this->assertGreaterThan(0, $res->json('metrics.total_views'));
        $this->assertGreaterThan(0, $res->json('benchmark.performance_multiplier'));
    }

    public function test_client_can_apply_high_roi_add_ons(): void
    {
        $client = $this->client();
        $deal = Deal::create([
            'brand_name' => 'Vercel Inc',
            'rate' => 10000,
            'currency' => 'USD',
            'stage' => 'contracted',
            'user_id' => $client->id,
            'deliverables' => [
                ['platform' => 'youtube', 'format' => 'Sponsorship'],
            ],
        ]);

        $this->actingAs($client);

        // Get catalog
        $catalogRes = $this->getJson("/api/portal/requests/{$deal->id}/add-ons")->assertOk();
        $this->assertNotEmpty($catalogRes->json('catalog'));

        // Apply 90-day Paid Ads Rights ($1500)
        $applyRes = $this->postJson("/api/portal/requests/{$deal->id}/add-ons", [
            'add_on' => 'paid_usage_90d',
        ])->assertStatus(201);

        $this->assertSame(11500.0, (float) $applyRes->json('new_rate'));

        $fresh = $deal->fresh();
        $this->assertSame(11500.0, (float) $fresh->rate);
        $this->assertCount(1, $fresh->add_ons);
        $this->assertSame('paid_usage_90d', $fresh->add_ons[0]['key']);
    }

    public function test_client_brand_vault_persists_assets_and_guidelines(): void
    {
        $client = $this->client();
        $this->actingAs($client);

        $getRes = $this->getJson('/api/portal/brand-vault')->assertOk();
        $this->assertArrayHasKey('vault', $getRes->json());

        $saveRes = $this->putJson('/api/portal/brand-vault', [
            'brand_name' => 'Postman Inc',
            'website' => 'https://postman.com',
            'brand_colors' => ['primary' => '#FF6C37', 'accent' => '#000000'],
            'guidelines_markdown' => 'Focus on API-first development and automated test collections.',
            'utm_params' => ['utm_source' => 'tech-unicorn', 'utm_medium' => 'video-sponsor'],
        ])->assertOk();

        $this->assertSame('Postman Inc', $saveRes->json('vault.brand_name'));
        $this->assertSame('#FF6C37', $saveRes->json('vault.brand_colors.primary'));

        $this->assertDatabaseHas('client_brand_vaults', [
            'user_id' => $client->id,
            'brand_name' => 'Postman Inc',
        ]);
    }

    public function test_tenant_isolation_prevents_unauthorized_cross_client_access(): void
    {
        $clientAlice = $this->client(['email' => 'alice@company-a.test', 'name' => 'Alice']);
        $clientBob = $this->client(['email' => 'bob@company-b.test', 'name' => 'Bob']);

        $dealAlice = Deal::create([
            'brand_name' => 'Company A',
            'rate' => 20000,
            'stage' => 'in_review',
            'user_id' => $clientAlice->id,
        ]);

        // Client Bob tries to access Client Alice's deal -> 404 (strictly scoped)
        $this->actingAs($clientBob);
        $this->getJson("/api/portal/requests/{$dealAlice->id}")->assertStatus(404);
        $this->getJson("/api/portal/requests/{$dealAlice->id}/roi")->assertStatus(404);
        $this->postJson("/api/portal/requests/{$dealAlice->id}/annotations", ['body' => 'Intruder note'])->assertStatus(404);
    }
}

