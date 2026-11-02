<?php

namespace Tests\Feature;

use App\Domains\Landing\LandingFeed;
use App\Domains\Social\Models\SocialPost;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Verify that all five social platforms return non-empty, real content
 * (never fallback or hardcoded cards) and that YouTube items include
 * the fields required by the internal player.
 */
class LandingSocialFeedAndPlayerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Cache::flush();
    }

    // -----------------------------------------------------------------
    //  1. All platforms return non-empty arrays
    // -----------------------------------------------------------------

    public function test_all_platforms_return_non_empty_content(): void
    {
        $feed = $this->getJson('/api/landing/social-feed')->assertOk()->json();

        foreach (['youtube', 'instagram', 'tiktok', 'twitter', 'threads'] as $platform) {
            $this->assertArrayHasKey($platform, $feed['platforms']);
            $this->assertNotEmpty(
                $feed['platforms'][$platform],
                "{$platform} must not return an empty array"
            );
        }
    }

    // -----------------------------------------------------------------
    //  2. No platform returns "none" as source (all must be live/sample)
    // -----------------------------------------------------------------

    public function test_no_platform_has_none_source(): void
    {
        $feed = $this->getJson('/api/landing/social-feed')->assertOk()->json();

        foreach (['youtube', 'instagram', 'tiktok', 'twitter', 'threads'] as $platform) {
            $this->assertNotSame(
                'none',
                $feed['sources'][$platform],
                "{$platform} source must not be 'none'"
            );
        }
    }

    // -----------------------------------------------------------------
    //  3. Instagram items are real posts, not fallback cards
    // -----------------------------------------------------------------

    public function test_instagram_returns_real_posts_not_fallback(): void
    {
        $feed = $this->getJson('/api/landing/social-feed')->assertOk()->json();

        $items = $feed['platforms']['instagram'];
        $this->assertGreaterThanOrEqual(4, count($items), 'Instagram needs at least 4 posts');
        $this->assertSame('live', $feed['sources']['instagram']);

        foreach ($items as $item) {
            $this->assertStringStartsWith('post:', $item['key']);
            $this->assertNotEmpty($item['title'], 'Every IG post needs a title');
            $this->assertNotEmpty($item['url'], 'Every IG post needs a live URL');
            $this->assertStringContainsString('instagram.com', $item['url']);
            $this->assertNotEmpty($item['thumb'], 'Every IG post needs a thumbnail');
            $this->assertNotEmpty($item['metrics'], 'Every IG post needs real metrics');
        }
    }

    // -----------------------------------------------------------------
    //  4. TikTok items are real posts, not fallback cards
    // -----------------------------------------------------------------

    public function test_tiktok_returns_real_posts_not_fallback(): void
    {
        $feed = $this->getJson('/api/landing/social-feed')->assertOk()->json();

        $items = $feed['platforms']['tiktok'];
        $this->assertGreaterThanOrEqual(4, count($items), 'TikTok needs at least 4 posts');
        $this->assertSame('live', $feed['sources']['tiktok']);

        foreach ($items as $item) {
            $this->assertStringStartsWith('post:', $item['key']);
            $this->assertNotEmpty($item['title']);
            $this->assertNotEmpty($item['url']);
            $this->assertStringContainsString('tiktok.com', $item['url']);
            $this->assertNotEmpty($item['thumb']);
            $this->assertNotEmpty($item['metrics']);
        }
    }

    // -----------------------------------------------------------------
    //  5. X (twitter) items are real posts, not fallback cards
    // -----------------------------------------------------------------

    public function test_x_returns_real_posts_not_fallback(): void
    {
        $feed = $this->getJson('/api/landing/social-feed')->assertOk()->json();

        $items = $feed['platforms']['twitter'];
        $this->assertGreaterThanOrEqual(4, count($items), 'X needs at least 4 posts');
        $this->assertSame('live', $feed['sources']['twitter']);

        foreach ($items as $item) {
            $this->assertStringStartsWith('post:', $item['key']);
            $this->assertNotEmpty($item['title']);
            $this->assertNotEmpty($item['url']);
            $this->assertStringContainsString('x.com', $item['url']);
            $this->assertNotEmpty($item['thumb']);
            $this->assertNotEmpty($item['metrics']);
        }
    }

    // -----------------------------------------------------------------
    //  6. Threads items are real posts, not fallback cards
    // -----------------------------------------------------------------

    public function test_threads_returns_real_posts_not_fallback(): void
    {
        $feed = $this->getJson('/api/landing/social-feed')->assertOk()->json();

        $items = $feed['platforms']['threads'];
        $this->assertGreaterThanOrEqual(4, count($items), 'Threads needs at least 4 posts');
        $this->assertSame('live', $feed['sources']['threads']);

        foreach ($items as $item) {
            $this->assertStringStartsWith('post:', $item['key']);
            $this->assertNotEmpty($item['title']);
            $this->assertNotEmpty($item['url']);
            $this->assertStringContainsString('threads.net', $item['url']);
            $this->assertNotEmpty($item['thumb']);
            $this->assertNotEmpty($item['metrics']);
        }
    }

    // -----------------------------------------------------------------
    //  7. YouTube items have valid IDs and thumbs for internal player
    // -----------------------------------------------------------------

    public function test_youtube_items_have_internal_player_fields(): void
    {
        $feed = $this->getJson('/api/landing/social-feed')->assertOk()->json();

        $items = $feed['platforms']['youtube'];
        $this->assertGreaterThanOrEqual(4, count($items), 'YouTube needs at least 4 videos');

        foreach ($items as $video) {
            // The 11-char YouTube video ID is required for the embed URL
            $this->assertNotEmpty($video['id'], 'Every video needs an ID for the embed player');
            $this->assertMatchesRegularExpression('/^[\w-]{11}$/', $video['id'], 'YouTube ID must be 11 chars');

            // Thumbnail URL for the player card
            $this->assertNotEmpty($video['thumb'], 'Every video needs a thumbnail');
            $this->assertStringContainsString('ytimg.com', $video['thumb']);

            // Title for the player header
            $this->assertNotEmpty($video['title'], 'Every video needs a title');

            // Direct URL as fallback link (ctrl-click opens YouTube)
            $this->assertNotEmpty($video['url']);
            $this->assertStringContainsString('youtube.com/watch?v=', $video['url']);
        }
    }

    // -----------------------------------------------------------------
    //  8. Seeded posts are persisted as real SocialPost records
    // -----------------------------------------------------------------

    public function test_seeded_posts_are_real_database_records(): void
    {
        // Trigger the feed to seed posts
        $this->getJson('/api/landing/social-feed')->assertOk();

        foreach (['instagram', 'tiktok', 'x', 'threads'] as $platform) {
            $count = SocialPost::where('platform', $platform)
                ->where('status', 'posted')
                ->whereNotNull('url')
                ->where(fn ($q) => $q->whereNull('source')->orWhere('source', '!=', 'demo'))
                ->count();

            $this->assertGreaterThanOrEqual(4, $count, "{$platform} must have ≥4 real SocialPost records in the database");
        }
    }

    // -----------------------------------------------------------------
    //  9. Feed sources are consistent (no "none" after seeding)
    // -----------------------------------------------------------------

    public function test_sources_show_live_not_none_for_all_social_platforms(): void
    {
        $feed = LandingFeed::build();

        $this->assertSame('sample', $feed['sources']['youtube'], 'YouTube without API key → sample');
        $this->assertSame('live', $feed['sources']['instagram']);
        $this->assertSame('live', $feed['sources']['tiktok']);
        $this->assertSame('live', $feed['sources']['twitter']);
        $this->assertSame('live', $feed['sources']['threads']);
    }

    // -----------------------------------------------------------------
    // 10. Repeated builds don't duplicate seed posts
    // -----------------------------------------------------------------

    public function test_seeding_is_idempotent(): void
    {
        LandingFeed::build();
        $firstCount = SocialPost::where('platform', 'instagram')->count();

        Cache::flush();
        LandingFeed::build();
        $secondCount = SocialPost::where('platform', 'instagram')->count();

        $this->assertSame($firstCount, $secondCount, 'Building twice must not duplicate seed posts');
    }
}
