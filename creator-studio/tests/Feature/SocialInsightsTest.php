<?php

namespace Tests\Feature;

use App\Domains\Social\AnalyticsImport;
use App\Domains\Social\Models\SocialPost;
use App\Domains\Social\SocialAnalytics;
use App\Models\Setting;
use App\Services\Llm;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SocialInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Setting::put('timezone', 'UTC');
        $this->travelTo(CarbonImmutable::parse('2026-10-28 12:00', 'UTC')); // a Wednesday
        $this->actingAs($this->admin([
            'name' => 'Delia',
            'email' => 'd@example.com',
            'password' => 'secret123',
        ]));
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        parent::tearDown();
    }

    public function test_overview_totals_change_series_and_top_posts(): void
    {
        $this->posted('linkedin', '2026-10-27 08:00', [
            'impressions' => 1000,
            'likes' => 50,
            'comments' => 10,
        ]); // this week
        $this->posted('linkedin', '2026-10-20 08:00', [
            'impressions' => 1000,
            'likes' => 20,
        ]);                   // last week
        $this->posted('linkedin', '2026-10-13 08:00', null);                                                     // no numbers
        $this->posted('linkedin', '2026-09-14 08:00', [
            'impressions' => 1000,
            'likes' => 40,
        ]);                   // previous period (7 Sep to 4 Oct)
        $this->posted('x', '2026-10-26 09:00', [
            'likes' => 5,
        ]);

        $o = $this->getJson('/api/social/analytics?weeks=4')->assertOk()->json();
        $li = $o['platforms']['linkedin'];
        $this->assertSame(3, $li['posts']);
        $this->assertSame(2, $li['logged']);
        $this->assertSame(80, $li['engagement']);
        $this->assertSame(40, $li['previous_engagement']);
        $this->assertSame(100, $li['change'], '+100%');
        $this->assertEquals(4.0, $li['avg_rate'], '(6% + 2%) / 2');
        $this->assertCount(4, $li['series']);
        $this->assertSame(60, end($li['series'])['engagement'], 'this week');
        $this->assertSame(1, $li['missing_numbers']);
        $this->assertArrayNotHasKey('threads', $o['platforms'], 'platforms with no posts are left out');
        $this->assertSame('linkedin', $o['top'][0]['post']['platform']);
        $this->assertGreaterThan(1, $o['top'][0]['vs_median']);
    }

    public function test_best_times_need_enough_posts_then_rank_slots(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->posted('instagram', "2026-10-0{$i} 19:00", [
                'likes' => 10,
            ]);
        }
        $this->assertFalse(SocialAnalytics::bestTimes('instagram')['enough']);

        // Tuesdays at 19:00 do far better than Mondays at 08:00.
        foreach (['2026-09-01', '2026-09-08', '2026-09-15', '2026-09-22'] as $tue) {
            $this->posted('instagram', "{$tue} 19:30", [
                'likes' => 300,
            ]);
        }
        foreach (['2026-09-07', '2026-09-14'] as $mon) {
            $this->posted('instagram', "{$mon} 08:00", [
                'likes' => 20,
            ]);
        }
        $bt = $this->getJson('/api/social/best-times?platform=instagram')->assertOk()->json();
        $this->assertTrue($bt['enough']);
        $this->assertSame('engagements', $bt['metric'], 'no impressions logged, so totals');
        $this->assertSame([
            'day' => 1,
            'block' => 4,
        ], [
            'day' => $bt['best'][0]['day'],
            'block' => $bt['best'][0]['block'],
        ], 'Tuesday evening');
        $this->assertSame(4, $bt['grid'][1][4]['posts']);
        $this->getJson('/api/social/best-times?platform=myspace')->assertNotFound();
    }

    public function test_recycle_picks_old_winners_once_and_fresh_take_drafts_one(): void
    {
        $winner = $this->posted('linkedin', '2026-08-01 08:00', [
            'likes' => 400,
        ], 'The rule that saved my inbox.');
        $this->posted('linkedin', '2026-08-05 08:00', [
            'likes' => 100,
        ]);
        $this->posted('linkedin', '2026-08-09 08:00', [
            'likes' => 90,
        ]);
        $this->posted('linkedin', '2026-10-20 08:00', [
            'likes' => 900,
        ], 'Too recent to recycle');

        $recycle = SocialAnalytics::recyclable();
        $this->assertSame([$winner->id], $recycle->pluck('post.id')->all());

        $seen = null;
        Llm::$fake = function ($system, $user) use (&$seen) {
            $seen = $user;

            return json_encode([
                'title' => 'Inbox rule, again',
                'body' => 'New hook. Same rule.',
                'parts' => ['ignored for a text post'],
                'why' => 'Kept the rule, new story',
            ]);
        };
        $draft = $this->postJson("/api/social/posts/{$winner->id}/fresh-take")->assertOk()->json('post');
        $this->assertStringContainsString('The rule that saved my inbox.', $seen);
        $this->assertStringContainsString('400 engagements', $seen);
        $this->assertSame('draft', $draft['status']);
        $this->assertSame($winner->id, $draft['source_post_id']);
        $this->assertSame([], $draft['parts']);
        $this->assertSame('Kept the rule, new story', $draft['notes']);
        $this->assertTrue(SocialAnalytics::recyclable()->isEmpty(), 'not suggested twice');
    }

    public function test_csv_import_guesses_columns_matches_and_creates(): void
    {
        $existing = $this->posted('x', '2026-10-20 10:00', null, 'Hot take: the best AI email setup is three rules.');
        $csv = "\xEF\xBB\xBFPost text;Date;Impressions;Likes;Reposts;Replies;Link\n"
            . "\"Hot take: the best AI email setup is three rules.\";2026-10-20;\"1.234\";56;7;8;\n"
            . "Brand new post we never logged;2026-10-22 09:15;2.5K;30;1;2;https://x.com/tech__unicorn/status/1\n"
            . "No numbers row;2026-10-23;;;;;\n";
        $file = UploadedFile::fake()->createWithContent('x.csv', $csv);

        $preview = $this->post('/api/social/import', [
            'file' => $file,
            'platform' => 'x',
        ], [
            'Accept' => 'application/json',
        ])->assertOk()->json();
        $this->assertSame([
            'date' => 1,
            'text' => 0,
            'url' => 6,
            'impressions' => 2,
            'likes' => 3,
            'comments' => 5,
            'shares' => 4,
            'saves' => null,
        ], $preview['guess']);
        $this->assertSame(3, $preview['rows']);

        $res = $this->post('/api/social/import', [
            'file' => UploadedFile::fake()->createWithContent('x.csv', $csv),
            'platform' => 'x',
            'mapping' => json_encode($preview['guess']),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertOk()->json();
        $this->assertSame([
            'created' => 1,
            'updated' => 1,
            'skipped' => 1,
        ], array_intersect_key($res, array_flip(['created', 'updated', 'skipped'])));
        $this->assertSame([
            'impressions' => 1234,
            'likes' => 56,
            'comments' => 8,
            'shares' => 7,
        ], $existing->fresh()->metrics);
        $new = SocialPost::where('source', 'import')->firstOrFail();
        $this->assertSame(2500, $new->metrics['impressions']);
        $this->assertSame('https://x.com/tech__unicorn/status/1', $new->url);
        $this->assertSame('posted', $new->status);

        // Re-importing matches by link and date, so nothing is duplicated.
        $this->post('/api/social/import', [
            'file' => UploadedFile::fake()->createWithContent('x.csv', $csv),
            'platform' => 'x',
            'mapping' => json_encode($preview['guess']),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertOk()->assertJsonPath('created', 0);

        $this->post('/api/social/import', [
            'file' => UploadedFile::fake()->createWithContent('a.xlsx', "PK\x03\x04junk"),
            'platform' => 'x',
        ], [
            'Accept' => 'application/json',
        ])
            ->assertStatus(422)->assertJsonPath('error', fn ($e) => str_contains($e, 'Save as'));
    }

    public function test_dashboard_kpis_series_followers_and_ticker(): void
    {
        $this->posted('instagram', '2026-10-28 09:00', [
            'impressions' => 1000,
            'likes' => 80,
            'comments' => 20,
        ]); // today
        $this->posted('instagram', '2026-10-20 09:00', [
            'impressions' => 1000,
            'likes' => 40,
        ]);
        $this->posted('linkedin', '2026-10-27 09:00', [
            'impressions' => 500,
            'likes' => 10,
        ]);
        $this->posted('linkedin', '2026-09-15 09:00', [
            'impressions' => 500,
            'likes' => 5,
        ]); // previous 30 days
        SocialPost::create([
            'platform' => 'x',
            'format' => 'post',
            'body' => 'Later',
            'status' => 'scheduled',
            'scheduled_for' => '2026-11-02 09:00',
        ]);
        SocialPost::create([
            'platform' => 'x',
            'format' => 'post',
            'body' => 'Someday',
            'status' => 'draft',
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-09-28 12:00', 'UTC'));
        $this->postJson('/api/social/followers', [
            'counts' => [
                'instagram' => 1000,
                'linkedin' => 400,
                'nope' => 5,
            ],
        ])->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-10-28 12:00', 'UTC'));
        $this->postJson('/api/social/followers', [
            'counts' => [
                'instagram' => 1250,
                'linkedin' => '',
            ],
        ])->assertOk()
            ->assertJsonPath('followers.instagram.change_30d', 250);

        $d = $this->getJson('/api/social/dashboard?days=30')->assertOk()->json();
        $this->assertCount(30, $d['dates']);
        $this->assertSame('2026-10-28', end($d['dates']));
        $this->assertSame(1, $d['kpis']['posts_today']);
        $this->assertSame(3, $d['kpis']['posts']);
        $this->assertSame(1, $d['kpis']['posts_prev']);
        $this->assertEquals(5.3, $d['kpis']['avg_engagement'], '(10% + 4% + 2%) / 3');
        $this->assertSame(1650, $d['kpis']['followers'], 'Instagram 1,250 + LinkedIn 400 carried forward');
        $this->assertSame(250, $d['kpis']['followers_change']);
        $this->assertSame(100, end($d['series']['engagement']));
        $this->assertSame(1000, end($d['series']['impressions']));
        $this->assertSame(1650, end($d['series']['followers']));
        $this->assertSame(1400, $d['series']['followers'][0], 'the 28 Sep counts carry into the window');
        $this->assertNull($this->getJson('/api/social/dashboard?days=90')->json('series.followers.0'), 'no counts before the first snapshot');
        $this->assertStringContainsString('4 posts are live', $d['ticker'][0]);
        $this->assertTrue(collect($d['ticker'])->contains(fn ($l) => str_contains($l, '1 post is scheduled')));
        $this->assertTrue(collect($d['ticker'])->contains(fn ($l) => str_contains($l, '+250 followers')));

        $ig = $this->getJson('/api/social/dashboard?days=7&platform=instagram')->assertOk()->json();
        $this->assertCount(7, $ig['dates']);
        $this->assertSame(1250, $ig['kpis']['followers']);
        $this->assertSame(1, $ig['kpis']['posts'], 'the 20 Oct post is outside the last 7 days');
    }

    public function test_number_parsing(): void
    {
        foreach ([
            '1,234' => 1234,
            '1.234' => 1234,
            '2.5K' => 2500,
            '3M' => 3000000,
            '12' => 12,
            '' => null,
            '-' => null,
            '4.7%' => 4,
        ] as $in => $out) {
            $this->assertSame($out, AnalyticsImport::number((string)$in), "'{$in}'");
        }
    }

    public function test_bios_options_and_save(): void
    {
        $this->getJson('/api/social/bios')->assertOk()->assertJsonPath('bios.tiktok.limit', 80)->assertJsonPath('bios.linkedin.what', 'Headline');
        Llm::$fake = fn () => json_encode([
            'options' => ['Tech made simple 🦄', str_repeat('x', 120)],
        ]);
        $opts = $this->postJson('/api/social/bios/options', [
            'platform' => 'tiktok',
        ])->assertOk()->json('options');
        $this->assertTrue($opts[0]['fits']);
        $this->assertFalse($opts[1]['fits'], '120 > 80');
        $this->putJson('/api/social/bios', [
            'platform' => 'tiktok',
            'text' => 'Tech made simple 🦄',
        ])->assertOk();
        $this->assertSame('Tech made simple 🦄', Setting::get('bio_tiktok'));
    }

    public function test_weekly_review_needs_posts_then_streams_and_keeps_it(): void
    {
        $this->postJson('/api/stream/social/review')->assertStatus(422);
        $this->posted('threads', '2026-10-26 09:00', [
            'likes' => 12,
        ], 'Conversational post about AI inboxes');
        $seen = null;
        Llm::$fake = function ($system, $user) use (&$seen) {
            $seen = [$system, $user];

            return "## What worked\nThe inbox post.";
        };
        $this->postJson('/api/stream/social/review')->assertOk()->streamedContent();
        $this->assertStringContainsString('Threads: 1 posts', $seen[1]);
        $this->assertStringContainsString('12 engagements', $seen[1]);
        $this->assertStringContainsString('too small', $seen[0]);
        $this->assertStringContainsString('What worked', Setting::get('social_review'));
        $this->getJson('/api/social/analytics')->assertOk()->assertJsonPath('review', "## What worked\nThe inbox post.");
    }

    private function posted(string $platform, string $at, ?array $metrics, string $body = 'A post', string $format = 'post'): SocialPost
    {
        return SocialPost::create([
            'platform' => $platform,
            'format' => $format,
            'body' => $body,
            'status' => 'posted',
            'posted_at' => CarbonImmutable::parse($at, 'UTC'),
            'metrics' => $metrics,
        ]);
    }
}
