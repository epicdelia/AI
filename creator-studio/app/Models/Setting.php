<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    /** Settings whose values are stored encrypted. */
    public const SECRET = ['youtube_api_key', 'llm_api_key', 'assemblyai_api_key', 'serper_api_key', 'perplexity_api_key', 'embedding_api_key'];

    public const DEFAULTS = [
        'creator_name' => '',
        'channel_id' => '',
        'niche' => '',
        'angle' => '',
        'audience' => '',
        'voice_excerpts' => '',
        'tone_of_voice' => '',
        'language' => 'English',
        'specific_instructions' => '',
        'llm_engine' => '',   // empty = guessed from llm_base_url (see EngineManager::current)
        'llm_base_url' => '', // empty = the engine's own default
        'llm_model' => '',    // empty = the engine's default model
        'transcription_engine' => 'auto', // auto | assemblyai | openai
        'search_provider' => 'auto',      // auto | serper | perplexity | off (web search for fact-checks and chat)
        'embedding_model' => '',          // empty = provider default; used for Memory search
        'schedule_enabled' => '1',        // daily scan, feed refresh, Monday check-in (needs schedule:work)
        'lookback_days' => '4',
        'min_outlier_score' => '3',
        'min_views' => '20000',
        'baseline_views' => '',
        'commitment' => '1 long-form video per week',
        'timezone' => 'Europe/London',
    ];

    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        self::$cache ??= self::query()->pluck('value', 'key')->all();
        $value = self::$cache[$key] ?? null;
        if ($value !== null && $value !== '' && in_array($key, self::SECRET, true)) {
            try {
                $value = Crypt::decryptString($value);
            } catch (\Throwable) {
                $value = null;
            }
        }
        if ($value === null || $value === '') {
            return $default ?? (self::DEFAULTS[$key] ?? null);
        }

        return $value;
    }

    public static function put(string $key, ?string $value): void
    {
        $value = $value === null ? null : trim($value);
        if ($value !== null && $value !== '' && in_array($key, self::SECRET, true)) {
            $value = Crypt::encryptString($value);
        }
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        self::$cache = null;
    }

    public static function has(string $key): bool
    {
        return (string) self::get($key, '') !== '';
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}

