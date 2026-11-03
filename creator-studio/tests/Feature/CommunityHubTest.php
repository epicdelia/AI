<?php

namespace Tests\Feature;

use App\Domains\Community\Community;
use App\Domains\Community\Models\CommunityItem;
use App\Domains\Community\Models\CommunityMembership;
use App\Domains\Crm\Models\CrmContact;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CommunityHubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        $this->admin();
    }

    private function makePost(array $attrs = []): CommunityItem
    {
        return CommunityItem::create($attrs + [
            'type' => 'dispatch',
            'title' => 'How I plan a week',
            'body' => 'Full text',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function test_a_fresh_hub_shows_no_invented_content(): void
    {
        $this->actingAs($this->member());
        $d = $this->getJson('/api/portal/community/hub')->assertOk()->json();
        foreach (['kits', 'blueprints', 'dispatches', 'perks', 'showcase', 'ama', 'leaderboard'] as $k) {
            $this->assertSame([], $d[$k], "{$k} should start empty");
        }
        $this->assertSame('Newcomer', $d['me']['name']);
        $this->assertFalse($d['pro']['available']);
    }

    public function test_questions_earn_points_capped_per_day(): void
    {
        $m = $this->member();
        $this->actingAs($m);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/portal/community/ask', ['question' => "Question number {$i} about pacing?"])->assertOk();
        }
        $this->assertSame(Community::DAILY_ASK_POINTS * Community::POINTS['asked'], Community::points($m));
    }

    public function test_one_vote_per_member_toggles_and_never_on_own_post(): void
    {
        $author = $this->member();
        $q = CommunityItem::create(['type' => 'question', 'title' => 'How long should intros be?', 'status' => 'published', 'published_at' => now(), 'user_id' => $author->id]);

        $this->actingAs($author)->postJson("/api/portal/community/items/{$q->id}/vote")->assertStatus(422);

        $voter = $this->member(['email' => 'voter@x.test']);
        $this->actingAs($voter);
        $this->postJson("/api/portal/community/items/{$q->id}/vote")->assertOk()->assertJsonPath('voted', true)->assertJsonPath('upvotes', 1);
        $this->postJson("/api/portal/community/upvote", ['id' => $q->id])->assertOk()->assertJsonPath('voted', false)->assertJsonPath('upvotes', 0);
        $this->postJson("/api/portal/community/items/{$q->id}/vote")->assertOk()->assertJsonPath('upvotes', 1);
        $this->assertSame(Community::POINTS['upvoted'], Community::points($author));
    }

    public function test_showcase_waits_for_approval_then_earns_points(): void
    {
        $author = $this->member();
        $this->actingAs($author);
        $id = $this->postJson('/api/portal/community/showcase', [
            'title' => 'My terminal agent',
            'description' => 'An agent that runs in the terminal.',
            'repo_url' => 'https://github.com/me/agent',
        ])->assertCreated()->json('item.id');

        $other = $this->member(['email' => 'other@x.test']);
        $this->actingAs($other)->getJson('/api/portal/community/hub')->assertJsonCount(0, 'showcase');
        $this->actingAs($author)->getJson('/api/portal/community/hub')->assertJsonPath('showcase.0.status', 'pending');

        $this->actingAs($this->admin())->patchJson("/api/community/admin/items/{$id}", ['status' => 'published'])->assertOk();
        $this->actingAs($other)->getJson('/api/portal/community/hub')->assertJsonCount(1, 'showcase');
        $this->assertSame(Community::POINTS['showcase_approved'], Community::points($author));
    }

    public function test_answering_emails_the_member_and_awards_points(): void
    {
        Mail::fake();
        $m = $this->member();
        $q = CommunityItem::create(['type' => 'question', 'title' => 'Which mic do you use?', 'status' => 'published', 'published_at' => now(), 'user_id' => $m->id]);
        $this->actingAs($this->admin())->patchJson("/api/community/admin/items/{$q->id}", ['answer' => 'A dynamic one.'])->assertOk()
            ->assertJsonPath('item.answer', 'A dynamic one.');
        $this->assertNotNull($q->fresh()->answered_at);
        $this->assertSame(Community::POINTS['answered'], Community::points($m));
    }

    public function test_pro_content_is_locked_until_membership(): void
    {
        $this->makePost(['tier' => 'pro', 'title' => 'Pro deep dive', 'body' => 'Secret body', 'url' => 'https://example.com/pro']);
        $m = $this->member();
        $this->actingAs($m);
        $d = $this->getJson('/api/portal/community/hub')->json('dispatches.0');
        $this->assertTrue($d['locked']);
        $this->assertNull($d['body']);
        $this->assertNull($d['url']);

        CommunityMembership::create(['user_id' => $m->id, 'status' => 'comped']);
        $d = $this->getJson('/api/portal/community/hub')->json('dispatches.0');
        $this->assertFalse($d['locked']);
        $this->assertSame('Secret body', $d['body']);
    }

    public function test_pro_checkout_and_webhook_activate_and_end_membership(): void
    {
        Setting::put('stripe_secret_key', 'sk_test');
        Setting::put('stripe_webhook_secret', 'whsec');
        Setting::put('community_pro', json_encode(['enabled' => true, 'amount' => 9, 'name' => 'Inner circle']));
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['url' => 'https://checkout.stripe.test/x', 'id' => 'cs_1'])]);
        $m = $this->member();
        $this->actingAs($m)->postJson('/api/portal/community/pro/checkout')->assertOk()->assertJsonPath('url', 'https://checkout.stripe.test/x');
        Http::assertSent(fn ($r) => $r['mode'] === 'subscription' && $r['metadata']['kind'] === 'community' && $r['line_items'][0]['price_data']['unit_amount'] === 900);

        $send = function (array $event) {
            $payload = json_encode($event);
            $t = time();
            $sig = hash_hmac('sha256', "{$t}.{$payload}", 'whsec');

            return $this->call('POST', '/api/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$t},v1={$sig}", 'CONTENT_TYPE' => 'application/json'], $payload);
        };
        $send(['type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_1', 'payment_status' => 'paid', 'customer' => 'cus_9', 'subscription' => 'sub_9',
            'metadata' => ['kind' => 'community', 'user_id' => $m->id],
        ]]])->assertOk();
        $this->assertTrue(Community::isPro($m->fresh()));
        $this->assertSame('cus_9', $m->fresh()->stripe_customer_id);

        $send(['type' => 'customer.subscription.deleted', 'data' => ['object' => ['id' => 'sub_9']]])->assertOk();
        $this->assertFalse(Community::isPro($m->fresh()));
    }

    public function test_engaged_members_see_the_hire_card_and_she_can_add_them_to_crm(): void
    {
        $m = $this->member();
        $this->actingAs($m)->getJson('/api/portal/community/hub')->assertJsonPath('hire', null);
        foreach (range(1, 4) as $i) {
            Community::award($m, 'answered');
        }
        $this->getJson('/api/portal/community/hub')->assertJsonPath('hire.cta_label', 'Book an intro call');

        $this->actingAs($this->admin());
        $this->getJson('/api/community/admin')->assertOk()->assertJsonPath('warm_leads.0.email', $m->email);
        $this->postJson("/api/community/admin/members/{$m->id}/to-crm")->assertOk();
        $this->assertSame('community', CrmContact::where('email', $m->email)->value('source'));
    }

    public function test_members_and_staff_cannot_manage_the_community(): void
    {
        $this->actingAs($this->member())->getJson('/api/community/admin')->assertForbidden();
        $this->actingAs($this->staff())->getJson('/api/community/admin')->assertForbidden();
        $this->actingAs($this->staff())->postJson('/api/community/admin/items', ['type' => 'dispatch', 'title' => 'x'])->assertForbidden();
    }

    public function test_weekly_digest_goes_to_opted_in_members_once(): void
    {
        Mail::fake();
        $this->makePost();
        $this->member();
        $this->member(['email' => 'off@x.test'])->forceFill(['community_digest' => false])->save();
        $this->assertSame(1, Community::sendDigests());
        $this->assertSame(0, Community::sendDigests()); // not twice in a week
    }
}
