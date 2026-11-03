<?php

namespace App\Domains\Engine\Drivers;

use App\Domains\Engine\Contracts\EngineDriver;
use App\Domains\Engine\Enums\EngineEnum;
use App\Models\Setting;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Shared config lookup: the engine, base URL, model and key(s) all come from Settings.
 */
abstract class BaseDriver implements EngineDriver
{
    public function __construct(
        protected EngineEnum $engine
    ) {
    }

    public function enum(): EngineEnum
    {
        return $this->engine;
    }

    protected function baseUrl(): string
    {
        // A saved base URL only belongs to the engine picked in Settings.
        $saved = trim((string)Setting::get('llm_base_url', ''));
        $url = $this->engine === EngineEnum::CUSTOM ? $saved : ($saved !== '' && EngineEnum::fromBaseUrl($saved) === $this->engine ? $saved : $this->engine->baseUrl());
        if ($url === '') {
            throw new RuntimeException('Set a base URL for your custom AI provider in Settings.');
        }

        return rtrim($url, '/');
    }

    /**
     * $options['model'] overrides Settings for one call (the Copilot's model picker).
     */
    protected function model(array $options = []): string
    {
        $model = ($options['model'] ?? null) ?: (Setting::get('llm_model') ?: $this->engine->defaultModel());

        // Strip any residual engine prefix (e.g. "gemini|gemini-2.5-flash" -> "gemini-2.5-flash")
        if (str_contains($model, '|')) {
            $parts = explode('|', $model, 2);
            $model = $parts[1] !== '' ? $parts[1] : $this->engine->defaultModel();
        }

        // If the model string is just an engine name itself (e.g. "gemini" or "openai"), use default
        if ($model === $this->engine->value || EngineEnum::tryFrom($model) !== null) {
            $model = $this->engine->defaultModel();
        }

        // Google's OpenAI-compatible endpoint expects bare IDs (e.g. "gemini-2.5-flash"), not "models/..."
        if ($this->engine === EngineEnum::GEMINI && str_starts_with($model, 'models/')) {
            $model = substr($model, 7);
        }

        return $model;
    }

    /**
     * Key pool, ported from MagicAI's HasApiKeys::setOpenAiKey(): the key field may hold several
     * comma-separated keys. MagicAI picked one at random per request; here they're tried in a
     * random order and the next one is used when a key is rate-limited or rejected.
     *
     * @return array<int, string>
     */
    protected function keys(array $options = []): array
    {
        $user = ($options['user'] ?? null) instanceof \App\Models\User ? $options['user'] : auth()->user();

        $raw = ($options['api_key'] ?? null)
            ?: ($user?->getApiKeyFor($this->engine)
            ?: \App\Domains\Engine\EngineKeys::for($this->engine, $user));

        $keys = array_values(array_filter(array_map('trim', explode(',', (string)$raw))));
        if (!$keys) {
            throw new RuntimeException("No {$this->engine->label()} key yet. Add one under Settings or your User Profile.");
        }
        shuffle($keys);

        return $keys;
    }

    /**
     * Send with the first key; on 401/403/429 or a 5xx move to the next key in the pool.
     *
     * @param  callable(string $key): Response  $send
     */
    protected function withKeyFailover(callable $send, array $options = []): Response
    {
        $keys = $this->keys($options);
        foreach ($keys as $i => $key) {
            $resp = $send($key);
            $retryable = in_array($resp->status(), [401, 403, 429], true) || $resp->serverError();
            if (!$retryable || $i === count($keys) - 1) {
                return $resp;
            }
        }

        return $resp; // unreachable; keeps static analysis happy
    }

    protected static function clip(mixed $msg): string
    {
        return mb_substr(is_string($msg) ? $msg : (string)json_encode($msg), 0, 300);
    }
}
