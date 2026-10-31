<?php

namespace App\Domains\Engine\Drivers;

use App\Domains\Engine\Data\ChatResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Anthropic's native Messages API. */
class AnthropicDriver extends BaseDriver
{
    public function chat(string $system, array $messages, array $options = []): ChatResult
    {
        $body = [
            'model' => $this->model(),
            'max_tokens' => $options['max_tokens'] ?? 8000,
            'system' => $system,
            'messages' => $messages,
        ];
        if (isset($options['temperature'])) {
            $body['temperature'] = $options['temperature'];
        }

        $resp = Http::timeout($options['timeout'] ?? 180)->connectTimeout(10)
            ->withHeaders(['x-api-key' => $this->key(), 'anthropic-version' => '2023-06-01'])
            ->post($this->baseUrl().'/messages', $body);

        if ($resp->failed()) {
            throw new RuntimeException("AI request failed ({$resp->status()}): ".self::clip($resp->json('error.message') ?? $resp->body()));
        }

        $text = collect($resp->json('content') ?? [])->where('type', 'text')->pluck('text')->implode('');
        if (trim($text) === '') {
            throw new RuntimeException('The AI returned an empty answer. Try again, or pick another model in Settings.');
        }

        return new ChatResult(
            trim($text),
            $resp->json('usage.input_tokens'),
            $resp->json('usage.output_tokens'),
            $resp->json('model') ?? $body['model'],
        );
    }
}
