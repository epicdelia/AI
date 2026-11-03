<?php

namespace Tests\Feature;

use App\Domains\Clients\Intake;
use App\Models\Deal;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientExpansionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Mail::fake();
        $this->admin();
    }

    public function test_billing_portal_uses_the_stripe_customer_from_their_payment(): void
    {
        Setting::put('stripe_secret_key', 'sk_test');
        $client = $this->client();
        $this->actingAs($client)->postJson('/api/portal/billing/manage')->assertStatus(422);

        Deal::create(['brand_name' => 'Acme', 'rate' => 100, 'stage' => 'paid', 'user_id' => $client->id, 'paid_at' => now()])->forceFill(['stripe_customer_id' => 'cus_A'])->save();
        Http::fake(['api.stripe.com/v1/billing_portal/sessions' => Http::response(['url' => 'https://billing.stripe.test/s'])]);
        $this->postJson('/api/portal/billing/manage')->assertOk()->assertJsonPath('url', 'https://billing.stripe.test/s');
        Http::assertSent(fn ($r) => $r['customer'] === 'cus_A' && str_ends_with($r['return_url'], '/billing'));
    }

    public function test_another_clients_customer_is_never_used(): void
    {
        Setting::put('stripe_secret_key', 'sk_test');
        $other = $this->client(['email' => 'other@x.test']);
        Deal::create(['brand_name' => 'Theirs', 'rate' => 100, 'stage' => 'paid', 'user_id' => $other->id])->forceFill(['stripe_customer_id' => 'cus_OTHER'])->save();
        $this->actingAs($this->client())->postJson('/api/portal/billing/manage')->assertStatus(422);
    }

    public function test_intake_questions_are_asked_and_saved_on_the_request(): void
    {
        $service = array_key_first(\App\Http\Controllers\Api\BookingController::PROJECT_LABELS);
        Intake::save($service, [
            ['label' => 'Your website', 'type' => 'url', 'required' => true],
            ['label' => 'Size', 'type' => 'select', 'options' => ['Small', 'Large']],
        ]);
        $client = $this->client();
        $this->actingAs($client);
        $q = $this->getJson('/api/portal/requests')->assertOk()->json("intake.{$service}");
        $this->assertSame('Your website', $q[0]['label']);

        $base = ['service' => $service, 'details' => 'We need a launch video.'];
        $this->postJson('/api/portal/requests', $base)->assertStatus(422)->assertJsonValidationErrors(['intake.q1']);
        $this->postJson('/api/portal/requests', $base + ['intake' => ['q1' => 'not a link']])->assertStatus(422);
        $this->postJson('/api/portal/requests', $base + ['intake' => ['q1' => 'https://acme.test', 'q2' => 'Huge']])->assertStatus(422);
        $id = $this->postJson('/api/portal/requests', $base + ['intake' => ['q1' => 'https://acme.test', 'q2' => 'Large']])->assertCreated()->json('request.id');
        $this->assertSame('https://acme.test', Deal::find($id)->intake[0]['answer']);
        $this->getJson("/api/portal/requests/{$id}")->assertJsonPath('request.intake.1.answer', 'Large');
    }

    public function test_she_edits_intake_questions_and_clients_cannot(): void
    {
        $this->actingAs($this->client())->putJson('/api/clients/intake', ['service' => '*', 'questions' => []])->assertForbidden();
        $this->actingAs($this->admin())->putJson('/api/clients/intake', ['service' => '*', 'questions' => [['label' => 'Budget?', 'type' => 'nonsense']]])
            ->assertOk()->assertJsonPath('questions.0.type', 'text');
        $this->assertSame('Budget?', Intake::questionsFor('anything')[0]['label']);
    }

    public function test_video_deliverables_stream_inline_only_for_the_owner(): void
    {
        Storage::fake('local');
        $client = $this->client();
        $deal = Deal::create(['brand_name' => 'Acme', 'rate' => 100, 'stage' => 'scheduled', 'user_id' => $client->id]);
        Storage::disk('local')->put('deal-files/cut.mp4', 'fake-mp4');
        $file = $deal->files()->create(['name' => 'cut.mp4', 'path' => 'deal-files/cut.mp4', 'size' => 8, 'from_admin' => true]);
        $pdf = $deal->files()->create(['name' => 'brief.pdf', 'path' => 'deal-files/brief.pdf', 'size' => 8, 'from_admin' => true]);

        $this->actingAs($client);
        $files = collect($this->getJson("/api/portal/requests/{$deal->id}/files")->json('files'))->keyBy('name');
        $this->assertTrue($files['cut.mp4']['is_video']);
        $this->assertFalse($files['brief.pdf']['is_video']);
        $res = $this->get("/api/portal/requests/{$deal->id}/files/{$file->id}/stream")->assertOk();
        $this->assertSame('video/mp4', $res->headers->get('Content-Type'));
        $this->get("/api/portal/requests/{$deal->id}/files/{$pdf->id}/stream")->assertNotFound();

        $this->actingAs($this->client(['email' => 'nosy@x.test']))->get("/api/portal/requests/{$deal->id}/files/{$file->id}/stream")->assertNotFound();

        $this->actingAs($client)->postJson("/api/portal/requests/{$deal->id}/annotations", [
            'kind' => 'video', 'deliverable_index' => $file->id, 'timecode_seconds' => 12.5, 'body' => 'Cut this pause',
        ])->assertCreated()->assertJsonPath('annotation.kind', 'video')->assertJsonPath('annotation.deliverable_index', $file->id);
    }
}
