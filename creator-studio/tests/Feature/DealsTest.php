<?php

namespace Tests\Feature;

use App\Domains\Copilot\CopilotTools;
use App\Models\Deal;
use App\Models\Setting;
use App\Services\Llm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Setting::put('creator_name', 'Delia Lazarescu');
        $this->actingAs($this->admin([
            'name' => 'Delia',
            'email' => 'delia@example.com',
            'password' => 'secret123',
        ]));
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        parent::tearDown();
    }

    public function test_deals_crud_and_pipeline_stats(): void
    {
        // 1. Create deal
        $res = $this->postJson('/api/deals', [
            'brand_name' => 'Supabase',
            'contact_name' => 'Alex Developer',
            'contact_email' => 'alex@supabase.io',
            'stage' => 'negotiating',
            'rate' => 3000,
            'currency' => 'USD',
            'deliverables' => [
                [
                    'platform' => 'youtube',
                    'format' => 'integration_60s',
                ],
                [
                    'platform' => 'linkedin',
                    'format' => 'post',
                ],
            ],
            'coupon_code' => 'TECHUNICORN20',
            'tracking_url' => 'https://supabase.com/?ref=techunicorn',
            'key_talking_points' => 'Postgres database, real-time edge functions, 14-day free trial.',
        ])->assertCreated()->json();

        $this->assertEquals('Supabase', $res['deal']['brand_name']);
        $dealId = $res['deal']['id'];

        // Add a second deal that is paid
        Deal::create([
            'brand_name' => 'Linear',
            'stage' => 'paid',
            'rate' => 2000,
            'currency' => 'USD',
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        // Add a third deal that is invoiced
        Deal::create([
            'brand_name' => 'Cursor',
            'stage' => 'invoiced',
            'rate' => 2500,
            'currency' => 'USD',
            'payment_status' => 'invoiced',
            'invoiced_at' => now(),
        ]);

        // 2. Fetch list and stats
        $list = $this->getJson('/api/deals')->assertOk()->json();
        $this->assertCount(3, $list['deals']);
        $stats = $list['stats'];

        // total pipeline excludes paid deals: 3000 (Supabase) + 2500 (Cursor) = 5500
        $this->assertEquals(5500, $stats['total_pipeline']);
        $this->assertEquals(2000, $stats['received_revenue']);
        $this->assertEquals(2500, $stats['pending_invoices']);

        // 3. Update deal stage to paid
        $update = $this->patchJson("/api/deals/{$dealId}", [
            'stage' => 'paid',
        ])->assertOk()->json();

        $this->assertEquals('paid', $update['deal']['stage']);
        $this->assertEquals('paid', $update['deal']['payment_status']);
        $this->assertNotNull($update['deal']['paid_at']);

        // 4. Delete deal
        $this->deleteJson("/api/deals/{$dealId}")->assertOk();
        $this->assertDatabaseMissing('deals', [
            'id' => $dealId,
        ]);
    }

    public function test_deals_rate_card_endpoint(): void
    {
        $rates = $this->getJson('/api/deals/rate-card')->assertOk()->json();

        $this->assertArrayHasKey('metrics', $rates);
        $this->assertArrayHasKey('rates', $rates);
        $this->assertArrayHasKey('packages', $rates);

        $this->assertArrayHasKey('youtube_integration_60s', $rates['rates']);
        $this->assertArrayHasKey('linkedin_post', $rates['rates']);
        $this->assertGreaterThan(0, $rates['rates']['youtube_integration_60s']['rate']);
        $this->assertArrayHasKey('omnichannel_launch', $rates['packages']);
    }

    public function test_deals_media_kit_endpoint(): void
    {
        $kit = $this->getJson('/api/deals/media-kit')->assertOk()->json();

        $this->assertEquals('Delia Lazarescu', $kit['creator']['name']);
        $this->assertEquals('@Tech.Unicorn', $kit['creator']['handle']);
        $this->assertGreaterThan(0, $kit['audience']['total_reach']);
        $this->assertNotEmpty($kit['audience']['platforms']);
        $this->assertNotEmpty($kit['audience']['demographics']['professions']);
    }

    public function test_deal_pitch_generation(): void
    {
        Llm::$fake = fn () => json_encode([
            'subject' => 'Delia Lazarescu (@Tech.Unicorn) × Cursor Partnership',
            'body' => 'Hi Cursor team, I love your editor and would love to showcase it to my community.',
        ]);

        $res = $this->postJson('/api/deals/pitch', [
            'brand_name' => 'Cursor',
            'product_name' => 'Cursor AI Code Editor',
            'inquiry_text' => 'Looking to sponsor YouTube tech videos',
        ])->assertOk()->json();

        $this->assertStringContainsString('Cursor', $res['pitch']['subject']);
        $this->assertNotEmpty($res['pitch']['body']);
    }

    public function test_deal_sponsor_script_generation(): void
    {
        $deal = Deal::create([
            'brand_name' => 'Notion',
            'key_talking_points' => 'Organize your engineering sprints, free template at link',
            'coupon_code' => 'UNICORN',
            'tracking_url' => 'https://notion.so/techunicorn',
        ]);

        Llm::$fake = fn () => json_encode([
            'transition_in' => "When managing large codebases, keeping your notes organized is key. That's why today's video is sponsored by Notion.",
            'script_body' => 'I use Notion every single day to track feature architecture. Check the link below with code UNICORN.',
            'transition_out' => 'Now back to the third design pattern.',
            'checklist' => ['Sprint organization mentioned', 'Link in description CTA'],
        ]);

        $res = $this->postJson("/api/deals/{$deal->id}/sponsor-script", [
            'video_topic' => 'Microservices with Docker and Kubernetes',
            'duration_seconds' => 60,
        ])->assertOk()->json();

        $this->assertArrayHasKey('segment', $res);
        $this->assertStringContainsString('Notion', $res['segment']['transition_in']);
        $this->assertNotEmpty($res['segment']['script_body']);
    }

    public function test_copilot_deals_tools(): void
    {
        Deal::create([
            'brand_name' => 'Vercel',
            'stage' => 'contracted',
            'rate' => 3500,
        ]);

        $tools = app(CopilotTools::class);
        $readDeals = json_decode($tools->read('list_brand_deals', []), true);
        $this->assertIsArray($readDeals);
        $this->assertEquals('Vercel', $readDeals[0]['brand_name']);

        $rateCard = json_decode($tools->read('get_rate_card', []), true);
        $this->assertIsArray($rateCard);
        $this->assertArrayHasKey('rates', $rateCard);
    }

    public function test_global_search_finds_deals(): void
    {
        Deal::create([
            'brand_name' => 'Datadog',
            'stage' => 'negotiating',
            'rate' => 4000,
        ]);

        $res = $this->getJson('/api/search?q=Datadog')->assertOk()->json();
        $groupTitles = array_column($res['groups'], 'title');
        $this->assertContains('Brand Deals', $groupTitles);

        $dealGroup = collect($res['groups'])->firstWhere('title', 'Brand Deals');
        $this->assertStringContainsString('Datadog', $dealGroup['items'][0]['label']);
    }

    public function test_deal_campaign_report(): void
    {
        $deal = Deal::create([
            'brand_name' => 'Supabase',
            'contact_name' => 'Ant Wilson',
            'contact_email' => 'ant@supabase.com',
            'stage' => 'paid',
            'rate' => 3500,
            'currency' => 'USD',
            'coupon_code' => 'UNICORN35',
            'tracking_url' => 'https://supabase.com/?ref=techunicorn',
            'deliverables' => [
                [
                    'platform' => 'youtube',
                    'format' => 'integration_60s',
                ],
            ],
            'published_at' => now()->subDays(3),
        ]);

        $res = $this->getJson("/api/deals/{$deal->id}/report")->assertOk()->json();

        $this->assertArrayHasKey('report', $res);
        $report = $res['report'];
        $this->assertEquals('Supabase', $report['brand_name']);
        $this->assertGreaterThan(0, $report['kpis']['views']);
        $this->assertGreaterThan(0, $report['kpis']['clicks']);
        $this->assertNotEmpty($report['deliverables']);
        $this->assertNotEmpty($report['audience_demographics']['seniority']);
        $this->assertNotEmpty($report['viewer_feedback']);
        $this->assertNotEmpty($report['executive_summary']);
    }
}
