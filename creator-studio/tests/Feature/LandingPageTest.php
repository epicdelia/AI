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

    public function test_old_landing_addresses_redirect_to_the_one_landing_page(): void
    {
        $this->get('/techunicorn')->assertStatus(301)->assertRedirect('/');
        $this->get('/landing-page')->assertStatus(301)->assertRedirect('/');
        // Booking and payment links from old pages and bios keep working.
        $this->get('/techunicorn?book=mvp_build')->assertRedirect('/?book=mvp_build');
        $this->get('/techunicorn?pay=retainer')->assertRedirect('/?pay=retainer');
    }

    public function test_landing_page_has_search_and_share_metadata(): void
    {
        $html = file_get_contents(public_path('index.html'));
        $this->assertStringContainsString('<title>Tech Unicorn', $html);
        $this->assertStringContainsString('name="description"', $html);
        $this->assertStringContainsString('property="og:title"', $html);
    }

    public function test_links_bio_landing_page_renders_in_dashboard_design_language(): void
    {
        $response = $this->get('/links');

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('Delia Lazarescu', $content);
        $this->assertStringContainsString('@Tech.Unicorn', $content);
        $this->assertStringContainsString('href="/"', $content);
        $this->assertStringNotContainsString('/techunicorn', $content);
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
        Http::assertSent(fn($req) => ($req->data()['success_url'] ?? null) === url('/?payment=success&deal=' . $deal->id));
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
        $post = fn(?string $sig) => $this->call(
            'POST',
            '/api/stripe/webhook',
            [],
            [],
            [],
            array_filter(['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $sig]),
            $payload
        );

        // No secret configured: rejected.
        $post(null)->assertStatus(400);

        Setting::put('stripe_webhook_secret', 'whsec_test');
        $post('t=' . time() . ',v1=forged')->assertStatus(400);
        $old = time() - 3600;
        $post("t={$old},v1=" . hash_hmac('sha256', "{$old}.{$payload}", 'whsec_test'))->assertStatus(400);
        $this->assertSame('pending', $deal->fresh()->payment_status);

        $t = time();
        $post("t={$t},v1=" . hash_hmac('sha256', "{$t}.{$payload}", 'whsec_test'))->assertOk();

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
            'name' => 'Sarah Connor',
            'email' => 'sarah@example.com',
            'company' => 'Cyberdyne',
            'project_type' => 'speaking',
            'preferred_date' => '2026-11-15',
            'preferred_time' => '15:00 UTC',
        ])->assertOk();
        Deal::create(['brand_name' => 'Paid Co', 'stage' => 'paid', 'rate' => 500, 'currency' => 'USD', 'payment_status' => 'paid', 'paid_at' => now()]);
        Deal::create(['brand_name' => 'Signed Co', 'stage' => 'contracted', 'rate' => 5000, 'currency' => 'USD', 'payment_status' => 'pending']);

        $this->actingAs($this->admin(['name' => 'Delia', 'email' => 'delia@example.com', 'password' => 'secret123']));

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
        $book = fn(string $email, ?string $service) => $this->postJson('/api/booking', array_filter([
            'name' => 'Lead',
            'email' => $email,
            'project_type' => 'consulting',
            'preferred_date' => '2026-11-15',
            'preferred_time' => '15:00 UTC',
            'service' => $service,
        ]))->assertOk();
        $book('a@example.com', 'Technical Consulting');
        $book('b@example.com', 'Technical Consulting');
        $book('c@example.com', 'Speaking & Workshops');
        $book('d@example.com', null);

        $deal = Deal::where('contact_email', 'a@example.com')->first();
        $this->assertSame('Technical Consulting', $deal->deliverables[0]['service']);
        $this->assertArrayNotHasKey('service', Deal::where('contact_email', 'd@example.com')->first()->deliverables[0]);

        $this->actingAs($this->admin(['name' => 'Delia', 'email' => 'delia@example.com', 'password' => 'secret123']));
        $this->assertSame([
            ['service' => 'Technical Consulting', 'count' => 2],
            ['service' => 'Speaking & Workshops', 'count' => 1],
        ], $this->getJson('/api/dashboard')->json('inbound.by_service'));
        $messages = collect($this->getJson('/api/agents/notifications')->json('events'))->pluck('message')->implode(' ');
        $this->assertStringContainsString('Technical Consulting', $messages);
    }

    public function test_closing_section_leads_are_attributed_on_the_dashboard(): void
    {
        foreach (['Let us build: An app or MVP', 'Let us build: An app or MVP', 'Let us build: A keynote or workshop'] as $i => $service) {
            $this->postJson('/api/booking', [
                'name' => "Lead {$i}",
                'email' => "lead{$i}@example.com",
                'project_type' => str_contains($service, 'keynote') ? 'speaking' : 'mvp_build',
                'preferred_date' => '2026-11-20',
                'preferred_time' => '15:00 UTC',
                'service' => $service,
            ])->assertOk();
        }

        $this->actingAs($this->admin(['name' => 'Delia', 'email' => 'delia@example.com', 'password' => 'secret123']));
        $dash = $this->getJson('/api/dashboard')->json();
        $this->assertSame(['service' => 'Let us build: An app or MVP', 'count' => 2], $dash['inbound']['by_service'][0]);
        $this->assertSame(1, $dash['speaking']['requests'], 'The keynote tile counts as a speaking request');
    }
}
