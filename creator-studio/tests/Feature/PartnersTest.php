<?php

namespace Tests\Feature;

use App\Domains\Landing\Talk;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
    }

    public function test_strip_lists_her_brands_then_the_stages_from_her_talks(): void
    {
        $items = $this->getJson('/api/landing/partners')->assertOk()->json('items');

        $this->assertSame(['IBM', 'Adobe', 'Airalo', 'Temporal'], array_column(array_filter($items, fn ($i) => $i['kind'] === 'brand'), 'name'));
        $stages = array_column(array_values(array_filter($items, fn ($i) => $i['kind'] === 'stage')), 'name');
        $this->assertSame(['KM & AI Summit 2025', 'Head Start', 'Nomad Cruise', 'Vently Panel', 'Digital Garage Tokyo'], $stages);

        // A new talk shows up by itself; a hidden talk doesn't.
        Talk::create([
            'event' => 'AI Engineer World',
            'kind' => 'keynote',
            'date' => now()->addDays(30)->toDateString(),
        ]);
        Talk::where('event', 'Vently Panel')->update([
            'visible' => false,
        ]);
        $names = array_column($this->getJson('/api/landing/partners')->json('items'), 'name');
        $this->assertContains('AI Engineer World', $names);
        $this->assertNotContains('Vently Panel', $names);
    }

    public function test_she_edits_brands_and_hides_stages_from_the_dashboard(): void
    {
        $body = [
            'brands' => [[
                'name' => 'Google Cloud',
                'url' => 'https://cloud.google.com',
            ], [
                'name' => '  ',
                'url' => '',
            ], [
                'name' => 'Adobe',
            ]],
            'show_stages' => true,
            'hidden_stages' => ['Head Start'],
        ];
        $this->putJson('/api/partners', $body)->assertUnauthorized();
        $this->signIn();

        $this->putJson('/api/partners', $body)->assertOk();
        $items = $this->getJson('/api/landing/partners')->json('items');
        $this->assertSame([
            'name' => 'Google Cloud',
            'kind' => 'brand',
            'url' => 'https://cloud.google.com',
        ], $items[0]);
        $this->assertSame('Adobe', $items[1]['name'], 'Blank rows are dropped');
        $this->assertNotContains('Head Start', array_column($items, 'name'));
        $this->assertTrue(collect($this->getJson('/api/dashboard')->json('partners.stages'))->firstWhere('name', 'Head Start')['hidden']);

        $this->putJson('/api/partners', [
            'brands' => [[
                'name' => 'Adobe',
            ]],
            'show_stages' => false,
            'hidden_stages' => [],
        ])->assertOk();
        $this->assertSame(['Adobe'], array_column($this->getJson('/api/landing/partners')->json('items'), 'name'));

        $this->putJson('/api/partners', [
            'brands' => [[
                'name' => 'X',
                'url' => 'javascript:alert(1)',
            ]],
            'show_stages' => true,
            'hidden_stages' => [],
        ])->assertUnprocessable();
    }

    private function signIn(): void
    {
        $this->actingAs($this->admin([
            'name' => 'Delia',
            'email' => 'delia@example.com',
            'password' => 'secret123',
        ]));
    }
}
