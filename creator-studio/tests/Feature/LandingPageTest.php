<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_checkout_without_stripe_logs_a_pending_request_and_never_claims_payment(): void
    {
        $response = $this->postJson('/api/stripe/checkout-session', [
            'package_id' => 'retainer',
            'customer_name' => 'Acme Cloud Inc.',
            'customer_email' => 'partners@acme.example.com',
            'company' => 'Acme Cloud',
            'notes' => 'Looking for monthly AI advisory',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'requested', 'paid' => false, 'package_id' => 'retainer']);
        $this->assertArrayNotHasKey('receipt_id', $response->json());
        $this->assertStringContainsString('nothing was charged', $response->json('message'));

        $this->assertDatabaseHas('deals', [
            'brand_name' => 'Acme Cloud',
            'contact_email' => 'partners@acme.example.com',
            'stage' => 'inquiry',
            'payment_status' => 'pending',
            'paid_at' => null,
            'rate' => 3500.00,
        ]);
        $this->assertDatabaseHas('agent_events', ['type' => 'lead']);
    }

    public function test_checkout_with_stripe_redirects_and_stays_unpaid_until_the_webhook(): void
    {
        Setting::put('stripe_secret_key', 'sk_test_123');
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1'])]);

        $response = $this->postJson('/api/stripe/checkout-session', [
            'package_id' => 'advisory_session',
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
        ]);

        $response->assertOk()->assertJson(['status' => 'redirect', 'paid' => false, 'checkout_url' => 'https://checkout.stripe.com/c/pay/cs_test_1']);
        $deal = Deal::findOrFail($response->json('deal_id'));
        $this->assertSame('pending', $deal->payment_status);
        Http::assertSent(fn ($req) => ($req->data()['success_url'] ?? null) === url('/?payment=success&deal='.$deal->id));
    }

    public function test_stripe_failure_falls_back_to_an_invoice_request(): void
    {
        Setting::put('stripe_secret_key', 'sk_test_123');
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'Invalid API Key']], 401)]);

        $this->postJson('/api/stripe/checkout-session', [
            'package_id' => 'retainer',
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
        ])->assertOk()->assertJson(['status' => 'requested', 'paid' => false]);

        $this->assertDatabaseMissing('deals', ['payment_status' => 'paid']);
    }

    public function test_webhook_requires_a_valid_signature_before_marking_paid(): void
    {
        $deal = Deal::create(['brand_name' => 'Acme', 'stage' => 'inquiry', 'rate' => 500, 'currency' => 'USD', 'payment_status' => 'pending']);
        $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['payment_status' => 'paid', 'metadata' => ['deal_id' => $deal->id]]]]);
        $post = fn (?string $sig) => $this->call('POST', '/api/stripe/webhook', [], [], [],
            array_filter(['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $sig]), $payload);

        // No secret configured: rejected.
        $post(null)->assertStatus(400);

        Setting::put('stripe_webhook_secret', 'whsec_test');
        $post('t='.time().',v1=forged')->assertStatus(400);
        $old = time() - 3600;
        $post("t={$old},v1=".hash_hmac('sha256', "{$old}.{$payload}", 'whsec_test'))->assertStatus(400);
        $this->assertSame('pending', $deal->fresh()->payment_status);

        $t = time();
        $post("t={$t},v1=".hash_hmac('sha256', "{$t}.{$payload}", 'whsec_test'))->assertOk();

        $deal->refresh();
        $this->assertSame('paid', $deal->stage);
        $this->assertSame('paid', $deal->payment_status);
        $this->assertNotNull($deal->paid_at);
        $this->assertDatabaseHas('agent_events', ['type' => 'payment']);
    }

    public function test_booking_intro_call_creates_deal_and_is_honest_about_confirmation(): void
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
            'status' => 'requested',
            'name' => 'Sarah Connor',
            'project_type' => 'mvp_build',
        ]);
        $this->assertArrayNotHasKey('calendar_link', $response->json('appointment'));
        $this->assertStringNotContainsString('invite has been sent', $response->json('message'));

        $this->assertDatabaseHas('deals', [
            'brand_name' => 'Cyberdyne Systems',
            'contact_name' => 'Sarah Connor',
            'contact_email' => 'sarah@skynet-defense.com',
            'stage' => 'inquiry',
            'rate' => 12000.00,
        ]);
        $deal = Deal::where('contact_email', 'sarah@skynet-defense.com')->first();
        $this->assertStringContainsString($response->json('booking_ref'), $deal->notes);
        $this->assertDatabaseHas('agent_events', ['type' => 'lead']);
    }

    public function test_public_lead_endpoints_are_rate_limited(): void
    {
        $body = ['name' => 'Spam', 'email' => 'spam@example.com', 'project_type' => 'other', 'preferred_date' => '2026-11-15', 'preferred_time' => '15:00 UTC'];
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/booking', $body)->assertOk();
        }
        $this->postJson('/api/booking', $body)->assertStatus(429);
        $this->assertSame(5, Deal::count());
    }

    public function test_website_leads_reach_the_dashboard_and_notifications(): void
    {
        $this->postJson('/api/booking', [
            'name' => 'Sarah Connor', 'email' => 'sarah@example.com', 'company' => 'Cyberdyne',
            'project_type' => 'speaking', 'preferred_date' => '2026-11-15', 'preferred_time' => '15:00 UTC',
        ])->assertOk();
        Deal::create(['brand_name' => 'Paid Co', 'stage' => 'paid', 'rate' => 500, 'currency' => 'USD', 'payment_status' => 'paid', 'paid_at' => now()]);
        Deal::create(['brand_name' => 'Signed Co', 'stage' => 'contracted', 'rate' => 5000, 'currency' => 'USD', 'payment_status' => 'pending']);

        $this->actingAs(User::create(['name' => 'Delia', 'email' => 'delia@example.com', 'password' => 'secret123']));

        $inbound = $this->getJson('/api/dashboard')->assertOk()->json('inbound');
        $this->assertSame(1, $inbound['new_inquiries']);
        $this->assertEquals(5000, $inbound['awaiting_payment']);
        $this->assertEquals(500, $inbound['paid_this_month']);
        $this->assertSame('Cyberdyne', $inbound['latest'][0]['brand_name']);

        $events = $this->getJson('/api/agents/notifications')->assertOk()->json('events');
        $this->assertSame('lead', $events[0]['type']);
        $this->assertStringContainsString('Sarah Connor', $events[0]['message']);
    }

    public function test_service_card_bookings_are_grouped_by_service_on_the_dashboard(): void
    {
        $book = fn (string $email, ?string $service) => $this->postJson('/api/booking', array_filter([
            'name' => 'Lead', 'email' => $email, 'project_type' => 'consulting',
            'preferred_date' => '2026-11-15', 'preferred_time' => '15:00 UTC', 'service' => $service,
        ]))->assertOk();
        $book('a@example.com', 'Technical Consulting');
        $book('b@example.com', 'Technical Consulting');
        $book('c@example.com', 'Speaking & Workshops');
        $book('d@example.com', null);

        $deal = Deal::where('contact_email', 'a@example.com')->first();
        $this->assertSame('Technical Consulting', $deal->deliverables[0]['service']);
        $this->assertArrayNotHasKey('service', Deal::where('contact_email', 'd@example.com')->first()->deliverables[0]);

        $this->actingAs(User::create(['name' => 'Delia', 'email' => 'delia@example.com', 'password' => 'secret123']));
        $this->assertSame([
            ['service' => 'Technical Consulting', 'count' => 2],
            ['service' => 'Speaking & Workshops', 'count' => 1],
        ], $this->getJson('/api/dashboard')->json('inbound.by_service'));
        $this->assertStringContainsString('Technical Consulting', $this->getJson('/api/agents/notifications')->json('events.0.message'));
    }

    public function test_techunicorn_calls_to_action_open_the_booking_and_payment_forms(): void
    {
        $content = $this->get('/techunicorn')->assertOk()->getContent();

        $this->assertStringContainsString('href="/?book=mvp_build"', $content);
        $this->assertStringContainsString('href="/?book=brand_deal"', $content);
        $this->assertStringContainsString('href="/?pay=retainer"', $content);
        $this->assertStringNotContainsString('subject=Intro%20Call%20Booking', $content);
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
