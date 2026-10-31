<?php

namespace App\Domains\Engine\Drivers;

use App\Domains\Engine\Catalog\ModelCatalog;
use App\Domains\Engine\Data\ChatResult;
use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Engine\Stream\SseParser;
use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** OpenAI, Gemini, OpenRouter, DeepSeek, xAI, Perplexity, Together, AssemblyAI and custom servers. */
class OpenAiCompatibleDriver extends BaseDriver
{
    public function chat(string $system, array $messages, array $options = []): ChatResult
    {
        $body = $this->body($system, $messages, $options);
        $resp = $this->send($body, $options);

        // Some models reject a custom temperature: retry once without it.
        if ($resp->status() === 400 && isset($body['temperature']) && str_contains(strtolower($resp->body()), 'temperature')) {
            unset($body['temperature']);
            $resp = $this->send($body, $options);
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

    public function stream(string $system, array $messages, callable $onDelta, array $options = []): ChatResult
    {
        $body = $this->body($system, $messages, $options) + ['stream' => true];
        // Token usage in the final chunk; only OpenAI and OpenRouter document this option.
        if (in_array($this->engine, [EngineEnum::OPENAI, EngineEnum::OPENROUTER], true)) {
            $body['stream_options'] = ['include_usage' => true];
        }

        $resp = $this->send($body, $options, true);
        if ($resp->failed()) {
            $msg = $resp->json('error.message') ?? $resp->json('error') ?? $resp->body();
            throw new RuntimeException("AI request failed ({$resp->status()}): ".self::clip($msg));
        }

        $text = '';
        $in = $out = null;
        $model = $body['model'];
        SseParser::read($resp->toPsrResponse()->getBody(), function (?string $event, string $data) use (&$text, &$in, &$out, &$model, $onDelta) {
            if ($data === '[DONE]') {
                return false;
            }
            $json = json_decode($data, true);
            if (! is_array($json)) {
                return null;
            }
            if (isset($json['error'])) {
                throw new RuntimeException('AI stream error: '.self::clip($json['error']['message'] ?? $json['error']));
            }
            $delta = $json['choices'][0]['delta']['content'] ?? null;
            if (is_string($delta) && $delta !== '') {
                $text .= $delta;
                $onDelta($delta);
            }
            $model = $json['model'] ?? $model;
            $in = $json['usage']['prompt_tokens'] ?? $in;
            $out = $json['usage']['completion_tokens'] ?? $out;

            return null;
        });

        if (trim($text) === '') {
            throw new RuntimeException('The AI returned an empty answer. Try again, or pick another model in Settings.');
        }

        return new ChatResult(trim($text), $in, $out, $model);
    }

    /**
     * Request body. Reasoning models (ModelCatalog, ported from MagicAI's EntityEnum) get no
     * temperature, max_completion_tokens instead of max_tokens, and a resolved reasoning_effort.
     */
    private function body(string $system, array $messages, array $options): array
    {
        $model = $this->model();
        $body = [
            'model' => $model,
            'messages' => array_merge([['role' => 'system', 'content' => $system]], array_map([$this, 'message'], $messages)),
        ];

        $reasoning = ModelCatalog::isReasoning($this->engine, $model);
        if (isset($options['temperature']) && ! $reasoning) {
            $body['temperature'] = $options['temperature'];
        }
        if (isset($options['max_tokens'])) {
            $body[ModelCatalog::requiresMaxCompletionTokens($this->engine, $model) ? 'max_completion_tokens' : 'max_tokens'] = (int) $options['max_tokens'];
        }
        if ($reasoning && ($effort = Setting::get('reasoning_effort'))) {
            $resolved = ModelCatalog::resolveEffort($this->engine, $model, $effort) ?? $effort;
            $body['reasoning_effort'] = $resolved;
        }

        return $body;
    }

    /** Images become content parts (MagicAI's viaOpenAiWithImage used the same data-URI form). */
    private function message(array $m): array
    {
        if (empty($m['images'])) {
            return ['role' => $m['role'], 'content' => $m['content']];
        }
        $parts = [['type' => 'text', 'text' => (string) $m['content']]];
        foreach ($m['images'] as $img) {
            $parts[] = ['type' => 'image_url', 'image_url' => ['url' => "data:{$img['mime']};base64,{$img['data']}"]];
        }

        return ['role' => $m['role'], 'content' => $parts];
    }

    private function send(array $body, array $options, bool $stream = false): Response
    {
        return $this->withKeyFailover(function (string $key) use ($body, $options, $stream) {
            // AssemblyAI's gateway wants the bare key; everyone else wants "Bearer <key>".
            $headers = ['Authorization' => $this->engine === EngineEnum::ASSEMBLYAI ? $key : "Bearer {$key}"];
            if ($this->engine === EngineEnum::OPENROUTER) {
                $headers['X-Title'] = 'Creator Studio';
            }
            if ($stream) {
                $headers['Accept'] = 'text/event-stream';
            }
            $http = Http::timeout($options['timeout'] ?? 180)->connectTimeout(10)->withHeaders($headers);
            if ($stream) {
                $http = $http->withOptions(['stream' => true]);
            }

            return $http->post($this->baseUrl().'/chat/completions', $body);
        });
    }
}
