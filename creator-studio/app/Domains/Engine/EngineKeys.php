<?php

namespace App\Domains\Engine;

use App\Domains\Engine\Enums\EngineEnum;
use App\Models\Setting;

/**
 * Which API key to use for a given engine, so the Copilot's model picker can use any
 * provider she has a key for, not just the one picked in Settings:
 *   - the engine picked in Settings → the main AI key (may be a comma-separated pool)
 *   - "More AI keys" (one "engine=key" per line, encrypted) → any engine
 *   - the extra OpenAI key → OpenAI; the Perplexity key → Perplexity; the AssemblyAI key → its gateway
 */
class EngineKeys
{
    public static function for(EngineEnum $engine): ?string
    {
        $current = app(EngineManager::class)->current();
        if ($engine === $current && Setting::has('llm_api_key')) {
            return Setting::get('llm_api_key');
        }
        if ($key = self::extra()[$engine->value] ?? null) {
            return $key;
        }

        return match ($engine) {
            EngineEnum::OPENAI => Setting::get('embedding_api_key') ?: null,
            EngineEnum::PERPLEXITY => Setting::get('perplexity_api_key') ?: null,
            EngineEnum::ASSEMBLYAI => Setting::get('assemblyai_api_key') ?: null,
            default => null,
        };
    }

    /**
     * Engines usable right now (custom servers only when they're the engine in Settings).
     */
    public static function available(): array
    {
        $current = app(EngineManager::class)->current();

        return array_values(array_filter(EngineEnum::cases(), fn (EngineEnum $e) => ($e !== EngineEnum::CUSTOM || $e === $current) && self::for($e) !== null));
    }

    /**
     * @return array<string, string> engine value => key, from the "More AI keys" setting
     */
    public static function extra(): array
    {
        $out = [];
        foreach (preg_split('/[\r\n;]+/', (string)Setting::get('extra_llm_keys', '')) as $line) {
            if (preg_match('/^\s*([a-z]+)\s*[=:]\s*(\S+)\s*$/i', $line, $m) && EngineEnum::tryFrom(strtolower($m[1]))) {
                $out[strtolower($m[1])] = $m[2];
            }
        }

        return $out;
    }

    /**
     * "anthropic|claude-sonnet-5-5" → [EngineEnum, model]; a bare model id stays on the Settings engine.
     */
    public static function parse(?string $choice): array
    {
        $current = app(EngineManager::class)->current();
        if (!$choice) {
            return [$current, null];
        }
        if (str_contains($choice, '|')) {
            [$engine, $model] = explode('|', $choice, 2);
            $e = EngineEnum::tryFrom($engine);
            if ($e && self::for($e)) {
                $cleaned = ($model !== '' && $model !== $engine && EngineEnum::tryFrom($model) === null) ? $model : null;

                return [$e, $cleaned];
            }

            // Engine unavailable: fall back to current engine's default/configured model
            return [$current, null];
        }

        // If a bare engine name was passed (e.g. "gemini" or "openai"), use default model
        if (($e = EngineEnum::tryFrom($choice)) !== null) {
            return [self::for($e) ? $e : $current, null];
        }

        return [$current, $choice];
    }
}
