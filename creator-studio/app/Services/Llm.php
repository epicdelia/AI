<?php

namespace App\Services;

use App\Domains\Engine\Catalog\ModelCatalog;
use App\Domains\Engine\Data\ToolTurn;
use App\Domains\Engine\EngineKeys;
use App\Domains\Engine\EngineManager;
use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Usage\AiUsage;
use App\Domains\Usage\UsageLimit;
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

    private ?string $modelOverride = null;

    /** @var callable|null test hook for agentTurn: fn(string $system, array $messages, array $tools): ToolTurn */
    public static $toolFake = null;

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

    /**
     * Ask about one or more images (port of MagicAI's AiCompletionService::completeWithImage).
     *
     * @param  array<int, array{mime: string, data: string}>  $images  base64 data
     */
    public function see(string $system, string $text, array $images, int $timeout = 180): string
    {
        $engine = $this->engines->current();
        $model = Setting::get('llm_model') ?: $engine->defaultModel();
        if (! self::$fake && ! ModelCatalog::supportsVision($engine, $model)) {
            throw new RuntimeException("{$engine->label()} model '{$model}' can't look at images. Pick a vision model in Settings (e.g. GPT-4.1, GPT-5, Claude or Gemini).");
        }

        return $this->converse($system, [['role' => 'user', 'content' => $text, 'images' => $images]], $timeout);
    }

    /** @param array<int, array{role: string, content: string, images?: array}> $messages */
    public function converse(string $system, array $messages, int $timeout = 180): string
    {
        UsageLimit::ensure('token');
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
        UsageLimit::ensure('token');
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

    /** Use a specific model for the next calls (the Copilot's model picker); null = Settings. */
    public function model(?string $model): static
    {
        $clone = clone $this;
        $clone->modelOverride = $model ?: null;

        return $clone;
    }

    /**
     * One step of the Copilot's tool loop (native function calling on the current engine).
     *
     * @param  array<int, array>  $messages  provider-neutral transcript (see EngineDriver::toolChat)
     * @param  array<int, array{name: string, description: string, parameters: array}>  $tools
     */
    public function agentTurn(string $system, array $messages, array $tools, int $timeout = 180): ToolTurn
    {
        UsageLimit::ensure('token');
        if (self::$toolFake) {
            return (self::$toolFake)($system, $messages, $tools);
        }

        @set_time_limit($timeout + 30);
        // "anthropic|claude-sonnet-5-5" picks another provider (with its own key); a bare id stays on the Settings engine.
        [$engine, $model] = EngineKeys::parse($this->modelOverride);
        $started = microtime(true);
        $options = ['timeout' => $timeout, 'max_tokens' => 4000]
            + ($model ? ['model' => $model] : [])
            + ($engine !== $this->engines->current() ? ['api_key' => EngineKeys::for($engine)] : []);

        try {
            $turn = $this->engines->driver($engine)->toolChat($system, $messages, $tools, $options);
        } catch (Throwable $e) {
            $this->record($engine, $model, null, null, $started, $e->getMessage());

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }
        $this->record($engine, $turn->model, $turn->inputTokens, $turn->outputTokens, $started);

        return $turn;
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
