<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Engine\Services\AnthropicService;
use App\Domains\Engine\Services\GeminiService;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\Helper;
use GuzzleHttp\Client;
use Illuminate\Http\UploadedFile;
use OpenAI as OpenAIMain;
use OpenAI\Laravel\Facades\OpenAI;

class AiCompletionService
{
    public function completeUserOnly(string $userContent, ?EngineEnum $engine = null): string
    {
        return $this->complete('You are a helpful assistant.', $userContent, $engine);
    }

    public function complete(string $systemPrompt, string $userContent, ?EngineEnum $engine = null): string
    {
        $engine ??= Helper::defaultEngine();

        return match ($engine) {
            EngineEnum::ANTHROPIC => $this->viaAnthropic($systemPrompt, $userContent),
            EngineEnum::GEMINI    => $this->viaGemini($systemPrompt, $userContent, $engine),
            EngineEnum::DEEP_SEEK => $this->viaDeepSeek($systemPrompt, $userContent, $engine),
            EngineEnum::X_AI      => $this->viaXAi($systemPrompt, $userContent, $engine),
            default               => $this->viaOpenAi($systemPrompt, $userContent),
        };
    }

    private function viaOpenAi(string $systemPrompt, string $userContent): string
    {
        ApiHelper::setOpenAiKey();

        $model = Helper::defaultWordModel();

        $result = OpenAI::responses()->create([
            'model' => $model->value,
            'input' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userContent],
            ],
        ]);

        return collect($result['output'] ?? [])
            ->flatMap(fn ($item) => $item['content'] ?? [])
            ->pluck('text')
            ->implode('');
    }

    private function viaAnthropic(string $systemPrompt, string $userContent): string
    {
        $client = app(AnthropicService::class);

        $response = $client
            ->setSystem($systemPrompt)
            ->setMessages([
                ['role' => 'user', 'content' => $userContent],
            ])
            ->setStream(false)
            ->stream();

        $body = $response->json();

        return collect($body['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');
    }

    private function viaGemini(string $systemPrompt, string $userContent, EngineEnum $engine): string
    {
        $model = $engine->getDefaultWordModel(null);

        $client = app(GeminiService::class);

        $response = $client
            ->setHistory([
                ['role' => 'user', 'parts' => [['text' => $systemPrompt . "\n\n" . $userContent]]],
            ])
            ->generateContent($model->value);

        $body = $response->json();

        return collect($body['candidates'] ?? [])
            ->pluck('content.parts')
            ->flatten(1)
            ->pluck('text')
            ->implode('');
    }

    private function viaDeepSeek(string $systemPrompt, string $userContent, EngineEnum $engine): string
    {
        ApiHelper::setDeepseekKey();

        $model = $engine->getDefaultWordModel(null);
        $apiKey = config('deepseek.api_key');

        $response = (new Client)->post('https://api.deepseek.com/chat/completions', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => "Bearer {$apiKey}",
            ],
            'json' => [
                'model'    => $model->value,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userContent],
                ],
                'stream' => false,
            ],
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        return $body['choices'][0]['message']['content'] ?? '';
    }

    private function viaXAi(string $systemPrompt, string $userContent, EngineEnum $engine): string
    {
        $apiKey = ApiHelper::setXAiKey();
        $model = $engine->getDefaultWordModel(null);

        $client = OpenAIMain::factory()
            ->withApiKey($apiKey)
            ->withBaseUri('https://api.x.ai/v1')
            ->make();

        $result = $client->chat()->create([
            'model'    => $model->value,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userContent],
            ],
        ]);

        return $result->choices[0]->message->content ?? '';
    }

    public function completeWithImage(string $systemPrompt, string $userContent, ?UploadedFile $image = null, ?EngineEnum $engine = null): string
    {
        if (! $image) {
            return $this->complete($systemPrompt, $userContent, $engine);
        }

        $engine ??= Helper::defaultEngine();
        $base64 = base64_encode(file_get_contents($image->getRealPath()));
        $mimeType = $image->getMimeType() ?: 'image/jpeg';

        return match ($engine) {
            EngineEnum::ANTHROPIC => $this->viaAnthropicWithImage($systemPrompt, $userContent, $base64, $mimeType),
            EngineEnum::GEMINI    => $this->viaGeminiWithImage($systemPrompt, $userContent, $base64, $mimeType, $engine),
            default               => $this->viaOpenAiWithImage($systemPrompt, $userContent, $base64, $mimeType),
        };
    }

    public function completeWithImagePath(string $systemPrompt, string $userContent, string $imagePath, ?EngineEnum $engine = null): string
    {
        $engine ??= Helper::defaultEngine();
        $base64 = base64_encode(file_get_contents($imagePath));
        $mimeType = mime_content_type($imagePath) ?: 'image/png';

        return match ($engine) {
            EngineEnum::ANTHROPIC => $this->viaAnthropicWithImage($systemPrompt, $userContent, $base64, $mimeType),
            EngineEnum::GEMINI    => $this->viaGeminiWithImage($systemPrompt, $userContent, $base64, $mimeType, $engine),
            default               => $this->viaOpenAiWithImage($systemPrompt, $userContent, $base64, $mimeType),
        };
    }

    private function viaOpenAiWithImage(string $systemPrompt, string $userContent, string $base64, string $mimeType): string
    {
        ApiHelper::setOpenAiKey();

        $model = Helper::defaultWordModel();

        $result = OpenAI::responses()->create([
            'model' => $model->value,
            'input' => [
                ['role' => 'system', 'content' => $systemPrompt],
                [
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => $userContent],
                        ['type' => 'input_image', 'image_url' => "data:{$mimeType};base64,{$base64}"],
                    ],
                ],
            ],
        ]);

        return collect($result['output'] ?? [])
            ->flatMap(fn ($item) => $item['content'] ?? [])
            ->pluck('text')
            ->implode('');
    }

    private function viaAnthropicWithImage(string $systemPrompt, string $userContent, string $base64, string $mimeType): string
    {
        $client = app(AnthropicService::class);

        $response = $client
            ->setSystem($systemPrompt)
            ->setMessages([
                [
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $userContent],
                        [
                            'type'   => 'image',
                            'source' => [
                                'type'       => 'base64',
                                'media_type' => $mimeType,
                                'data'       => $base64,
                            ],
                        ],
                    ],
                ],
            ])
            ->setStream(false)
            ->stream();

        $body = $response->json();

        return collect($body['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');
    }

    private function viaGeminiWithImage(string $systemPrompt, string $userContent, string $base64, string $mimeType, EngineEnum $engine): string
    {
        $model = $engine->getDefaultWordModel(null);

        $client = app(GeminiService::class);

        $response = $client
            ->setHistory([
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => $systemPrompt . "\n\n" . $userContent],
                        ['inlineData' => ['mimeType' => $mimeType, 'data' => $base64]],
                    ],
                ],
            ])
            ->generateContent($model->value);

        $body = $response->json();

        return collect($body['candidates'] ?? [])
            ->pluck('content.parts')
            ->flatten(1)
            ->pluck('text')
            ->implode('');
    }
}
