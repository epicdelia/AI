<?php

namespace App\Domains\Media;

use App\Domains\Usage\AiUsage;
use App\Models\Setting;
use App\Services\Transcription;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Remove background noise from a recording. Port of MagicAI's AIController::audioIsolator
 * (ElevenLabs POST /v1/audio-isolation, multipart "audio", same accepted formats), returning
 * the cleaned audio as private media instead of a public file.
 */
class AudioCleaner
{
    public const TYPES = ['ogg', 'mp3', 'mp4', 'mpeg', 'mpga', 'm4a', 'wav', 'webm', 'mov'];

    /** @var callable|null test hook: fn(string $path): string (cleaned mp3 bytes) */
    public static $fake = null;

    public static function available(): bool
    {
        return self::$fake !== null || Setting::has('elevenlabs_api_key');
    }

    public function clean(UploadedFile $file, ?int $scriptId = null): MediaFile
    {
        if (! self::available()) {
            throw new RuntimeException('Audio clean-up needs an ElevenLabs key in Settings.');
        }
        $ext = Str::lower($file->getClientOriginalExtension() ?: (string) $file->guessExtension());
        if (! in_array($ext, self::TYPES, true)) {
            throw new RuntimeException('Invalid file type. Accepted: '.implode(', ', self::TYPES).'.');
        }

        @set_time_limit(900);
        $started = microtime(true);
        try {
            if (self::$fake) {
                $bytes = (self::$fake)($file->getRealPath());
            } else {
                $resp = Http::timeout(900)->withHeaders(['xi-api-key' => (string) Setting::get('elevenlabs_api_key')])
                    ->attach('audio', fopen($file->getRealPath(), 'rb'), 'recording.'.$ext)
                    ->post('https://api.elevenlabs.io/v1/audio-isolation');
                if ($resp->failed()) {
                    throw new RuntimeException("ElevenLabs clean-up failed ({$resp->status()}): ".mb_substr((string) ($resp->json('detail.message') ?? $resp->body()), 0, 200));
                }
                $bytes = $resp->body();
            }
            AiUsage::create(['feature' => 'audio_cleanup', 'engine' => 'elevenlabs', 'model' => 'audio-isolation', 'unit' => 'file', 'quantity' => 1,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000)]);
        } catch (Throwable $e) {
            AiUsage::create(['feature' => 'audio_cleanup', 'engine' => 'elevenlabs', 'model' => 'audio-isolation', 'unit' => 'file', 'quantity' => 0, 'ok' => false, 'error' => mb_substr($e->getMessage(), 0, 250)]);

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }

        return MediaFile::store('audio', $bytes, 'mp3', 'audio/mpeg', [
            'script_id' => $scriptId,
            'title' => 'Cleaned: '.mb_substr(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 0, 120),
            'provider' => 'elevenlabs', 'model' => 'audio-isolation',
            'meta' => ['source' => $file->getClientOriginalName(), 'cleaned' => true],
        ]);
    }

    public static function uploadLimitMb(): int
    {
        return Transcription::uploadLimitMb();
    }
}
