<?php

namespace App\Services;

use App\Domains\Engine\EngineManager;
use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Usage\AiUsage;
use App\Models\Setting;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Speech to text for uploaded recordings. Ported from MagicAI's AIController::audioOutput
 * (OpenAI Whisper, verbose_json), plus AssemblyAI pre-recorded transcription, which takes
 * whole video files and reuses the dictation key.
 *
 * Unlike the original, uploads are kept in private storage and deleted once transcribed.
 */
class Transcription
{
    /** Same list MagicAI accepted, plus common camera/phone formats AssemblyAI handles. */
    public const AUDIO_TYPES = ['mp3', 'mp4', 'mpeg', 'mpga', 'm4a', 'wav', 'webm', 'ogg', 'mov', 'mkv', 'aac', 'flac'];

    public const WHISPER_MAX_MB = 25; // OpenAI's own upload cap

    /** Seconds between AssemblyAI status checks (tests set 0). */
    public static int $pollSeconds = 3;

    /** @var callable|null test hook: fn(string $path, string $engine): array{text: string, segments: array} */
    public static $fake = null;

    /** Which engine will be used, or null if none is configured. */
    public static function engine(): ?string
    {
        $pref = Setting::get('transcription_engine', 'auto');
        $hasAssembly = Setting::has('assemblyai_api_key');
        $hasOpenAi = app(EngineManager::class)->current() === EngineEnum::OPENAI && Setting::has('llm_api_key');

        return match (true) {
            $pref === 'assemblyai' && $hasAssembly, $pref === 'auto' && $hasAssembly => 'assemblyai',
            $pref === 'openai' && $hasOpenAi, $pref === 'auto' && $hasOpenAi => 'openai',
            default => null,
        };
    }

    /** Largest upload PHP will accept, in MB. */
    public static function uploadLimitMb(): int
    {
        $toMb = function (string $v): int {
            $n = (int) $v;

            return match (strtolower(substr(trim($v), -1))) {
                'g' => $n * 1024,
                'm' => $n,
                'k' => (int) ceil($n / 1024),
                default => (int) ceil($n / 1048576),
            };
        };

        return max(1, min($toMb((string) ini_get('upload_max_filesize')), $toMb((string) ini_get('post_max_size'))));
    }

    /** @return array{text: string, segments: array<int, array{start: float, text: string}>, engine: string, duration_ms: int} */
    public function transcribe(UploadedFile $file): array
    {
        $engine = self::engine();
        if (! $engine) {
            throw new RuntimeException('Add an AssemblyAI key (or use OpenAI as your AI engine) in Settings to transcribe recordings.');
        }

        $ext = Str::lower($file->getClientOriginalExtension() ?: (string) $file->guessExtension());
        if (! in_array($ext, self::AUDIO_TYPES, true)) {
            throw new RuntimeException('Unsupported file type. Accepted: '.implode(', ', self::AUDIO_TYPES).'.');
        }
        if ($engine === 'openai' && $file->getSize() > self::WHISPER_MAX_MB * 1048576) {
            throw new RuntimeException('Whisper accepts files up to '.self::WHISPER_MAX_MB.' MB. Export the audio only (m4a/mp3), or add an AssemblyAI key for full video files.');
        }

        $path = $file->storeAs('recordings', Str::random(12).'.'.$ext, 'local');
        $full = storage_path('app/private/'.$path);
        if (! is_file($full)) {
            $full = storage_path('app/'.$path);
        }

        $started = microtime(true);
        try {
            $result = self::$fake ? (self::$fake)($full, $engine)
                : ($engine === 'assemblyai' ? $this->assemblyAi($full) : $this->whisper($full));
            $this->record($engine, $started);
        } catch (Throwable $e) {
            $this->record($engine, $started, $e->getMessage());

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        } finally {
            @unlink($full);
        }

        return $result + ['engine' => $engine, 'duration_ms' => (int) round((microtime(true) - $started) * 1000)];
    }

    /** MagicAI's call: whisper-1 with verbose_json for segment timing. */
    private function whisper(string $path): array
    {
        @set_time_limit(600);
        $resp = Http::timeout(600)->withToken((string) Setting::get('llm_api_key'))
            ->attach('file', fopen($path, 'rb'), basename($path))
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
                'response_format' => 'verbose_json',
            ]);
        if ($resp->failed()) {
            throw new RuntimeException("Transcription failed ({$resp->status()}): ".mb_substr((string) ($resp->json('error.message') ?? $resp->body()), 0, 300));
        }

        return [
            'text' => trim((string) $resp->json('text')),
            'segments' => collect($resp->json('segments') ?? [])->map(fn ($s) => ['start' => round((float) $s['start'], 1), 'text' => trim($s['text'])])->all(),
        ];
    }

    /** Upload, request a transcript, poll until done. */
    private function assemblyAi(string $path): array
    {
        @set_time_limit(1800);
        $key = (string) Setting::get('assemblyai_api_key');
        $api = Http::withHeaders(['Authorization' => $key])->baseUrl('https://api.assemblyai.com/v2');

        $upload = Http::withHeaders(['Authorization' => $key])->timeout(900)
            ->withBody(Utils::streamFor(fopen($path, 'rb')), 'application/octet-stream')
            ->post('https://api.assemblyai.com/v2/upload');
        if ($upload->failed() || ! $upload->json('upload_url')) {
            throw new RuntimeException("Upload to AssemblyAI failed ({$upload->status()}): ".mb_substr($upload->body(), 0, 300));
        }

        $job = $api->timeout(30)->post('transcript', [
            'audio_url' => $upload->json('upload_url'),
            'language_detection' => true,
            'punctuate' => true,
            'format_text' => true,
        ]);
        if ($job->failed() || ! $job->json('id')) {
            throw new RuntimeException("AssemblyAI refused the job ({$job->status()}): ".mb_substr($job->body(), 0, 300));
        }

        $deadline = time() + 1700;
        do {
            sleep(self::$pollSeconds);
            $poll = $api->timeout(30)->get('transcript/'.$job->json('id'));
            $status = $poll->json('status');
            if ($status === 'error') {
                throw new RuntimeException('AssemblyAI: '.$poll->json('error'));
            }
        } while ($status !== 'completed' && time() < $deadline);

        if ($status !== 'completed') {
            throw new RuntimeException('Transcription is taking too long. Try a shorter file or the audio track only.');
        }

        // Group words into ~sentence segments with start times for the chapters prompt.
        $segments = [];
        $current = null;
        foreach ($poll->json('words') ?? [] as $w) {
            $current ??= ['start' => round($w['start'] / 1000, 1), 'text' => ''];
            $current['text'] .= ($current['text'] === '' ? '' : ' ').$w['text'];
            if (preg_match('/[.!?]$/', $w['text']) || strlen($current['text']) > 240) {
                $segments[] = $current;
                $current = null;
            }
        }
        if ($current) {
            $segments[] = $current;
        }

        return ['text' => trim((string) $poll->json('text')), 'segments' => $segments];
    }

    private function record(string $engine, float $started, ?string $error = null): void
    {
        try {
            AiUsage::create([
                'feature' => 'transcription',
                'engine' => $engine,
                'model' => $engine === 'openai' ? 'whisper-1' : 'assemblyai-universal',
                'unit' => 'file', 'quantity' => 1,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'ok' => $error === null,
                'error' => $error ? mb_substr($error, 0, 250) : null,
            ]);
        } catch (Throwable) {
            // never block a transcript on stats
        }
    }
}
