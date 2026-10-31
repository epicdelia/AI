<?php

namespace App\Domains\Media;

use App\Domains\Usage\AiUsage;
use App\Domains\Usage\UsageLimit;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Read a script aloud, to check pacing before filming. Ported from MagicAI's TTSController
 * (processOpenAISpeech → /v1/audio/speech, processElevenLabsSpeech → /v1/text-to-speech/{voice}
 * with the same voice_settings and eleven_multilingual_v2 default).
 *
 * Changes from the original: the model is a real setting (MagicAI passed it in a field named
 * "pace" and sent an unsupported "language" param), long scripts are split under each
 * provider's character limit and joined (MP3 frames concatenate cleanly), [VISUAL] cues and
 * Markdown are stripped so only spoken words are read, and audio is stored privately.
 */
class TextToSpeech
{
    public const OPENAI_VOICES = ['alloy', 'ash', 'ballad', 'coral', 'echo', 'fable', 'nova', 'onyx', 'sage', 'shimmer', 'verse'];

    public const OPENAI_MODEL = 'gpt-4o-mini-tts';

    public const ELEVEN_MODEL = 'eleven_multilingual_v2';

    private const LIMITS = ['openai' => 4000, 'elevenlabs' => 4500];

    public const MAX_CHARS = 30000; // ~4,500 words: a 25-minute script

    /** @var callable|null test hook: fn(string $provider, string $text, string $voice): string (mp3 bytes) */
    public static $fake = null;

    public static function providers(): array
    {
        return array_values(array_filter([
            OpenAiKey::get() ? 'openai' : null,
            Setting::has('elevenlabs_api_key') ? 'elevenlabs' : null,
        ]));
    }

    public static function defaultProvider(): ?string
    {
        $pref = Setting::get('tts_provider', 'auto');
        $available = self::providers();

        return in_array($pref, $available, true) ? $pref : ($available[0] ?? null);
    }

    /** ElevenLabs voices (MagicAI's ElevenlabsService::getVoices). */
    public function elevenVoices(): array
    {
        $resp = Http::timeout(20)->withHeaders(['xi-api-key' => (string) Setting::get('elevenlabs_api_key')])->get('https://api.elevenlabs.io/v1/voices');
        if ($resp->failed()) {
            throw new RuntimeException("ElevenLabs voices failed ({$resp->status()}).");
        }

        return collect($resp->json('voices') ?? [])->map(fn ($v) => [
            'id' => $v['voice_id'], 'name' => $v['name'], 'preview_url' => $v['preview_url'] ?? null,
            'labels' => array_values(array_filter((array) ($v['labels'] ?? []))),
        ])->values()->all();
    }

    /** What would actually be said: no [VISUAL]/[B-ROLL] cues, headings, list markers or Markdown. */
    public static function spoken(string $script): string
    {
        $t = preg_replace('/\[(VISUAL|B-ROLL|TEXT|SFX|MUSIC|CUT|VERIFY)[^\]]*\]/i', '', $script);
        $t = preg_replace('/\[YOUR STORY:[^\]]*\]/i', '(your story here)', $t);
        // Stop at the post-script sections the writer adds (titles, chapters, claims).
        $t = preg_split('/^#{1,3}\s*(Title options|Thumbnail text|Chapters|Claims list)/mi', $t)[0];
        $t = preg_replace('/^#{1,6}\s.*$/m', '', $t);                 // chapter headings aren't spoken
        $t = preg_replace('/^\s*([-*+]|\d+[.)])\s+/m', '', $t);
        $t = str_replace(['**', '__', '`', '>'], '', $t);
        $t = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $t);
        $t = preg_replace("/[ \t]+/", ' ', $t);

        return trim(preg_replace("/\n{3,}/", "\n\n", $t));
    }

    /** Split at paragraph, then sentence boundaries, under $limit characters each. */
    public static function split(string $text, int $limit): array
    {
        $parts = [];
        $buf = '';
        foreach (preg_split("/\n\s*\n/", $text) as $para) {
            $sentences = mb_strlen($para) > $limit ? preg_split('/(?<=[.!?])\s+/u', $para) : [$para];
            foreach ($sentences as $s) {
                while (mb_strlen($s) > $limit) { // one enormous sentence: hard cut
                    $parts[] = mb_substr($s, 0, $limit);
                    $s = mb_substr($s, $limit);
                }
                if ($buf !== '' && mb_strlen($buf) + mb_strlen($s) + 2 > $limit) {
                    $parts[] = $buf;
                    $buf = '';
                }
                $buf .= ($buf === '' ? '' : "\n\n").$s;
            }
        }
        if (trim($buf) !== '') {
            $parts[] = $buf;
        }

        return array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));
    }

    /**
     * @param  array{provider?: string, voice?: string, speed?: float, style?: string, script_id?: int, title?: string}  $opts
     */
    public function speak(string $text, array $opts = []): MediaFile
    {
        $provider = $opts['provider'] ?? self::defaultProvider();
        if (! $provider || ! in_array($provider, self::providers(), true) && ! self::$fake) {
            throw new RuntimeException('Read-aloud needs an OpenAI key or an ElevenLabs key in Settings.');
        }
        $text = self::spoken($text);
        if (mb_strlen($text) < 2) {
            throw new RuntimeException('Nothing to read: the text is empty once cues and headings are removed.');
        }
        if (mb_strlen($text) > self::MAX_CHARS) {
            throw new RuntimeException('That’s longer than '.number_format(self::MAX_CHARS).' characters. Select a part of the script to hear instead.');
        }
        UsageLimit::ensure('character', mb_strlen($text));
        $voice = $opts['voice'] ?? Setting::get('tts_voice') ?: ($provider === 'openai' ? 'coral' : null);
        if (! $voice) {
            throw new RuntimeException('Pick an ElevenLabs voice first.');
        }

        @set_time_limit(600);
        $started = microtime(true);
        $audio = '';
        try {
            foreach (self::split($text, self::LIMITS[$provider] ?? 4000) as $part) {
                $audio .= self::$fake ? (self::$fake)($provider, $part, $voice)
                    : ($provider === 'openai' ? $this->openai($part, $voice, $opts) : $this->eleven($part, $voice, $opts));
            }
            $this->record($provider, $started, mb_strlen($text));
        } catch (Throwable $e) {
            $this->record($provider, $started, mb_strlen($text), $e->getMessage());

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }

        $words = str_word_count($text);

        return MediaFile::store('audio', $audio, 'mp3', 'audio/mpeg', [
            'script_id' => $opts['script_id'] ?? null,
            'title' => mb_substr($opts['title'] ?? mb_strimwidth($text, 0, 60, '…'), 0, 255),
            'provider' => $provider,
            'model' => $provider === 'openai' ? self::OPENAI_MODEL : self::ELEVEN_MODEL,
            'meta' => ['voice' => $voice, 'chars' => mb_strlen($text), 'words' => $words, 'est_seconds' => (int) round($words / 150 * 60 / max(0.5, (float) ($opts['speed'] ?? 1)))],
        ]);
    }

    private function openai(string $text, string $voice, array $opts): string
    {
        $body = [
            'model' => self::OPENAI_MODEL,
            'input' => $text,
            'voice' => in_array($voice, self::OPENAI_VOICES, true) ? $voice : 'coral',
            'response_format' => 'mp3',
            'speed' => max(0.25, min(4.0, (float) ($opts['speed'] ?? 1))),
            'instructions' => $opts['style'] ?? 'Read this like a YouTuber talking straight to camera: natural, warm, conversational, with energy on the hook.',
        ];
        $resp = Http::timeout(180)->withToken((string) OpenAiKey::get())->post('https://api.openai.com/v1/audio/speech', $body);
        if ($resp->failed()) {
            throw new RuntimeException("Read-aloud failed ({$resp->status()}): ".mb_substr((string) ($resp->json('error.message') ?? $resp->body()), 0, 200));
        }

        return $resp->body();
    }

    /** MagicAI's voice_settings: similarity 0.75, stability 0.95, style from "pace", speaker boost. */
    private function eleven(string $text, string $voice, array $opts): string
    {
        $resp = Http::timeout(180)
            ->withHeaders(['xi-api-key' => (string) Setting::get('elevenlabs_api_key'), 'Accept' => 'audio/mpeg'])
            ->post("https://api.elevenlabs.io/v1/text-to-speech/{$voice}", [
                'text' => $text,
                'model_id' => self::ELEVEN_MODEL,
                'voice_settings' => [
                    'similarity_boost' => 0.75,
                    'stability' => 0.95,
                    'style' => (float) ($opts['eleven_style'] ?? 0),
                    'use_speaker_boost' => true,
                    'speed' => max(0.7, min(1.2, (float) ($opts['speed'] ?? 1))),
                ],
            ]);
        if ($resp->failed()) {
            throw new RuntimeException("ElevenLabs failed ({$resp->status()}): ".mb_substr((string) ($resp->json('detail.message') ?? $resp->body()), 0, 200));
        }

        return $resp->body();
    }

    private function record(string $provider, float $started, int $chars, ?string $error = null): void
    {
        try {
            AiUsage::create([
                'feature' => 'read_aloud', 'engine' => $provider,
                'model' => $provider === 'openai' ? self::OPENAI_MODEL : self::ELEVEN_MODEL,
                'unit' => 'character', 'quantity' => $chars, // the unit both providers bill by
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'ok' => $error === null, 'error' => $error ? mb_substr($error, 0, 250) : null,
            ]);
        } catch (Throwable) {
        }
    }
}
