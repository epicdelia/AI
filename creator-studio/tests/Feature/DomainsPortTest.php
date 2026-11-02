<?php

namespace Tests\Feature;

use App\Domains\Engine\Catalog\ModelCatalog;
use App\Domains\Engine\Drivers\AnthropicDriver;
use App\Domains\Engine\Drivers\OpenAiCompatibleDriver;
use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Media\MediaFile;
use App\Domains\Media\ThumbnailGenerator;
use App\Domains\Usage\AiUsage;
use App\Domains\Usage\UsageLimit;
use App\Http\Controllers\Api\VisionController;
use App\Models\Outlier;
use App\Models\Setting;
use App\Services\Llm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Model catalog, vision, key pool failover, usage limits and login throttling (ported from MagicAI's domains).
 */
class DomainsPortTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = "\x89PNG\r\n\x1a\n0000fakepng";

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Llm::$fake = null;
        ThumbnailGenerator::$fake = null;
        VisionController::$fetchFake = null;
        parent::tearDown();
    }

    // ---- Model catalog (EntityEnum rules) --------------------------------------------

    public function test_reasoning_rules_match_magicai(): void
    {
        $this->assertTrue(ModelCatalog::isReasoning(EngineEnum::OPENAI, 'gpt-5-mini'));
        $this->assertFalse(ModelCatalog::isReasoning(EngineEnum::OPENAI, 'gpt-4.1'));
        $this->assertTrue(ModelCatalog::isReasoning(EngineEnum::OPENAI, 'gpt-5.9-future'), 'unknown gpt-5* treated as reasoning');
        $this->assertTrue(ModelCatalog::isReasoning(EngineEnum::OPENROUTER, 'openai/gpt-5'), 'OpenRouter ids reuse upstream rules');

        // resolveReasoningEffort: nearest supported value by ordered distance.
        $this->assertSame('minimal', ModelCatalog::resolveEffort(EngineEnum::OPENAI, 'gpt-5', 'minimal'));
        $this->assertSame('minimal', ModelCatalog::resolveEffort(EngineEnum::OPENAI, 'gpt-5', 'none'));
        $this->assertSame('none', ModelCatalog::resolveEffort(EngineEnum::OPENAI, 'gpt-5.1', 'minimal'));
        $this->assertSame('high', ModelCatalog::resolveEffort(EngineEnum::OPENAI, 'gpt-5.4', 'xhigh'));
        $this->assertSame('medium', ModelCatalog::resolveEffort(EngineEnum::OPENAI, 'gpt-5-pro', 'low'));
        $this->assertNull(ModelCatalog::resolveEffort(EngineEnum::ANTHROPIC, 'claude-sonnet-5-5', 'high'));

        $this->assertFalse(ModelCatalog::supportsVision(EngineEnum::DEEPSEEK, 'deepseek-chat'));
        $this->assertTrue(ModelCatalog::supportsVision(EngineEnum::ANTHROPIC, 'claude-opus-5-5'));
    }

    public function test_reasoning_model_request_drops_temperature_and_sends_effort(): void
    {
        Setting::put('llm_api_key', 'sk');
        Setting::put('llm_model', 'gpt-5.1');
        Setting::put('reasoning_effort', 'minimal');
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => 'ok',
                    ],
                ]],
            ]),
        ]);

        (new OpenAiCompatibleDriver(EngineEnum::OPENAI))->chat('s', [[
            'role' => 'user',
            'content' => 'x',
        ]], [
            'temperature' => 0.7,
            'max_tokens' => 500,
        ]);
        Http::assertSent(fn ($r) => !isset($r['temperature']) && $r['max_completion_tokens'] === 500 && !isset($r['max_tokens']) && $r['reasoning_effort'] === 'none');

        Setting::put('llm_model', 'gpt-4.1');
        (new OpenAiCompatibleDriver(EngineEnum::OPENAI))->chat('s', [[
            'role' => 'user',
            'content' => 'x',
        ]], [
            'temperature' => 0.7,
            'max_tokens' => 500,
        ]);
        Http::assertSent(fn ($r) => $r['model'] === 'gpt-4.1' && $r['temperature'] === 0.7 && $r['max_tokens'] === 500 && !isset($r['reasoning_effort']));
    }

    // ---- Vision (completeWithImage) --------------------------------------------------

    public function test_images_become_provider_specific_content_parts(): void
    {
        Setting::put('llm_api_key', 'sk');
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => 'ok',
                    ],
                ]],
            ]),
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => 'ok',
                ]],
            ]),
        ]);
        $msg = [[
            'role' => 'user',
            'content' => 'Rate this',
            'images' => [[
                'mime' => 'image/png',
                'data' => 'QUJD',
            ]],
        ]];

        (new OpenAiCompatibleDriver(EngineEnum::OPENAI))->chat('s', $msg);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'openai') && $r['messages'][1]['content'][0] === [
            'type' => 'text',
            'text' => 'Rate this',
        ]
            && $r['messages'][1]['content'][1]['image_url']['url'] === 'data:image/png;base64,QUJD');

        (new AnthropicDriver(EngineEnum::ANTHROPIC))->chat('s', $msg);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'anthropic') && $r['messages'][0]['content'][0]['source'] === [
            'type' => 'base64',
            'media_type' => 'image/png',
            'data' => 'QUJD',
        ]
            && $r['messages'][0]['content'][1]['text'] === 'Rate this');
    }

    public function test_vision_refused_for_text_only_models(): void
    {
        Setting::put('llm_engine', 'deepseek');
        Setting::put('llm_api_key', 'sk');
        $this->expectExceptionMessage("can't look at images");
        app(Llm::class)->see('s', 'x', [[
            'mime' => 'image/png',
            'data' => 'QUJD',
        ]]);
    }

    public function test_thumbnail_critic_with_breakout_comparison(): void
    {
        $this->login();
        $o = Outlier::create([
            'video_id' => 'dQw4w9WgXcQ',
            'channel_title' => 'Rival',
            'title' => 'I saved £10k',
            'url' => 'https://youtu.be/dQw4w9WgXcQ',
            'outlier_score' => 7.5,
        ]);
        $mine = MediaFile::store('thumbnail', self::PNG, 'png', 'image/png', [
            'title' => 'mock',
        ]);
        VisionController::$fetchFake = fn ($id) => self::PNG;
        $seen = null;
        Llm::$fake = function ($system, $user) use (&$seen) {
            $seen = [$system, $user];

            return "## Score\n7/10";
        };

        $this->postJson('/api/thumbnails/review', [
            'media_id' => $mine->id,
            'title' => 'Budget apps',
            'compare' => ["outlier:{$o->id}"],
        ])
            ->assertOk()->assertJsonPath('compared', 1)->assertJsonPath('review', "## Score\n7/10");
        $this->assertStringContainsString('Image 2: a breakout video in her niche: "I saved £10k" (Rival, 7.5× its channel median)', $seen[1]);
        $this->assertStringContainsString('## Against the breakouts', $seen[0]);

        $this->postJson('/api/thumbnails/review', [])->assertStatus(422);
        $this->postJson('/api/thumbnails/review', [
            'youtube_url' => 'not a link',
        ])->assertStatus(422);
    }

    // ---- Key pool (HasApiKeys) -------------------------------------------------------

    public function test_key_pool_fails_over_on_rate_limit(): void
    {
        Setting::put('llm_api_key', 'key-a, key-b');
        $used = [];
        Http::fake([
            'api.openai.com/*' => function ($req) use (&$used) {
                $used[] = $req->header('Authorization')[0];

                return count($used) === 1 ? Http::response([
                    'error' => [
                        'message' => 'rate limited',
                    ],
                ], 429) : Http::response([
                    'choices' => [[
                        'message' => [
                            'content' => 'ok',
                        ],
                    ]],
                ]);
            },
        ]);

        $this->assertSame('ok', (new OpenAiCompatibleDriver(EngineEnum::OPENAI))->chat('s', [[
            'role' => 'user',
            'content' => 'x',
        ]])->text);
        $this->assertCount(2, $used);
        $this->assertNotSame($used[0], $used[1], 'second attempt used the other key');
        $this->assertEqualsCanonicalizing(['Bearer key-a', 'Bearer key-b'], $used);
    }

    // ---- Usage limits (HasCreditLimit) -----------------------------------------------

    public function test_monthly_token_limit_blocks_ai_calls(): void
    {
        $this->login();
        Llm::$fake = fn () => 'ok';
        AiUsage::create([
            'feature' => 'script',
            'engine' => 'openai',
            'input_tokens' => 900,
            'output_tokens' => 200,
        ]);
        $this->assertSame(1100, UsageLimit::used('token'), 'quantity = input + output');

        Setting::put('monthly_token_limit', '1000');
        $this->postJson('/api/tools/run', [
            'tool' => 'yt_titles',
            'inputs' => [
                'topic' => 'x',
            ],
        ])
            ->assertStatus(502)->assertJsonPath('error', fn ($e) => str_contains($e, "this month's limit for AI tokens (1,100 of 1,000)"));

        Setting::put('monthly_token_limit', '');
        $this->postJson('/api/tools/run', [
            'tool' => 'yt_titles',
            'inputs' => [
                'topic' => 'x',
            ],
        ])->assertOk();

        $old = AiUsage::create([
            'feature' => 'old',
            'engine' => 'openai',
            'input_tokens' => 5000,
            'output_tokens' => 0,
        ]);
        $old->created_at = now()->subMonths(2);
        $old->save();
        $this->assertSame(1100, UsageLimit::used('token'), 'only this month counts');
    }

    public function test_image_and_character_limits_and_spend_estimate(): void
    {
        $this->login();
        Setting::put('embedding_api_key', 'sk');
        Setting::put('monthly_image_limit', '1');
        AiUsage::create([
            'feature' => 'thumbnail',
            'engine' => 'openai',
            'unit' => 'image',
            'quantity' => 1,
        ]);
        $this->postJson('/api/thumbnails', [
            'concept' => 'x',
        ])->assertStatus(422)->assertJsonPath('error', fn ($e) => str_contains($e, 'thumbnail mockups (1 of 1)'));

        Setting::put('monthly_tts_char_limit', '10');
        $this->postJson('/api/tts', [
            'text' => 'This sentence is far longer than ten characters.',
        ])->assertStatus(422)->assertJsonPath('error', fn ($e) => str_contains($e, 'read-aloud characters'));

        $this->assertNull(UsageLimit::estimatedSpend());
        AiUsage::create([
            'feature' => 'chat',
            'engine' => 'openai',
            'input_tokens' => 2_000_000,
            'output_tokens' => 500_000,
        ]);
        Setting::put('price_input_per_million', '1.25');
        Setting::put('price_output_per_million', '10');
        $this->assertSame(7.5, UsageLimit::estimatedSpend());
        $this->getJson('/api/settings')->assertJsonPath('limits.units.image.limit', 1)->assertJsonPath('limits.estimated_spend', 7.5);
    }

    public function test_usage_rows_get_units(): void
    {
        $this->login();
        Setting::put('embedding_api_key', 'sk');
        Http::fake([
            'api.openai.com/v1/audio/speech' => Http::response('MP3'),
        ]);
        $this->postJson('/api/tts', [
            'text' => 'Hello friends',
        ])->assertOk();
        $row = AiUsage::where('feature', 'read_aloud')->first();
        $this->assertSame(['character', 13], [$row->unit, (int)$row->quantity]);
        $this->assertNull($row->input_tokens);
        $this->getJson('/api/usage')->assertJsonPath('by_feature.0.unit', 'character');
    }

    // ---- Login throttle (RateLimiter) ------------------------------------------------

    public function test_login_is_throttled_after_five_failures(): void
    {
        $this->admin([
            'name' => 'Delia',
            'email' => 'd@example.com',
            'password' => 'secret123',
        ]);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'd@example.com',
                'password' => 'wrong',
            ])->assertStatus(401);
        }
        $this->postJson('/api/auth/login', [
            'email' => 'd@example.com',
            'password' => 'secret123',
        ])
            ->assertStatus(429)->assertJsonPath('error', fn ($e) => str_starts_with($e, 'Too many attempts'));
        // Changing the email's case doesn't get around the lock.
        $this->postJson('/api/auth/login', [
            'email' => 'D@example.com',
            'password' => 'secret123',
        ])->assertStatus(429);
    }

    public function test_successful_login_clears_failures(): void
    {
        $this->admin([
            'name' => 'Delia',
            'email' => 'd@example.com',
            'password' => 'secret123',
        ]);
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'd@example.com',
                'password' => 'wrong',
            ]);
        }
        $this->postJson('/api/auth/login', [
            'email' => 'd@example.com',
            'password' => 'secret123',
        ])->assertOk();
        $this->postJson('/api/auth/logout');
        $this->postJson('/api/auth/login', [
            'email' => 'd@example.com',
            'password' => 'wrong',
        ])->assertStatus(401);
        $this->postJson('/api/auth/login', [
            'email' => 'd@example.com',
            'password' => 'secret123',
        ])->assertOk();
    }

    private function login(): void
    {
        $this->actingAs($this->admin([
            'name' => 'Delia',
            'email' => 'd@example.com',
            'password' => 'secret123',
        ]));
    }
}
