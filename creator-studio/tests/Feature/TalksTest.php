<?php

namespace Tests\Feature;

use App\Domains\Landing\Talk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TalksTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): void
    {
        $this->actingAs($this->admin(['name' => 'Delia', 'email' => 'delia@example.com', 'password' => 'secret123']));
    }

    public function test_on_stage_leads_with_the_km_summit_keynote_and_its_photo(): void
    {
        $d = $this->getJson('/api/landing/talks')->assertOk()->json();

        $this->assertSame('KM & AI Summit 2025', $d['featured']['event']);
        $this->assertSame('AI for Enterprise KM & Collaboration', $d['featured']['title']);
        $this->assertSame('/images/speaking/km-ai-summit-2025.jpg', $d['featured']['image_url']);
        $this->assertFileExists(public_path('images/speaking/km-ai-summit-2025.jpg'));
        $this->assertSame(['talks' => 5, 'keynotes' => 2, 'cities' => 2, 'upcoming' => 0], $d['stats']);
        $this->assertSame(['KM & AI Summit 2025', 'Head Start', 'Nomad Cruise', 'Vently Panel', 'Digital Garage Tokyo'], array_column($d['talks'], 'event'));
        $headStart = collect($d['talks'])->firstWhere('event', 'Head Start');
        $this->assertSame('/images/speaking/head-start-2024.jpg', $headStart['image_url']);
        $this->assertFileExists(public_path('images/speaking/head-start-2024.jpg'));
        $this->assertNotEmpty($d['topics']);
    }

    public function test_upcoming_talks_count_down_and_hidden_ones_stay_off_the_page(): void
    {
        Talk::create(['event' => 'AI Engineer World', 'kind' => 'keynote', 'date' => now()->addDays(12)->toDateString(), 'city' => 'San Francisco']);
        Talk::create(['event' => 'Secret Meetup', 'kind' => 'panel', 'visible' => false]);

        $d = $this->getJson('/api/landing/talks')->json();
        $this->assertSame('AI Engineer World', $d['upcoming'][0]['event']);
        $this->assertTrue($d['upcoming'][0]['upcoming']);
        $this->assertNotContains('Secret Meetup', array_column($d['talks'], 'event'));
        $this->assertSame(1, $d['stats']['upcoming']);

        $this->signIn();
        $this->assertSame('AI Engineer World', $this->getJson('/api/dashboard')->json('speaking.next.event'));
    }

    public function test_she_manages_talks_and_featuring_one_unfeatures_the_other(): void
    {
        $this->postJson('/api/talks', ['event' => 'X', 'kind' => 'keynote'])->assertUnauthorized();
        $this->signIn();

        $id = $this->postJson('/api/talks', ['event' => 'DevFest Lisbon', 'kind' => 'workshop', 'date' => '2026-12-05', 'city' => 'Lisbon', 'featured' => true])
            ->assertCreated()->json('talk.id');
        $this->assertSame(1, Talk::where('featured', true)->count());
        $this->assertSame('DevFest Lisbon', $this->getJson('/api/landing/talks')->json('featured.event'));

        $this->patchJson("/api/talks/{$id}", ['visible' => false])->assertOk();
        $this->assertNotContains('DevFest Lisbon', array_column($this->getJson('/api/landing/talks')->json('talks'), 'event'));

        $this->postJson('/api/talks', ['event' => 'Bad', 'kind' => 'karaoke'])->assertUnprocessable();
        $this->postJson('/api/talks', ['event' => 'Bad', 'kind' => 'panel', 'url' => 'javascript:alert(1)'])->assertUnprocessable();

        $this->deleteJson("/api/talks/{$id}")->assertOk();
        $this->assertNull(Talk::find($id));
    }

    public function test_uploaded_stage_photos_are_served_only_for_visible_talks(): void
    {
        Storage::fake('local');
        $this->signIn();
        $talk = Talk::create(['event' => 'Vently', 'kind' => 'panel']);

        $res = $this->post("/api/talks/{$talk->id}/image", ['image' => UploadedFile::fake()->image('stage.jpg', 800, 600)])->assertOk();
        $talk = $talk->fresh(); // the upload saved the photo on its own copy
        $path = $talk->image;
        Storage::disk('local')->assertExists($path);
        $this->assertStringStartsWith("/landing/talk-image/{$talk->id}", $res->json('talk.image_url'));
        $this->get("/landing/talk-image/{$talk->id}")->assertOk();

        $talk->update(['visible' => false]);
        $this->get("/landing/talk-image/{$talk->id}")->assertNotFound();

        $talk->delete();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_speaking_requests_from_the_website_reach_her_dashboard(): void
    {
        $this->postJson('/api/booking', [
            'name' => 'Ana', 'email' => 'ana@confs.example', 'company' => 'DevConf', 'project_type' => 'speaking',
            'preferred_date' => '2026-11-20', 'preferred_time' => '15:00 UTC', 'service' => 'Speaking & Workshops',
        ])->assertOk();
        $this->postJson('/api/booking', [
            'name' => 'Bo', 'email' => 'bo@example.com', 'project_type' => 'mvp_build', 'preferred_date' => '2026-11-20', 'preferred_time' => '15:00 UTC',
        ])->assertOk();

        $this->signIn();
        $speaking = $this->getJson('/api/dashboard')->assertOk()->json('speaking');
        $this->assertSame(1, $speaking['requests']);
        $this->assertSame('DevConf', $speaking['latest_requests'][0]['company']);
    }
}
