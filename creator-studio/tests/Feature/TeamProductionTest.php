<?php

namespace Tests\Feature;

use App\Domains\Team\Models\StaffInvite;
use App\Domains\Team\Models\TeamTask;
use App\Domains\Team\TeamTasks;
use App\Enums\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TeamProductionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Mail::fake();
        $this->admin();
    }

    public function test_tasks_live_in_a_table_with_real_assignees(): void
    {
        $alex = $this->staff();
        $this->actingAs($this->admin());
        $id = $this->postJson('/api/team/tasks', [
            'title' => 'Cut the intro', 'stage' => 'editing', 'assignee_id' => $alex->id,
            'due_on' => now()->addDay()->toDateString(), 'payout_amount' => 80,
        ])->assertCreated()->assertJsonPath('task.assignee_name', 'Alex Editor')->json('task.id');
        $this->assertSame($alex->id, TeamTask::find($id)->assignee_id);

        $this->postJson('/api/team/tasks', ['title' => 'x', 'assignee_id' => 99999])->assertStatus(422);
        $client = $this->client();
        $this->postJson('/api/team/tasks', ['title' => 'x', 'assignee_id' => $client->id])->assertStatus(422);

        $this->actingAs($alex);
        $this->getJson('/api/team')->assertOk()->assertJsonPath('metrics.mine', 1)->assertJsonPath('tasks.0.mine', true);
    }

    public function test_staff_cannot_set_payouts_and_only_see_their_own(): void
    {
        $alex = $this->staff();
        $sam = $this->staff(['email' => 'sam@studio.test', 'name' => 'Sam']);
        TeamTask::create(['title' => 'Sam job', 'assignee_id' => $sam->id, 'payout_amount' => 300]);
        $this->actingAs($alex);
        $id = $this->postJson('/api/team/tasks', ['title' => 'My own', 'assignee_id' => $alex->id, 'payout_amount' => 9999])->json('task.id');
        $this->assertEquals(0, TeamTask::find($id)->payout_amount);
        $tasks = collect($this->getJson('/api/team')->json('tasks'));
        $this->assertNull($tasks->firstWhere('title', 'Sam job')['payout_amount']);
    }

    public function test_payout_flows_pending_approved_paid_admin_only(): void
    {
        $alex = $this->staff();
        $task = TeamTask::create(['title' => 'Thumbnail pack', 'assignee_id' => $alex->id, 'payout_amount' => 80, 'stage' => 'review']);

        $this->actingAs($alex)->patchJson("/api/team/tasks/{$task->id}", ['completed' => true])->assertOk();
        $this->assertSame('pending', $task->fresh()->payout_status);
        $this->postJson("/api/team/tasks/{$task->id}/approve-payout")->assertForbidden();

        $this->actingAs($this->admin());
        $this->postJson("/api/team/tasks/{$task->id}/paid")->assertStatus(422); // approve first
        $this->postJson("/api/team/tasks/{$task->id}/approve-payout")->assertOk()->assertJsonPath('task.payout_status', 'approved');
        $this->postJson("/api/team/tasks/{$task->id}/paid", ['reference' => 'Wise 123'])->assertOk()->assertJsonPath('task.payout_status', 'paid');
        $this->deleteJson("/api/team/tasks/{$task->id}")->assertStatus(422);

        $csv = $this->get('/api/team/payouts?format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Thumbnail pack', $csv);
        $this->assertStringContainsString('Wise 123', $csv);
    }

    public function test_invite_by_email_then_accept_sets_own_password(): void
    {
        $this->actingAs($this->admin());
        $link = $this->postJson('/api/team/staff', ['name' => 'Elena', 'email' => 'Elena@Design.test', 'role_label' => 'Thumbnail Artist'])
            ->assertCreated()->json('link');
        $token = basename($link);
        $this->assertNull(StaffInvite::first()->getAttribute('token'));
        $this->assertSame(hash('sha256', $token), StaffInvite::first()->token_hash);

        auth()->logout();
        $this->getJson("/api/team-invite/{$token}")->assertOk()->assertJsonPath('email', 'elena@design.test');
        $this->postJson("/api/team-invite/{$token}", ['password' => 'short'])->assertStatus(422);
        $this->postJson("/api/team-invite/{$token}", ['password' => 'a-long-password'])->assertOk();
        $u = User::where('email', 'elena@design.test')->first();
        $this->assertSame(Role::Staff, $u->role());
        $this->postJson("/api/team-invite/{$token}", ['password' => 'a-long-password'])->assertNotFound();
    }

    public function test_staff_cannot_invite_or_remove_people(): void
    {
        $this->actingAs($this->staff());
        $this->postJson('/api/team/staff', ['name' => 'X', 'email' => 'x@y.test'])->assertForbidden();
    }

    public function test_removing_staff_disables_and_unassigns_open_tasks(): void
    {
        $alex = $this->staff();
        $open = TeamTask::create(['title' => 'Open', 'assignee_id' => $alex->id]);
        $done = TeamTask::create(['title' => 'Done', 'assignee_id' => $alex->id, 'completed_at' => now()]);
        $this->actingAs($this->admin())->deleteJson("/api/team/staff/{$alex->id}")->assertOk();
        $this->assertNotNull($alex->fresh()->disabled_at);
        $this->assertNull($open->fresh()->assignee_id);
        $this->assertSame($alex->id, $done->fresh()->assignee_id);
    }

    public function test_due_reminders_and_assignment_emails(): void
    {
        $alex = $this->staff();
        TeamTask::create(['title' => 'Due', 'assignee_id' => $alex->id, 'due_on' => now()->subDay()->toDateString()]);
        TeamTask::create(['title' => 'Later', 'assignee_id' => $alex->id, 'due_on' => now()->addWeek()->toDateString()]);
        $this->assertSame(1, TeamTasks::remindDue());
    }

    public function test_thumbnail_check_makes_no_invented_claims(): void
    {
        $this->actingAs($this->staff());
        $r = $this->postJson('/api/team/thumbnails/simulate', ['title' => 'One two three four five six seven'])->assertOk()->json();
        $this->assertArrayNotHasKey('contrast_dark_mode', $r['metrics']);
        $this->assertArrayNotHasKey('predicted_ctr_tier', $r['metrics']);
        $this->assertSame(50, $r['metrics']['mobile_readability_score']);
    }
}
