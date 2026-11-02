<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
    }

    public function test_cards_use_live_audience_and_never_promise_discounts_that_dont_exist(): void
    {
        $d = $this->getJson('/api/landing/pricing')->assertOk()->json();
        $plans = collect($d['plans'])->keyBy('key');

        $this->assertSame(['strategy', 'retainer', 'mvp', 'brand'], $plans->keys()->all());
        $this->assertTrue($plans['mvp']['popular']);
        $this->assertSame([
            'package' => 'mvp_deposit',
            'price' => 6000,
        ], $plans['mvp']['deposit']);
        $this->assertSame('open', $plans['mvp']['availability']['status']);
        $this->assertSame('519K+ audience reach', $plans['brand']['badge']['label']);
        $this->assertSame(['Instagram', 'TikTok', 'X', 'Total reach'], array_column($plans['brand']['metrics'], 'label'));
        $this->assertStringContainsString('384K Instagram, 45K TikTok & 28K X reach', json_encode($plans['brand']['benefits']));
        $this->assertStringContainsString('90-minute', $plans['strategy']['desc']);
        $json = json_encode($d);
        $this->assertStringNotContainsString('Save 15%', $json);
        $this->assertStringNotContainsString('54M', $json);
        $this->assertFalse($d['stripe_configured']);
    }

    public function test_her_prices_reach_the_page_and_stripe_checkout(): void
    {
        $this->putJson('/api/pricing', [
            'plans' => $this->plans(),
        ])->assertUnauthorized();
        $this->signIn();
        $this->putJson('/api/pricing', [
            'plans' => $this->plans([
                'mvp' => [
                    'price' => 15000,
                    'popular' => false,
                ],
                'strategy' => [
                    'price' => 650,
                    'popular' => true,
                ],
                'unknown' => [
                    'price' => 1,
                    'visible' => true,
                ],
            ]),
        ])->assertOk(); // unknown plans are ignored

        $plans = collect($this->getJson('/api/landing/pricing')->json('plans'))->keyBy('key');
        $this->assertSame(15000, $plans['mvp']['price']);
        $this->assertSame(7500, $plans['mvp']['deposit']['price'], 'Deposit is half the build');
        $this->assertTrue($plans['strategy']['popular']);
        $this->assertFalse($plans['mvp']['popular'], 'Only one plan is most popular');
        $this->assertSame(750000, $this->getJson('/api/stripe/packages')->json('packages.mvp_deposit.amount'));

        Setting::put('stripe_secret_key', 'sk_test_123');
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'cs_1',
                'url' => 'https://checkout.stripe.com/c/pay/cs_1',
            ]),
        ]);
        $dealId = $this->postJson('/api/stripe/checkout-session', [
            'package_id' => 'advisory_session',
            'customer_name' => 'A',
            'customer_email' => 'a@example.com',
        ])->assertOk()->json('deal_id');
        Http::assertSent(fn ($r) => ($r->data()['line_items'][0]['price_data']['unit_amount'] ?? null) === 65000);
        $this->assertEquals(650, Deal::find($dealId)->rate);
        $this->assertSame('advisory_session', Deal::find($dealId)->deliverables[0]['package']);
    }

    public function test_hidden_plans_leave_the_page_but_one_always_stays(): void
    {
        $this->signIn();
        $this->putJson('/api/pricing', [
            'plans' => $this->plans([
                'brand' => [
                    'visible' => false,
                ],
            ]),
        ])->assertOk();
        $this->assertNotContains('brand', array_column($this->getJson('/api/landing/pricing')->json('plans'), 'key'));

        $none = array_map(fn ($p) => [
            'visible' => false,
        ] + $p, $this->plans());
        $this->putJson('/api/pricing', [
            'plans' => $none,
        ])->assertUnprocessable();
        $this->putJson('/api/pricing', [
            'plans' => $this->plans([
                'mvp' => [
                    'price' => 0,
                ],
            ]),
        ])->assertUnprocessable();
    }

    public function test_dashboard_shows_what_each_plan_brought_in(): void
    {
        $this->postJson('/api/stripe/checkout-session', [
            'package_id' => 'mvp_deposit',
            'customer_name' => 'A',
            'customer_email' => 'a@example.com',
        ])->assertOk();
        $this->postJson('/api/stripe/checkout-session', [
            'package_id' => 'retainer',
            'customer_name' => 'B',
            'customer_email' => 'b@example.com',
        ])->assertOk();
        Deal::where('contact_email', 'b@example.com')->first()->update([
            'payment_status' => 'paid',
            'stage' => 'paid',
            'paid_at' => now(),
        ]);
        $this->postJson('/api/booking', [
            'name' => 'C',
            'email' => 'c@example.com',
            'project_type' => 'mvp_build',
            'preferred_date' => '2026-11-20',
            'preferred_time' => '15:00 UTC',
            'service' => 'Pricing: MVP Build',
        ])->assertOk();

        $this->signIn();
        $pricing = $this->getJson('/api/dashboard')->assertOk()->json('pricing');
        $plans = collect($pricing['plans'])->keyBy('key');
        $this->assertSame(1, $plans['mvp']['requests']);
        $this->assertSame(1, $plans['mvp']['calls']);
        $this->assertSame(1, $plans['retainer']['paid']);
        $this->assertEquals(3500, $plans['retainer']['revenue']);
        $this->assertEquals(3500, $pricing['revenue']);
        $this->assertSame(2, $pricing['requests']);
    }

    private function signIn(): void
    {
        $this->actingAs($this->admin([
            'name' => 'Delia',
            'email' => 'delia@example.com',
            'password' => 'secret123',
        ]));
    }

    private function plans(array $changes = []): array
    {
        $plans = [
            'strategy' => [
                'price' => 500,
                'visible' => true,
            ],
            'retainer' => [
                'price' => 3500,
                'visible' => true,
            ],
            'mvp' => [
                'price' => 12000,
                'visible' => true,
                'popular' => true,
            ],
            'brand' => [
                'price' => 5000,
                'visible' => true,
            ],
        ];

        return array_replace_recursive($plans, $changes);
    }
}
