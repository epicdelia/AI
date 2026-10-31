<?php

namespace App\Domains\Engine\Drivers;

use App\Domains\Engine\Data\ChatResult;
use App\Domains\Engine\Data\ToolTurn;
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

    /** Native tool use: `tools` with input_schema in, `tool_use` content blocks out. */
    public function toolChat(string $system, array $messages, array $tools, array $options = []): ToolTurn
    {
        $body = $this->body($system, [], $options);
        $body['messages'] = self::toolMessages($messages);
        if ($tools) {
            $body['tools'] = array_map(fn ($t) => ['name' => $t['name'], 'description' => $t['description'], 'input_schema' => $t['parameters']], $tools);
        }

        $onDelta = $options['on_delta'] ?? null;
        if ($onDelta) {
            $body['stream'] = true;
        }
        $resp = $this->send($body, $options, (bool) $onDelta);
        if ($resp->failed()) {
            throw new RuntimeException("AI request failed ({$resp->status()}): ".self::clip($resp->json('error.message') ?? $resp->body()));
        }
        if ($onDelta) {
            return $this->readToolStream($resp, $body['model'], $onDelta);
        }

        $blocks = collect($resp->json('content') ?? []);
        $calls = $blocks->where('type', 'tool_use')->map(fn ($b) => ['id' => (string) $b['id'], 'name' => (string) $b['name'], 'args' => (array) ($b['input'] ?? [])])->values()->all();
        $text = trim($blocks->where('type', 'text')->pluck('text')->implode(''));
        if ($text === '' && ! $calls) {
            throw new RuntimeException('The AI returned an empty answer. Try again, or pick another model.');
        }

        return new ToolTurn($text, $calls, $resp->json('usage.input_tokens'), $resp->json('usage.output_tokens'), $resp->json('model') ?? $body['model']);
    }

    /** Streamed tool turn: text_delta pieces go to $onDelta; tool_use inputs arrive as input_json_delta fragments. */
    private function readToolStream(Response $resp, string $model, callable $onDelta): ToolTurn
    {
        $text = '';
        $blocks = [];
        $in = $out = null;
        SseParser::read($resp->toPsrResponse()->getBody(), function (?string $event, string $data) use (&$text, &$blocks, &$in, &$out, &$model, $onDelta) {
            $json = json_decode($data, true);
            if (! is_array($json)) {
                return null;
            }
            switch ($json['type'] ?? $event) {
                case 'message_start':
                    $in = $json['message']['usage']['input_tokens'] ?? $in;
                    $model = $json['message']['model'] ?? $model;
                    break;
                case 'content_block_start':
                    $b = $json['content_block'] ?? [];
                    if (($b['type'] ?? '') === 'tool_use') {
                        $blocks[$json['index'] ?? count($blocks)] = ['id' => (string) $b['id'], 'name' => (string) $b['name'], 'json' => ''];
                    }
                    break;
                case 'content_block_delta':
                    $d = $json['delta'] ?? [];
                    if (($d['type'] ?? '') === 'input_json_delta' && isset($blocks[$json['index'] ?? -1])) {
                        $blocks[$json['index']]['json'] .= (string) ($d['partial_json'] ?? '');
                    } elseif (is_string($d['text'] ?? null) && $d['text'] !== '') {
                        $text .= $d['text'];
                        $onDelta($d['text']);
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

        ksort($blocks);
        $calls = array_values(array_map(function ($b) {
            $args = json_decode($b['json'] !== '' ? $b['json'] : '{}', true);

            return ['id' => $b['id'], 'name' => $b['name'], 'args' => is_array($args) ? $args : []];
        }, $blocks));
        if (trim($text) === '' && ! $calls) {
            throw new RuntimeException('The AI returned an empty answer. Try again, or pick another model.');
        }

        return new ToolTurn(trim($text), $calls, $in, $out, $model);
    }

    /** Neutral transcript → Anthropic messages; consecutive tool results share one user turn. */
    private static function toolMessages(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'tool') {
                $block = ['type' => 'tool_result', 'tool_use_id' => $m['tool_call_id'], 'content' => (string) $m['content']];
                $last = array_key_last($out);
                if ($last !== null && $out[$last]['role'] === 'user' && is_array($out[$last]['content']) && ($out[$last]['content'][0]['type'] ?? '') === 'tool_result') {
                    $out[$last]['content'][] = $block;
                } else {
                    $out[] = ['role' => 'user', 'content' => [$block]];
                }
            } elseif ($m['role'] === 'assistant' && ! empty($m['tool_calls'])) {
                $content = ($m['content'] ?? '') !== '' ? [['type' => 'text', 'text' => $m['content']]] : [];
                foreach ($m['tool_calls'] as $c) {
                    $content[] = ['type' => 'tool_use', 'id' => $c['id'], 'name' => $c['name'], 'input' => (object) $c['args']];
                }
                $out[] = ['role' => 'assistant', 'content' => $content];
            } else {
                $out[] = self::message(['role' => $m['role'], 'content' => (string) $m['content'], 'images' => $m['images'] ?? []]);
            }
        }

        return $out;
    }

    private function body(string $system, array $messages, array $options): array
    {
        $body = [
            'model' => $this->model($options),
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
    private static function message(array $m): array
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
        }, $options);
    }
}
