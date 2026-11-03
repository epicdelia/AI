<?php

namespace Tests\Feature;

use App\Domains\Team\Models\TeamTask;
use App\Models\Script;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeamRoundThreeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Mail::fake();
        $this->admin();
    }

    public function test_brief_template_fills_new_tasks_for_that_role(): void
    {
        $this->actingAs($this->admin());
        $this->putJson('/api/team/brief-templates', ['role' => 'Video Editor', 'template' => "Cut: {title}\nScript: {script_title} {script_link}\nDue {due}\n{unknown}"])->assertOk();
        $script = Script::create(['title' => 'Agents explained', 'body' => "## Intro\nHi"]);
        $id = $this->postJson('/api/team/tasks', ['title' => 'Short #1', 'role_label' => 'Video Editor', 'script_id' => $script->id, 'due_on' => '2026-12-01'])->json('task.id');
        $brief = TeamTask::find($id)->brief;
        $this->assertStringContainsString('Cut: Short #1', $brief);
        $this->assertStringContainsString('Script: Agents explained', $brief);
        $this->assertStringContainsString('{unknown}', $brief);

        $this->actingAs($this->staff())->putJson('/api/team/brief-templates', ['role' => 'Video Editor', 'template' => 'x'])->assertForbidden();
    }

    public function test_timer_and_hourly_payout(): void
    {
        $alex = $this->staff();
        $this->actingAs($this->admin())->putJson("/api/team/rates/{$alex->id}", ['hourly_rate' => 40])->assertOk();
        $task = TeamTask::create(['title' => 'Edit', 'assignee_id' => $alex->id, 'hourly' => true, 'stage' => 'editing']);

        $this->actingAs($alex);
        $this->postJson("/api/team/tasks/{$task->id}/timer")->assertOk()->assertJsonPath('minutes_logged', 0);
        $task->forceFill(['timer_started_at' => now()->subMinutes(30)])->save();
        $this->postJson("/api/team/tasks/{$task->id}/timer")->assertOk()->assertJsonPath('minutes_logged', 30);
        $this->postJson("/api/team/tasks/{$task->id}/time", ['minutes' => 60, 'note' => 'Color grade'])->assertOk()
            ->assertJsonPath('minutes_logged', 90)->assertJsonPath('estimate', 60);

        $this->patchJson("/api/team/tasks/{$task->id}", ['completed' => true])->assertOk();
        $task->refresh();
        $this->assertEquals(60.0, (float)$task->payout_amount);
        $this->assertSame('pending', $task->payout_status);

        $other = $this->staff(['email' => 'sam@studio.test', 'name' => 'Sam']);
        $this->actingAs($other)->postJson("/api/team/tasks/{$task->id}/time", ['minutes' => 5])->assertForbidden();
        $this->actingAs($other)->getJson('/api/team/rates')->assertForbidden();
        $this->actingAs($alex)->patchJson("/api/team/tasks/{$task->id}", ['hourly' => false])->assertOk();
        $this->assertTrue($task->fresh()->hourly); // staff can't switch billing
    }

    public function test_upload_review_notes_and_send_back(): void
    {
        Storage::fake('local');
        $alex = $this->staff();
        $task = TeamTask::create(['title' => 'Edit', 'assignee_id' => $alex->id, 'stage' => 'editing']);
        $this->actingAs($alex);
        $fid = $this->post("/api/team/tasks/{$task->id}/files", ['file' => UploadedFile::fake()->create('cut.mp4', 10, 'video/mp4')], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $this->assertSame('review', $task->fresh()->stage);
        $this->getJson("/api/team/tasks/{$task->id}/files")->assertJsonPath('files.0.is_video', true);

        $this->actingAs($this->admin());
        $this->postJson("/api/team/tasks/{$task->id}/send-back")->assertStatus(422);
        $this->postJson("/api/team/tasks/{$task->id}/notes", ['deliverable_index' => $fid, 'timecode_seconds' => 12.5, 'body' => 'Tighten this cut'])
            ->assertCreated()->assertJsonPath('annotation.deliverable_index', $fid)->assertJsonPath('annotation.kind', 'video');
        $this->postJson("/api/team/tasks/{$task->id}/send-back")->assertOk()->assertJsonPath('notes', 1);
        $this->assertSame('editing', $task->fresh()->stage);

        $csv = $this->get('/api/team/payouts?format=csv')->streamedContent();
        $this->assertStringContainsString('Hours', $csv);
        $this->actingAs($this->client())->getJson("/api/team/tasks/{$task->id}/notes")->assertForbidden();
    }
}
