<?php

namespace Tests\Feature;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Love\LoveLetter;
use App\Domains\Social\Models\SocialPost;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Setting::put('timezone', 'UTC');
        $this->travelTo(CarbonImmutable::parse('2026-11-04 08:00', 'UTC'));
        $this->actingAs(User::create(['name' => 'Delia Lazarescu', 'email' => 'd@example.com', 'password' => 'secret123']));
    }

    private function snap(string $p, int $count, string $day): void
    {
        DB::table('follower_snapshots')->updateOrInsert(['platform' => $p, 'recorded_on' => $day], ['count' => $count, 'source' => 'api', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_letters_stay_sealed_until_their_moment(): void
    {
        $this->postJson('/api/love/letters', ['title' => 'Open when you hit 10K', 'body' => 'So proud of you!', 'unlock' => 'followers', 'unlock_value' => 'instagram:10000'])->assertCreated();
        $this->postJson('/api/love/letters', ['title' => 'Open on our anniversary', 'body' => 'Happy anniversary', 'unlock' => 'date', 'unlock_value' => '2026-11-10'])->assertCreated();
        $this->postJson('/api/love/letters', ['title' => 'Open when you need a hug', 'body' => 'Hug!', 'unlock' => 'anytime'])->assertCreated();
        $this->postJson('/api/love/letters', ['title' => 'Bad', 'body' => 'x', 'unlock' => 'followers', 'unlock_value' => 'myspace:5'])->assertStatus(422);

        // the page never sends sealed text
        $r = $this->getJson('/api/love')->assertOk();
        $this->assertStringNotContainsString('So proud of you', $r->getContent());
        $this->assertSame(['Open when you need a hug'], collect($r->json('letters'))->where('open', true)->pluck('title')->all());
        $this->assertSame('Opens at 10,000 followers on Instagram', collect($r->json('letters'))->firstWhere('unlock', 'followers')['hint']);
        // encrypted at rest
        $this->assertStringNotContainsString('So proud', (string) DB::table('love_letters')->value('body'));

        $sealed = LoveLetter::where('unlock', 'followers')->first();
        $this->postJson("/api/love/letters/{$sealed->id}/open")->assertStatus(423);
        $hug = LoveLetter::where('unlock', 'anytime')->first();
        $this->postJson("/api/love/letters/{$hug->id}/open")->assertOk()->assertJsonPath('letter.body', 'Hug!');

        // she reaches 10K → it unlocks on the next check, once
        $this->snap('instagram', 10250, '2026-11-04');
        $s = $this->getJson('/api/love/status')->assertOk();
        $this->assertSame(['Open when you hit 10K'], array_column($s->json('unlocked'), 'title'));
        $this->assertSame([], $this->getJson('/api/love/status')->json('unlocked'));
        $this->assertSame('So proud of you!', $this->postJson("/api/love/letters/{$sealed->id}/open")->json('letter.body'));

        // the date letter opens on the day
        $this->travelTo(CarbonImmutable::parse('2026-11-10 07:00', 'UTC'));
        $this->assertSame(['Open on our anniversary'], array_column($this->getJson('/api/love/status')->json('unlocked'), 'title'));
    }

    public function test_first_post_and_streak_letters(): void
    {
        LoveLetter::create(['title' => 'First post', 'body' => 'Yay', 'unlock' => 'first_post']);
        SocialPost::create(['platform' => 'x', 'format' => 'post', 'body' => 'demo', 'status' => 'posted', 'source' => 'demo']);
        $this->assertSame([], $this->getJson('/api/love/status')->json('unlocked'));
        SocialPost::create(['platform' => 'x', 'format' => 'post', 'body' => 'real', 'status' => 'posted']);
        $this->assertSame(['First post'], array_column($this->getJson('/api/love/status')->json('unlocked'), 'title'));
    }

    public function test_milestones_celebrated_once_and_not_for_old_history(): void
    {
        $this->snap('instagram', 4800, '2026-11-01');
        $this->assertSame([], $this->getJson('/api/love/status')->json('milestones')); // first check: quiet
        $this->snap('instagram', 5100, '2026-11-04');
        $this->snap('tiktok', 1200, '2026-11-04');
        $m = $this->getJson('/api/love/status')->json('milestones');
        $this->assertEqualsCanonicalizing([['platform' => 'instagram', 'label' => 'Instagram', 'count' => 5000], ['platform' => 'tiktok', 'label' => 'TikTok', 'count' => 1000]], $m);
        $this->assertSame([], $this->getJson('/api/love/status')->json('milestones'));
        $this->assertSame(2, AgentEvent::where('type', 'love')->count());
    }

    public function test_dates_countdown_today_and_days_together(): void
    {
        $this->putJson('/api/love/dates', ['together_since' => '2023-11-04', 'dates' => [
            ['label' => 'Our anniversary', 'date' => '2023-11-04', 'kind' => 'anniversary', 'yearly' => true],
            ['label' => 'Delia’s birthday', 'date' => '1996-03-12', 'kind' => 'birthday', 'yearly' => true],
            ['label' => 'Old trip', 'date' => '2025-01-01', 'kind' => 'other', 'yearly' => false],
        ]])->assertOk();
        $c = $this->getJson('/api/love/status')->json('calendar');
        $this->assertSame(1096, $c['together_days']);
        $this->assertSame('Our anniversary', $c['today'][0]['label']);
        $this->assertSame(3, $c['today'][0]['years']);
        $this->assertSame(['Our anniversary', 'Delia’s birthday'], array_column($c['upcoming'], 'label'));
        $this->assertSame(128, $c['upcoming'][1]['days']);
    }

    public function test_love_off_hides_everything_and_photo_is_private(): void
    {
        Storage::fake('local');
        $this->postJson('/api/love/photo', ['photo' => UploadedFile::fake()->image('us.jpg', 800, 600)])->assertOk();
        $this->get('/api/love/photo')->assertOk();
        Setting::put('love_notes', '0');
        $this->assertSame(['enabled' => false], $this->getJson('/api/love/status')->json());
        auth()->logout();
        $this->get('/api/love/photo')->assertStatus(401);
    }

    public function test_prefs_and_nickname_in_status(): void
    {
        $this->putJson('/api/settings', ['nickname' => 'Dee', 'accent' => 'ocean', 'nav_hidden' => '["/breakouts"]', 'dashboard_layout' => '[{"id":"love","hidden":true}]'])->assertOk();
        $s = $this->getJson('/api/auth/status')->assertOk();
        $this->assertSame('Dee', $s->json('love.name'));
        $this->assertSame('ocean', $s->json('prefs.accent'));
        $this->assertSame(['/breakouts'], $s->json('prefs.nav_hidden'));
        $this->assertSame([['id' => 'love', 'hidden' => true]], $s->json('prefs.dashboard_layout'));
        auth()->logout();
        $this->assertSame(['name' => 'Dee', 'accent' => 'ocean', 'love' => true, 'google' => false], $this->getJson('/api/auth/status')->json('welcome'));
    }
}
