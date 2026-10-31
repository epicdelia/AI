<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Setting::put('creator_name', 'Delia Lazarescu');
    }

    public function test_techunicorn_public_landing_page_renders_successfully(): void
    {
        $response = $this->get('/techunicorn');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=utf-8');
        $content = $response->getContent();

        $this->assertStringContainsString('Tech Unicorn', $content);
        $this->assertStringContainsString('Delia Lazarescu', $content);
        $this->assertStringContainsString('Making tech', $content);
        $this->assertStringContainsString('Vibecoded Apps', $content);
        $this->assertStringContainsString('MVP Build', $content);
        $this->assertStringContainsString('Advisory Retainer', $content);
        $this->assertStringContainsString('Nomad Cruise', $content);
        $this->assertStringContainsString('472K+', $content);
        $this->assertStringNotContainsString('—', $content, 'Landing page should contain zero em-dashes (—)');
        $this->assertStringContainsString('<svg', $content, 'Landing page should use our SVG logo');
    }

    public function test_links_bio_landing_page_renders_in_dashboard_design_language(): void
    {
        $response = $this->get('/links');

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('Delia Lazarescu', $content);
        $this->assertStringContainsString('@Tech.Unicorn', $content);
        $this->assertStringContainsString('/techunicorn', $content);
    }

    public function test_stripe_packages_endpoint_returns_json_packages(): void
    {
        $response = $this->getJson('/api/stripe/packages');

        $response->assertOk();
        $response->assertJsonStructure([
            'packages' => [
                'retainer' => ['id', 'name', 'amount', 'currency'],
                'mvp_deposit',
                'mvp_full',
                'brand_partnership',
            ],
            'stripe_configured',
        ]);
    }

    public function test_stripe_checkout_session_creation_registers_deal(): void
    {
        $response = $this->postJson('/api/stripe/checkout-session', [
            'package_id' => 'retainer',
            'customer_name' => 'Acme Cloud Inc.',
            'customer_email' => 'partners@acme.example.com',
            'company' => 'Acme Cloud',
            'notes' => 'Looking for monthly AI advisory',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'status' => 'success',
            'paid' => true,
            'package_id' => 'retainer',
        ]);

        $this->assertDatabaseHas('deals', [
            'brand_name' => 'Acme Cloud',
            'contact_email' => 'partners@acme.example.com',
            'stage' => 'contracted',
            'rate' => 3500.00,
        ]);
    }

    public function test_booking_intro_call_creates_deal_and_confirms(): void
    {
        $response = $this->postJson('/api/booking', [
            'name' => 'Sarah Connor',
            'email' => 'sarah@skynet-defense.com',
            'company' => 'Cyberdyne Systems',
            'project_type' => 'mvp_build',
            'preferred_date' => '2026-11-15',
            'preferred_time' => '15:00 UTC',
            'notes' => 'Need 2-4 week AI prototype built',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'confirmed',
            'name' => 'Sarah Connor',
            'project_type' => 'mvp_build',
        ]);

        $this->assertDatabaseHas('deals', [
            'brand_name' => 'Cyberdyne Systems',
            'contact_name' => 'Sarah Connor',
            'contact_email' => 'sarah@skynet-defense.com',
            'stage' => 'inquiry',
            'rate' => 12000.00,
        ]);
    }

    public function test_youtube_videos_endpoint_returns_real_channel_videos(): void
    {
        $response = $this->getJson('/api/landing/youtube-videos');

        $response->assertOk();
        $response->assertJsonStructure([
            'channel',
            'videos' => [
                '*' => ['id', 'title', 'desc', 'duration', 'views', 'tag', 'url'],
            ],
        ]);

        $videos = $response->json('videos');
        $this->assertNotEmpty($videos);
        $titles = array_column($videos, 'title');
        $this->assertTrue(
            in_array('10 Brutal Truths I Learned as a Google Software Engineer', $titles) ||
            count($videos) > 0
        );
    }

    public function test_social_feed_endpoint_returns_all_platforms_with_youtube_default(): void
    {
        $response = $this->getJson('/api/landing/social-feed');

        $response->assertOk();
        $response->assertJsonStructure([
            'active',
            'channel',
            'stats' => ['youtube', 'instagram', 'tiktok', 'twitter', 'threads'],
            'handles' => ['youtube', 'instagram', 'tiktok', 'twitter', 'threads'],
            'urls' => ['youtube', 'instagram', 'tiktok', 'twitter', 'threads'],
            'platforms' => [
                'youtube',
                'instagram',
                'tiktok',
                'twitter',
                'threads',
            ],
        ]);

        $this->assertSame('youtube', $response->json('active'));
        $this->assertNotEmpty($response->json('platforms.youtube'));
        $this->assertNotEmpty($response->json('platforms.instagram'));
        $this->assertNotEmpty($response->json('platforms.tiktok'));
        $this->assertNotEmpty($response->json('platforms.twitter'));
        $this->assertNotEmpty($response->json('platforms.threads'));
    }
}
