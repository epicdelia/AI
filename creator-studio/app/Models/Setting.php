<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    /**
     * Settings whose values are stored encrypted.
     */
    public const SECRET = ['youtube_api_key', 'llm_api_key', 'assemblyai_api_key', 'serper_api_key', 'perplexity_api_key', 'embedding_api_key',
        'elevenlabs_api_key', 'fal_api_key', 'vizard_api_key', 'extra_llm_keys',
        // app secrets for Connections (OAuth)
        'youtube_client_secret', 'instagram_client_secret', 'tiktok_client_secret', 'threads_client_secret', 'linkedin_client_secret', 'x_client_secret'];

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
        'schedule_enabled' => '1',        // every automation (scans, agents, auto-posting, replies, workflows); needs schedule:work
        'automation_daily_tokens' => '200000', // AI tokens automations may spend per day ('' = no cap)
        'reasoning_effort' => '',         // '' = model default; none|minimal|low|medium|high|xhigh (resolved per model)
        'monthly_token_limit' => '',      // '' = no limit (UsageLimit, after MagicAI's HasCreditLimit)
        'monthly_image_limit' => '',
        'monthly_tts_char_limit' => '',
        'monthly_video_limit' => '',
        'tour_done' => '',                // first-run tour finished (timestamp)
        'love_notes' => '1',              // little love notes around the studio 💕
        'love_from' => '',                // how the notes are signed, e.g. "D"
        'love_custom' => '',              // his own notes, one per line ({name} = her name)
        'price_input_per_million' => '',  // her own prices, only for the spend estimate
        'price_output_per_million' => '',
        'tts_provider' => 'auto',         // auto | openai | elevenlabs (read-aloud)
        'tts_voice' => '',                // OpenAI voice name or ElevenLabs voice id
        'image_provider' => 'auto',       // auto | openai | fal (thumbnail mockups)
        'image_model' => 'gpt-image-1',
        'lookback_days' => '4',
        'min_outlier_score' => '3',
        'min_views' => '20000',
        'baseline_views' => '',
        'commitment' => '1 long-form video per week',
        'timezone' => 'Europe/London',
        'linkedin_handle' => '',          // social profiles (shown on the platform pages)
        'instagram_handle' => '',
        'x_handle' => '',
        'tiktok_handle' => '',
        'threads_handle' => '',
        'x_premium' => '0',               // 1 = long posts allowed on X (25,000 characters)
        'cadence_linkedin' => '',         // posts per week she aims for on each platform
        'cadence_instagram' => '',
        'cadence_x' => '',
        'cadence_tiktok' => '',
        'cadence_threads' => '',
        'cadence_youtube' => '',          // Shorts and community posts (long-form is on Weekly goals)
        'brand_color' => '#d8487e',
        'content_pillars' => '',          // her content pillars, one per line (tags for posts)
        // Make it yours (Settings → Personalise); also sent with auth/status so every device matches
        'nickname' => '',                 // what the studio (and the love notes) call her
        'accent' => 'rose',               // rose | violet | coral | ocean | forest | plum
        'text_size' => 'default',         // default | large
        'density' => 'comfortable',       // comfortable | compact
        'nav_hidden' => '[]',             // sidebar items she hid (paths)
        'dashboard_layout' => '',         // JSON: [{id, hidden}] in her order; '' = default
        'motto' => '',                    // a line of her own under the dashboard greeting
        'start_page' => '/',              // which page the studio opens on
        'nav_mode' => 'top',              // top: mega menus across the top, sidebar as a drawer | sidebar: sidebar kept open
    ];
    public $incrementing = false;
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        if (self::$cache === null) {
            try {
                self::$cache = self::query()->pluck('value', 'key')->all();
            } catch (\Throwable) {
                return $default ?? (self::DEFAULTS[$key] ?? null);
            }
        }
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
        try {
            self::query()->updateOrCreate([
                'key' => $key,
            ], [
                'value' => $value,
            ]);
        } catch (\Throwable) {
            // Table doesn't exist yet (e.g. unmigrated database)
        }
        self::$cache = null;
    }

    public static function has(string $key): bool
    {
        return (string)self::get($key, '') !== '';
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
