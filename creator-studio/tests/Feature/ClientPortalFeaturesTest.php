<?php

namespace Tests\Feature;

use App\Domains\Backup\Backup;
use App\Domains\Clients\Models\TeamInvite;
use App\Domains\Security\Audit;
use App\Jobs\RunAiTask;
use App\Models\Deal;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientPortalFeaturesTest extends TestCase
{
    use RefreshDatabase;

    // Uses $this->admin() and $this->client() from TestCase

    public function test_https_and_hsts_enforcement(): void
    {
        config([
            'app.force_https' => true,
        ]);

        // Non-secure request redirects
        $res = $this->get('http://localhost/login');
        $res->assertRedirect();
        $this->assertStringStartsWith('https://', $res->headers->get('Location'));

        // Health check /up is not redirected
        $this->get('http://localhost/up')->assertOk();

        // Secure response has HSTS and nosniff
        $resSecure = $this->get('https://localhost/login');
        $this->assertSame('nosniff', $resSecure->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('max-age=', (string)$resSecure->headers->get('Strict-Transport-Security'));
    }

    public function test_bot_protection_blocks_honeypot_and_too_fast(): void
    {
        // Honeypot filled -> blocked with friendly 202
        $resTrap = $this->postJson('/api/auth/register', [
            'name' => 'Bot',
            'email' => 'bot@spammer.test',
            'password' => 'Pass12345678',
            'website' => 'https://spam.test',
            '_t' => 5000,
        ]);
        $resTrap->assertStatus(202);

        // Submitted too fast (< 1200 ms) -> blocked with friendly 202
        $resFast = $this->postJson('/api/auth/register', [
            'name' => 'FastBot',
            'email' => 'fast@spammer.test',
            'password' => 'Pass12345678',
            '_t' => 200,
        ]);
        $resFast->assertStatus(202);
    }

    public function test_audit_logs_and_sessions_management(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Audit::log('keys_changed', $admin, null, [
            'test' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'keys_changed',
            'user_id' => $admin->id,
        ]);

        $res = $this->getJson('/api/security/audit')->assertOk();
        $this->assertNotEmpty($res->json('events'));

        // Account sessions endpoint
        $sRes = $this->getJson('/api/account/sessions')->assertOk();
        $this->assertArrayHasKey('sessions', $sRes->json());
        $this->assertArrayHasKey('events', $sRes->json());

        // End other sessions requires current password
        $this->deleteJson('/api/account/sessions', [
            'current_password' => 'wrong',
        ])->assertStatus(422);
        $this->deleteJson('/api/account/sessions', [
            'current_password' => 'secret123',
        ])->assertOk();
    }

    public function test_fresh_password_middleware_and_confirm(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // Before confirming, fresh is false
        $this->getJson('/api/auth/password-fresh')->assertOk()->assertJson([
            'fresh' => false,
        ]);

        // Wrong password
        $this->postJson('/api/auth/confirm-password', [
            'password' => 'wrong',
        ])->assertStatus(422);

        // Correct password confirms
        $this->postJson('/api/auth/confirm-password', [
            'password' => 'secret123',
        ])->assertOk();
        $this->getJson('/api/auth/password-fresh')->assertOk()->assertJson([
            'fresh' => true,
        ]);
    }

    public function test_encrypted_offsite_backups_and_decryption(): void
    {
        Storage::fake('sftp');
        config([
            'services.backup.disk' => 'sftp',
        ]);
        config([
            'services.backup.passphrase' => 'test-encryption-key-1234',
        ]);

        $plain = "SQLite format 3\x00 test database content";
        $encrypted = Backup::encrypt($plain);
        $this->assertNotSame($plain, $encrypted);

        $decrypted = Backup::decrypt($encrypted);
        $this->assertSame($plain, $decrypted);
    }

    public function test_deal_files_upload_download_and_permissions(): void
    {
        Storage::fake('local');
        $bob = $this->client([
            'email' => 'bob@brand.test',
        ]);
        $deal = Deal::create([
            'brand_name' => 'Brand Co',
            'contact_email' => 'bob@brand.test',
            'stage' => 'inquiry',
            'user_id' => $bob->id,
        ]);

        $this->actingAs($bob);

        // Unsupported extension rejected
        $badFile = UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload');
        $this->postJson("/api/portal/requests/{$deal->id}/files", [
            'file' => $badFile,
        ])->assertStatus(422);

        // Valid file upload
        $pdf = UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf');
        $res = $this->postJson("/api/portal/requests/{$deal->id}/files", [
            'file' => $pdf,
        ])->assertCreated();
        $fileId = $res->json('file.id');
        $this->assertDatabaseHas('deal_files', [
            'id' => $fileId,
            'deal_id' => $deal->id,
            'name' => 'brief.pdf',
        ]);

        // List files
        $this->getJson("/api/portal/requests/{$deal->id}/files")->assertOk()->assertJsonCount(1, 'files');

        // Download file
        $down = $this->get("/api/portal/requests/{$deal->id}/files/{$fileId}")->assertOk();
        $this->assertSame('nosniff', $down->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('attachment', (string)$down->headers->get('Content-Disposition'));

        // Client can delete their own file
        $this->deleteJson("/api/portal/requests/{$deal->id}/files/{$fileId}")->assertOk();
        $this->assertDatabaseMissing('deal_files', [
            'id' => $fileId,
        ]);
    }

    public function test_deliverable_approval_and_changes_workflow(): void
    {
        $bob = $this->client([
            'email' => 'bob@brand.test',
        ]);
        $deal = Deal::create([
            'brand_name' => 'Brand Co',
            'contact_email' => 'bob@brand.test',
            'stage' => 'in_review',
            'user_id' => $bob->id,
            'deliverables' => [[
                'platform' => 'request',
                'format' => 'Landing Page',
            ]],
        ]);

        $this->actingAs($bob);

        // Request changes moves deal to production
        $this->postJson("/api/portal/requests/{$deal->id}/changes", [
            'note' => 'Please use darker blue.',
        ])->assertOk();
        $this->assertSame('production', $deal->fresh()->stage);
        $this->assertDatabaseHas('deal_events', [
            'deal_id' => $deal->id,
            'kind' => 'changes',
        ]);

        // Set back to in_review
        $deal->refresh()->update([
            'stage' => 'in_review',
        ]);

        // Approve moves deal to scheduled
        $this->postJson("/api/portal/requests/{$deal->id}/approve")->assertOk();
        $this->assertSame('scheduled', $deal->fresh()->stage);
        $this->assertNotNull($deal->fresh()->approved_at);
        $this->assertDatabaseHas('deal_events', [
            'deal_id' => $deal->id,
            'kind' => 'approved',
        ]);
    }

    public function test_booking_call_from_dashboard(): void
    {
        $bob = $this->client([
            'email' => 'bob@brand.test',
        ]);
        $this->actingAs($bob);

        $slots = $this->getJson('/api/portal/calls/slots')->assertOk()->json();
        $this->assertArrayHasKey('days', $slots);

        if (!empty($slots['days']) && !empty($slots['days'][0]['slots'])) {
            $slot = $slots['days'][0]['slots'][0];
            $res = $this->postJson('/api/portal/calls', [
                'slot' => $slot,
                'topic' => 'Project Kickoff',
            ]);
            $res->assertCreated();
            $dealId = $res->json('request_id');
            $this->assertDatabaseHas('deals', [
                'id' => $dealId,
                'user_id' => $bob->id,
            ]);
        }
    }

    public function test_receipts_and_invoices_generation(): void
    {
        $bob = $this->client([
            'email' => 'bob@brand.test',
            'company' => 'Brand Co',
        ]);
        $deal = Deal::create([
            'brand_name' => 'Brand Co',
            'contact_email' => 'bob@brand.test',
            'stage' => 'invoiced',
            'rate' => 3500,
            'currency' => 'USD',
            'user_id' => $bob->id,
            'deliverables' => [[
                'platform' => 'stripe',
                'format' => 'Advisory Retainer',
            ]],
        ]);

        $payment = Payment::create([
            'deal_id' => $deal->id,
            'amount' => 3500,
            'currency' => 'USD',
            'kind' => 'first',
            'paid_at' => now(),
        ]);

        $this->actingAs($bob);

        // Invoice
        $inv = $this->getJson("/api/portal/requests/{$deal->id}/invoice")->assertOk();
        $this->assertStringContainsString('Invoice', $inv->json('title'));
        $this->assertStringContainsString('Brand Co', $inv->json('markdown'));

        // Receipt
        $rec = $this->getJson("/api/portal/payments/{$payment->id}/receipt")->assertOk();
        $this->assertStringContainsString('Receipt', $rec->json('title'));
        $this->assertStringContainsString('3,500.00', $rec->json('markdown'));
    }

    public function test_review_request_created_on_paid(): void
    {
        $bob = $this->client([
            'email' => 'bob@brand.test',
        ]);
        $deal = Deal::create([
            'brand_name' => 'Brand Co',
            'contact_name' => 'Bob',
            'contact_email' => 'bob@brand.test',
            'stage' => 'contracted',
            'rate' => 2000,
            'user_id' => $bob->id,
        ]);

        $deal->update([
            'stage' => 'paid',
            'payment_status' => 'paid',
        ]);

        $testimonial = Testimonial::where('deal_id', $deal->id)->first();
        $this->assertNotNull($testimonial);
        $this->assertNotEmpty($testimonial->token);
    }

    public function test_unread_badges_for_client_and_admin(): void
    {
        $admin = $this->admin();
        $bob = $this->client([
            'email' => 'bob@brand.test',
        ]);
        $deal = Deal::create([
            'brand_name' => 'Brand Co',
            'contact_email' => 'bob@brand.test',
            'stage' => 'production',
            'user_id' => $bob->id,
        ]);

        // Admin sends message -> bob unread increases
        $deal->messages()->create([
            'user_id' => $admin->id,
            'from_admin' => true,
            'body' => 'Hello Bob!',
        ]);
        $this->actingAs($bob);
        $this->getJson('/api/portal/unread')->assertOk()->assertJson([
            'unread' => 1,
        ]);

        // Client sends message -> admin unread increases
        $deal->messages()->create([
            'user_id' => $bob->id,
            'from_admin' => false,
            'body' => 'Thanks!',
        ]);
        $this->actingAs($admin);
        $this->getJson('/api/clients/unread')->assertOk()->assertJson([
            'unread' => 1,
        ]);
    }

    public function test_company_teams_invite_and_join(): void
    {
        $owner = $this->client([
            'email' => 'owner@brand.test',
        ]);
        $colleague = $this->client([
            'email' => 'colleague@brand.test',
        ]);

        $this->actingAs($owner);

        // Invite colleague
        $invRes = $this->postJson('/api/portal/team', [
            'email' => 'colleague@brand.test',
        ])->assertCreated();
        $invite = TeamInvite::findOrFail($invRes->json('id'));

        // Colleague accepts invite
        $this->actingAs($colleague);
        $this->postJson('/api/portal/team/join', [
            'token' => $invite->token,
        ])->assertOk();
        $this->assertSame($owner->id, $colleague->fresh()->team_owner_id);

        // Colleague shares owner's requests
        $deal = Deal::create([
            'brand_name' => 'Brand Co',
            'contact_email' => 'owner@brand.test',
            'stage' => 'inquiry',
            'user_id' => $owner->id,
        ]);
        $this->getJson("/api/portal/requests/{$deal->id}")->assertOk();
    }

    public function test_automations_pause_and_budget_stats(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Setting::put('schedule_enabled', '0');
        Setting::put('automation_daily_tokens', '150000');

        $res = $this->getJson('/api/automations')->assertOk();
        $this->assertTrue($res->json('paused'));
        $this->assertSame(150000, $res->json('cap'));
    }

    public function test_background_ai_tasks_dispatch(): void
    {
        \Illuminate\Support\Facades\Queue::fake([RunAiTask::class]);

        $admin = $this->admin();
        $this->actingAs($admin);

        $res = $this->postJson('/api/tasks', [
            'kind' => 'ideas.generate',
            'payload' => [
                'extra' => 'test ideas',
            ],
        ])->assertStatus(202);

        $taskId = $res->json('id');
        $this->assertNotEmpty($taskId);
        $this->assertDatabaseHas('ai_tasks', [
            'id' => $taskId,
            'kind' => 'ideas.generate',
        ]);
        \Illuminate\Support\Facades\Queue::assertPushed(RunAiTask::class, fn ($j) => $j->taskId === $taskId);

        $poll = $this->getJson("/api/tasks/{$taskId}")->assertOk();
        $this->assertSame($taskId, $poll->json('id'));
    }

    public function test_revoking_share_links(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $deal = Deal::create([
            'brand_name' => 'Brand Co',
            'stage' => 'pitching',
            'room_token' => 'test-room-token-12345678901234567890',
            'kit_token' => 'test-kit-token-12345678901234567890',
        ]);

        $this->deleteJson("/api/deals/{$deal->id}/share-links")->assertOk();
        $this->assertNull($deal->fresh()->room_token);
        $this->assertNull($deal->fresh()->kit_token);
    }
}
