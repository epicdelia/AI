<?php

namespace Tests\Feature;

use App\Domains\Engagement\Models\EngagementItem;
use App\Domains\Social\Models\SocialPost;
use App\Domains\Social\PostScorer;
use App\Models\Setting;
use App\Models\User;
use App\Services\Llm;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GrowthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Setting::put('timezone', 'UTC');
        Setting::put('llm_api_key', 'test');
        $this->travelTo(CarbonImmutable::parse('2026-11-04 08:00', 'UTC')); // Wednesday
        $this->actingAs($this->admin(['name' => 'Delia', 'email' => 'd@example.com', 'password' => 'secret123']));
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        parent::tearDown();
    }

    /** Her history: posts with a number in the hook do 3× better than question openers. */
    private function history(): void
    {
        for ($i = 0; $i < 6; $i++) {
            SocialPost::create(['platform' => 'linkedin', 'format' => 'post', 'status' => 'posted', 'posted_at' => now()->subDays(3 + $i * 3)->setTime(9, 0),
                'body' => "I saved {$i}0 hours with one tool.\nHere is what changed in my week and why it matters.\nWhat would you automate?", 'metrics' => ['impressions' => 1000, 'likes' => 60]]);
            SocialPost::create(['platform' => 'linkedin', 'format' => 'post', 'status' => 'posted', 'posted_at' => now()->subDays(4 + $i * 3)->setTime(19, 0),
                'body' => "Have you ever wondered about productivity?\nSome thoughts on tools.", 'metrics' => ['impressions' => 1000, 'likes' => 20]]);
        }
    }

    public function test_rule_of_thumb_score_without_history_and_checks(): void
    {
        $r = $this->postJson('/api/social/score', ['platform' => 'linkedin', 'format' => 'post',
            'body' => str_repeat('A very long opening line that keeps going well past the fold on phones ', 3)."\nMore. Read it at https://x.co"])->assertOk();
        $this->assertSame('rules of thumb', $r->json('basis'));
        $texts = collect($r->json('checks'))->pluck('text')->implode(' | ');
        $this->assertStringContainsString('past “see more”', $texts);
        $this->assertStringContainsString('first comment', $texts);
        $this->assertLessThan(50, $r->json('score'));
        $this->assertSame('bad', $this->postJson('/api/social/score', ['platform' => 'x', 'body' => ''])->json('checks.0.status'));
    }

    public function test_data_score_predicts_from_similar_posts(): void
    {
        $this->history();
        $good = $this->postJson('/api/social/score', ['platform' => 'linkedin', 'format' => 'post', 'scheduled_for' => '2026-11-05T09:00:00Z',
            'body' => "I saved 12 hours with one tool.\nHere is what changed in my week and why it matters.\nWhat would you automate?"])->assertOk();
        $weak = $this->postJson('/api/social/score', ['platform' => 'linkedin', 'format' => 'post', 'scheduled_for' => '2026-11-05T19:00:00Z',
            'body' => "Have you ever wondered about tools?\nSome thoughts."])->assertOk();
        $this->assertSame('your data', $good->json('basis'));
        $this->assertGreaterThan($weak->json('score'), $good->json('score'));
        $this->assertGreaterThanOrEqual(58, $good->json('score'));
        $this->assertSame('engagement rate (%)', $good->json('metric'));
        $this->assertCount(3, $good->json('similar'));
        $this->assertEquals(6, $good->json('similar.0.value'));
        $this->assertStringContainsString('strongest time slots', collect($good->json('checks'))->pluck('text')->implode(' '));
    }

    public function test_hook_lab_scores_and_sorts_variants(): void
    {
        $this->history();
        Llm::$fake = fn () => json_encode(['hooks' => [
            ['style' => 'question', 'text' => 'Have you ever wondered about this?'],
            ['style' => 'result', 'text' => 'I saved 9 hours last week.'],
            ['style' => 'empty', 'text' => ''],
        ]]);
        $r = $this->postJson('/api/social/hooks', ['platform' => 'linkedin', 'format' => 'post', 'body' => "Some thoughts on tools.\nHere is what changed in my week and why it matters.\nWhat would you automate?"])->assertOk();
        $this->assertCount(2, $r->json('hooks'));
        $this->assertSame('result', $r->json('hooks.0.style'));
        $this->assertGreaterThan($r->json('hooks.1.score'), $r->json('hooks.0.score'));
        $this->postJson('/api/social/hooks', ['platform' => 'linkedin', 'body' => 'Hi'])->assertStatus(422);
    }

    public function test_split_hook_and_features(): void
    {
        $this->assertSame(['Hook', "Line 2\nLine 3"], PostScorer::splitHook("Hook\nLine 2\nLine 3"));
        $f = PostScorer::features(['body' => "3 tips #ai #tools\nWhat do you think?", 'parts' => []]);
        $this->assertSame([2, 1, 1], [$f['hashtags'], $f['question'], $f['number_hook']]);
    }

    public function test_daily_brief_counts_and_cadence(): void
    {
        Setting::put('cadence_linkedin', '3');
        SocialPost::create(['platform' => 'linkedin', 'format' => 'post', 'body' => 'Due later today', 'status' => 'scheduled', 'scheduled_for' => now()->addHours(4)]);
        SocialPost::create(['platform' => 'x', 'format' => 'post', 'body' => 'Pending', 'status' => 'draft', 'approval' => 'pending']);
        EngagementItem::create(['platform' => 'instagram', 'kind' => 'dm', 'external_id' => 'd1', 'text' => 'Rates?', 'intent' => 'lead', 'status' => 'new']);
        DB::table('follower_snapshots')->insert([
            ['platform' => 'instagram', 'count' => 1000, 'recorded_on' => '2026-10-29', 'source' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['platform' => 'instagram', 'count' => 1070, 'recorded_on' => '2026-11-04', 'source' => 'api', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $b = $this->getJson('/api/brief')->assertOk();
        $this->assertSame('Good morning', $b->json('greeting'));
        $this->assertCount(1, $b->json('due_today'));
        $this->assertSame([1, 1, 1], [$b->json('approvals'), $b->json('inbox'), $b->json('leads')]);
        $this->assertSame(70, $b->json('followers.0.change_7d'));
        $this->assertSame(['done' => 1, 'target' => 3], array_intersect_key($b->json('cadence.0'), ['done' => 0, 'target' => 0]));
        $this->assertNotNull($b->json('cadence.0.next_slot'));
    }

    public function test_goals_forecast_on_track_behind_and_validation(): void
    {
        for ($d = 0; $d <= 20; $d += 5) {
            DB::table('follower_snapshots')->insert(['platform' => 'instagram', 'count' => 1000 + $d * 10, 'recorded_on' => now()->subDays(20 - $d)->toDateString(),
                'source' => 'api', 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->putJson('/api/social/goals', ['goals' => [['platform' => 'instagram', 'target' => 1500, 'by' => '2027-01-31']]])->assertOk()
            ->assertJsonPath('goals.0.status', 'on_track')->assertJsonPath('goals.0.per_day', 10)->assertJsonPath('goals.0.current', 1200);
        $r = $this->putJson('/api/social/goals', ['goals' => [['platform' => 'instagram', 'target' => 5000, 'by' => '2026-12-01']]])->assertOk();
        $this->assertSame('behind', $r->json('goals.0.status'));
        $this->assertGreaterThan(10, $r->json('goals.0.needed_per_day'));
        $this->putJson('/api/social/goals', ['goals' => [['platform' => 'instagram', 'target' => 10, 'by' => '2020-01-01']]])->assertStatus(422);
        $this->putJson('/api/social/goals', ['goals' => [['platform' => 'tiktok', 'target' => 10, 'by' => '2027-01-01']]])->assertOk()->assertJsonPath('goals.0.status', 'no_data');
    }
}
