<?php

namespace App\Domains\Engine;

use App\Domains\Engine\Enums\EngineEnum;
use App\Models\Setting;
use App\Models\User;

/**
 * Which API key to use for a given engine, prioritizing the signed-in user's
 * encrypted keys linked to their account in the database (BYOK):
 *   - User's database key (user_api_keys table) -> specific to Delia / user
 *   - The engine picked in Settings -> the main studio platform AI key
 *   - "More AI keys" (one "engine=key" per line, encrypted) -> any engine
 *   - Provider-specific settings keys (ElevenLabs, Fal, OpenAI, Perplexity, AssemblyAI)
 */
class EngineKeys
{
    public static function for(EngineEnum $engine, ?User $user = null): ?string
    {
        $user = $user ?? auth()->user();

        // 1. Check user-linked encrypted key in database (BYOK)
        if ($user && ($userKey = $user->getApiKeyFor($engine))) {
            return $userKey;
        }

        // 2. Main engine key from Settings
        $current = app(EngineManager::class)->current();
        if ($engine === $current && Setting::has('llm_api_key')) {
            return Setting::get('llm_api_key');
        }

        // 3. Extra engine keys
        if ($key = self::extra()[$engine->value] ?? null) {
            return $key;
        }

        // 4. Provider-specific fallback keys
        return match ($engine) {
            EngineEnum::OPENAI => Setting::get('embedding_api_key') ?: null,
            EngineEnum::ELEVENLABS => Setting::get('elevenlabs_api_key') ?: null,
            EngineEnum::FAL_AI => Setting::get('fal_api_key') ?: null,
            EngineEnum::PERPLEXITY => Setting::get('perplexity_api_key') ?: null,
            EngineEnum::ASSEMBLYAI => Setting::get('assemblyai_api_key') ?: null,
            default => null,
        };
    }

    /**
     * Engines usable right now by the user (custom servers only when configured).
     */
    public static function available(?User $user = null): array
    {
        $user = $user ?? auth()->user();
        $current = app(EngineManager::class)->current();

        return array_values(array_filter(EngineEnum::cases(), fn (EngineEnum $e) => ($e !== EngineEnum::CUSTOM || $e === $current) && self::for($e, $user) !== null));
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
    public static function parse(?string $choice, ?User $user = null): array
    {
        $user = $user ?? auth()->user();
        $current = app(EngineManager::class)->current();
        if (!$choice) {
            return [$current, null];
        }
        if (str_contains($choice, '|')) {
            [$engine, $model] = explode('|', $choice, 2);
            $e = EngineEnum::tryFrom($engine);
            if ($e && self::for($e, $user)) {
                $cleaned = ($model !== '' && $model !== $engine && EngineEnum::tryFrom($model) === null) ? $model : null;

                return [$e, $cleaned];
            }

            // Engine unavailable: fall back to current engine's default/configured model
            return [$current, null];
        }

        // If a bare engine name was passed (e.g. "gemini" or "openai"), use default model
        if (($e = EngineEnum::tryFrom($choice)) !== null) {
            return [self::for($e, $user) ? $e : $current, null];
        }

        return [$current, $choice];
    }
}

