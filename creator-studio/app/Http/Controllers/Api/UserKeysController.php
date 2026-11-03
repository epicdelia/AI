<?php

namespace App\Http\Controllers\Api;

use App\Domains\Engine\Catalog\ModelCatalog;
use App\Domains\Engine\Drivers\OpenAiCompatibleDriver;
use App\Domains\Engine\Enums\EngineEnum;
use App\Http\Controllers\Controller;
use App\Models\UserApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class UserKeysController extends Controller
{
    public const SUPPORTED_PROVIDERS = [
        'gemini' => [
            'label' => 'Google Gemini (Studio Default)',
            'models' => 'Gemini 4 Argon, 3.8 Flash, 3.7 Flash, Lyria 3.5',
            'doc_url' => 'https://aistudio.google.com/app/apikey',
            'placeholder' => 'AIzaSy...',
        ],
        'anthropic' => [
            'label' => 'Anthropic Claude',
            'models' => 'Claude Opus 5.5, Sonnet 5.5, Fable 5.1',
            'doc_url' => 'https://console.anthropic.com/settings/keys',
            'placeholder' => 'sk-ant-...',
        ],
        'openai' => [
            'label' => 'OpenAI',
            'models' => 'GPT 6 Astra / Sol / Luna, GPT 5.6 Cyber, GPT-IMAGE-2.5',
            'doc_url' => 'https://platform.openai.com/api-keys',
            'placeholder' => 'sk-proj-...',
        ],
        'deepseek' => [
            'label' => 'DeepSeek',
            'models' => 'DeepSeek V4 Pro, Flash Vision Exp, Flash, R1',
            'doc_url' => 'https://platform.deepseek.com/api_keys',
            'placeholder' => 'sk-...',
        ],
        'xai' => [
            'label' => 'xAI Grok',
            'models' => 'Grok 4.6, Grok 4, Grok 3',
            'doc_url' => 'https://console.x.ai/',
            'placeholder' => 'xai-...',
        ],
        'elevenlabs' => [
            'label' => 'ElevenLabs Voice & Audio',
            'models' => 'ElevenLabs V4, V4 Turbo, Voice Isolator',
            'doc_url' => 'https://elevenlabs.io/app/settings/api-keys',
            'placeholder' => 'xi-api-key-...',
        ],
        'fal_ai' => [
            'label' => 'Fal.ai Generative Video & Audio',
            'models' => 'Kling 3, Veo 3.1, Seedance 2',
            'doc_url' => 'https://fal.ai/dashboard/keys',
            'placeholder' => 'key-...',
        ],
        'perplexity' => [
            'label' => 'Perplexity Research',
            'models' => 'Sonar Online, Deep Research',
            'doc_url' => 'https://www.perplexity.ai/settings/api',
            'placeholder' => 'pplx-...',
        ],
        'serper' => [
            'label' => 'Serper Google Web Search',
            'models' => 'Live Google Search & Fact-Checking',
            'doc_url' => 'https://serper.dev/api-key',
            'placeholder' => 'serper-...',
        ],
    ];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $keys = $user->apiKeys()->get()->keyBy('provider');

        $providers = [];
        foreach (self::SUPPORTED_PROVIDERS as $id => $meta) {
            $userKey = $keys->get($id);
            $providers[] = [
                'provider' => $id,
                'label' => $meta['label'],
                'models' => $meta['models'],
                'doc_url' => $meta['doc_url'],
                'placeholder' => $meta['placeholder'],
                'configured' => $userKey !== null && !empty($userKey->api_key),
                'masked_key' => $userKey?->maskedKey(),
                'base_url' => $userKey?->base_url,
                'default_model' => $userKey?->default_model,
                'is_active' => $userKey?->is_active ?? true,
                'last_used_at' => $userKey?->last_used_at?->diffForHumans(),
            ];
        }

        return response()->json([
            'credit_balance' => $user->creditBalance(),
            'providers' => $providers,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'required|string|max:50',
            'api_key' => 'required|string|max:1000',
            'base_url' => 'nullable|url|max:300',
            'default_model' => 'nullable|string|max:100',
        ]);

        $user = $request->user();
        $provider = strtolower(trim($validated['provider']));

        $record = $user->setApiKeyFor(
            $provider,
            $validated['api_key'],
            $validated['base_url'] ?? null,
            $validated['default_model'] ?? null
        );

        return response()->json([
            'status' => 'Key saved securely in user account.',
            'provider' => $provider,
            'masked_key' => $record->maskedKey(),
        ]);
    }

    public function destroy(Request $request, string $provider): JsonResponse
    {
        $user = $request->user();
        $user->removeApiKeyFor($provider);

        return response()->json([
            'status' => "Key for {$provider} removed.",
        ]);
    }

    public function test(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'required|string|max:50',
            'api_key' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $provider = strtolower(trim($validated['provider']));
        $rawKey = $validated['api_key'] ?? $user->getApiKeyFor($provider);

        if (!$rawKey) {
            return response()->json([
                'ok' => false,
                'message' => "No key provided for {$provider}.",
            ], 422);
        }

        try {
            $engine = EngineEnum::tryFrom($provider);
            if (!$engine) {
                return response()->json([
                    'ok' => true,
                    'message' => "Key format saved for {$provider}.",
                ]);
            }

            $driver = $engine === EngineEnum::ANTHROPIC
                ? new \App\Domains\Engine\Drivers\AnthropicDriver($engine)
                : new OpenAiCompatibleDriver($engine);

            $result = $driver->chat('System: You are an API verification bot.', [
                ['role' => 'user', 'content' => 'Say OK.'],
            ], [
                'api_key' => $rawKey,
                'max_tokens' => 10,
            ]);

            return response()->json([
                'ok' => true,
                'message' => "Key verified successfully with {$result->model()}!",
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}

