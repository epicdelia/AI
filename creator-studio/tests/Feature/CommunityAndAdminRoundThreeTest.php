<?php

namespace Tests\Feature;

use App\Domains\Admin\WeeklyDigest;
use App\Domains\Community\Community;
use App\Domains\Community\Models\CommunityItem;
use App\Domains\Community\Models\CommunityMembership;
use App\Domains\YouTube\TestImpact;
use App\Http\Controllers\Api\LearningController;
use App\Models\Deal;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CommunityAndAdminRoundThreeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Mail::fake();
        $this->admin();
    }

    public function test_courses_progress_points_and_pro_lock(): void
    {
        $this->actingAs($this->admin());
        $cid = $this->postJson('/api/community/admin/courses', ['title' => 'Shipping MVPs', 'tier' => 'pro', 'status' => 'published'])->assertOk()->json('courses.0.id');
        $this->postJson("/api/community/admin/courses/{$cid}/lessons", ['title' => 'Intro', 'tier' => 'free_preview', 'body' => 'Hello'])->assertOk();
        $this->postJson("/api/community/admin/courses/{$cid}/lessons", ['title' => 'Deep part', 'body' => 'Secret'])->assertOk();
        [$intro, $deep] = DB::table('course_lessons')->orderBy('position')->pluck('id')->all();

        $m = $this->member();
        $this->actingAs($m);
        $this->getJson('/api/portal/community/courses')->assertJsonPath('courses.0.locked', true)->assertJsonPath('courses.0.lessons', 2);
        $l = $this->getJson("/api/portal/community/courses/{$cid}")->json('lessons');
        $this->assertFalse($l[0]['locked']);
        $this->assertTrue($l[1]['locked']);
        $this->assertNull($l[1]['body']);
        $this->postJson("/api/portal/community/lessons/{$deep}/complete", ['done' => true])->assertForbidden();
        $this->postJson("/api/portal/community/lessons/{$intro}/complete", ['done' => true])->assertOk();
        $this->postJson("/api/portal/community/lessons/{$intro}/complete", ['done' => true])->assertOk(); // no double points
        $this->assertSame(Community::POINTS['lesson'], Community::points($m));

        CommunityMembership::create(['user_id' => $m->id, 'status' => 'comped']);
        $this->assertSame('Secret', $this->getJson("/api/portal/community/courses/{$cid}")->json('lessons.1.body'));
        $this->getJson('/api/portal/community/courses')->assertJsonPath('courses.0.completed', 1);
    }

    public function test_events_rsvp_ics_reminders_and_replay_post(): void
    {
        $this->actingAs($this->admin());
        $this->postJson('/api/community/admin/events', ['title' => 'Live Q&A', 'starts_at' => now()->addMinutes(50)->toIso8601String(), 'join_url' => 'https://zoom.us/j/1'])->assertOk();
        $id = DB::table('community_events')->value('id');

        $m = $this->member();
        $this->actingAs($m);
        $this->postJson("/api/portal/community/events/{$id}/rsvp")->assertOk()->assertJsonPath('going', true)->assertJsonPath('count', 1);
        $this->getJson('/api/portal/community/events')->assertJsonPath('upcoming.0.going', true);
        $ics = $this->get("/api/portal/community/events/{$id}/ics")->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->getContent();
        $this->assertStringContainsString('SUMMARY:Live Q&A', $ics);

        $this->assertSame(2, LearningController::remindEvents()); // within 24h and within 1h
        $this->assertSame(0, LearningController::remindEvents());

        $this->actingAs($this->admin())->patchJson("/api/community/admin/events/{$id}", ['replay_url' => 'https://youtube.com/watch?v=abc'])->assertOk();
        $this->assertSame('Replay: Live Q&A', CommunityItem::where('type', 'dispatch')->value('title'));
        $this->actingAs($this->staff())->postJson('/api/community/admin/events', ['title' => 'x', 'starts_at' => now()->toIso8601String()])->assertForbidden();
    }

    public function test_winback_once_for_quiet_members(): void
    {
        CommunityItem::create(['type' => 'dispatch', 'title' => 'Popular post', 'status' => 'published', 'published_at' => now()->subDays(3), 'upvotes' => 9]);
        $quiet = $this->member();
        $quiet->forceFill(['community_seen_at' => now()->subDays(30), 'created_at' => now()->subDays(60)])->save();
        $active = $this->member(['email' => 'active@x.test']);
        $active->forceFill(['community_seen_at' => now()->subDay()])->save();
        $this->assertSame(1, Community::sendWinbacks());
        $this->assertSame(0, Community::sendWinbacks());

        $this->actingAs($this->admin())->getJson('/api/community/admin')->assertJsonPath('counts.at_risk', 1);
    }

    public function test_title_test_impact_uses_real_snapshots_only(): void
    {
        foreach (['aaaaaaaaaaa' => 1000, 'bbbbbbbbbbb' => 2000, 'ccccccccccc' => 3000, 'ttttttttttt' => 3000] as $vid => $views) {
            DB::table('video_stats')->insert(['video_id' => $vid, 'captured_on' => now()->toDateString(), 'age_days' => 7.2, 'views' => $views, 'likes' => 0, 'comments' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
        $r = TestImpact::forVideo('ttttttttttt');
        $this->assertSame(3000, $r['ages'][0]['views']);
        $this->assertSame(2000, $r['ages'][0]['median']);
        $this->assertSame(50, $r['ages'][0]['change_pct']);
        $this->assertNull($r['ages'][1]['views']); // day 28 not reached
    }

    public function test_weekly_digest_numbers_and_switch(): void
    {
        Deal::create(['brand_name' => 'Paid Co', 'rate' => 1200, 'stage' => 'paid', 'payment_status' => 'paid', 'paid_at' => now()->subDays(2)]);
        Deal::create(['brand_name' => 'Late Co', 'rate' => 500, 'stage' => 'invoiced', 'payment_status' => 'invoiced', 'invoiced_at' => now()->subDays(40)])
            ->forceFill(['invoice_due_on' => now()->subDays(10)->toDateString()])->saveQuietly();
        $d = WeeklyDigest::build();
        $this->assertEquals(1200, $d['paid_in']);
        $this->assertSame(1, $d['overdue_count']);
        $this->assertStringContainsString('Paid in: $1,200.00', WeeklyDigest::text($d));
        $this->assertSame(1, WeeklyDigest::send());

        $this->actingAs($this->admin())->putJson('/api/needs-you/weekly', ['on' => false])->assertOk()->assertJsonPath('on', false);
        $this->assertSame(0, WeeklyDigest::send());
        $this->actingAs($this->staff())->getJson('/api/needs-you/weekly')->assertForbidden();
    }
}
