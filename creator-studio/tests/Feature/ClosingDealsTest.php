<?php

namespace Tests\Feature;

use App\Domains\Copilot\CopilotTools;
use App\Domains\Deals\FollowUps;
use App\Domains\Deals\MorningPipeline;
use App\Domains\Deals\PipelineInsights;
use App\Domains\Search\WebSearch;
use App\Domains\Workflows\Catalog;
use App\Domains\Workflows\Engine;
use App\Domains\Workflows\Models\Workflow;
use App\Models\Deal;
use App\Models\Setting;
use App\Services\Llm;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Closing deals: the client deal page (proposal, accept, pay), call prep, lost deals and
 * insights, business workflow triggers, the morning pipeline briefing, and ideas that follow
 * the money.
 */
class ClosingDealsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Setting::put('timezone', 'UTC');
        Setting::put('creator_name', 'Delia Lazarescu');
        $this->travelTo(CarbonImmutable::parse('2026-11-16 07:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        WebSearch::$fake = null;
        parent::tearDown();
    }

    public function test_the_client_page_shows_the_proposal_records_opens_and_acceptance(): void
    {
        $this->signIn();
        $deal = $this->proposalDeal([
            'last_contacted_at' => now(),
        ]);
        $url = $this->postJson("/api/deals/{$deal->id}/room-link")->assertOk()->assertJson([
            'has_proposal' => true,
        ])->json('url');
        $token = basename($url);
        $this->assertArrayNotHasKey('room_token', $deal->fresh()->toArray());

        auth()->logout();
        $this->travel(1)->hours();
        $page = $this->getJson("/api/room/{$token}")->assertOk()->json();
        $this->assertSame('MVP for Acme', $page['proposal']['title']);
        $this->assertStringContainsString('## Investment', $page['markdown']);
        $this->assertFalse($page['can_pay']);
        $this->assertSame('proposal_opened', collect(FollowUps::list())->firstWhere('deal_id', $deal->id)['kind']);
        $this->assertDatabaseHas('agent_events', [
            'message' => 'Acme opened your proposal.',
        ]);

        $this->postJson("/api/room/{$token}/accept", [
            'name' => 'Ana Ruiz',
        ])->assertStatus(422); // must agree
        $this->postJson("/api/room/{$token}/accept", [
            'name' => 'Ana Ruiz',
            'agree' => true,
        ])->assertOk()->assertJsonPath('accepted_by', 'Ana Ruiz');
        $deal->refresh();
        $this->assertSame('contracted', $deal->stage);
        $this->assertNotNull($deal->accepted_at);
        $this->postJson("/api/room/{$token}/accept", [
            'name' => 'Someone Else',
            'agree' => true,
        ])->assertJsonPath('accepted_by', 'Ana Ruiz'); // once
        $this->assertNull(collect(FollowUps::list())->firstWhere('deal_id', $deal->id));

        $this->postJson("/api/room/{$token}/pay")->assertStatus(409); // no Stripe: an invoice follows
        Setting::put('stripe_secret_key', 'sk_test');
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'cs_r',
                'url' => 'https://checkout.stripe.com/c/pay/cs_r',
            ]),
        ]);
        $this->postJson("/api/room/{$token}/pay")->assertOk()->assertJsonPath('checkout_url', 'https://checkout.stripe.com/c/pay/cs_r');
        Http::assertSent(fn ($r) => $r['line_items'][0]['price_data']['unit_amount'] === 1200000 && str_contains($r['success_url'], "/deal/{$token}"));
        $this->getJson('/api/room/' . str_repeat('a', 32))->assertNotFound();
    }

    public function test_call_prep_uses_web_research_and_suggests_a_real_package(): void
    {
        $this->signIn();
        Setting::put('llm_api_key', 'test-key');
        $searched = null;
        WebSearch::$fake = function (string $q) use (&$searched) {
            $searched = $q;

            return [
                'results' => [[
                    'title' => 'Acme raises seed',
                    'link' => 'https://news.dev/acme',
                    'snippet' => 'Acme raised $3M',
                    'date' => null,
                ]],
            ];
        };
        $prompt = null;
        Llm::$fake = function (string $system, string $user) use (&$prompt) {
            $prompt = $user;

            return json_encode([
                'summary' => 'Seed-stage startup.',
                'about_them' => 'Raised $3M.',
                'likely_needs' => ['MVP'],
                'questions' => ['Q1', 'Q2'],
                'objections' => [[
                    'objection' => 'Price',
                    'answer' => 'Phase it.',
                ]],
                'package' => [
                    'plan' => 'nonsense',
                    'why' => 'x',
                ],
                'red_flags' => [],
                'next_step' => 'Send proposal',
            ]);
        };
        $deal = Deal::create([
            'brand_name' => 'Acme',
            'contact_email' => 'ana@acme.dev',
            'stage' => 'inquiry',
            'rate' => 12000,
            'source' => 'youtube',
            'deliverables' => [[
                'project_type' => 'mvp_build',
            ]],
            'meeting_at' => now()->addHours(5),
        ]);

        $prep = $this->postJson("/api/deals/{$deal->id}/prep")->assertOk()->json('prep');
        $this->assertStringContainsString('Acme', $searched);
        $this->assertStringContainsString('acme.dev', $searched);
        $this->assertStringContainsString('Acme raises seed', $prompt);
        $this->assertTrue($prep['researched']);
        $this->assertSame('https://news.dev/acme', $prep['sources'][0]['url']);
        $this->assertSame('mvp', $prep['package']['plan']); // the model's made-up key is replaced by a real package
        $this->assertGreaterThan(0, $prep['package']['price']);
        $this->assertNotNull($deal->fresh()->prep_at);
    }

    public function test_lost_deals_feed_win_rate_and_insights(): void
    {
        $this->signIn();
        $make = fn (string $stage, float $rate, ?string $source, array $x = []) => Deal::create([
            'brand_name' => "D{$rate}",
            'stage' => $stage,
            'rate' => $rate,
            'source' => $source,
        ] + $x);
        $make('paid', 5000, 'youtube', [
            'payment_status' => 'paid',
            'paid_at' => now()->addDays(10),
        ]);
        $make('paid', 3000, null, [
            'payment_status' => 'paid',
            'paid_at' => now()->addDays(20),
        ]);
        $lost = $make('negotiating', 8000, 'youtube');
        $make('inquiry', 1000, 'search');

        $this->postJson("/api/deals/{$lost->id}/lost", [
            'reason' => 'nope',
        ])->assertStatus(422);
        $this->postJson("/api/deals/{$lost->id}/lost", [
            'reason' => 'price',
            'note' => 'Budget cut',
        ])->assertOk();
        $this->assertSame(['lost', 'price'], [$lost->fresh()->stage, $lost->fresh()->lost_reason]);
        $this->assertSame(1000.0, (float)$this->getJson('/api/deals')->json('stats.total_pipeline')); // lost isn't pipeline

        $r = $this->getJson('/api/deals/insights')->assertOk()->json();
        $this->assertTrue($r['enough']);
        $this->assertSame(67, $r['win_rate']);
        $this->assertSame(4000, (int)$r['median_deal']);
        $this->assertSame(15, $r['median_days_to_close']);
        $this->assertSame('price', $r['lost_reasons'][0]['reason']);
        $yt = collect($r['by_source'])->firstWhere('key', 'youtube');
        $this->assertSame([2, 1, 1], [$yt['deals'], $yt['won'], $yt['lost']]);
        $this->assertSame(4, PipelineInsights::summary(30)['closed'] + PipelineInsights::summary(30)['open']);
    }

    public function test_business_events_start_workflows(): void
    {
        $this->signIn();
        $nodes = [
            [
                'key' => 't',
                'type' => 'new_lead',
                'config' => [
                    'min_fit' => 40,
                ],
                'enabled' => true,
            ],
            [
                'key' => 'n',
                'type' => 'notify',
                'config' => [
                    'message' => 'Lead {deal} ({contact}) fit {fit} from {source}, {amount}',
                ],
                'enabled' => true,
            ],
            [
                'key' => 'm',
                'type' => 'move_deal',
                'config' => [
                    'stage' => 'pitching',
                ],
                'enabled' => true,
            ],
        ];
        $this->assertSame([], Engine::problems($nodes));
        $this->assertNotEmpty(Engine::problems([[
            'key' => 't',
            'type' => 'any_comment',
            'config' => [],
            'enabled' => true,
        ], $nodes[2]])); // needs a deal trigger
        Workflow::create([
            'name' => 'Leads',
            'active' => true,
            'nodes' => $nodes,
        ]);
        Workflow::create([
            'name' => 'Paid',
            'active' => true,
            'nodes' => [[
                'key' => 't',
                'type' => 'payment_received',
                'config' => [],
                'enabled' => true,
            ], [
                'key' => 'n',
                'type' => 'notify',
                'config' => [
                    'message' => 'Paid: {deal} {amount}',
                ],
                'enabled' => true,
            ]],
        ]);

        $this->postJson('/api/booking', [
            'name' => 'Kai',
            'email' => 'kai@startup.io',
            'company' => 'Startup',
            'project_type' => 'mvp_build',
            'preferred_date' => '2026-11-20',
            'preferred_time' => '15:00',
            'notes' => 'We closed our seed round and need an AI onboarding assistant live by March, budget around $15k. Designs ready for the main flows already.',
        ])->assertOk();
        Deal::create([
            'brand_name' => 'Weak',
            'contact_email' => 'w@gmail.com',
            'stage' => 'inquiry',
            'source' => 'direct',
            'deliverables' => [[
                'project_type' => 'other',
            ]],
        ]); // fit too low
        app(Engine::class)->drain();

        $deal = Deal::where('brand_name', 'Startup')->firstOrFail();
        $this->assertSame('pitching', $deal->stage);
        $this->assertDatabaseHas('agent_events', [
            'type' => 'workflow',
            'message' => "Leads: Lead Startup (Kai) fit {$deal->lead_score['score']} from Direct, $12,000",
        ]);
        $this->assertSame(1, \App\Domains\Workflows\Models\WorkflowRun::whereHas('workflow', fn ($q) => $q->where('name', 'Leads'))->count());

        $deal->update([
            'stage' => 'paid',
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
        $deal->update([
            'notes' => 'again',
        ]); // no second payment event
        app(Engine::class)->drain();
        $this->assertSame(1, \App\Domains\Agents\Models\AgentEvent::where('message', 'Paid: Paid: Startup $12,000')->count()); // "<workflow>: <message>"

        foreach (['new_lead', 'deal_stage', 'payment_received', 'deal_opened', 'testimonial_received', 'new_subscriber', 'prep_call', 'move_deal'] as $type) {
            $this->assertNotNull(Catalog::get($type));
        }
        $this->assertContains('lead_alert', array_column(Catalog::templates(), 'id'));
    }

    public function test_morning_pipeline_preps_todays_calls_and_briefs_once(): void
    {
        Setting::put('llm_api_key', 'test-key');
        Llm::$fake = fn () => json_encode([
            'summary' => 'S',
            'questions' => ['Q'],
            'package' => [
                'plan' => 'strategy',
                'why' => 'w',
            ],
        ]);
        Deal::create([
            'brand_name' => 'Call Co',
            'stage' => 'inquiry',
            'rate' => 500,
            'meeting_at' => now()->addHours(3),
        ]);
        Deal::create([
            'brand_name' => 'Next Week',
            'stage' => 'inquiry',
            'rate' => 500,
            'meeting_at' => now()->addDays(6),
        ]);
        Deal::create([
            'brand_name' => 'Quiet',
            'stage' => 'inquiry',
            'rate' => 900,
            'source' => 'direct',
        ])->forceFill([
            'created_at' => now()->subDays(4),
            'stage_changed_at' => now()->subDays(4),
        ])->saveQuietly();

        $r = app(MorningPipeline::class)->run();
        $this->assertSame([
            'prepped' => 1,
            'calls' => 1,
            'follow_ups' => 1,
        ], array_intersect_key($r, array_flip(['prepped', 'calls', 'follow_ups'])));
        $this->assertStringContainsString('Call Co at 10:00', $r['message']);
        $this->assertStringContainsString('Quiet', $r['message']);
        $this->assertNotNull(Deal::where('brand_name', 'Call Co')->first()->prep);
        $this->assertNull(Deal::where('brand_name', 'Next Week')->first()->prep);
        $this->assertNull(app(MorningPipeline::class)->run()['message']); // once a day
        $this->artisan('studio:pipeline')->assertSuccessful();
    }

    public function test_ideas_follow_the_money_and_copilot_reads_insights(): void
    {
        $this->signIn();
        Setting::put('llm_api_key', 'test-key');
        $prompt = '';
        Llm::$fake = function (string $system, string $user) use (&$prompt) {
            $prompt .= $user;

            return json_encode([
                'ideas' => [],
            ]);
        };
        Deal::create([
            'brand_name' => 'Lead',
            'stage' => 'inquiry',
            'rate' => 3000,
            'source' => 'youtube',
            'attribution' => [
                'item' => 'yt:abcdefghijk',
            ],
            'notes' => 'Intro call requested via website. Notes from client: We need help adding RAG search to our support docs before the Q1 launch.',
        ]);
        $this->postJson('/api/ideas/generate');
        $this->assertStringContainsString('brought website leads', $prompt);
        $this->assertStringContainsString('RAG search to our support docs', $prompt);

        $tools = app(CopilotTools::class);
        $this->assertContains('get_pipeline_insights', array_column($tools->definitions('ask'), 'name'));
        $this->assertArrayHasKey('win_rate', json_decode($tools->read('get_pipeline_insights', []), true));
    }

    private function signIn(): void
    {
        $this->actingAs($this->admin([
            'email' => 'delia@example.com',
            'name' => 'Delia',
            'password' => 'secret123',
        ]));
    }

    private function proposalDeal(array $extra = []): Deal
    {
        return Deal::create($extra + [
            'brand_name' => 'Acme',
            'contact_name' => 'Ana',
            'contact_email' => 'ana@acme.dev',
            'stage' => 'pitching',
            'rate' => 12000,
            'currency' => 'USD',
            'proposal' => [
                'title' => 'MVP for Acme',
                'client' => 'Acme',
                'summary' => 'Ship it.',
                'goals' => [],
                'scope' => [[
                    'item' => 'Build',
                    'detail' => '',
                ]],
                'timeline' => [],
                'deliverables' => [],
                'assumptions' => [],
                'next_steps' => [],
                'investment' => [
                    'amount' => 12000,
                    'currency' => 'USD',
                    'terms' => '50/50',
                ],
                'valid_until' => '2026-12-01',
                'plan' => 'mvp',
            ],
        ]);
    }
}
