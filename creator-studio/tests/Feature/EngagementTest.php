<?php

namespace Tests\Feature;

use App\Domains\Engagement\Engagement;
use App\Domains\Engagement\Models\EngagementItem;
use App\Domains\Engagement\Models\EngagementRule;
use App\Domains\Social\Models\SocialAccount;
use App\Models\Setting;
use App\Services\Llm;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EngagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Setting::put('timezone', 'UTC');
        Setting::put('llm_api_key', 'test');
        Setting::put('instagram_client_id', 'i');
        Setting::put('instagram_client_secret', 's');
        config([
            'app.url' => 'https://studio.test',
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-10-28 06:00', 'UTC'));
        $this->actingAs($this->admin([
            'name' => 'Delia',
            'email' => 'd@example.com',
            'password' => 'secret123',
        ]));
        SocialAccount::create([
            'platform' => 'instagram',
            'access_token' => 'AT',
            'external_id' => '178',
            'handle' => 'tech.unicorn',
            'expires_at' => now()->addDays(50),
        ]);
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        parent::tearDown();
    }

    public function test_poll_imports_comments_and_dms_skips_her_own_and_tags_intent(): void
    {
        $this->fakeInstagram();
        $r = app(Engagement::class)->poll();
        $this->assertSame(4, $r['new']);
        $this->assertSame(['ana', 'ben', 'spammer', 'cara'], EngagementItem::orderBy('id')->pluck('author')->all());
        $this->assertSame('question', EngagementItem::where('external_id', 'c2')->value('intent'));
        $this->assertSame('spam', EngagementItem::where('external_id', 'c4')->value('intent'));
        $this->assertSame('lead', EngagementItem::where('external_id', 'dm1')->value('intent'));
        $this->assertSame('dm', EngagementItem::where('external_id', 'dm1')->value('kind'));
        // a second poll doesn't duplicate
        $this->assertSame(0, app(Engagement::class)->poll()['new']);
        // the inbox hides spam from "open"
        $this->assertSame(3, $this->getJson('/api/engage')->assertOk()->json('counts.open'));
    }

    public function test_keyword_rule_auto_replies_and_sends_private_dm(): void
    {
        $this->postJson('/api/engage/rules', [
            'name' => 'Guide',
            'platforms' => ['instagram'],
            'trigger' => 'keyword',
            'keywords' => ['guide'],
            'reply_mode' => 'template',
            'reply_template' => 'Sent it to your DMs {name}!',
            'dm_text' => 'Here you go: https://delia.link/guide',
            'auto_send' => true,
        ])->assertCreated();
        $this->fakeInstagram();
        $r = app(Engagement::class)->poll();
        $this->assertSame(1, $r['sent']);
        $item = EngagementItem::where('external_id', 'c1')->first();
        $this->assertSame(['replied', 'rule'], [$item->status, $item->replied_by]);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'c1/replies') && $req['message'] === 'Sent it to your DMs @ana!');
        Http::assertSent(fn ($req) => str_contains($req->url(), 'me/messages') && $req['recipient'] === [
            'comment_id' => 'c1',
        ] && str_contains($req['message']['text'], 'delia.link'));
        $this->assertSame(1, EngagementRule::first()->fired);
    }

    public function test_review_rule_drafts_with_ai_in_her_style_and_rate_limit(): void
    {
        $this->postJson('/api/engage/rules', [
            'name' => 'Questions',
            'platforms' => ['instagram'],
            'trigger' => 'question',
            'reply_mode' => 'ai',
            'instructions' => 'Gear list is on my site',
        ])->assertCreated();
        EngagementItem::create([
            'platform' => 'instagram',
            'kind' => 'comment',
            'external_id' => 'old',
            'text' => 'Love it',
            'status' => 'replied',
            'reply' => 'Aww thank you so much!! 💕',
            'replied_by' => 'her',
            'replied_at' => now()->subDay(),
        ]);
        $sys = null;
        Llm::$fake = function ($s, $u) use (&$sys) {
            $sys = $s;

            return '"It’s the Shure MV7, links on my site!"';
        };
        $this->fakeInstagram();
        app(Engagement::class)->poll();

        $item = EngagementItem::where('external_id', 'c2')->first();
        $this->assertSame('drafted', $item->status);
        $this->assertSame('It’s the Shure MV7, links on my site!', $item->reply_draft);
        $this->assertStringContainsString('Gear list is on my site', $sys);
        $this->assertStringContainsString('Aww thank you so much', $sys); // learns from her own replies
        Http::assertNotSent(fn ($req) => str_contains($req->url(), 'c2/replies'));

        // she sends it unchanged: counted as an AI reply, not as her style
        $this->postJson("/api/engage/items/{$item->id}/reply", [
            'text' => $item->reply_draft,
        ])->assertOk();
        $this->assertSame('ai', $item->fresh()->replied_by);
    }

    public function test_manual_reply_ignore_and_failures(): void
    {
        $this->fakeInstagram();
        app(Engagement::class)->poll();
        $dm = EngagementItem::where('external_id', 'dm1')->first();
        $this->postJson("/api/engage/items/{$dm->id}/reply", [
            'text' => '',
        ])->assertStatus(422);
        $this->postJson("/api/engage/items/{$dm->id}/reply", [
            'text' => 'Yes! Details are on my site.',
        ])->assertOk();
        Http::assertSent(fn ($req) => str_contains($req->url(), 'me/messages') && $req['recipient'] === [
            'id' => 'u9',
        ]);
        $this->assertSame('her', $dm->fresh()->replied_by);

        $c = EngagementItem::where('external_id', 'c1')->first();
        $this->postJson("/api/engage/items/{$c->id}/ignore")->assertOk()->assertJsonPath('item.status', 'ignored');

        SocialAccount::where('platform', 'instagram')->delete();
        $b = EngagementItem::where('external_id', 'c2')->first();
        $this->postJson("/api/engage/items/{$b->id}/reply", [
            'text' => 'Hi',
        ])->assertStatus(422)->assertJsonFragment([
            'error' => 'Instagram isn’t connected.',
        ]);
        $this->assertSame('failed', $b->fresh()->status);
    }

    public function test_rule_validation_and_keyword_word_boundaries(): void
    {
        $this->postJson('/api/engage/rules', [
            'name' => 'x',
            'platforms' => ['instagram'],
            'trigger' => 'keyword',
            'keywords' => [],
        ])->assertStatus(422);
        $this->postJson('/api/engage/rules', [
            'name' => 'x',
            'platforms' => ['tiktok'],
            'trigger' => 'any_comment',
        ])->assertStatus(422);
        $rule = new EngagementRule([
            'platforms' => ['instagram'],
            'trigger' => 'keyword',
            'keywords' => ['link'],
            'active' => true,
        ]);
        $mk = fn ($t) => new EngagementItem([
            'platform' => 'instagram',
            'kind' => 'comment',
            'text' => $t,
        ]);
        $this->assertTrue($rule->matches($mk('LINK pls')));
        $this->assertFalse($rule->matches($mk('linkedin is better')));
    }

    private function fakeInstagram(): void
    {
        Http::fake([
            'graph.instagram.com/v21.0/me/media*' => Http::response([
                'data' => [[
                    'id' => 'm1',
                    'timestamp' => '2026-10-27T10:00:00+0000',
                    'comments_count' => 4,
                ]],
            ]),
            'graph.instagram.com/v21.0/m1/comments*' => Http::response([
                'data' => [
                    [
                        'id' => 'c1',
                        'text' => 'GUIDE please!',
                        'timestamp' => '2026-10-27T12:00:00+0000',
                        'username' => 'ana',
                        'from' => [
                            'id' => 'u1',
                        ],
                    ],
                    [
                        'id' => 'c2',
                        'text' => 'Which mic do you use?',
                        'timestamp' => '2026-10-27T13:00:00+0000',
                        'username' => 'ben',
                        'from' => [
                            'id' => 'u2',
                        ],
                    ],
                    [
                        'id' => 'c3',
                        'text' => 'Thanks everyone',
                        'timestamp' => '2026-10-27T13:30:00+0000',
                        'username' => 'tech.unicorn',
                    ],
                    [
                        'id' => 'c4',
                        'text' => 'Promote it on my page, DM me for collab https://spam.biz',
                        'timestamp' => '2026-10-27T14:00:00+0000',
                        'username' => 'spammer',
                    ],
                ],
            ]),
            'graph.instagram.com/v21.0/me/conversations*' => Http::response([
                'data' => [[
                    'id' => 'conv1',
                    'messages' => [
                        'data' => [
                            [
                                'id' => 'dm1',
                                'message' => 'Do you offer coaching? What are your rates',
                                'from' => [
                                    'id' => 'u9',
                                    'username' => 'cara',
                                ],
                                'created_time' => '2026-10-27T15:00:00+0000',
                            ],
                            [
                                'id' => 'dm0',
                                'message' => 'Hi!',
                                'from' => [
                                    'id' => '178',
                                    'username' => 'tech.unicorn',
                                ],
                                'created_time' => '2026-10-27T14:59:00+0000',
                            ],
                        ],
                    ],
                ]],
            ]),
            'graph.instagram.com/v21.0/c1/replies' => Http::response([
                'id' => 'r1',
            ]),
            'graph.instagram.com/v21.0/me/messages' => Http::response([
                'message_id' => 'mid1',
            ]),
            'graph.instagram.com/v21.0/c2/replies' => Http::response([
                'id' => 'r2',
            ]),
        ]);
    }
}
