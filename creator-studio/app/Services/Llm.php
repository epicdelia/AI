<?php

namespace App\Services;

use App\Domains\Engine\EngineManager;
use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Usage\AiUsage;
use App\Models\Setting;
use RuntimeException;
use Throwable;

/**
 * The one door to the AI. Delegates to the engine picked in Settings (see App\Domains\Engine)
 * and records every call in ai_usages so the Settings page can show what the AI cost.
 */
class Llm
{
    /** @var callable|null test hook: fn(string $system, string $user): string */
    public static $fake = null;

    private string $feature = 'general';

    private ?float $temperature = null;

    public function __construct(private ?EngineManager $engines = null)
    {
        $this->engines ??= app(EngineManager::class);
    }

    public static function configured(): bool
    {
        $engine = app(EngineManager::class)->current();

        return Setting::has('llm_api_key') || $engine->canUseAssemblyKey() && Setting::has('assemblyai_api_key');
    }

    /** Label the next calls for usage stats: $llm->as('ideas')->json(...). */
    public function as(string $feature): static
    {
        $clone = clone $this;
        $clone->feature = $feature;

        return $clone;
    }

    /** 0 = precise, 1 = creative. Ignored by models that don't support it. */
    public function creativity(?float $value): static
    {
        $clone = clone $this;
        $clone->temperature = $value === null ? null : max(0, min(1, $value));

        return $clone;
    }

    public function chat(string $system, string $user, int $timeout = 180): string
    {
        return $this->converse($system, [['role' => 'user', 'content' => $user]], $timeout);
    }

    /** @param array<int, array{role: string, content: string}> $messages */
    public function converse(string $system, array $messages, int $timeout = 180): string
    {
        if (self::$fake) {
            $last = end($messages);

            return (self::$fake)($system, $last['content'] ?? '');
        }

        @set_time_limit($timeout + 30);
        $engine = $this->engines->current();
        $started = microtime(true);
        $options = ['timeout' => $timeout] + ($this->temperature !== null ? ['temperature' => $this->temperature] : []);

        try {
            $result = $this->engines->driver($engine)->chat($system, $messages, $options);
        } catch (Throwable $e) {
            $this->record($engine, null, null, null, $started, $e->getMessage());

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }

        $this->record($engine, $result->model, $result->inputTokens, $result->outputTokens, $started);

        return $result->text;
    }

    /**
     * Like converse(), but $onDelta receives text as it's written. Returns the full text.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  callable(string): void  $onDelta
     */
    public function stream(string $system, array $messages, callable $onDelta, int $timeout = 180): string
    {
        if (self::$fake) {
            $last = end($messages);
            $text = (self::$fake)($system, $last['content'] ?? '');
            foreach (preg_split('/(?<=\s)/', $text) as $piece) {
                $onDelta($piece);
            }

            return $text;
        }

        @set_time_limit($timeout + 30);
        $engine = $this->engines->current();
        $started = microtime(true);
        $options = ['timeout' => $timeout] + ($this->temperature !== null ? ['temperature' => $this->temperature] : []);

        try {
            $result = $this->engines->driver($engine)->stream($system, $messages, $onDelta, $options);
        } catch (Throwable $e) {
            $this->record($engine, null, null, null, $started, $e->getMessage());

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }

        $this->record($engine, $result->model, $result->inputTokens, $result->outputTokens, $started);

        return $result->text;
    }

    /** Ask for JSON and decode it, tolerating ```json fences and chatter around the object. */
    public function json(string $system, string $user): array
    {
        $text = $this->chat($system."\n\nReply with valid JSON only, no markdown fences, no commentary.", $user);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text));
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false) {
            throw new RuntimeException('The AI answer was not JSON. Try again.');
        }
        $data = json_decode(substr($text, $start, $end - $start + 1), true);
        if (! is_array($data)) {
            throw new RuntimeException('The AI answer was not valid JSON. Try again.');
        }

        return $data;
    }

    private function record(EngineEnum $engine, ?string $model, ?int $in, ?int $out, float $started, ?string $error = null): void
    {
        try {
            AiUsage::create([
                'feature' => $this->feature,
                'engine' => $engine->value,
                'model' => $model ?? (Setting::get('llm_model') ?: $engine->defaultModel()),
                'input_tokens' => $in,
                'output_tokens' => $out,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'ok' => $error === null,
                'error' => $error ? mb_substr($error, 0, 250) : null,
            ]);
        } catch (Throwable) {
            // Usage stats must never break the feature that's using the AI.
        }
    }
}
