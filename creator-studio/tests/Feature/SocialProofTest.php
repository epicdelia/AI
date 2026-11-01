<?php

namespace Tests\Feature;

use App\Domains\Deals\FollowUps;
use App\Domains\Deals\LeadScore;
use App\Domains\Engagement\Models\EngagementItem;
use App\Domains\Social\Models\SocialPost;
use App\Http\Controllers\Api\StripePaymentController;
use App\Models\Deal;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Models\Testimonial;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testimonials, the email opt-in, inbox leads into Deals, Stripe renewals and MRR, unpaid
 * checkouts and the lead fit score.
 */
class SocialProofTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Setting::put('timezone', 'UTC');
        Setting::put('creator_name', 'Delia Lazarescu');
        $this->travelTo(CarbonImmutable::parse('2026-11-16 09:00', 'UTC'));
    }

    private function signIn(): void
    {
        $this->actingAs($this->admin(['email' => 'delia@example.com', 'name' => 'Delia', 'password' => 'secret123']));
    }

    public function test_a_paid_client_is_asked_for_a_testimonial_and_approved_ones_reach_the_landing_page(): void
    {
        $this->signIn();
        $deal = Deal::create(['brand_name' => 'Acme', 'contact_name' => 'Ana Ruiz', 'contact_email' => 'ana@acme.dev', 'stage' => 'paid', 'rate' => 6000, 'payment_status' => 'paid', 'paid_at' => now()->subDays(8)]);
        $due = collect(FollowUps::list())->firstWhere('deal_id', $deal->id);
        $this->assertSame('testimonial', $due['kind']);

        $draft = $this->postJson("/api/deals/{$deal->id}/follow-up")->assertOk()->json('draft');
        $t = Testimonial::where('deal_id', $deal->id)->firstOrFail();
        $this->assertStringContainsString("/review/{$t->token}", $draft['body']);
        $this->assertNull(collect(FollowUps::list())->firstWhere('deal_id', $deal->id)); // asked: no longer due
        $this->assertSame($t->id, Testimonial::requestFor($deal)->id); // one link per deal

        auth()->logout();
        $this->getJson("/api/review/{$t->token}")->assertOk()->assertJson(['name' => 'Ana Ruiz', 'company' => 'Acme', 'submitted' => false]);
        $this->postJson("/api/review/{$t->token}", ['name' => 'Ana Ruiz', 'quote' => 'Short', 'permission' => true])->assertStatus(422);
        $this->postJson("/api/review/{$t->token}", ['name' => 'Ana Ruiz', 'role' => 'CTO', 'company' => 'Acme', 'quote' => 'Shipped our MVP in three weeks, clean code.', 'rating' => 5, 'permission' => true])->assertOk();
        $this->getJson('/api/landing/testimonials')->assertOk()->assertJsonCount(0, 'testimonials'); // not until she approves
        $this->assertDatabaseHas('agent_events', ['type' => 'lead']);

        $this->signIn();
        $this->patchJson("/api/testimonials/{$t->id}", ['status' => 'approved'])->assertOk()->assertJsonPath('public', 1);
        $this->getJson('/api/landing/testimonials')->assertJsonPath('testimonials.0.quote', 'Shipped our MVP in three weeks, clean code.');
        $this->assertArrayNotHasKey('token', $this->getJson('/api/landing/testimonials')->json('testimonials.0'));

        // No permission: never public, even if approved.
        $private = Testimonial::create(['name' => 'Bo', 'quote' => 'Great work but keep this private.', 'status' => 'approved', 'permission' => false]);
        $this->assertCount(1, Testimonial::public());
        $this->postJson('/api/testimonials', ['name' => 'Cy', 'quote' => 'From a LinkedIn recommendation.'])->assertCreated();
        $this->assertCount(2, Testimonial::public());
        $this->getJson('/api/testimonials')->assertOk()->assertJsonCount(3, 'testimonials');
        $this->getJson('/api/review/'.str_repeat('q', 32))->assertNotFound();
        $this->assertNotNull($private);
    }

    public function test_email_opt_in_collects_subscribers_with_their_source_and_exports_csv(): void
    {
        $this->getJson('/api/landing/lead-magnet')->assertOk()->assertExactJson(['enabled' => false]);
        $this->signIn();
        $this->putJson('/api/landing/lead-magnet', ['enabled' => true, 'title' => 'AI toolkit', 'button' => 'Get it'])->assertStatus(422); // needs the download link
        $this->putJson('/api/landing/lead-magnet', ['enabled' => true, 'title' => 'AI toolkit', 'button' => 'Get it', 'delivery_url' => 'https://notion.so/toolkit'])->assertOk();
        auth()->logout();

        $m = $this->getJson('/api/landing/lead-magnet')->assertOk()->json();
        $this->assertSame('AI toolkit', $m['title']);
        $this->assertArrayNotHasKey('delivery_url', $m); // only after signing up

        $this->postJson('/api/subscribe', ['email' => 'Dev@Corp.io', 'attribution' => ['ref' => 'yt-abcdefghijk']])->assertOk()->assertJsonPath('delivery_url', 'https://notion.so/toolkit');
        $this->postJson('/api/subscribe', ['email' => 'dev@corp.io', 'name' => 'Dev'])->assertOk(); // same person again
        $this->postJson('/api/subscribe', ['email' => 'bot@spam.io', 'website' => 'http://spam'])->assertOk(); // honeypot
        $this->postJson('/api/subscribe', ['email' => 'nope'])->assertStatus(422);

        $this->assertSame(1, Subscriber::count());
        $s = Subscriber::first();
        $this->assertSame(['dev@corp.io', 'Dev', 'youtube', 'AI toolkit'], [$s->email, $s->name, $s->source, $s->magnet]);

        $this->signIn();
        $this->getJson('/api/subscribers')->assertOk()->assertJson(['total' => 1, 'last_30' => 1])->assertJsonPath('by_source.0.source', 'youtube');
        $this->get('/api/subscribers/export')->assertStatus(423); // personal data: password again first
        $csv = $this->withSession(['auth.password_confirmed_at' => time()])->get('/api/subscribers/export')->assertOk()->streamedContent();
        $this->assertStringContainsString("email,name,source,signed_up_for,signed_up_at\n", $csv);
        $this->assertStringContainsString('dev@corp.io,Dev,youtube,"AI toolkit"', $csv);
        $this->assertSame(1, $this->getJson('/api/dashboard')->json('revenue.subscribers_30d'));
    }

    public function test_an_inbox_lead_becomes_a_deal_credited_to_its_network_and_post(): void
    {
        $this->signIn();
        $post = SocialPost::create(['platform' => 'instagram', 'format' => 'reel', 'body' => 'AI agents', 'status' => 'posted']);
        $item = EngagementItem::create(['platform' => 'instagram', 'kind' => 'dm', 'external_id' => 'm1', 'social_post_id' => $post->id, 'author' => 'founder.jo',
            'text' => 'We want to sponsor a video, what are your rates?', 'received_at' => now(), 'status' => 'new', 'intent' => 'lead']);

        $deal = $this->postJson("/api/engage/items/{$item->id}/to-deal")->assertCreated()->json('deal');
        $this->assertSame('instagram', $deal['source']);
        $this->assertSame("post:{$post->id}", $deal['attribution']['item']);
        $this->assertSame('inquiry', $deal['stage']);
        $this->assertStringContainsString('sponsor a video', $deal['notes']);
        $this->postJson("/api/engage/items/{$item->id}/to-deal")->assertOk()->assertJsonPath('deal.id', $deal['id']); // not twice
        $this->assertSame($deal['id'], $item->fresh()->deal_id);
    }

    public function test_stripe_renewals_count_and_cancellations_end_the_retainer(): void
    {
        Setting::put('stripe_webhook_secret', 'whsec_test');
        $deal = Deal::create(['brand_name' => 'Retainer Co', 'stage' => 'inquiry', 'rate' => 3500, 'payment_status' => 'pending', 'source' => 'linkedin']);
        $send = function (array $event) {
            $body = json_encode($event);
            $t = time();

            return $this->call('POST', '/api/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', "{$t}.{$body}", 'whsec_test'), 'CONTENT_TYPE' => 'application/json'], $body);
        };

        $send(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_1', 'payment_status' => 'paid', 'subscription' => 'sub_9', 'amount_total' => 350000, 'currency' => 'usd', 'metadata' => ['deal_id' => $deal->id]]]])->assertOk();
        $deal->refresh();
        $this->assertSame(['paid', 'sub_9', 'active'], [$deal->stage, $deal->stripe_subscription_id, $deal->subscription_status]);
        $this->assertSame(1, Payment::where('kind', 'checkout')->count());

        $this->travel(1)->months();
        $renewal = ['type' => 'invoice.paid', 'data' => ['object' => ['id' => 'in_2', 'billing_reason' => 'subscription_cycle', 'amount_paid' => 350000, 'currency' => 'usd',
            'parent' => ['subscription_details' => ['subscription' => 'sub_9']]]]];
        $send($renewal)->assertOk();
        $send($renewal)->assertOk(); // Stripe retries: counted once
        $send(['type' => 'invoice.paid', 'data' => ['object' => ['id' => 'in_1', 'billing_reason' => 'subscription_create', 'subscription' => 'sub_9', 'amount_paid' => 350000]]])->assertOk(); // the first invoice is the checkout
        $this->assertSame(1, Payment::where('kind', 'renewal')->count());

        $this->signIn();
        $rev = $this->getJson('/api/dashboard')->json('revenue');
        $this->assertEquals(3500, $rev['paid_this_month']); // the renewal; the deal itself was paid last month
        $this->assertEquals(3500, $rev['paid_last_month']);
        $this->assertEquals(3500, $rev['mrr']);
        $this->assertEquals(7000, $this->getJson('/api/deals/attribution?days=365')->json('totals.paid')); // lifetime value

        $send(['type' => 'customer.subscription.deleted', 'data' => ['object' => ['id' => 'sub_9']]])->assertOk();
        $this->assertSame('cancelled', $deal->fresh()->subscription_status);
        $this->assertEquals(0, $this->getJson('/api/dashboard')->json('revenue.mrr'));
    }

    public function test_an_unpaid_checkout_is_a_follow_up_and_leads_get_a_fit_score(): void
    {
        $this->postJson('/api/stripe/checkout-session', ['package_id' => 'mvp_full', 'customer_name' => 'Kai', 'customer_email' => 'kai@startup.io', 'company' => 'Startup'])->assertOk();
        $deal = Deal::where('contact_email', 'kai@startup.io')->firstOrFail();
        $this->assertSame([], FollowUps::list());
        $this->travel(25)->hours();
        $this->assertSame('checkout', FollowUps::list()[0]['kind']);

        $score = $deal->fresh()->lead_score;
        $this->assertContains('work email (@startup.io)', $score['reasons']);
        $this->assertContains('started a checkout', $score['reasons']);
        $this->assertSame('rule of thumb', $score['basis']);

        $weak = Deal::create(['brand_name' => 'Pat Inquiry', 'contact_email' => 'pat@gmail.com', 'stage' => 'inquiry', 'source' => 'direct', 'deliverables' => [['project_type' => 'other']], 'notes' => 'Notes from client: None provided.']);
        $strong = Deal::create(['brand_name' => 'Seedco', 'contact_email' => 'cto@seedco.ai', 'stage' => 'inquiry', 'source' => 'youtube', 'attribution' => ['item' => 'yt:abcdefghijk'],
            'deliverables' => [['project_type' => 'mvp_build']], 'meeting_at' => now()->addDay(),
            'notes' => 'Notes from client: We just closed our seed round and need an AI onboarding assistant live by March. Budget is around $15k and we have designs ready for the main flows.']);
        $this->assertLessThan(45, LeadScore::for($weak)['score']);
        $this->assertGreaterThanOrEqual(70, LeadScore::for($strong)['score']);
        $this->assertContains('mentions budget or timing', LeadScore::for($strong)['reasons']);
        $this->assertNull(LeadScore::for(Deal::create(['brand_name' => 'Manual', 'stage' => 'inquiry']))); // not a website lead
        $this->assertTrue(StripePaymentController::validSignature('x', 't='.time().',v1='.hash_hmac('sha256', time().'.x', 'k'), 'k'));
    }
}
