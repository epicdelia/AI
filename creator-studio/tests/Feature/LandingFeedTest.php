<?php

namespace Tests\Feature;

use App\Domains\Landing\Audience;
use App\Domains\Landing\LandingFeed;
use App\Domains\Social\Models\SocialPost;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LandingFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Cache::flush();
    }

    private function published(string $platform, array $extra = []): SocialPost
    {
        return SocialPost::create($extra + [
            'platform' => $platform, 'format' => 'post', 'title' => null, 'body' => "Ship small, ship often.\nThat is the whole trick.",
            'status' => 'posted', 'posted_at' => now()->subDay(), 'url' => "https://{$platform}.example/p/1",
            'metrics' => ['impressions' => 12400, 'likes' => 830],
        ]);
    }

    private function signIn(): void
    {
        $this->actingAs(User::create(['name' => 'Delia', 'email' => 'delia@example.com', 'password' => 'secret123']));
    }

    public function test_without_published_posts_networks_show_nothing_invented(): void
    {
        $feed = $this->getJson('/api/landing/social-feed')->assertOk()->json();

        $this->assertSame('youtube', $feed['active']);
        $this->assertSame('sample', $feed['sources']['youtube']);
        $this->assertNotEmpty($feed['platforms']['youtube']);
        foreach ($feed['platforms']['youtube'] as $v) {
            $this->assertNull($v['views'], 'Sample videos never carry made-up view counts');
            $this->assertMatchesRegularExpression('~^https://www\.youtube\.com/watch\?v=[\w-]{11}$~', $v['url']);
        }
        foreach (['instagram', 'tiktok', 'twitter', 'threads'] as $net) {
            $this->assertSame([], $feed['platforms'][$net]);
            $this->assertSame('none', $feed['sources'][$net]);
            $this->assertNotEmpty($feed['urls'][$net]);
        }
        $this->assertSame('22K Subs', $feed['stats']['youtube']);
        $this->assertSame('384K', $feed['stats']['instagram']);
        $this->assertSame(Audience::CLAIM, $feed['headline']);
    }

    public function test_her_published_posts_appear_with_their_real_numbers_and_demo_posts_never_do(): void
    {
        $real = $this->published('instagram');
        $this->published('instagram', ['source' => 'demo', 'url' => 'https://instagram.example/demo']);
        $this->published('instagram', ['url' => null]);              // no link to send visitors to
        $this->published('instagram', ['status' => 'draft']);        // not published
        $this->published('x', ['metrics' => ['likes' => 5]]);

        $feed = $this->getJson('/api/landing/social-feed')->json();

        $this->assertCount(1, $feed['platforms']['instagram']);
        $item = $feed['platforms']['instagram'][0];
        $this->assertSame('post:'.$real->id, $item['key']);
        $this->assertSame('Ship small, ship often.', $item['title']);
        $this->assertSame(['views' => 12400, 'likes' => 830], $item['metrics']);
        $this->assertSame('live', $feed['sources']['instagram']);
        // X is "twitter" in the feed; only the numbers she logged.
        $this->assertSame(['likes' => 5], $feed['platforms']['twitter'][0]['metrics']);
    }

    public function test_youtube_uses_the_api_for_her_latest_uploads(): void
    {
        Setting::put('youtube_api_key', 'yt-key');
        Setting::put('channel_id', 'UCabc');
        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['id' => 'UCabc', 'snippet' => ['title' => 'Tech Unicorn'], 'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UUabc']]]]]),
            'www.googleapis.com/youtube/v3/playlistItems*' => Http::response(['items' => [['contentDetails' => ['videoId' => 'AAAAAAAAAAA']]]]),
            'www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => [[
                'id' => 'AAAAAAAAAAA', 'snippet' => ['title' => 'New video', 'publishedAt' => now()->subDays(2)->toIso8601String()],
                'statistics' => ['viewCount' => '15300'], 'contentDetails' => ['duration' => 'PT12M5S'],
            ]]]),
        ]);

        $feed = LandingFeed::build();

        $this->assertSame('live', $feed['sources']['youtube']);
        $video = $feed['platforms']['youtube'][0];
        $this->assertSame('New video', $video['title']);
        $this->assertSame('12:05', $video['duration']);
        $this->assertSame('15K views', $video['views_label']);
    }

    public function test_demo_followers_never_reach_the_public_page(): void
    {
        DB::table('follower_snapshots')->insert(['platform' => 'instagram', 'count' => 8940, 'recorded_on' => now()->toDateString(), 'source' => 'demo', 'created_at' => now(), 'updated_at' => now()]);

        $feed = LandingFeed::build();

        $this->assertSame('384K', $feed['stats']['instagram']);
        $this->assertFalse($feed['followers']['instagram']['live']);
        $this->assertSame(Audience::CLAIM, $feed['headline']);
    }

    public function test_synced_follower_counts_replace_the_public_figures(): void
    {
        DB::table('follower_snapshots')->insert([
            ['platform' => 'instagram', 'count' => 401234, 'recorded_on' => now()->toDateString(), 'source' => 'instagram', 'created_at' => now(), 'updated_at' => now()],
            ['platform' => 'youtube', 'count' => 23900, 'recorded_on' => now()->toDateString(), 'source' => 'youtube', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $feed = LandingFeed::build();

        $this->assertSame('401K', $feed['stats']['instagram']);
        $this->assertSame('24K Subs', $feed['stats']['youtube']);
        $this->assertTrue($feed['followers']['instagram']['live']);
        $total = 401234 + 23900 + 25000 + 45000 + 28000 + 15000;
        $this->assertSame(Audience::compact($total).'+', $feed['headline']);
        // The media kit reads the same numbers.
        $signIn = $this->signIn();
        $this->assertSame($total, $this->getJson('/api/deals/media-kit')->json('audience.total_reach'));
        $this->assertSame('hello@thetechunicorn.com', $this->getJson('/api/deals/media-kit')->json('creator.email'));
    }

    public function test_she_can_feature_and_hide_items_from_the_dashboard(): void
    {
        $older = $this->published('threads', ['posted_at' => now()->subDays(5), 'url' => 'https://threads.example/old']);
        $newer = $this->published('threads', ['posted_at' => now()->subDay(), 'url' => 'https://threads.example/new']);
        $gone = $this->published('threads', ['posted_at' => now()->subHours(2), 'url' => 'https://threads.example/gone']);

        $this->putJson('/api/landing/content', ['hidden' => [], 'pinned' => []])->assertUnauthorized();
        $this->signIn();

        $res = $this->putJson('/api/landing/content', ['hidden' => ["post:{$gone->id}"], 'pinned' => ["post:{$older->id}"]])->assertOk();
        $this->assertTrue(collect($res->json('platforms.threads'))->firstWhere('key', "post:{$gone->id}")['hidden']);

        $public = collect($this->getJson('/api/landing/social-feed')->json('platforms.threads'))->pluck('key')->all();
        $this->assertSame(["post:{$older->id}", "post:{$newer->id}"], $public, 'Featured first, hidden left out');

        $this->putJson('/api/landing/content', ['hidden' => ['not a key'], 'pinned' => []])->assertUnprocessable();
    }

    public function test_publishing_a_post_refreshes_the_public_feed(): void
    {
        $this->assertSame([], $this->getJson('/api/landing/social-feed')->json('platforms.tiktok'));

        $draft = SocialPost::create(['platform' => 'tiktok', 'format' => 'video', 'body' => 'Fresh one', 'status' => 'draft', 'url' => 'https://tiktok.example/v/9']);
        $this->assertSame([], LandingFeed::get()['platforms']['tiktok'], 'A draft does not touch the cache');

        $draft->update(['status' => 'posted']);
        $this->assertSame("post:{$draft->id}", $this->getJson('/api/landing/social-feed')->json('platforms.tiktok.0.key'));

        $draft->delete();
        $this->assertSame([], $this->getJson('/api/landing/social-feed')->json('platforms.tiktok'));
    }

    public function test_clicks_are_counted_and_reach_the_dashboard(): void
    {
        $post = $this->published('instagram');
        $key = "post:{$post->id}";

        $this->postJson('/api/landing/click', ['key' => $key, 'platform' => 'instagram'])->assertNoContent();
        $this->postJson('/api/landing/click', ['key' => $key, 'platform' => 'instagram'])->assertNoContent();
        $this->postJson('/api/landing/click', ['key' => 'profile:instagram', 'platform' => 'instagram'])->assertNoContent();
        $this->postJson('/api/landing/click', ['key' => 'javascript:alert(1)', 'platform' => 'instagram'])->assertUnprocessable();
        $this->postJson('/api/landing/click', ['key' => $key, 'platform' => 'myspace'])->assertUnprocessable();

        $this->signIn();
        $website = $this->getJson('/api/dashboard')->assertOk()->json('website');
        $this->assertSame(3, $website['clicks_7d']);
        $this->assertSame($key, $website['top'][0]['key']);
        $this->assertSame(2, $website['top'][0]['clicks']);
        $this->assertSame('live', $website['sources']['instagram']);

        $content = $this->getJson('/api/landing/content')->assertOk()->json();
        $this->assertSame(2, $content['platforms']['instagram'][0]['clicks_30d']);
    }

    public function test_post_thumbnails_are_public_only_while_shown(): void
    {
        $post = $this->published('instagram', ['media' => [['id' => 999, 'mime' => 'image/png', 'url' => '/x', 'name' => 'x', 'alt' => null]]]);
        $this->get("/landing/thumb/{$post->id}")->assertNotFound(); // the file doesn't exist
        $hidden = $this->published('instagram');
        Setting::put('landing_hidden', json_encode(["post:{$hidden->id}"]));
        LandingFeed::forget();
        $this->get("/landing/thumb/{$hidden->id}")->assertNotFound();
    }

    public function test_the_hourly_job_rebuilds_the_feed(): void
    {
        $this->published('threads');
        Cache::put(LandingFeed::CACHE, ['stale' => true], now()->addHour());

        $this->artisan('studio:landing')->expectsOutputToContain('Landing page feed rebuilt')->assertSuccessful();

        $this->assertArrayHasKey('platforms', Cache::get(LandingFeed::CACHE));
        $this->assertNotNull(Setting::get('last_run_landing'));
    }

    public function test_retainer_checkout_is_a_monthly_subscription(): void
    {
        Setting::put('stripe_secret_key', 'sk_test_123');
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_1'])]);

        $this->postJson('/api/stripe/checkout-session', ['package_id' => 'retainer', 'customer_name' => 'A', 'customer_email' => 'a@example.com'])->assertOk();
        $this->postJson('/api/stripe/checkout-session', ['package_id' => 'mvp_full', 'customer_name' => 'B', 'customer_email' => 'b@example.com'])->assertOk();

        $sent = Http::recorded()->map(fn ($pair) => $pair[0]->data());
        $this->assertSame('subscription', $sent[0]['mode']);
        $this->assertSame('month', $sent[0]['line_items'][0]['price_data']['recurring']['interval']);
        $this->assertSame('payment', $sent[1]['mode']);
        $this->assertArrayNotHasKey('recurring', $sent[1]['line_items'][0]['price_data']);
    }
}
