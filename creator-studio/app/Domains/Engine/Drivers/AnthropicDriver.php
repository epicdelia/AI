<?php

namespace App\Domains\Engine\Drivers;

use App\Domains\Engine\Data\ChatResult;
use App\Domains\Engine\Stream\SseParser;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Anthropic's native Messages API (ported from MagicAI's AnthropicService + viaAnthropicWithImage). */
class AnthropicDriver extends BaseDriver
{
    public function chat(string $system, array $messages, array $options = []): ChatResult
    {
        $body = $this->body($system, $messages, $options);
        $resp = $this->send($body, $options);

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

    /** Ported from MagicAI's anthropicChatStream, reading the body incrementally. */
    public function stream(string $system, array $messages, callable $onDelta, array $options = []): ChatResult
    {
        $body = $this->body($system, $messages, $options) + ['stream' => true];
        $resp = $this->send($body, $options, true);

        if ($resp->failed()) {
            throw new RuntimeException("AI request failed ({$resp->status()}): ".self::clip($resp->json('error.message') ?? $resp->body()));
        }

        $text = '';
        $in = $out = null;
        $model = $body['model'];
        SseParser::read($resp->toPsrResponse()->getBody(), function (?string $event, string $data) use (&$text, &$in, &$out, &$model, $onDelta) {
            $json = json_decode($data, true);
            if (! is_array($json)) {
                return null;
            }
            switch ($json['type'] ?? $event) {
                case 'message_start':
                    $in = $json['message']['usage']['input_tokens'] ?? $in;
                    $model = $json['message']['model'] ?? $model;
                    break;
                case 'content_block_delta':
                    $delta = $json['delta']['text'] ?? null;
                    if (is_string($delta) && $delta !== '') {
                        $text .= $delta;
                        $onDelta($delta);
                    }
                    break;
                case 'message_delta':
                    $out = $json['usage']['output_tokens'] ?? $out;
                    break;
                case 'message_stop':
                    return false;
                case 'error':
                    throw new RuntimeException('AI stream error: '.self::clip($json['error']['message'] ?? $data));
            }

            return null;
        });

        if (trim($text) === '') {
            throw new RuntimeException('The AI returned an empty answer. Try again, or pick another model in Settings.');
        }

        return new ChatResult(trim($text), $in, $out, $model);
    }

    private function body(string $system, array $messages, array $options): array
    {
        $body = [
            'model' => $this->model(),
            'max_tokens' => $options['max_tokens'] ?? 8000,
            'system' => $system,
            'messages' => array_map([$this, 'message'], $messages),
        ];
        if (isset($options['temperature'])) {
            $body['temperature'] = $options['temperature'];
        }

        return $body;
    }

    /** Images as base64 image blocks before the text, as in MagicAI's viaAnthropicWithImage. */
    private function message(array $m): array
    {
        if (empty($m['images'])) {
            return ['role' => $m['role'], 'content' => $m['content']];
        }
        $blocks = [];
        foreach ($m['images'] as $img) {
            $blocks[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $img['mime'], 'data' => $img['data']]];
        }
        $blocks[] = ['type' => 'text', 'text' => (string) $m['content']];

        return ['role' => $m['role'], 'content' => $blocks];
    }

    private function send(array $body, array $options, bool $stream = false): Response
    {
        return $this->withKeyFailover(function (string $key) use ($body, $options, $stream) {
            $http = Http::timeout($options['timeout'] ?? 180)->connectTimeout(10)
                ->withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'] + ($stream ? ['Accept' => 'text/event-stream'] : []));
            if ($stream) {
                $http = $http->withOptions(['stream' => true]);
            }

            return $http->post($this->baseUrl().'/messages', $body);
        });
    }
}
