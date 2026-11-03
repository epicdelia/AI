<?php

namespace Tests\Feature;

use App\Domains\Community\Models\CommunityItem;
use App\Domains\Crm\Models\CrmContact;
use App\Domains\Team\Models\TeamTask;
use App\Models\Deal;
use App\Models\Script;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Her cross-role tools (Needs you, CRM, portal branding) and editing scripts together.
 */
class AdminExpansionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Mail::fake();
        $this->admin();
    }

    public function test_two_people_saving_the_same_script_are_merged(): void
    {
        $base = "## Intro\nHello\nWorld\n## Outro\nBye";
        $script = Script::create(['title' => 'Together', 'body' => $base]);
        $rev = $script->fresh()->revision;
        $this->assertGreaterThan(0, $rev);

        $this->actingAs($this->staff());
        $this->patchJson("/api/scripts/{$script->id}", ['body' => str_replace('Hello', 'Hello there', $base), 'base_revision' => $rev])
            ->assertOk()->assertJsonPath('merged', false);

        $this->actingAs($this->admin());
        $r = $this->patchJson("/api/scripts/{$script->id}", ['body' => str_replace('Bye', 'See you', $base), 'base_revision' => $rev])
            ->assertOk()->assertJsonPath('merged', true)->assertJsonPath('conflicts', 0)->json('script.body');
        $this->assertSame("## Intro\nHello there\nWorld\n## Outro\nSee you", $r);
    }

    public function test_live_poll_reports_presence_and_newer_text(): void
    {
        $script = Script::create(['title' => 'Live', 'body' => 'v1']);
        $rev = $script->fresh()->revision;
        $this->actingAs($this->staff())->postJson("/api/scripts/{$script->id}/live", ['revision' => $rev, 'line' => 3])->assertOk();

        $script->update(['body' => 'v2 from Copilot']);
        $this->actingAs($this->admin());
        $r = $this->postJson("/api/scripts/{$script->id}/live", ['revision' => $rev])->assertOk()->json();
        $this->assertTrue($r['changed']);
        $this->assertSame('v2 from Copilot', $r['body']);
        $this->assertSame('Alex Editor', $r['others'][0]['name']);
        $this->assertSame(3, $r['others'][0]['line']);
    }

    public function test_needs_you_gathers_every_role(): void
    {
        $client = $this->client();
        $deal = Deal::create(['brand_name' => 'Acme', 'rate' => 10, 'stage' => 'negotiating', 'user_id' => $client->id]);
        $deal->messages()->create(['user_id' => $client->id, 'from_admin' => false, 'body' => 'Any update?']);
        TeamTask::create(['title' => 'Edit', 'payout_amount' => 50, 'payout_status' => 'pending', 'completed_at' => now()]);
        CommunityItem::create(['type' => 'question', 'title' => 'How do you light videos?', 'status' => 'published', 'published_at' => now()]);
        CrmContact::create(['name' => 'Sam Sponsor', 'next_follow_up_at' => now()->subDay()]);

        $d = $this->actingAs($this->admin())->getJson('/api/needs-you')->assertOk()->json();
        $ids = array_column($d['groups'], 'id');
        foreach (['clients', 'team', 'community', 'crm'] as $g) {
            $this->assertContains($g, $ids);
        }
        $this->assertGreaterThanOrEqual(4, $d['total']);
        $this->actingAs($this->staff())->getJson('/api/needs-you')->assertForbidden();
    }

    public function test_crm_contacts_timeline_and_sync(): void
    {
        $this->actingAs($this->admin());
        $id = $this->postJson('/api/crm', ['name' => 'Jo', 'email' => 'jo@brand.test', 'tags' => 'sponsor, podcast'])->assertCreated()->json('contact.id');
        $this->postJson('/api/crm', ['name' => 'Jo again', 'email' => 'JO@brand.test'])->assertStatus(422);
        $this->postJson("/api/crm/{$id}/activities", ['kind' => 'call', 'body' => 'Intro call went well', 'next_follow_up_at' => now()->addWeek()->toDateString()])
            ->assertCreated()->assertJsonPath('contact.stage', 'contacted');
        $this->getJson("/api/crm/{$id}")->assertOk()->assertJsonPath('activities.0.kind', 'call')->assertJsonPath('contact.tags.1', 'podcast');

        $this->client(['email' => 'buyer@acme.test']);
        Deal::create(['brand_name' => 'Globex', 'contact_name' => 'Hank', 'contact_email' => 'hank@globex.test', 'rate' => 500, 'stage' => 'inquiry']);
        $this->postJson('/api/crm/sync')->assertOk()->assertJsonPath('added', 2);
        $this->postJson('/api/crm/sync')->assertOk()->assertJsonPath('added', 0);
        $this->assertSame('lead', CrmContact::where('email', 'hank@globex.test')->value('stage'));
        $this->assertStringContainsString('hank@globex.test', $this->get('/api/crm/export')->streamedContent());

        $this->actingAs($this->staff())->getJson('/api/crm')->assertForbidden();
        $this->actingAs(\App\Models\User::where('email', 'buyer@acme.test')->first())->getJson('/api/crm')->assertForbidden();
    }

    public function test_portal_branding_is_public_only_when_on_and_only_she_edits_it(): void
    {
        Storage::fake('local');
        $this->getJson('/api/branding')->assertOk()->assertExactJson(['enabled' => false]);

        $this->actingAs($this->staff())->putJson('/api/portal-brand', ['enabled' => true])->assertForbidden();
        $this->actingAs($this->staff())->postJson('/api/portal-brand/logo', [])->assertForbidden();

        $this->actingAs($this->admin());
        $this->putJson('/api/portal-brand', ['enabled' => true, 'name' => 'Unicorn Studio', 'accent' => '#123456', 'support_email' => 'help@u.test'])->assertOk();
        $this->putJson('/api/portal-brand', ['enabled' => true, 'accent' => 'red'])->assertStatus(422);
        $this->post('/api/portal-brand/logo', ['logo' => UploadedFile::fake()->image('logo.png', 64, 64)], ['Accept' => 'application/json'])->assertOk();
        $this->post('/api/portal-brand/logo', ['logo' => UploadedFile::fake()->create('x.svg', 1, 'image/svg+xml')], ['Accept' => 'application/json'])->assertStatus(422);

        auth()->logout();
        $b = $this->getJson('/api/branding')->assertOk()->json();
        $this->assertSame('Unicorn Studio', $b['name']);
        $this->assertSame('#123456', $b['accent']);
        $this->assertNotNull($b['logo_url']);
        $this->get('/api/branding/logo')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame('Unicorn Studio', \App\Http\Controllers\Api\PortalBrandController::fromName());
    }
}
