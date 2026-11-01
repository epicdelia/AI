<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
    }

    private function signIn(): void
    {
        $this->actingAs($this->admin(['name' => 'Delia', 'email' => 'delia@example.com', 'password' => 'secret123']));
    }

    public function test_hero_uses_her_real_numbers_and_never_contradicts_itself(): void
    {
        $d = $this->getJson('/api/landing/hero')->assertOk()->json();

        $this->assertSame('Making tech *less scary*: built from beach cafés in 69 countries.', $d['headline'], 'Same countries as the stat beside it');
        $this->assertStringContainsString('content for 519K+ people', $d['lede']);
        $this->assertSame(['audience', 'countries', 'mvp', 'google'], array_column($d['stats'], 'key'));
        $this->assertSame('519K+', $d['stats'][0]['value']);
        $this->assertSame('69', $d['stats'][1]['value']);
        $this->assertSame(['text' => 'Booking new projects', 'state' => 'open'], $d['status']);
    }

    public function test_hero_follows_about_you_and_client_journey(): void
    {
        $this->signIn();
        $about = ['headline' => 'Engineer first. Storyteller always.', 'bio' => ['x'], 'thesis' => '', 'thesis_detail' => '', 'now' => '', 'location' => 'Lisbon',
            'countries' => 72, 'story_url' => null, 'milestones' => [['label' => 'A', 'detail' => '']]];
        $this->putJson('/api/about', $about)->assertOk();
        $this->putJson('/api/landing/capacity', ['capacity' => 0])->assertOk();
        $this->putJson('/api/hero', [
            'headline' => 'Shipping from *{countries}* countries', 'lede' => 'For {audience} people.', 'reassurance' => '', 'cta' => 'Say hi',
            'show_status' => true, 'stats' => ['talks', 'slots', 'networks', 'countries'],
        ])->assertOk();

        $d = $this->getJson('/api/landing/hero')->json();
        $this->assertSame('Shipping from *72* countries', $d['headline']);
        $this->assertSame('Say hi', $d['cta']);
        $this->assertSame(['text' => 'Now in Lisbon', 'state' => 'info'], $d['status'], 'No capacity set: only the location shows');
        // Slots are hidden at capacity 0, so the strip tops up with a default stat.
        $this->assertSame(['talks', 'networks', 'countries', 'audience'], array_column($d['stats'], 'key'));
        $this->assertSame('5', $d['stats'][0]['value']);

        $this->putJson('/api/hero', ['headline' => 'x', 'lede' => 'y', 'cta' => 'z', 'show_status' => false, 'stats' => ['talks', 'talks', 'mvp', 'google']])->assertUnprocessable();
        $this->putJson('/api/hero', ['headline' => 'x', 'lede' => 'y', 'cta' => 'z', 'show_status' => false, 'stats' => ['talks', 'mvp', 'google']])->assertUnprocessable();
        $this->putJson('/api/hero', ['headline' => 'x', 'lede' => 'y', 'cta' => 'z', 'show_status' => false, 'stats' => ['talks', 'mvp', 'google', 'followers']])->assertUnprocessable();
    }

    public function test_hero_editor_is_on_her_dashboard(): void
    {
        $this->putJson('/api/hero', [])->assertUnauthorized();
        $this->signIn();
        $hero = $this->getJson('/api/dashboard')->assertOk()->json('hero');
        $this->assertSame(['audience', 'countries', 'mvp', 'google', 'talks', 'slots', 'networks'], array_column($hero['catalog'], 'key'));
        $this->assertStringContainsString('69 countries', $hero['preview']['headline']);
    }
}
