<?php

namespace App\Domains\Engine\Drivers;

use App\Domains\Engine\Data\ChatResult;
use App\Domains\Engine\Enums\EngineEnum;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** OpenAI, Gemini, OpenRouter, DeepSeek, xAI, Perplexity, Together, AssemblyAI and custom servers. */
class OpenAiCompatibleDriver extends BaseDriver
{
    public function chat(string $system, array $messages, array $options = []): ChatResult
    {
        $timeout = $options['timeout'] ?? 180;
        // AssemblyAI's gateway wants the bare key; everyone else wants "Bearer <key>".
        $auth = $this->engine === EngineEnum::ASSEMBLYAI ? $this->key() : 'Bearer '.$this->key();
        $headers = ['Authorization' => $auth];
        if ($this->engine === EngineEnum::OPENROUTER) {
            $headers['X-Title'] = 'Creator Studio';
        }

        $body = [
            'model' => $this->model(),
            'messages' => array_merge([['role' => 'system', 'content' => $system]], $messages),
        ];
        if (isset($options['temperature'])) {
            $body['temperature'] = $options['temperature'];
        }

        $resp = Http::timeout($timeout)->connectTimeout(10)->withHeaders($headers)
            ->post($this->baseUrl().'/chat/completions', $body);

        // Some reasoning models reject a custom temperature: retry once without it.
        if ($resp->status() === 400 && isset($body['temperature']) && str_contains(strtolower($resp->body()), 'temperature')) {
            unset($body['temperature']);
            $resp = Http::timeout($timeout)->connectTimeout(10)->withHeaders($headers)
                ->post($this->baseUrl().'/chat/completions', $body);
        }

        if ($resp->failed()) {
            $msg = $resp->json('error.message') ?? $resp->json('error') ?? $resp->body();
            throw new RuntimeException("AI request failed ({$resp->status()}): ".self::clip($msg));
        }
        $content = $resp->json('choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('The AI returned an empty answer. Try again, or pick another model in Settings.');
        }

        return new ChatResult(
            trim($content),
            $resp->json('usage.prompt_tokens'),
            $resp->json('usage.completion_tokens'),
            $resp->json('model') ?? $body['model'],
        );
    }
}
