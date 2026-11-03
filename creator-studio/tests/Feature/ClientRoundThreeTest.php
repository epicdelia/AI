<?php

namespace Tests\Feature;

use App\Domains\Clients\ReviewAndOnboarding;
use App\Domains\Deals\Billing;
use App\Domains\Deals\DealRight;
use App\Domains\Deals\Rights;
use App\Models\Deal;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ClientRoundThreeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Mail::fake();
        $this->admin();
    }

    private function deal(array $attrs = []): Deal
    {
        return Deal::create($attrs + [
            'brand_name' => 'Acme', 'rate' => 1000, 'currency' => 'USD', 'stage' => 'production',
            'user_id' => $this->client()->id, 'contact_email' => 'buyer@acme.test',
        ]);
    }

    private function webhook(array $event)
    {
        $payload = json_encode($event);
        $t = time();
        $sig = hash_hmac('sha256', "{$t}.{$payload}", 'whsec');

        return $this->call('POST', '/api/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$t},v1={$sig}", 'CONTENT_TYPE' => 'application/json'], $payload);
    }

    public function test_review_gets_a_deadline_and_two_reminders_at_most(): void
    {
        $deal = $this->deal();
        $deal->update(['stage' => 'in_review']);
        $this->assertSame(now()->addDays(3)->toDateString(), $deal->fresh()->review_due_on->toDateString());

        $deal->forceFill(['review_due_on' => now()->addDay()->toDateString()])->save();
        $this->assertSame(1, ReviewAndOnboarding::remindReviews());
        $this->assertSame(0, ReviewAndOnboarding::remindReviews()); // once a day
        $deal->forceFill(['review_reminded_on' => now()->subDay()->toDateString()])->saveQuietly();
        $this->assertSame(1, ReviewAndOnboarding::remindReviews());
        $deal->forceFill(['review_reminded_on' => now()->subDay()->toDateString()])->saveQuietly();
        $this->assertSame(0, ReviewAndOnboarding::remindReviews()); // max two

        $this->actingAs($deal->client)->getJson("/api/portal/requests/{$deal->id}")->assertOk()
            ->assertJsonPath('request.review_due_on', now()->addDay()->toDateString());
    }

    public function test_onboarding_ticks_itself_and_custom_steps_by_hand(): void
    {
        $deal = $this->deal(['accepted_at' => now()]);
        $this->actingAs($deal->client);
        $c = $this->getJson("/api/portal/requests/{$deal->id}")->json('request.onboarding');
        $this->assertTrue($c['active']);
        $this->assertSame(0, $c['done']);

        $deal->forceFill(['intake' => [['key' => 'goal', 'label' => 'Goal', 'answer' => 'Launch']]])->save();
        $this->postJson("/api/portal/requests/{$deal->id}/onboarding/brief", ['done' => true])->assertStatus(422);
        $c = $this->postJson("/api/portal/requests/{$deal->id}/onboarding/billing", ['done' => true])->assertOk()->json('onboarding');
        $this->assertSame(2, $c['done']);
    }

    public function test_deposit_then_balance_after_publish(): void
    {
        Setting::put('stripe_secret_key', 'sk_test');
        Setting::put('stripe_webhook_secret', 'whsec');
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['url' => 'https://pay.test/x', 'id' => 'cs_dep'])]);
        $deal = $this->deal(['accepted_at' => now(), 'stage' => 'contracted']);
        $this->actingAs($this->admin())->putJson("/api/deals/{$deal->id}/terms", ['deposit_percent' => 50, 'payment_plan' => 'net15'])->assertOk()
            ->assertJsonPath('billing.next_amount', 500)->assertJsonPath('billing.next_part', 'deposit');

        $this->actingAs($deal->client)->postJson("/api/portal/requests/{$deal->id}/pay")->assertOk();
        Http::assertSent(fn ($r) => $r['line_items'][0]['price_data']['unit_amount'] === 50000 && $r['metadata']['part'] === 'deposit');

        $this->webhook(['type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_dep', 'payment_status' => 'paid', 'amount_total' => 50000, 'currency' => 'usd', 'metadata' => ['deal_id' => $deal->id, 'part' => 'deposit'],
        ]]])->assertOk();
        $deal->refresh();
        $this->assertNotNull($deal->deposit_paid_at);
        $this->assertNotSame('paid', $deal->payment_status);
        $this->assertSame('deposit', Payment::first()->kind);

        // The balance waits until the work is live.
        $this->postJson("/api/portal/requests/{$deal->id}/pay")->assertStatus(409);
        $deal->update(['stage' => 'invoiced', 'invoiced_at' => now()]);
        $this->assertSame(now()->addDays(15)->toDateString(), $deal->fresh()->invoice_due_on->toDateString());
        $this->assertSame(['amount' => 500.0, 'part' => 'balance'], Billing::nextPayment($deal->fresh()));
    }

    public function test_overdue_reminders_on_day_1_7_14_and_late_fee_only_when_set(): void
    {
        $deal = $this->deal(['stage' => 'invoiced', 'payment_status' => 'invoiced']);
        $deal->forceFill(['invoice_due_on' => now()->subDay()->toDateString()])->saveQuietly();
        $this->assertTrue(Billing::isOverdue($deal->fresh()));
        $this->assertSame(1, Billing::remindOverdue());
        $this->assertSame(0, Billing::remindOverdue());
        $deal->forceFill(['invoice_due_on' => now()->subDays(3)->toDateString(), 'pay_reminded_on' => null])->saveQuietly();
        $this->assertSame(0, Billing::remindOverdue()); // next one on day 7
        $deal->forceFill(['invoice_due_on' => now()->subDays(7)->toDateString()])->saveQuietly();
        $this->assertSame(1, Billing::remindOverdue());
        $this->assertSame(0.0, Billing::lateFeePercent());
        $this->actingAs($this->admin())->getJson('/api/needs-you')->assertJsonPath('groups.0.id', 'money');
    }

    public function test_usage_rights_start_on_publish_remind_and_extend_when_paid(): void
    {
        Setting::put('rights_pricing', json_encode(['usage' => 20]));
        Setting::put('stripe_secret_key', 'sk_test');
        Setting::put('stripe_webhook_secret', 'whsec');
        $deal = $this->deal();
        $this->actingAs($this->admin());
        $right = $this->postJson("/api/deals/{$deal->id}/rights", ['kind' => 'usage', 'days' => 30, 'scope' => 'Paid social'])->assertCreated()->json('rights.0');
        $this->assertEquals(200, $right['price']); // 20% of 1000 per 30 days
        $this->assertNull($right['ends_on']);

        $deal->update(['published_at' => now()->subDays(20)]);
        $r = DealRight::first();
        $this->assertSame(now()->addDays(10)->toDateString(), $r->ends_on->toDateString());
        $this->assertSame(1, Rights::remindEnding());
        $this->assertSame(0, Rights::remindEnding());

        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['url' => 'https://pay.test/r', 'id' => 'cs_r'])]);
        $this->actingAs($deal->client)->postJson("/api/portal/requests/{$deal->id}/rights/{$r->id}/extend")->assertOk();
        $this->webhook(['type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_r', 'payment_status' => 'paid', 'amount_total' => 20000, 'metadata' => ['deal_id' => $deal->id, 'part' => "rights:{$r->id}"],
        ]]])->assertOk();
        $this->assertSame(now()->addDays(40)->toDateString(), $r->fresh()->ends_on->toDateString());
        $this->assertSame(1, $r->fresh()->renewals);
        $this->assertNotSame('paid', $deal->fresh()->payment_status);
    }

    public function test_terms_are_hers_alone(): void
    {
        $deal = $this->deal();
        $this->actingAs($this->staff())->putJson("/api/deals/{$deal->id}/terms", ['deposit_percent' => 10])->assertForbidden();
        $this->actingAs($deal->client)->putJson('/api/clients/terms', ['late_fee_percent' => 5])->assertForbidden();
        $this->actingAs($this->admin())->putJson('/api/clients/terms', ['late_fee_percent' => 2])->assertOk()->assertJsonPath('late_fee_percent', 2);
    }
}
