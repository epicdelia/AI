<?php

namespace Tests\Feature;

use App\Domains\Social\Connect\Connections;
use App\Domains\Social\Models\SocialAccount;
use App\Domains\Social\Models\SocialPost;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialConnectTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Setting::put('timezone', 'UTC');
        config(['app.url' => 'https://studio.test']);
        $this->travelTo(CarbonImmutable::parse('2026-10-28 06:00', 'UTC'));
        $this->user = User::create(['name' => 'Delia', 'email' => 'd@example.com', 'password' => 'secret123']);
        $this->actingAs($this->user);
    }

    private function keys(string $p): void
    {
        $this->putJson("/api/social/accounts/{$p}/app", ['client_id' => "{$p}-id", 'client_secret' => "{$p}-secret"])->assertOk();
    }

    /** Start a sign-in and return the state the network would echo back. */
    private function start(string $p): string
    {
        $url = $this->postJson("/api/social/accounts/{$p}/connect")->assertOk()->json('url');
        parse_str(parse_url($url, PHP_URL_QUERY), $q);

        return $q['state'];
    }

    public function test_catalog_lists_all_networks_and_never_exposes_secrets(): void
    {
        $this->keys('youtube');
        $res = $this->getJson('/api/social/accounts')->assertOk();
        $this->assertSame(['youtube', 'instagram', 'tiktok', 'threads', 'linkedin', 'x'], array_column($res->json('networks'), 'platform'));
        $yt = collect($res->json('networks'))->firstWhere('platform', 'youtube');
        $this->assertTrue($yt['configured']);
        $this->assertSame('https://studio.test/oauth/youtube/callback', $yt['redirect_uri']);
        $this->assertStringNotContainsString('youtube-secret', $res->getContent());
        // stored encrypted
        $this->assertNotSame('youtube-secret', DB::table('settings')->where('key', 'youtube_client_secret')->value('value'));
        $this->assertSame('youtube-secret', Setting::get('youtube_client_secret'));
    }

    public function test_connect_needs_keys_and_https_where_required(): void
    {
        $this->postJson('/api/social/accounts/instagram/connect')->assertStatus(422)->assertJsonFragment(['error' => 'Add the Instagram app keys first (Set up).']);
        $this->keys('instagram');
        config(['app.url' => 'http://localhost:8000']);
        $this->assertStringContainsString('https://', $this->postJson('/api/social/accounts/instagram/connect')->assertStatus(422)->json('error'));
        $this->keys('youtube');
        $url = $this->postJson('/api/social/accounts/youtube/connect')->assertOk()->json('url');
        $this->assertStringContainsString('code_challenge=', $url);
        $this->assertStringContainsString('access_type=offline', $url);
    }

    public function test_youtube_full_sign_in_saves_encrypted_tokens_followers_and_first_sync(): void
    {
        $this->keys('youtube');
        $state = $this->start('youtube');
        $short = SocialPost::create(['platform' => 'youtube', 'format' => 'short', 'body' => 'Short', 'status' => 'scheduled',
            'scheduled_for' => now()->subDay(), 'url' => 'https://youtube.com/shorts/vid1']);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'AT1', 'refresh_token' => 'RT1', 'expires_in' => 3600, 'scope' => 'a b']),
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['id' => 'UC1', 'snippet' => ['title' => 'Tech Unicorn', 'customUrl' => '@tech.unicorn', 'thumbnails' => ['default' => ['url' => 'https://img/a.jpg']]], 'statistics' => ['subscriberCount' => '12345']]]]),
            'youtubeanalytics.googleapis.com/*' => Http::response([
                'columnHeaders' => array_map(fn ($n) => ['name' => $n], ['video', 'views', 'likes', 'comments', 'shares', 'estimatedMinutesWatched', 'averageViewPercentage']),
                'rows' => [['vid1', 900, 50, 7, 3, 120, 61.3], ['vidLong', 5000, 200, 40, 10, 9000, 44.0]],
            ]),
        ]);

        $this->get("/oauth/youtube/callback?state={$state}&code=CODE")->assertRedirect('/connections?connected=youtube');

        $a = SocialAccount::where('platform', 'youtube')->first();
        $this->assertSame('AT1', $a->access_token);
        $this->assertSame('RT1', $a->refresh_token);
        $this->assertNotSame('AT1', DB::table('social_accounts')->value('access_token')); // encrypted at rest
        $this->assertSame('@tech.unicorn', $a->handle);
        $this->assertSame(12345, (int) DB::table('follower_snapshots')->where('platform', 'youtube')->value('count'));
        $this->assertNotNull($a->last_synced_at);
        // her drafted Short got its numbers; the long-form video didn't become a social post
        $short->refresh();
        $this->assertSame('posted', $short->status);
        $this->assertSame(900, $short->metrics['impressions']);
        $this->assertSame(1, SocialPost::where('platform', 'youtube')->count());
        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth2.googleapis.com/token') && $r['code_verifier'] !== null && $r['code'] === 'CODE');

        // tokens never reach the browser
        $this->assertStringNotContainsString('AT1', $this->getJson('/api/social/accounts')->getContent());
    }

    public function test_state_is_single_use_and_bound_to_network(): void
    {
        $this->keys('youtube');
        $this->keys('linkedin');
        $state = $this->start('youtube');
        Http::fake();
        $this->get("/oauth/linkedin/callback?state={$state}&code=C")->assertRedirectContains('error=');
        $this->get("/oauth/youtube/callback?state={$state}&code=C")->assertRedirectContains('error='); // already used up
        $this->get('/oauth/youtube/callback?state=forged&code=C')->assertRedirectContains('error=');
        $this->assertSame(0, SocialAccount::count());
        Http::assertNothingSent();
    }

    public function test_cancelled_sign_in_and_signed_out_return(): void
    {
        $this->get('/oauth/instagram/callback?error=access_denied&error_description=User+denied')->assertRedirect('/connections?'.http_build_query(['error' => 'User denied', 'network' => 'instagram']));
        auth()->logout();
        $this->get('/oauth/instagram/callback?code=x&state=y')->assertRedirectContains('signed+out');
    }

    public function test_instagram_exchanges_for_long_lived_token_and_imports_posts(): void
    {
        $this->keys('instagram');
        $state = $this->start('instagram');
        $mine = SocialPost::create(['platform' => 'instagram', 'format' => 'carousel', 'body' => 'Mine', 'status' => 'posted',
            'posted_at' => now()->subDays(2), 'url' => 'https://www.instagram.com/p/AAA/']);
        Http::fake([
            'api.instagram.com/oauth/access_token' => Http::response(['access_token' => 'SHORT', 'user_id' => 1, 'permissions' => ['instagram_business_basic', 'instagram_business_manage_insights']]),
            'graph.instagram.com/access_token*' => Http::response(['access_token' => 'LONG', 'token_type' => 'bearer', 'expires_in' => 5184000]),
            'graph.instagram.com/v21.0/me?*' => Http::response(['user_id' => '178', 'username' => 'tech.unicorn', 'followers_count' => 4200]),
            'graph.instagram.com/v21.0/me/media*' => Http::response(['data' => [
                ['id' => 'm1', 'caption' => 'Mine', 'media_type' => 'CAROUSEL_ALBUM', 'permalink' => 'https://www.instagram.com/p/AAA/', 'timestamp' => '2026-10-26T10:00:00+0000', 'like_count' => 30, 'comments_count' => 4],
                ['id' => 'm2', 'caption' => 'Made in the app', 'media_type' => 'VIDEO', 'media_product_type' => 'REELS', 'permalink' => 'https://www.instagram.com/reel/BBB/', 'timestamp' => '2026-10-27T10:00:00+0000', 'like_count' => 90, 'comments_count' => 9],
            ]]),
            'graph.instagram.com/v21.0/m1/insights*' => Http::response(['data' => [['name' => 'reach', 'values' => [['value' => 800]]], ['name' => 'saved', 'values' => [['value' => 12]]]]]),
            'graph.instagram.com/v21.0/m2/insights*' => Http::response(['error' => ['message' => 'nope']], 400),
        ]);

        $this->get("/oauth/instagram/callback?state={$state}&code=C")->assertRedirect('/connections?connected=instagram');

        $a = SocialAccount::where('platform', 'instagram')->first();
        $this->assertSame('LONG', $a->access_token);
        $this->assertTrue($a->expires_at->between(now()->addDays(59), now()->addDays(61)));
        $mine->refresh();
        $this->assertSame('m1', $mine->external_id);
        $this->assertEquals(['impressions' => 800, 'likes' => 30, 'comments' => 4, 'saves' => 12], array_intersect_key($mine->metrics, array_flip(['impressions', 'likes', 'comments', 'saves'])));
        $reel = SocialPost::where('external_id', 'm2')->first();
        $this->assertSame('reel', $reel->format);
        $this->assertSame('synced', $reel->source);
        $this->assertSame(90, $reel->metrics['likes']);

        // a second sync updates, never duplicates
        Connections::sync($a->fresh());
        $this->assertSame(2, SocialPost::where('platform', 'instagram')->count());
    }

    public function test_long_lived_meta_token_is_renewed_inside_the_window(): void
    {
        $this->keys('threads');
        $a = SocialAccount::create(['platform' => 'threads', 'access_token' => 'OLD', 'expires_at' => now()->addDays(3), 'external_id' => '1']);
        Http::fake(['graph.threads.net/refresh_access_token*' => Http::response(['access_token' => 'NEW', 'expires_in' => 5184000])]);
        $this->assertSame('NEW', Connections::token($a));
        $this->assertTrue($a->fresh()->expires_at->gt(now()->addDays(50)));
    }

    public function test_failed_renewal_marks_account_for_reconnect(): void
    {
        $this->keys('tiktok');
        $a = SocialAccount::create(['platform' => 'tiktok', 'access_token' => 'OLD', 'refresh_token' => 'R', 'expires_at' => now()->subMinute()]);
        Http::fake(['open.tiktokapis.com/v2/oauth/token/' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Refresh token is invalid'], 400)]);
        $res = $this->postJson('/api/social/accounts/tiktok/sync')->assertStatus(422);
        $this->assertStringContainsString('Refresh token is invalid', $res->json('error'));
        $this->assertSame('expired', $a->fresh()->status);
        // expired accounts are skipped by the hourly sync until she reconnects
        $this->assertSame([], Connections::syncAll());
    }

    public function test_tiktok_sync_records_followers_and_videos(): void
    {
        $this->keys('tiktok');
        $a = SocialAccount::create(['platform' => 'tiktok', 'access_token' => 'AT', 'refresh_token' => 'R', 'expires_at' => now()->addHours(20)]);
        Http::fake([
            'open.tiktokapis.com/v2/user/info/*' => Http::response(['data' => ['user' => ['open_id' => 'o1', 'username' => 'tech.unicorn', 'follower_count' => 9000, 'likes_count' => 50000, 'video_count' => 40]]]),
            'open.tiktokapis.com/v2/video/list/*' => Http::response(['data' => ['videos' => [
                ['id' => 'v1', 'video_description' => 'Desk tour', 'share_url' => 'https://www.tiktok.com/@tech.unicorn/video/v1', 'create_time' => 1761645600, 'view_count' => 12000, 'like_count' => 800, 'comment_count' => 20, 'share_count' => 15],
            ]]]),
        ]);
        $this->assertStringContainsString('9,000 followers', Connections::sync($a));
        $this->assertSame(12000, SocialPost::where('external_id', 'v1')->first()->metrics['impressions']);
        $this->assertSame(['likes' => 50000, 'videos' => 40, 'followers' => 9000], $a->fresh()->meta);
    }

    public function test_x_sync_is_off_until_a_request_limit_is_set_and_stops_at_it(): void
    {
        $this->keys('x');
        $a = SocialAccount::create(['platform' => 'x', 'access_token' => 'AT', 'refresh_token' => 'R', 'expires_at' => now()->addHour(), 'external_id' => '42']);
        Http::fake([
            'api.x.com/2/users/me*' => Http::response(['data' => ['id' => '42', 'username' => 'tech__unicorn', 'public_metrics' => ['followers_count' => 700]]]),
            'api.x.com/2/users/42/tweets*' => Http::response(['data' => [['id' => 't1', 'text' => 'Hi', 'created_at' => '2026-10-27T09:00:00Z', 'public_metrics' => ['impression_count' => 300, 'like_count' => 5, 'reply_count' => 1, 'retweet_count' => 2, 'quote_count' => 1, 'bookmark_count' => 3]]]]),
        ]);
        $this->assertSame([], Connections::syncAll()); // skipped, nothing billed
        Http::assertNothingSent();

        $this->putJson('/api/social/accounts/x/app', ['client_id' => 'x-id', 'x_monthly_requests' => 3])->assertOk();
        $this->postJson('/api/social/accounts/x/sync')->assertOk();
        $this->assertSame(['impressions' => 300, 'likes' => 5, 'comments' => 1, 'shares' => 3, 'saves' => 3], SocialPost::where('external_id', 't1')->first()->metrics);
        $this->assertStringContainsString('limit is used up', $this->postJson('/api/social/accounts/x/sync')->assertStatus(422)->json('error'));
        $this->assertSame('x-secret', Setting::get('x_client_secret')); // empty secret field kept the saved one
    }

    public function test_disconnect_revokes_and_forgets(): void
    {
        $this->keys('youtube');
        SocialAccount::create(['platform' => 'youtube', 'access_token' => 'AT', 'refresh_token' => 'RT']);
        Http::fake(['oauth2.googleapis.com/revoke' => Http::response([], 200)]);
        $this->deleteJson('/api/social/accounts/youtube')->assertOk();
        $this->assertSame(0, SocialAccount::count());
        Http::assertSent(fn ($r) => str_contains($r->url(), 'revoke') && $r['token'] === 'RT');
    }

    public function test_unknown_network_404s(): void
    {
        $this->postJson('/api/social/accounts/myspace/connect')->assertNotFound();
        $this->get('/oauth/myspace/callback')->assertNotFound();
    }
}
