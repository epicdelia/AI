<?php

namespace Tests\Feature;

use App\Domains\Agents\Deals\DealFulfillmentGuardian;
use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Agents\YouTube\TitleBanditOptimizer;
use App\Domains\Copilot\CopilotTools;
use App\Domains\Copilot\Models\CopilotAction;
use App\Domains\Copilot\Models\CopilotThread;
use App\Domains\Deals\FollowUps;
use App\Domains\Deals\SponsorProspects;
use App\Domains\Landing\Attribution;
use App\Domains\Landing\BookingSlots;
use App\Domains\Social\Models\SocialPost;
use App\Domains\YouTube\TitleEvidence;
use App\Models\Deal;
use App\Models\Script;
use App\Models\Setting;
use App\Models\User;
use App\Services\Llm;
use App\Services\NextStep;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The money loop: where website leads come from, the funnel, follow-ups, proposals with a
 * payment link, booking slots, per-brand media kit links, sponsor prospects, the studio agent
 * for the AI engines, honest title scores, and the Copilot's deal tools.
 */
class RevenueLoopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Setting::put('timezone', 'UTC');
        Setting::put('creator_name', 'Delia Lazarescu');
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00', 'UTC')); // a Monday
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        parent::tearDown();
    }

    private function signIn(): void
    {
        $this->actingAs($this->admin(['name' => 'Delia', 'email' => 'delia@example.com', 'password' => 'secret123']));
    }

    // ------------------------------------------------------------ attribution

    public function test_attribution_turns_refs_tags_and_referrers_into_a_channel(): void
    {
        $this->assertSame(['source' => 'youtube', 'item' => 'yt:dQw4w9WgXcQ'], [
            'source' => ($r = Attribution::resolve(['ref' => 'yt-dQw4w9WgXcQ']))['source'], 'item' => $r['attribution']['item'],
        ]);
        $this->assertSame('linkedin', Attribution::resolve(['utm_source' => 'linkedin', 'utm_medium' => 'social'])['source']);
        $this->assertSame('search', Attribution::resolve(['referrer' => 'https://www.google.ca/'])['source']);
        $this->assertSame('x', Attribution::resolve(['referrer' => 'https://t.co/abc'])['source']);
        $this->assertSame('referral', Attribution::resolve(['referrer' => 'https://someblog.dev/post'])['source']);
        $this->assertSame('link_in_bio', Attribution::resolve(['ref' => 'bio'])['source']);
        // Her own site isn't a source, and nothing at all is "direct".
        $own = Attribution::resolve(['referrer' => 'https://thetechunicorn.com/about'], 'thetechunicorn.com');
        $this->assertSame('direct', $own['source']);
        $this->assertArrayNotHasKey('referrer', $own['attribution'] ?? []);
        $this->assertSame('direct', Attribution::resolve(null)['source']);

        $post = SocialPost::create(['platform' => 'threads', 'format' => 'post', 'body' => 'hi', 'status' => 'posted']);
        $r = Attribution::resolve(['ref' => "post-{$post->id}"]);
        $this->assertSame('threads', $r['source']);
        $this->assertSame("post:{$post->id}", $r['attribution']['item']);

        $this->assertSame('https://site.dev/?a=1&ref=yt-abc', Attribution::trackedUrl('https://site.dev/?a=1', 'yt-abc'));
    }

    public function test_booking_and_checkout_record_where_the_lead_came_from(): void
    {
        $this->postJson('/api/booking', [
            'name' => 'Sam Lee', 'email' => 'sam@acme.dev', 'company' => 'Acme', 'project_type' => 'mvp_build',
            'preferred_date' => '2026-11-10', 'preferred_time' => '15:00 UTC',
            'attribution' => ['ref' => 'yt-abcdefghijk', 'landing_path' => '/'],
        ])->assertOk()->assertJson(['status' => 'requested']);

        $deal = Deal::where('contact_email', 'sam@acme.dev')->firstOrFail();
        $this->assertSame('youtube', $deal->source);
        $this->assertSame('yt:abcdefghijk', $deal->attribution['item']);
        $this->assertSame('mvp_build', $deal->deliverables[0]['project_type']);

        $this->postJson('/api/stripe/checkout-session', [
            'package_id' => 'retainer', 'customer_name' => 'Jo', 'customer_email' => 'jo@corp.dev',
            'attribution' => ['utm_source' => 'newsletter'],
        ])->assertOk();
        $this->assertSame('newsletter', Deal::where('contact_email', 'jo@corp.dev')->value('source'));

        // Garbage attribution never blocks a lead.
        $this->postJson('/api/booking', [
            'name' => 'X', 'email' => 'x@y.dev', 'project_type' => 'other', 'preferred_date' => 'soon', 'preferred_time' => 'any',
            'attribution' => ['ref' => str_repeat('a', 301)],
        ])->assertStatus(422);
    }

    // ------------------------------------------------------------ funnel

    public function test_funnel_counts_events_and_reads_leads_and_payments_from_deals(): void
    {
        foreach (['visit', 'visit', 'cta', 'form_start'] as $e) {
            $this->postJson('/api/landing/event', ['event' => $e, 'attribution' => ['referrer' => 'https://youtube.com/watch?v=1']])->assertNoContent();
        }
        $this->postJson('/api/landing/event', ['event' => 'hack'])->assertStatus(422);
        Deal::create(['brand_name' => 'Lead Co', 'stage' => 'inquiry', 'rate' => 3500, 'source' => 'youtube']);
        Deal::create(['brand_name' => 'Paid Co', 'stage' => 'paid', 'rate' => 500, 'source' => 'youtube', 'payment_status' => 'paid', 'paid_at' => now()]);
        Deal::create(['brand_name' => 'Manual', 'stage' => 'inquiry', 'rate' => 999]); // added by hand: not a website lead

        $this->signIn();
        $f = $this->getJson('/api/landing/funnel?days=30')->assertOk()->json();
        $steps = collect($f['steps'])->keyBy('key');
        $this->assertSame(2, $steps['visit']['count']);
        $this->assertSame(1, $steps['cta']['count']);
        $this->assertSame(2, $steps['lead']['count']);
        $this->assertSame(1, $steps['paid']['count']);
        $this->assertEquals(50, $steps['cta']['of_previous']);
        $yt = collect($f['by_source'])->firstWhere('source', 'youtube');
        $this->assertEquals(['visits' => 2, 'leads' => 2, 'paid' => 1, 'revenue' => 500], array_intersect_key($yt, array_flip(['visits', 'leads', 'paid', 'revenue'])));

        $a = $this->getJson('/api/deals/attribution?days=90')->assertOk()->json();
        $this->assertSame(2, $a['totals']['leads']);
        $this->assertEquals(500, $a['totals']['paid']);
    }

    // ------------------------------------------------------------ follow-ups

    public function test_quiet_deals_become_follow_ups_and_reset_when_she_follows_up(): void
    {
        $this->signIn();
        $lead = Deal::create(['brand_name' => 'Quiet Lead', 'contact_name' => 'Ana Ruiz', 'contact_email' => 'ana@q.dev', 'stage' => 'inquiry', 'rate' => 12000, 'source' => 'search']);
        $fresh = Deal::create(['brand_name' => 'Fresh Lead', 'stage' => 'inquiry', 'rate' => 500]);
        $this->travel(3)->days();
        $fresh->update(['last_contacted_at' => now()]);

        $due = FollowUps::list();
        $this->assertSame(['Quiet Lead'], array_column($due, 'brand_name'));
        $this->assertSame(3, $due[0]['days_quiet']);
        $this->assertSame('follow_up', NextStep::current()['key']);

        // Drafting works without AI (a template) and never sends anything.
        $draft = $this->postJson("/api/deals/{$lead->id}/follow-up")->assertOk()->json('draft');
        $this->assertFalse($draft['ai']);
        $this->assertStringContainsString('Hi Ana', $draft['body']);

        Setting::put('llm_api_key', 'test-key');
        Llm::$fake = fn () => json_encode(['subject' => 'Quick follow-up', 'body' => 'Hi Ana, two times that work?']);
        $this->assertTrue($this->postJson("/api/deals/{$lead->id}/follow-up")->json('draft.ai'));

        $this->postJson("/api/deals/{$lead->id}/contacted", ['note' => 'Emailed'])->assertOk();
        $this->assertSame([], FollowUps::list());
        $this->assertStringContainsString('Emailed', $lead->fresh()->notes);

        $this->travel(6)->days();
        $this->postJson("/api/deals/{$lead->id}/snooze", ['days' => 3])->assertOk();
        $this->assertNotContains('Quiet Lead', array_column(FollowUps::list(), 'brand_name'));
        $this->getJson('/api/brief')->assertOk()->assertJsonStructure(['follow_ups', 'follow_ups_total', 'meetings_today']);
    }

    public function test_unpaid_invoice_past_terms_is_a_follow_up(): void
    {
        $d = Deal::create(['brand_name' => 'Slow Payer', 'stage' => 'invoiced', 'rate' => 4000, 'invoiced_at' => now()->subDays(31)]);
        $d->forceFill(['stage_changed_at' => now()->subDays(31)])->save();
        $this->assertSame('invoiced', FollowUps::list()[0]['kind']);
    }

    // ------------------------------------------------------------ proposals & payment

    public function test_proposal_uses_the_deal_price_saves_edits_and_moves_to_pitching_when_sent(): void
    {
        $this->signIn();
        Setting::put('llm_api_key', 'test-key');
        Llm::$fake = fn () => json_encode([
            'title' => 'MVP for Acme', 'summary' => 'Ship the onboarding app.', 'goals' => ['Launch'],
            'scope' => [['item' => 'Design', 'detail' => 'Flows']], 'timeline' => [['when' => 'Week 1', 'milestone' => 'Prototype']],
            'deliverables' => ['App'], 'assumptions' => ['A contact'], 'next_steps' => ['Reply'],
            'investment' => ['amount' => 1, 'currency' => 'EUR'], // the model can't set the price
        ]);
        $deal = Deal::create(['brand_name' => 'Acme', 'stage' => 'inquiry', 'rate' => 12000, 'deliverables' => [['project_type' => 'mvp_build']]]);

        $r = $this->postJson("/api/deals/{$deal->id}/proposal")->assertOk()->json();
        $this->assertSame(12000.0, (float) $r['proposal']['investment']['amount']);
        $this->assertSame('USD', $r['proposal']['investment']['currency']);
        $this->assertSame('mvp', $r['proposal']['plan']);
        $this->assertStringContainsString('## Scope', $r['markdown']);

        $edited = $this->putJson("/api/deals/{$deal->id}/proposal", ['proposal' => ['title' => 'Acme MVP, v2']])->assertOk()->json('proposal');
        $this->assertSame('Acme MVP, v2', $edited['title']);
        $this->assertSame(12000.0, (float) $edited['investment']['amount']);

        // No Stripe: a clear 422, nothing created.
        $this->postJson("/api/deals/{$deal->id}/payment-link")->assertStatus(422);
        Setting::put('stripe_secret_key', 'sk_test_1');
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_1'])]);
        $link = $this->postJson("/api/deals/{$deal->id}/payment-link", ['percent' => 50])->assertOk()->json();
        $this->assertSame(6000, (int) $link['amount']);
        Http::assertSent(fn ($req) => $req['line_items'][0]['price_data']['unit_amount'] === 600000 && (int) $req['metadata']['deal_id'] === $deal->id);
        $this->assertSame('https://checkout.stripe.com/c/pay/cs_1', $deal->fresh()->proposal['payment_url']);

        $this->postJson("/api/deals/{$deal->id}/proposal/sent")->assertOk();
        $this->assertSame('pitching', $deal->fresh()->stage);
        $this->assertNotNull($deal->fresh()->last_contacted_at);
    }

    public function test_proposal_falls_back_to_a_package_template_without_ai(): void
    {
        $this->signIn();
        $deal = Deal::create(['brand_name' => 'NoAI Inc', 'stage' => 'inquiry', 'rate' => 0, 'deliverables' => [['package' => 'retainer']]]);
        $p = $this->postJson("/api/deals/{$deal->id}/proposal")->assertOk()->json('proposal');
        $this->assertFalse($p['ai']);
        $this->assertSame('retainer', $p['plan']);
        $this->assertGreaterThan(0, $p['investment']['amount']);
    }

    // ------------------------------------------------------------ booking slots

    public function test_booking_slots_follow_her_hours_and_a_taken_slot_cannot_be_booked_twice(): void
    {
        $this->assertFalse($this->getJson('/api/booking/slots')->json('enabled'));
        BookingSlots::save(['enabled' => true, 'days' => [2], 'start' => '10:00', 'end' => '11:00', 'minutes' => 30, 'notice_hours' => 24, 'horizon_days' => 7]);

        $open = $this->getJson('/api/booking/slots')->assertOk()->json();
        $this->assertSame('2026-11-03', $open['days'][0]['date']); // Tuesday only
        $this->assertSame(['2026-11-03T10:00:00Z', '2026-11-03T10:30:00Z'], $open['days'][0]['slots']);

        $book = fn (string $email) => $this->postJson('/api/booking', [
            'name' => 'Kim', 'email' => $email, 'project_type' => 'consulting', 'slot' => '2026-11-03T10:30:00Z',
        ]);
        $book('kim@a.dev')->assertOk()->assertJson(['status' => 'booked', 'appointment' => ['start' => '2026-11-03T10:30:00Z']]);
        $book('lee@b.dev')->assertStatus(422)->assertJsonValidationErrors('slot');
        $this->assertSame(['2026-11-03T10:00:00Z'], $this->getJson('/api/booking/slots')->json('days.0.slots'));

        $this->signIn();
        $deal = Deal::where('contact_email', 'kim@a.dev')->firstOrFail();
        $ics = $this->get("/api/deals/{$deal->id}/meeting.ics")->assertOk();
        $this->assertStringContainsString('DTSTART:20261103T103000Z', $ics->getContent());
        $this->assertStringContainsString('text/calendar', $ics->headers->get('Content-Type'));

        $this->putJson('/api/landing/booking-hours', ['enabled' => true, 'days' => [1, 3], 'start' => '09:00', 'end' => '12:00', 'minutes' => 45, 'notice_hours' => 2, 'horizon_days' => 3])
            ->assertOk()->assertJsonPath('config.minutes', 45);
    }

    // ------------------------------------------------------------ media kit links

    public function test_a_brand_media_kit_link_is_public_and_opening_it_prompts_a_follow_up(): void
    {
        $this->signIn();
        $deal = Deal::create(['brand_name' => 'Neon', 'stage' => 'pitching', 'rate' => 5000, 'last_contacted_at' => now()]);
        $url = $this->postJson("/api/deals/{$deal->id}/kit-link")->assertOk()->json('url');
        $token = basename($url);
        $this->assertSame(32, strlen($token));
        $this->assertArrayNotHasKey('kit_token', $deal->fresh()->toArray());

        auth()->logout();
        $this->travel(1)->hours();
        $this->getJson("/api/kit/{$token}")->assertOk()->assertJsonPath('prepared_for', 'Neon')->assertJsonStructure(['creator', 'audience', 'rates']);
        $this->getJson("/api/kit/{$token}")->assertOk();
        $this->getJson('/api/kit/'.str_repeat('z', 32))->assertNotFound();

        $this->assertSame(2, $deal->fresh()->kit_views);
        $this->assertSame(1, AgentEvent::where('message', 'like', '%Neon opened your media kit%')->count()); // once, not per view
        $this->assertSame('kit_opened', FollowUps::list()[0]['kind']);
    }

    // ------------------------------------------------------------ sponsor prospects

    public function test_sponsors_are_found_in_descriptions_and_ranked_by_channels(): void
    {
        $found = SponsorProspects::detect("Great video!\nThis video is sponsored by Brilliant. Try it free\nUse code TECH20 at https://www.coderabbit.ai/?ref=chan for 20% off\nFollow me on https://twitter.com/me\nThanks to Warp for sponsoring today's episode");
        $this->assertSame(['Brilliant', 'Coderabbit', 'Warp'], array_column($found, 'brand'));
        $this->assertSame([], SponsorProspects::detect("Check out my course\nhttps://github.com/me/repo"));

        SponsorProspects::record([
            ['id' => 'vid00000001', 'title' => 'A', 'views' => 1000, 'channel' => 'Chan A', 'published_at' => '2026-10-30T00:00:00Z', 'description' => 'Sponsored by Warp'],
            ['id' => 'vid00000002', 'title' => 'B', 'views' => 2000, 'channel' => 'Chan B', 'published_at' => '2026-10-31T00:00:00Z', 'description' => 'Sponsored by Warp'],
            ['id' => 'vid00000003', 'title' => 'C', 'views' => 9000, 'channel' => 'Chan A', 'published_at' => '2026-10-31T00:00:00Z', 'description' => 'This video is sponsored by Neon'],
        ]);
        SponsorProspects::record([['id' => 'vid00000001', 'title' => 'A', 'description' => 'Sponsored by Warp']]); // seen again: no duplicate
        $this->assertSame(3, DB::table('sponsor_sightings')->count());

        $this->signIn();
        $list = $this->getJson('/api/deals/prospects')->assertOk()->json('prospects');
        $this->assertSame(['Warp', 'Neon'], array_column($list, 'brand'));
        $this->assertCount(2, $list[0]['channels']);

        $this->postJson('/api/deals/prospects', ['brand' => 'Warp', 'pitch' => 'Subject: hi'])->assertCreated();
        $this->postJson('/api/deals/prospects', ['brand' => 'warp'])->assertOk(); // already there
        $this->assertSame(1, Deal::where('stage', 'prospect')->count());
        $this->assertSame(['id' => Deal::first()->id, 'stage' => 'prospect'], $this->getJson('/api/deals/prospects')->json('prospects.0.deal'));
        // A prospect isn't pipeline money.
        Deal::first()->update(['rate' => 9000]);
        $this->assertSame(0.0, (float) $this->getJson('/api/deals')->json('stats.total_pipeline'));
    }

    public function test_the_breakout_scan_records_sponsors_from_every_scanned_video(): void
    {
        Setting::put('youtube_api_key', 'yt-key');
        \App\Models\Channel::create(['handle' => '@rival', 'active' => true]);
        Http::fake([
            '*youtube/v3/channels*' => Http::response(['items' => [['contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU1']], 'snippet' => ['title' => 'Rival']]]]),
            '*youtube/v3/playlistItems*' => Http::response(['items' => [['contentDetails' => ['videoId' => 'vidSPONSOR1']]]]),
            '*youtube/v3/videos*' => Http::response(['items' => [[
                'id' => 'vidSPONSOR1', 'snippet' => ['title' => 'Old video', 'publishedAt' => '2026-09-01T00:00:00Z', 'description' => "Today's sponsor is Linear."],
                'statistics' => ['viewCount' => '5000'], 'contentDetails' => ['duration' => 'PT12M'],
            ]]]),
        ]);
        app(\App\Services\BreakoutScanner::class)->scan();
        $this->assertSame('Linear', DB::table('sponsor_sightings')->value('brand'));
    }

    // ------------------------------------------------------------ studio agent & AI engines

    public function test_engines_run_as_the_studio_agent_without_any_agent(): void
    {
        $this->signIn();
        Setting::put('llm_api_key', 'test-key');
        Llm::$fake = fn () => json_encode(['matrix' => ['contrarian' => ['name' => 'Contrarian', 'hook_text' => 'Stop.', 'predictive_virality_score' => 80]], 'winning_hook' => ['archetype' => 'contrarian', 'hook_text' => 'Stop.', 'score' => 80], 'benchmark_insights' => 'x']);

        $this->assertSame(0, SocialAgent::count());
        $this->postJson('/api/agents/studio/hook-matrix', ['topic' => 'Postgres', 'platform' => 'linkedin'])->assertOk()->assertJsonPath('winning_hook.hook_text', 'Stop.');
        $this->assertSame(0, SocialAgent::count()); // nothing saved
        $this->postJson('/api/agents/nope/hook-matrix', ['topic' => 'x'])->assertClientError();

        $agent = SocialAgent::create(['name' => 'Main', 'platforms' => ['linkedin'], 'post_types' => [], 'posts_per_week' => 3, 'approval' => 'autonomous', 'brand' => 'Dev tools']);
        $studio = SocialAgent::studio();
        $this->assertFalse($studio->exists);
        $this->assertNull($studio->id);
        $this->assertSame('Dev tools', $studio->brand);
        $this->assertSame('review', $studio->approval);

        // An evergreen reboot scheduled from the studio waits for approval and belongs to no agent.
        $this->postJson('/api/agents/studio/schedule-evergreen-reboot', ['reboot' => ['platform' => 'linkedin', 'body' => 'Fresh take']])->assertCreated();
        $post = SocialPost::where('source', 'evergreen_reboot')->firstOrFail();
        $this->assertNull($post->social_agent_id);
        $this->assertSame('pending', $post->approval);
        $this->assertSame(0, $agent->posts()->count());
    }

    public function test_wrap_report_never_invents_numbers_or_quotes(): void
    {
        $deal = Deal::create(['brand_name' => 'Acme', 'stage' => 'scheduled', 'rate' => 3000, 'deliverables' => [['platform' => 'youtube', 'format' => 'integration']]]);
        Llm::$fake = fn () => throw new \RuntimeException('down');
        $r = app(DealFulfillmentGuardian::class)->generateWrapReport(SocialAgent::studio(), ['deal_id' => $deal->id]);
        $this->assertSame(0, $r['key_metrics']['impressions']);
        $this->assertSame([], $r['audience_sentiment']['highlighted_quotes']);
        $this->assertFalse($r['ready_for_invoicing']);
        $this->assertStringContainsString('not measured', $r['executive_summary']);

        Llm::$fake = fn () => json_encode(['brand_name' => 'Acme', 'key_metrics' => ['impressions' => 99999], 'ready_for_invoicing' => true]);
        $r = app(DealFulfillmentGuardian::class)->generateWrapReport(SocialAgent::studio(), ['deal_id' => $deal->id, 'impressions' => 1200]);
        $this->assertSame(1200, $r['key_metrics']['impressions']);
    }

    public function test_title_variants_are_scored_against_her_own_videos(): void
    {
        foreach (range(1, 9) as $i) {
            DB::table('video_stats')->insert(['video_id' => sprintf('vid%08d', $i), 'title' => $i <= 3 ? "I tried {$i} databases?" : "Plain title number {$i} here", 'published_at' => now()->subDays(40),
                'captured_on' => now()->toDateString(), 'age_days' => 40, 'views' => $i <= 3 ? 30000 : 10000, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->assertCount(9, TitleEvidence::history());
        $s = TitleEvidence::score('I tried 5 queues?');
        $this->assertSame('your videos', $s['basis']);
        $this->assertGreaterThan(1.0, $s['predicted_vs_median']);

        Llm::$fake = fn () => throw new \RuntimeException('down');
        $r = app(TitleBanditOptimizer::class)->optimize(SocialAgent::studio(), ['topic' => 'Kafka']);
        $this->assertSame('ai_estimate', $r['variants'][0]['score_source']);
        $this->assertSame('your videos', $r['variants'][0]['evidence']['basis']);
        $this->assertNotNull($r['data_winner']);

        DB::table('video_stats')->delete();
        $this->assertSame('not enough data', TitleEvidence::score('x')['basis']);
    }

    // ------------------------------------------------------------ copilot

    public function test_copilot_reads_the_money_loop_and_proposes_deal_changes_with_undo(): void
    {
        $this->signIn();
        $tools = app(CopilotTools::class);
        $names = array_column($tools->definitions('agent'), 'name');
        foreach (['get_revenue_overview', 'list_follow_ups', 'draft_follow_up', 'list_sponsor_prospects', 'audit_script_retention', 'check_deal_compliance', 'update_deal', 'create_deal', 'write_proposal'] as $t) {
            $this->assertContains($t, $names);
        }
        $this->assertNotContains('update_deal', array_column($tools->definitions('ask'), 'name'));
        $this->assertSame('write', CopilotTools::kind('update_deal'));
        $this->assertSame('read', CopilotTools::kind('get_revenue_overview'));

        $deal = Deal::create(['brand_name' => 'Supabase', 'stage' => 'negotiating', 'rate' => 4000, 'source' => 'youtube']);
        $overview = json_decode($tools->read('get_revenue_overview', []), true);
        $this->assertSame(4000.0, (float) $overview['revenue']['pipeline']);
        $this->assertSame(2000.0, (float) $overview['revenue']['weighted']);

        $thread = CopilotThread::create(['title' => 't', 'mode' => 'agent']);
        $action = $tools->propose($thread, 'update_deal', ['deal_id' => $deal->id, 'stage' => 'contracted', 'rate' => 4500, 'note' => 'Signed']);
        $this->assertStringContainsString('negotiating to contracted', $action->summary);
        $this->assertSame('negotiating', $deal->fresh()->stage); // nothing until Apply

        $result = $tools->apply($action);
        $action->update(['status' => 'applied', 'result' => $result]);
        $this->assertSame('contracted', $deal->fresh()->stage);
        $this->assertSame(4500.0, (float) $deal->fresh()->rate);
        $tools->undo(CopilotAction::find($action->id));
        $this->assertSame('negotiating', $deal->fresh()->stage);
        $this->assertSame(4000.0, (float) $deal->fresh()->rate);

        $create = $tools->propose($thread, 'create_deal', ['brand_name' => 'Linear', 'stage' => 'prospect']);
        $res = $tools->apply($create);
        $create->update(['status' => 'applied', 'result' => $res]);
        $this->assertTrue(Deal::where('brand_name', 'Linear')->where('stage', 'prospect')->exists());
        $tools->undo(CopilotAction::find($create->id));
        $this->assertFalse(Deal::where('brand_name', 'Linear')->exists());

        $this->assertStringStartsWith('ERROR', $tools->safeRead('draft_follow_up', ['deal_id' => 999]));
    }

    // ------------------------------------------------------------ dashboard, website, bio

    public function test_dashboard_website_and_bio_links_carry_the_money_loop(): void
    {
        $this->signIn();
        Deal::create(['brand_name' => 'Old Lead', 'stage' => 'inquiry', 'rate' => 2000, 'source' => 'direct'])
            ->forceFill(['created_at' => now()->subDays(5), 'stage_changed_at' => now()->subDays(5)])->saveQuietly();
        $d = $this->getJson('/api/dashboard')->assertOk()->json();
        $this->assertSame(1, $d['revenue']['follow_ups']);
        $this->assertSame('Old Lead', $d['follow_ups'][0]['brand_name']);
        $this->assertSame(200.0, (float) $d['revenue']['weighted']);
        $this->getJson('/api/website')->assertOk()->assertJsonStructure(['hero', 'pricing', 'journey', 'site_url']);
        $this->postJson('/api/landing/tracked-link', ['ref' => 'yt-abcdefghijk'])->assertOk()->assertJsonPath('url', fn ($u) => str_contains($u, 'ref=yt-abcdefghijk'));
        $this->postJson('/api/landing/tracked-link', ['ref' => 'bad ref!'])->assertStatus(422);

        config(['app.url' => 'https://thetechunicorn.com']);
        $own = DB::table('bio_links')->insertGetId(['title' => 'Work with me', 'url' => 'https://thetechunicorn.com/#contact', 'active' => true, 'position' => 0, 'clicks' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $other = DB::table('bio_links')->insertGetId(['title' => 'YouTube', 'url' => 'https://youtube.com/@x', 'active' => true, 'position' => 1, 'clicks' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->get("/links/go/{$own}")->assertRedirect('https://thetechunicorn.com/?ref=bio#contact');
        $this->get("/links/go/{$other}")->assertRedirect('https://youtube.com/@x');
    }
}
