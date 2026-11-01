<?php

namespace Tests\Feature;

use App\Domains\Landing\Talk;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarqueeTest extends TestCase
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

    public function test_band_mixes_her_phrases_with_live_facts(): void
    {
        $d = $this->getJson('/api/landing/marquee')->assertOk()->json();

        $this->assertSame(40, $d['duration']);
        $phrases = array_column(array_filter($d['items'], fn ($i) => $i['kind'] === 'phrase'), 'label');
        $this->assertSame(['Vibecoded Apps', 'Content Creation', 'Speaking & Keynotes', 'AI Integration', 'Brand Partnerships', 'Technical Advisory'], array_values($phrases));
        $live = array_column(array_filter($d['items'], fn ($i) => $i['kind'] === 'live'), 'label');
        $this->assertContains('519K+ people follow along', $live);
        $this->assertContains('2 build slots open', $live);
        $this->assertContains('Latest talk: KM & AI Summit 2025', $live);
        $this->assertContains('Shipped from 69 countries', $live);
        // A live fact after every second phrase.
        $this->assertSame('live', $d['items'][2]['kind']);

        Talk::create(['event' => 'AI Engineer World', 'kind' => 'keynote', 'date' => now()->addDays(9)->toDateString()]);
        $this->assertContains('Next on stage: AI Engineer World', array_column($this->getJson('/api/landing/marquee')->json('items'), 'label'));
    }

    public function test_she_edits_phrases_live_facts_and_speed(): void
    {
        $body = ['items' => [['label' => 'Ship it', 'target' => 'pricing'], ['label' => ' ', 'target' => 'about']], 'show_live' => false, 'speed' => 'slow'];
        $this->putJson('/api/marquee', $body)->assertUnauthorized();
        $this->signIn();

        $this->putJson('/api/marquee', $body)->assertOk();
        $d = $this->getJson('/api/landing/marquee')->json();
        $this->assertSame([['label' => 'Ship it', 'target' => 'pricing', 'kind' => 'phrase']], $d['items']);
        $this->assertSame(60, $d['duration']);
        $this->assertSame('slow', $this->getJson('/api/dashboard')->json('marquee.speed'));

        $this->putJson('/api/marquee', ['items' => [], 'show_live' => false, 'speed' => 'fast'])->assertUnprocessable();
        $this->putJson('/api/marquee', ['items' => [['label' => 'X', 'target' => 'nowhere']], 'show_live' => true, 'speed' => 'fast'])->assertUnprocessable();
        $this->putJson('/api/marquee', ['items' => [['label' => 'X', 'target' => 'about']], 'show_live' => true, 'speed' => 'warp'])->assertUnprocessable();
    }
}
