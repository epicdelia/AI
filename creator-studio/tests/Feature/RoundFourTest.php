<?php

namespace Tests\Feature;

use App\Domains\Community\Community;
use App\Domains\Community\MemberReferrals;
use App\Domains\Community\Models\CommunityItem;
use App\Domains\Community\Models\CommunityMembership;
use App\Domains\Deals\InquiryAutopilot;
use App\Domains\Social\Models\SocialPost;
use App\Domains\Team\Models\TeamTask;
use App\Http\Controllers\Api\AffiliateController;
use App\Http\Controllers\Api\NewsletterController;
use App\Models\Deal;
use App\Models\Idea;
use App\Models\Script;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoundFourTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Mail::fake();
        $this->admin();
    }

    private function webhook(array $event)
    {
        $payload = json_encode($event);
        $t = time();
        $sig = hash_hmac('sha256', "{$t}.{$payload}", 'whsec');

        return $this->call('POST', '/api/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$t},v1={$sig}", 'CONTENT_TYPE' => 'application/json'], $payload);
    }

    // ---- affiliate links ------------------------------------------------------------------

    public function test_affiliate_links_redirect_count_earnings_and_csv(): void
    {
        $this->actingAs($this->admin());
        $this->postJson('/api/affiliates', ['url' => 'https://partner.example.com/?aff=1', 'partner' => 'Cool Mic', 'placement' => 'video'])->assertOk()
            ->assertJsonPath('links.0.slug', 'cool-mic');
        $this->postJson('/api/affiliates', ['url' => 'https://partner.example.com/b', 'slug' => 'cool-mic'])->assertOk()->assertJsonPath('links.0.slug', 'cool-mic-2');

        auth()->logout();
        $this->get('/go/cool-mic')->assertRedirect('https://partner.example.com/?aff=1');
        $this->get('/go/cool-mic');
        $this->get('/go/nope')->assertNotFound();

        $this->actingAs($this->admin());
        $csv = UploadedFile::fake()->createWithContent('payout.csv', "date,link,amount\n2026-10-30,cool-mic,\$42.50\nnot a date,cool-mic,5\n2026-10-31,Some Partner,10\n");
        $r = $this->post('/api/affiliates/import', ['file' => $csv], ['Accept' => 'application/json'])->assertOk()->json();
        $this->assertSame(2, $r['added']);
        $this->assertSame([3], $r['skipped_rows']);
        $link = collect($r['links'])->firstWhere('slug', 'cool-mic');
        $this->assertSame(2, $link['clicks_30d']);
        $this->assertEquals(42.5, $link['earned']);

        $this->actingAs($this->staff())->getJson('/api/affiliates')->assertForbidden();
    }

    public function test_dead_link_check_never_fetches_private_hosts(): void
    {
        Http::fake(['partner.example.com/*' => Http::response('', 404)]);
        DB::table('affiliate_links')->insert([
            ['slug' => 'a', 'url' => 'https://partner.example.com/x', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'b', 'url' => 'http://127.0.0.1/admin', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->assertSame(2, AffiliateController::checkLinks()); // both broken (the local one isn't fetched)
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '127.0.0.1'));
    }

    // ---- questions → ideas → video ----------------------------------------------------------

    public function test_question_becomes_an_idea_and_the_asker_hears_when_it_is_live(): void
    {
        $m = $this->member();
        $q = CommunityItem::create(['type' => 'question', 'title' => 'How do you plan a week of videos?', 'status' => 'published', 'published_at' => now(), 'user_id' => $m->id, 'upvotes' => 7]);
        $id = $this->actingAs($this->admin())->postJson("/api/community/admin/items/{$q->id}/to-idea")->assertOk()->json('idea_id');
        $this->assertSame('How do you plan a week of videos?', Idea::find($id)->premise);
        $this->postJson("/api/community/admin/items/{$q->id}/to-idea")->assertJsonPath('idea_id', $id); // once

        $s = Script::create(['title' => 'Plan a week', 'idea_id' => $id, 'body' => 'x', 'status' => 'draft']);
        $s->update(['status' => 'published', 'youtube_url' => 'https://youtu.be/abcdefghijk']);
        $this->assertNotNull($q->fresh()->video_notified_at);
    }

    // ---- shop ------------------------------------------------------------------------------

    public function test_product_sale_download_limits_and_pro_discount(): void
    {
        Storage::fake('local');
        Setting::put('stripe_secret_key', 'sk_test');
        Setting::put('stripe_webhook_secret', 'whsec');
        $this->actingAs($this->admin());
        $pid = $this->postJson('/api/shop/admin/products', ['title' => 'Notion Planner', 'price' => 20, 'pro_discount_percent' => 25])->assertOk()->json('products.0.id');
        $this->patchJson("/api/shop/admin/products/{$pid}", ['status' => 'published'])->assertStatus(422); // no file yet
        $this->post("/api/shop/admin/products/{$pid}/file", ['file' => UploadedFile::fake()->create('planner.zip', 10)], ['Accept' => 'application/json'])->assertOk();
        $this->patchJson("/api/shop/admin/products/{$pid}", ['status' => 'published'])->assertOk();

        $m = $this->member();
        CommunityMembership::create(['user_id' => $m->id, 'status' => 'comped']);
        $this->actingAs($m)->getJson('/api/shop')->assertJsonPath('products.0.your_price', 15);
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['url' => 'https://pay.test/p', 'id' => 'cs_p'])]);
        $this->postJson('/api/shop/notion-planner/checkout')->assertOk()->assertJsonPath('checkout_url', 'https://pay.test/p');
        Http::assertSent(fn ($r) => $r['line_items'][0]['price_data']['unit_amount'] === 1500 && $r['metadata']['kind'] === 'product');
        $order = DB::table('orders')->first();

        $this->webhook(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_p', 'payment_status' => 'paid', 'amount_total' => 1500, 'metadata' => ['kind' => 'product', 'order_id' => $order->id]]]])->assertOk();
        $this->assertSame('paid', DB::table('orders')->value('status'));
        $this->assertSame(1, DB::table('products')->value('sales_count'));
        $this->getJson('/api/shop')->assertJsonPath('purchases.0.title', 'Notion Planner');
        $this->get("/api/account/purchases/{$order->id}/download")->assertOk();

        // The emailed link: 5 downloads, then gone.
        $token = 'abcdefghijabcdefghijabcdefghijabcdefghijabcdefgh';
        DB::table('orders')->update(['download_token_hash' => hash('sha256', $token), 'downloads' => 5]);
        auth()->logout();
        $this->get("/api/shop/download/{$token}")->assertStatus(410);
        DB::table('orders')->update(['downloads' => 0]);
        $this->get("/api/shop/download/{$token}")->assertOk();
    }

    // ---- newsletter ---------------------------------------------------------------------------

    public function test_newsletter_segments_batches_and_one_click_unsubscribe(): void
    {
        foreach (range(1, 3) as $i) {
            Subscriber::create(['email' => "s{$i}@x.test", 'source' => $i === 3 ? 'youtube' : 'site']);
        }
        Subscriber::create(['email' => 'gone@x.test', 'source' => 'site', 'unsubscribed_at' => now()]);
        $this->actingAs($this->admin());
        $id = $this->postJson('/api/newsletter', ['subject' => 'Hello', 'body' => "# Hi {name}\n\nNew video!", 'segment' => ['source' => 'site']])->assertOk()->json('id');
        $this->postJson("/api/newsletter/{$id}/send")->assertOk()->assertJsonPath('total', 2);
        $this->postJson("/api/newsletter/{$id}/send")->assertStatus(422);

        $this->assertSame(2, NewsletterController::drain());
        $this->assertSame('sent', DB::table('campaigns')->value('status'));

        $s1 = Subscriber::where('email', 's1@x.test')->first();
        $url = NewsletterController::unsubscribeUrl($s1->id);
        $this->get($url)->assertOk()->assertSee('unsubscribed');
        $this->assertNotNull($s1->fresh()->unsubscribed_at);
        $this->get('/api/newsletter/unsubscribe/' . $s1->id)->assertForbidden(); // unsigned
        $this->actingAs($this->staff())->getJson('/api/newsletter')->assertForbidden();
    }

    // ---- inquiry autopilot -------------------------------------------------------------------

    public function test_inquiry_autopilot_scores_by_her_rules_and_drafts_or_sends(): void
    {
        Setting::put('inquiry_rules', json_encode(['enabled' => true, 'mode' => 'draft', 'min_budget' => 3000, 'blocked_words' => ['casino'], 'min_lead_days' => 14, 'starting_price' => '$2,500']));
        $book = fn (array $x) => $this->postJson('/api/booking', $x + ['name' => 'Ana Brand', 'email' => 'ana@brand.test', 'project_type' => 'brand_deal', 'preferred_date' => 'next week', 'preferred_time' => 'morning'])->assertOk()->json('deal_id');

        $good = Deal::find($book(['company' => 'DevTools Inc', 'budget' => '3k_10k', 'timeline' => 'in 4 weeks', 'product_url' => 'https://devtools.test']));
        $this->assertSame('pursue', $good->fit_verdict);
        $this->assertSame('draft', $good->auto_reply);
        $this->assertStringContainsString('$2,500', $good->auto_reply_body);
        $this->assertStringContainsString('/kit/', $good->auto_reply_body);

        $bad = Deal::find($book(['company' => 'Lucky Casino', 'budget' => '10k_plus']));
        $this->assertSame('pass', $bad->fit_verdict);
        $mid = Deal::find($book(['company' => 'Small Co', 'timeline' => 'in 8 weeks', 'product_url' => 'https://s.test']));
        $this->assertSame('review', $mid->fit_verdict);
        $this->assertNull($mid->auto_reply);

        $this->actingAs($this->admin())->postJson("/api/deals/{$bad->id}/auto-reply/send")->assertOk()->assertJsonPath('ok', true);
        $this->assertSame('lost', $bad->fresh()->stage);
        $this->getJson('/api/needs-you')->assertJsonFragment(['id' => 'inquiries']);
        $this->assertSame(7, InquiryAutopilot::leadDays('1 week'));
    }

    // ---- sponsored copy ---------------------------------------------------------------------

    public function test_sponsored_copy_blocks_publishing_until_the_client_approves(): void
    {
        $client = $this->client();
        $post = SocialPost::create(['platform' => 'linkedin', 'format' => 'post', 'body' => 'Try DevTools: it saved me hours.', 'status' => 'draft']);
        $deal = Deal::create(['brand_name' => 'DevTools', 'rate' => 1000, 'stage' => 'production', 'user_id' => $client->id, 'social_post_id' => $post->id]);
        $this->actingAs($this->admin());
        $this->getJson("/api/deals/{$deal->id}/copy")->assertJsonPath('suggested', 'Try DevTools: it saved me hours.');
        $this->postJson("/api/deals/{$deal->id}/copy", ['text' => 'Try DevTools: it saved me hours.'])->assertOk()->assertJsonPath('copy.status', 'pending');
        $this->patchJson("/api/social/posts/{$post->id}", ['status' => 'scheduled'])->assertStatus(422);

        $this->actingAs($client);
        $this->getJson("/api/portal/requests/{$deal->id}")->assertJsonPath('request.sponsored_copy.status', 'pending');
        $this->postJson("/api/portal/requests/{$deal->id}/copy/changes", ['note' => 'Please mention the free tier'])->assertOk()->assertJsonPath('copy.status', 'changes');
        $this->actingAs($this->admin())->postJson("/api/deals/{$deal->id}/copy", ['text' => 'Try DevTools (free tier!)'])->assertOk();
        $this->actingAs($client)->postJson("/api/portal/requests/{$deal->id}/copy/approve")->assertOk()->assertJsonPath('copy.status', 'approved');
        $this->actingAs($this->admin())->patchJson("/api/social/posts/{$post->id}", ['status' => 'scheduled', 'scheduled_for' => now()->addDay()->toIso8601String()])->assertOk();

        $other = $this->client(['email' => 'other@x.test']);
        $this->actingAs($other)->postJson("/api/portal/requests/{$deal->id}/copy/approve")->assertNotFound();
    }

    // ---- stage checklists & workload ------------------------------------------------------------

    public function test_stage_checklist_gates_moving_on_and_workload_warns(): void
    {
        $alex = $this->staff();
        $this->actingAs($this->admin())->putJson('/api/team/checklists', ['stage' => 'editing', 'items' => ['Captions burned in', 'Loudness -14 LUFS']])->assertOk();
        $this->putJson("/api/team/hours/{$alex->id}", ['weekly_hours' => 2])->assertOk();
        $r = $this->postJson('/api/team/tasks', ['title' => 'Cut', 'stage' => 'editing', 'assignee_id' => $alex->id, 'estimate_minutes' => 180])->assertCreated();
        $this->assertStringContainsString('Alex Editor has about 3h', (string)$r->json('workload_warning'));
        $task = TeamTask::find($r->json('task.id'));

        $this->actingAs($alex);
        $this->patchJson("/api/team/tasks/{$task->id}", ['stage' => 'review'])->assertStatus(422)->assertJsonFragment(['message' => 'Finish the editing checklist first: Captions burned in, Loudness -14 LUFS.']);
        foreach ($task->toApi()['checklist'] as $item) {
            $this->postJson("/api/team/tasks/{$task->id}/checklist/{$item['key']}", ['done' => true])->assertOk();
        }
        $this->patchJson("/api/team/tasks/{$task->id}", ['stage' => 'review'])->assertOk();
        $this->getJson('/api/team/workload')->assertOk()->assertJsonPath('people.0.name', 'Alex Editor');
        $this->putJson('/api/team/checklists', ['stage' => 'review', 'items' => ['x']])->assertForbidden();
    }

    // ---- referrals ------------------------------------------------------------------------------

    public function test_referrals_reward_points_then_a_free_pro_month_with_limits(): void
    {
        $inviter = $this->member();
        $code = MemberReferrals::code($inviter);
        $sign = fn (string $email, string $ip = '1.1.1.1') => $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/auth/register', [
            'name' => 'Friend', 'email' => $email, 'password' => 'a-strong-pass-123', 'password_confirmation' => 'a-strong-pass-123', 'terms' => true, 'role' => 'member', 'ref' => $code,
        ])->assertStatus(202);
        $sign('f1@x.test');
        $this->assertSame(Community::POINTS['referred'], Community::points($inviter));
        $sign('f2@x.test'); // same network within 30 days: recorded, not rewarded
        $this->assertSame(Community::POINTS['referred'], Community::points($inviter));

        MemberReferrals::proPurchased(User::where('email', 'f1@x.test')->first());
        $this->assertTrue(Community::isPro($inviter->fresh()));
        $this->actingAs($inviter)->getJson('/api/portal/community/hub')->assertJsonPath('referrals.joined', 2)->assertJsonPath('referrals.went_pro', 1);
    }

    // ---- localization ---------------------------------------------------------------------------

    public function test_translations_are_pushed_to_youtube_localizations(): void
    {
        \App\Services\Llm::$fake = fn () => '{"title":"Hola título","description":"Descripción 00:00"}';
        \App\Domains\Social\Models\SocialAccount::create(['platform' => 'youtube', 'status' => 'connected', 'access_token' => 'tok', 'expires_at' => now()->addDays(2)]);
        Http::fake([
            'www.googleapis.com/youtube/v3/videos?part=snippet,localizations' => Http::response(['id' => 'abcdefghijk']),
            'www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => [['snippet' => ['title' => 'Hello', 'description' => 'Desc', 'categoryId' => '28']]]]),
        ]);
        $this->actingAs($this->admin());
        $this->postJson('/api/localize/abcdefghijk/translate', ['langs' => ['es'], 'title' => 'Hello', 'description' => 'Desc'])->assertOk()->assertJsonPath('drafts.0.title', 'Hola título');
        $this->postJson('/api/localize/abcdefghijk/push')->assertOk();
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r['localizations']['es']['title'] === 'Hola título' && $r['snippet']['defaultLanguage'] === 'en');
    }
}
