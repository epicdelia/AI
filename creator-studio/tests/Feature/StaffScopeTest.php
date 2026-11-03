<?php

namespace Tests\Feature;

use App\Domains\Copilot\CopilotTools;
use App\Enums\Role;
use App\Models\Deal;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Staff work in the content studio; her money, website, keys, settings, backups and love
 * pages stay hers (Role::STUDIO_PRIVATE).
 */
class StaffScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        $this->admin();
    }

    public function test_only_admin_has_the_private_studio(): void
    {
        $this->assertTrue(Role::Admin->allows(Role::STUDIO_PRIVATE));
        $this->assertFalse(Role::Staff->allows(Role::STUDIO_PRIVATE));
        $this->assertTrue(Role::Staff->allows(Role::STUDIO));
        $this->assertTrue(Role::isPrivatePath('GET', 'api/backups/studio.sqlite'));
        $this->assertTrue(Role::isPrivatePath('GET', 'api/love/letters/3'));
        $this->assertTrue(Role::isPrivatePath('PUT', 'api/brand'));
        $this->assertFalse(Role::isPrivatePath('GET', 'api/brand'));
        $this->assertFalse(Role::isPrivatePath('GET', 'api/scripts/4'));
        $this->assertFalse(Role::isPrivatePath('GET', 'api/usage/limits'));
    }

    /**
     * Every private studio route, present and future, refuses staff with a 403.
     */
    public function test_staff_get_403_on_every_private_studio_route(): void
    {
        $this->actingAs($this->staff());
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            if (!in_array('studio-private', $route->gatherMiddleware(), true)) {
                continue;
            }
            $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD');
            // A sample value that satisfies each parameter's pattern (ids, 11-char video ids, tokens…).
            $uri = preg_replace_callback('/\{(\w+)\??\}/', function ($m) use ($route) {
                $pattern = $route->wheres[$m[1]] ?? null;
                foreach (['1', 'x', 'abcdefghijk', str_repeat('a', 48)] as $sample) {
                    if (!$pattern || preg_match('/^' . $pattern . '$/', $sample)) {
                        return $sample;
                    }
                }

                return '1';
            }, $route->uri());
            if (!Role::isPrivatePath($method, $uri)) {
                continue;
            }
            $res = $this->json($method, '/' . $uri);
            $this->assertSame(403, $res->status(), "{$method} /{$uri} should refuse staff");
            $checked++;
        }
        $this->assertGreaterThan(60, $checked);
    }

    public function test_staff_keep_the_content_studio(): void
    {
        $this->actingAs($this->staff());
        $this->getJson('/api/scripts')->assertOk();
        $this->getJson('/api/ideas')->assertOk();
        $this->getJson('/api/brand')->assertOk();
        $this->getJson('/api/team')->assertOk();
    }

    public function test_staff_dashboard_and_search_hide_money(): void
    {
        Deal::create(['brand_name' => 'SecretBrand', 'rate' => 9000, 'stage' => 'negotiating']);
        $this->actingAs($this->staff());
        $d = $this->getJson('/api/dashboard')->assertOk()->json();
        foreach (['revenue', 'deals_summary', 'inbound', 'follow_ups'] as $k) {
            $this->assertArrayNotHasKey($k, $d);
        }
        $this->assertStringNotContainsString('SecretBrand', $this->getJson('/api/search?q=Secret')->getContent());
    }

    public function test_copilot_drops_money_tools_for_staff(): void
    {
        $tools = app(CopilotTools::class);
        $this->actingAs($this->admin());
        $this->assertContains('get_revenue_overview', array_column($tools->definitions('agent'), 'name'));
        $this->actingAs($this->staff());
        $names = array_column($tools->definitions('agent'), 'name');
        $this->assertNotContains('get_revenue_overview', $names);
        $this->assertNotContains('create_deal', $names);
        $this->assertContains('get_script', $names);
        $this->assertStringContainsString('Only the studio owner', $tools->read('list_brand_deals', []));
    }

    public function test_auth_status_sends_no_private_ability_to_staff(): void
    {
        $this->actingAs($this->staff());
        $abilities = $this->getJson('/api/auth/status')->assertOk()->json('user.abilities');
        $this->assertContains('studio', $abilities);
        $this->assertNotContains('studio.private', $abilities);
    }
}
