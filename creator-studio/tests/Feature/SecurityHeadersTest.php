<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_get_a_content_security_policy_that_allows_their_own_inline_scripts_and_youtube(): void
    {
        $page = $this->get('/');
        $page->assertOk();
        $csp = $page->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString('frame-src https://www.youtube-nocookie.com', $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString('wss://streaming.assemblyai.com', $csp);

        // Every inline script of the shell is allowed by its exact hash, and nothing else inline.
        $html = file_get_contents(public_path('index.html'));
        $hashes = SecurityHeaders::inlineScriptHashes($html);
        $this->assertNotEmpty($hashes);
        foreach ($hashes as $hash) {
            $this->assertStringContainsString($hash, $csp);
        }
        $this->assertDoesNotMatchRegularExpression("/script-src[^;]*'unsafe-inline'/", $csp);

        $this->assertSame('nosniff', $page->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('microphone=(self)', $page->headers->get('Permissions-Policy'));
    }

    public function test_json_has_the_basic_headers_but_no_page_policy(): void
    {
        $r = $this->getJson('/api/landing/hero')->assertOk();
        $this->assertNull($r->headers->get('Content-Security-Policy'));
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
    }

    public function test_the_link_in_bio_page_keeps_its_theme_script_working(): void
    {
        $r = $this->get('/links')->assertOk();
        foreach (SecurityHeaders::inlineScriptHashes($r->getContent()) as $hash) {
            $this->assertStringContainsString($hash, $r->headers->get('Content-Security-Policy'));
        }
    }
}
