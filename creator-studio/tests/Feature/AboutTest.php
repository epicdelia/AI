<?php

namespace Tests\Feature;

use App\Domains\Landing\About;
use App\Domains\Landing\Talk;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AboutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
    }

    public function test_about_combines_her_words_with_live_facts(): void
    {
        $d = $this->getJson('/api/landing/about')->assertOk()->json();

        $this->assertSame('Engineer first. Storyteller always.', $d['headline']);
        $this->assertSame(About::PORTRAIT_DEFAULT, $d['portrait_url']);
        $this->assertCount(4, $d['milestones']);
        $this->assertSame(69, $d['facts']['countries']);
        $this->assertSame('519K+', $d['facts']['audience'], 'Same total as Reach & Content');
        $this->assertSame(6, $d['facts']['networks']);
        $this->assertSame(5, $d['facts']['talks'], 'Same list as On Stage');
        $this->assertSame('KM & AI Summit 2025', $d['facts']['latest_talk']['event']);
        $this->assertSame('open', $d['facts']['availability']['status']);

        // Hide a network and add an upcoming talk: About follows.
        Setting::put('landing_reach_hidden', json_encode(['threads']));
        Talk::create([
            'event' => 'AI Engineer World',
            'kind' => 'keynote',
            'date' => now()->addDays(20)->toDateString(),
        ]);
        $d = $this->getJson('/api/landing/about')->json();
        $this->assertSame(5, $d['facts']['networks']);
        $this->assertSame('504K+', $d['facts']['audience']);
        $this->assertSame('AI Engineer World', $d['facts']['latest_talk']['event']);
        $this->assertTrue($d['facts']['latest_talk']['upcoming']);
    }

    public function test_she_edits_the_section_from_the_dashboard(): void
    {
        $this->putJson('/api/about', $this->valid())->assertUnauthorized();
        $this->signIn();

        $this->putJson('/api/about', $this->valid())->assertOk();
        $d = $this->getJson('/api/landing/about')->json();
        $this->assertSame(['First paragraph.'], $d['bio'], 'Empty paragraphs are skipped');
        $this->assertSame(['Self-taught', 'Google'], array_column($d['milestones'], 'label'), 'Rows without a label are skipped');
        $this->assertSame(71, $d['facts']['countries']);
        $this->assertSame('Lisbon, Portugal', $d['location']);
        $this->assertSame('Shipping an AI app for a fintech', $this->getJson('/api/dashboard')->json('about.now'));

        $this->putJson('/api/about', $this->valid([
            'countries' => 900,
        ]))->assertUnprocessable();
        $this->putJson('/api/about', $this->valid([
            'story_url' => 'javascript:alert(1)',
        ]))->assertUnprocessable();
        $this->putJson('/api/about', $this->valid([
            'headline' => '',
        ]))->assertUnprocessable();
    }

    public function test_her_portrait_can_be_replaced_and_reset(): void
    {
        Storage::fake('local');
        $this->signIn();

        $url = $this->post('/api/about/portrait', [
            'image' => UploadedFile::fake()->image('me.jpg', 600, 600),
        ])->assertOk()->json('about.portrait_url');
        $path = About::get()['portrait'];
        Storage::disk('local')->assertExists($path);
        $this->assertStringStartsWith('/landing/about-portrait', $url);
        $this->assertSame($url, $this->getJson('/api/landing/about')->json('portrait_url'));
        $this->get('/landing/about-portrait')->assertOk();

        $this->post('/api/about/portrait', [
            'image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors('image');

        $this->deleteJson('/api/about/portrait')->assertOk();
        Storage::disk('local')->assertMissing($path);
        $this->assertSame(About::PORTRAIT_DEFAULT, $this->getJson('/api/landing/about')->json('portrait_url'));
        $this->get('/landing/about-portrait')->assertNotFound();
    }

    private function signIn(): void
    {
        $this->actingAs($this->admin([
            'name' => 'Delia',
            'email' => 'delia@example.com',
            'password' => 'secret123',
        ]));
    }

    private function valid(array $overrides = []): array
    {
        return $overrides + [
            'headline' => 'Engineer first. Storyteller always.',
            'bio' => ['First paragraph.', ''],
            'thesis' => 'Tech feels scary because it is explained badly.',
            'thesis_detail' => 'Make it obvious.',
            'now' => 'Shipping an AI app for a fintech',
            'location' => 'Lisbon, Portugal',
            'countries' => 71,
            'story_url' => 'https://linkedin.com/in/delialazarescu',
            'milestones' => [[
                'label' => 'Self-taught',
                'detail' => '',
            ], [
                'label' => '',
                'detail' => 'skipped',
            ], [
                'label' => 'Google',
                'detail' => 'Production code',
            ]],
        ];
    }
}
