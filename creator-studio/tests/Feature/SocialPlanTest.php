<?php

namespace Tests\Feature;

use App\Domains\Media\MediaFile;
use App\Domains\Social\Models\SocialPost;
use App\Domains\Social\SocialAnalytics;
use App\Domains\Social\SocialScheduler;
use App\Models\Setting;
use App\Models\User;
use App\Services\Llm;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SocialPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Setting::put('timezone', 'UTC');
        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-10-28 06:00', 'UTC')); // Wednesday morning
        $this->actingAs($this->admin(['name' => 'Delia', 'email' => 'd@example.com', 'password' => 'secret123']));
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        parent::tearDown();
    }

    private function make(array $attrs): SocialPost
    {
        return SocialPost::create($attrs + ['platform' => 'linkedin', 'format' => 'post', 'body' => 'A post', 'status' => 'draft']);
    }

    public function test_posting_queue_lists_due_and_upcoming_scheduled_posts(): void
    {
        $due = $this->make(['status' => 'scheduled', 'scheduled_for' => now()->subMinutes(5)]);
        $soon = $this->make(['status' => 'scheduled', 'scheduled_for' => now()->addMinutes(8)]);
        $later = $this->make(['status' => 'scheduled', 'scheduled_for' => now()->addHours(3)]);
        $this->make(['status' => 'scheduled', 'scheduled_for' => now()->addDays(3)]);
        $this->make(['status' => 'draft']);

        $q = $this->getJson('/api/social/due')->assertOk()->json();
        $this->assertSame([$due->id, $soon->id], array_column($q['due'], 'id'), 'due now or within 10 minutes');
        $this->assertSame([$later->id], array_column($q['upcoming'], 'id'), 'next 24 hours');
    }

    public function test_next_slot_uses_a_labelled_starter_time_skips_booked_days_and_keeps_a_gap(): void
    {
        $s = SocialScheduler::nextSlot('linkedin');
        $this->assertSame('starter', $s['source']);
        $this->assertSame('2026-10-28 08:30', $s['at']->format('Y-m-d H:i'), 'Wednesday 08:30, later today');
        $this->assertStringContainsString('isn’t from your data', $s['reason']);

        $this->make(['status' => 'scheduled', 'scheduled_for' => '2026-10-28 08:30']);
        $this->assertSame('2026-10-29 08:30', SocialScheduler::nextSlot('linkedin')['at']->format('Y-m-d H:i'), 'one post a day per network');

        $this->make(['platform' => 'x', 'format' => 'post', 'status' => 'posted', 'posted_at' => '2026-10-28 11:00']);
        $this->assertSame('2026-10-29 12:30', SocialScheduler::nextSlot('x')['at']->format('Y-m-d H:i'), 'today already has an X post');
    }

    public function test_next_slot_follows_her_best_times_once_she_has_data(): void
    {
        foreach (['2026-09-01', '2026-09-08', '2026-09-15', '2026-09-22', '2026-09-29'] as $tue) {
            $this->make(['platform' => 'instagram', 'format' => 'post', 'status' => 'posted', 'posted_at' => "{$tue} 19:15", 'metrics' => ['likes' => 400]]);
        }
        foreach (['2026-09-07', '2026-09-14', '2026-09-21'] as $mon) {
            $this->make(['platform' => 'instagram', 'format' => 'post', 'status' => 'posted', 'posted_at' => "{$mon} 08:00", 'metrics' => ['likes' => 20]]);
        }
        $this->assertTrue(SocialAnalytics::bestTimes('instagram')['enough']);
        $s = SocialScheduler::nextSlot('instagram');
        $this->assertSame('your data', $s['source']);
        $this->assertSame('2026-11-03 19:00', $s['at']->format('Y-m-d H:i'), 'next Tuesday evening');
        $this->assertStringContainsString('One of your best slots', $s['reason']);
    }

    public function test_auto_schedule_endpoint(): void
    {
        $p = $this->make(['platform' => 'tiktok', 'format' => 'video', 'parts' => ['Hook']]);
        $this->postJson("/api/social/posts/{$p->id}/auto-schedule")->assertOk()
            ->assertJsonPath('post.status', 'scheduled')->assertJsonPath('source', 'starter');
        $this->assertSame('2026-10-28 20:00', $p->fresh()->scheduled_for->format('Y-m-d H:i'));

        $posted = $this->make(['status' => 'posted', 'posted_at' => now()]);
        $this->postJson("/api/social/posts/{$posted->id}/auto-schedule")->assertStatus(422);
    }

    public function test_week_planner_follows_cadence_and_schedules_each_post(): void
    {
        Setting::put('cadence_linkedin', '2');
        Setting::put('cadence_x', '1');
        $seen = null;
        Llm::$fake = function ($system, $user) use (&$seen) {
            $seen = [$system, $user];

            return json_encode(['posts' => [
                ['platform' => 'linkedin', 'format' => 'post', 'title' => 'One', 'body' => 'Angle one'],
                ['platform' => 'linkedin', 'format' => 'post', 'title' => 'Two', 'body' => 'Angle two'],
                ['platform' => 'x', 'format' => 'post', 'title' => 'Three', 'body' => 'X take'],
            ]]);
        };
        $res = $this->postJson('/api/social/plan-week', ['brief' => 'My desk setup video lands Friday'])->assertOk()->json();

        $this->assertSame(2, substr_count($seen[0], 'linkedin.post:'), 'two LinkedIn posts asked for');
        $this->assertStringContainsString('different angle', $seen[1]);
        $this->assertCount(3, $res['posts']);
        $posts = SocialPost::orderBy('id')->get();
        $this->assertTrue($posts->every(fn ($p) => $p->status === 'scheduled' && $p->scheduled_for !== null));
        $li = $posts->where('platform', 'linkedin')->pluck('scheduled_for')->map->toDateString()->values()->all();
        $this->assertNotSame($li[0], $li[1], 'not on the same day');
        $this->assertNotNull($posts->first()->notes, 'why that slot');
    }

    public function test_media_upload_attach_remove_alt_and_streaming(): void
    {
        $p = $this->make(['platform' => 'instagram', 'format' => 'post']);
        $img = $this->post("/api/social/posts/{$p->id}/media", ['file' => UploadedFile::fake()->image('desk.jpg', 800, 600)], ['Accept' => 'application/json'])
            ->assertOk()->json('post.media.0');
        $this->assertSame('image/jpeg', $img['mime']);
        $this->assertStringStartsWith('/api/media/', $img['url']);
        $this->get($img['url'])->assertOk();

        $this->post("/api/social/posts/{$p->id}/media", ['file' => UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('post.media.1.mime', 'video/mp4');
        $this->post("/api/social/posts/{$p->id}/media", ['file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $mockup = MediaFile::store('thumbnail', 'png-bytes', 'png', 'image/png', ['title' => 'Mockup']);
        $this->postJson("/api/social/posts/{$p->id}/media/attach", ['media_id' => $mockup->id])->assertOk()->assertJsonCount(3, 'post.media');

        Llm::$fake = fn () => 'A tidy white desk with one monitor and a plant.';
        $this->postJson("/api/social/posts/{$p->id}/media/{$img['id']}/alt")->assertOk()->assertJsonPath('alt', 'A tidy white desk with one monitor and a plant.');
        $this->postJson("/api/social/posts/{$p->id}/media/{$img['id']}/alt", ['alt' => 'My desk'])->assertOk()->assertJsonPath('post.media.0.alt', 'My desk');

        $this->deleteJson("/api/social/posts/{$p->id}/media/{$img['id']}")->assertOk()->assertJsonCount(2, 'post.media');
        $this->assertNull(MediaFile::find($img['id']), 'an upload only this post used is deleted');
        $this->deleteJson("/api/social/posts/{$p->id}/media/{$mockup->id}")->assertOk();
        $this->assertNotNull(MediaFile::find($mockup->id), 'her mockup stays in the library');
    }

    public function test_captions_file_from_beats(): void
    {
        $p = $this->make(['platform' => 'tiktok', 'format' => 'video', 'title' => 'Desk reset', 'parts' => ['[TEXT: 60-second desk reset]', 'Clear everything off the desk right now.', 'Put back only three things.']]);
        $srt = $this->get("/api/social/posts/{$p->id}/srt")->assertOk()->assertHeader('content-disposition', 'attachment; filename="desk-reset.srt"')->getContent();
        $this->assertSame("1\n00:00:00,000 --> 00:00:02,800\nClear everything off the desk right now.\n\n2\n00:00:02,800 --> 00:00:04,800\nPut back only three things.\n", $srt);
        $this->getJson('/api/social/posts/'.$this->make([])->id.'/srt')->assertStatus(422);
    }

    public function test_pillars_tag_drafts_and_show_in_analytics(): void
    {
        Setting::put('content_pillars', "AI tools\nDesk setups");
        Llm::$fake = fn () => json_encode(['posts' => [
            ['platform' => 'x', 'format' => 'post', 'body' => 'One', 'pillar' => 'AI tools'],
            ['platform' => 'x', 'format' => 'post', 'body' => 'Two', 'pillar' => 'Made up pillar'],
        ]]);
        $this->postJson('/api/social/compose', ['prompt' => 'two posts', 'targets' => [['platform' => 'x', 'format' => 'post'], ['platform' => 'x', 'format' => 'post']]])->assertOk();
        $this->assertSame(['AI tools', null], SocialPost::orderBy('id')->pluck('pillar')->all(), 'only her own pillars are kept');
        $this->getJson('/api/social/meta')->assertJsonPath('pillars', ['AI tools', 'Desk setups']);

        $this->make(['platform' => 'tiktok', 'format' => 'video', 'status' => 'posted', 'posted_at' => now()->subDay(), 'pillar' => 'Desk setups', 'metrics' => ['likes' => 300]]);
        $this->make(['platform' => 'tiktok', 'format' => 'video', 'status' => 'posted', 'posted_at' => now()->subDays(2), 'pillar' => 'AI tools', 'metrics' => ['likes' => 100]]);
        $mix = SocialAnalytics::overview(4)['pillars'];
        $this->assertSame('Desk setups', $mix[0]['pillar']);
        $this->assertSame(300, $mix[0]['median_engagement']);
        $p = SocialPost::first();
        $this->patchJson("/api/social/posts/{$p->id}", ['pillar' => 'Desk setups'])->assertOk()->assertJsonPath('post.pillar', 'Desk setups');
    }
}
