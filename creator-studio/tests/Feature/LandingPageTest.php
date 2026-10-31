<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Setting::put('creator_name', 'Delia Lazarescu');
    }

    public function test_techunicorn_public_landing_page_renders_successfully(): void
    {
        $response = $this->get('/techunicorn');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=utf-8');
        $content = $response->getContent();

        $this->assertStringContainsString('Tech Unicorn', $content);
        $this->assertStringContainsString('Delia Lazarescu', $content);
        $this->assertStringContainsString('Making tech', $content);
        $this->assertStringContainsString('Vibecoded Apps', $content);
        $this->assertStringContainsString('MVP Build', $content);
        $this->assertStringContainsString('Advisory Retainer', $content);
        $this->assertStringContainsString('Nomad Cruise', $content);
        $this->assertStringContainsString('472K+', $content);
    }

    public function test_links_bio_landing_page_renders_in_dashboard_design_language(): void
    {
        $response = $this->get('/links');

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('Delia Lazarescu', $content);
        $this->assertStringContainsString('@Tech.Unicorn', $content);
        $this->assertStringContainsString('/techunicorn', $content);
    }
}
