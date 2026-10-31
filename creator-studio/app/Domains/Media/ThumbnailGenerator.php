<?php

namespace App\Domains\Media;

use App\Domains\Usage\AiUsage;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Thumbnail mockups. Ported from MagicAI's OpenAIImageService + CreateImageService /
 * CreateImageEditService (gpt-image: /v1/images/generations, or /v1/images/edits with
 * reference images; b64_json out; size/quality/background/output_format as in
 * GptImageParamNormalizer) and FalAIImageService (fal.run, image URL out).
 *
 * Thumbnail-specific: 16:9 landscape (1536x1024), her reference photos so the mockup has her
 * face, and a prompt built for small-screen legibility. MagicAI's buildPrompt() style/
 * lighting/mood attributes are kept.
 */
class ThumbnailGenerator
{
    public const OPENAI_MODELS = ['gpt-image-1', 'gpt-image-1.5', 'gpt-image-2'];

    public const FAL_MODEL = 'fal-ai/flux/dev';

    public const MAX_REFERENCES = 4;

    /** @var callable|null test hook: fn(string $provider, string $prompt, array $refs): string (image bytes) */
    public static $fake = null;

    public static function providers(): array
    {
        return array_values(array_filter([
            OpenAiKey::get() ? 'openai' : null,
            Setting::has('fal_api_key') ? 'fal' : null,
        ]));
    }

    public static function defaultProvider(): ?string
    {
        $pref = Setting::get('image_provider', 'auto');
        $available = self::providers();

        return in_array($pref, $available, true) ? $pref : ($available[0] ?? null);
    }

    /** MagicAI's buildPrompt() attributes plus thumbnail rules. */
    public static function prompt(array $o): string
    {
        $parts = [
            'A YouTube thumbnail, 16:9, designed to be readable at small size on a phone.',
            'Scene: '.trim((string) $o['concept']),
        ];
        if (! empty($o['text'])) {
            $parts[] = 'Large bold text on the image, exactly: "'.trim($o['text']).'". Maximum 5 words, high contrast, never covering the face.';
        } else {
            $parts[] = 'No text on the image.';
        }
        if (! empty($o['use_photos'])) {
            $parts[] = 'The person in the reference photo(s) is the creator: keep her face and likeness accurate, with an expressive, readable expression.';
        }
        $attrs = array_filter([
            ! empty($o['style']) ? "{$o['style']} style" : null,
            ! empty($o['lighting']) ? "{$o['lighting']} lighting" : null,
            ! empty($o['mood']) ? "{$o['mood']} mood" : null,
        ]);
        if ($attrs) {
            $parts[] = implode(', ', $attrs).'.';
        }
        $parts[] = 'One clear focal point, bold colour contrast, uncluttered background.';

        return implode("\n", $parts);
    }

    /**
     * @param  array{concept: string, text?: ?string, style?: ?string, lighting?: ?string, mood?: ?string, use_photos?: bool, provider?: string, quality?: string, script_id?: ?int}  $o
     */
    public function generate(array $o): MediaFile
    {
        $provider = $o['provider'] ?? self::defaultProvider();
        if (! self::$fake && (! $provider || ! in_array($provider, self::providers(), true))) {
            throw new RuntimeException('Thumbnail mockups need an OpenAI key (or a fal.ai key) in Settings.');
        }
        $provider ??= 'openai';
        $refs = ! empty($o['use_photos']) ? MediaFile::where('kind', 'photo')->latest()->take(self::MAX_REFERENCES)->get() : collect();
        if (! empty($o['use_photos']) && $refs->isEmpty()) {
            throw new RuntimeException('Upload a photo of yourself first (Package tab → Your photos).');
        }
        if ($refs->isNotEmpty() && $provider !== 'openai') {
            throw new RuntimeException('Using your photos needs the OpenAI image model. Switch the image provider or untick “use my photos”.');
        }

        $prompt = self::prompt($o);
        $model = $provider === 'openai' ? (in_array(Setting::get('image_model'), self::OPENAI_MODELS, true) ? Setting::get('image_model') : 'gpt-image-1') : self::FAL_MODEL;

        @set_time_limit(300);
        $started = microtime(true);
        try {
            $bytes = self::$fake ? (self::$fake)($provider, $prompt, $refs->all())
                : ($provider === 'openai' ? $this->openai($model, $prompt, $refs->all(), $o['quality'] ?? 'medium') : $this->fal($prompt));
            $this->record($provider, $model, $started);
        } catch (Throwable $e) {
            $this->record($provider, $model, $started, $e->getMessage());

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }

        [$ext, $mime] = self::sniff($bytes);

        return MediaFile::store('thumbnail', $bytes, $ext, $mime, [
            'script_id' => $o['script_id'] ?? null,
            'title' => mb_substr($o['text'] ?? mb_strimwidth($o['concept'], 0, 60, '…'), 0, 255),
            'provider' => $provider,
            'model' => $model,
            'prompt' => $prompt,
            'meta' => ['concept' => $o['concept'], 'text' => $o['text'] ?? null, 'with_photos' => $refs->count()],
        ]);
    }

    /** /images/generations, or /images/edits (multipart, image[]) when reference photos are given. */
    private function openai(string $model, string $prompt, array $refs, string $quality): string
    {
        $quality = in_array($quality, ['low', 'medium', 'high', 'auto'], true) ? $quality : 'medium';
        $http = Http::timeout(240)->connectTimeout(10)->withToken((string) OpenAiKey::get());

        if ($refs) {
            foreach ($refs as $i => $ref) {
                $http = $http->attach('image[]', $ref->contents(), "ref{$i}.".pathinfo($ref->path, PATHINFO_EXTENSION));
            }
            $resp = $http->post('https://api.openai.com/v1/images/edits', [
                'model' => $model, 'prompt' => $prompt, 'n' => 1, 'size' => '1536x1024', 'quality' => $quality,
            ]);
        } else {
            $resp = $http->post('https://api.openai.com/v1/images/generations', [
                'model' => $model, 'prompt' => $prompt, 'n' => 1, 'size' => '1536x1024', 'quality' => $quality,
                'output_format' => 'webp', 'background' => 'opaque',
            ]);
        }

        if ($resp->failed()) {
            throw new RuntimeException("Image generation failed ({$resp->status()}): ".mb_substr((string) ($resp->json('error.message') ?? $resp->body()), 0, 250));
        }
        $b64 = $resp->json('data.0.b64_json');
        if (! $b64) {
            throw new RuntimeException('The image service returned no image.');
        }

        return base64_decode($b64);
    }

    /** fal.run synchronous endpoint: returns an image URL, which is downloaded and stored. */
    private function fal(string $prompt): string
    {
        $resp = Http::timeout(180)->withHeaders(['Authorization' => 'Key '.Setting::get('fal_api_key')])
            ->post('https://fal.run/'.self::FAL_MODEL, ['prompt' => $prompt, 'image_size' => 'landscape_16_9', 'num_images' => 1]);
        if ($resp->failed()) {
            throw new RuntimeException("fal.ai failed ({$resp->status()}): ".mb_substr((string) ($resp->json('detail') ? json_encode($resp->json('detail')) : $resp->body()), 0, 250));
        }
        $url = $resp->json('images.0.url');
        if (! $url) {
            throw new RuntimeException('fal.ai returned no image.');
        }
        if (str_starts_with($url, 'data:')) {
            return base64_decode(substr($url, strpos($url, ',') + 1));
        }
        $img = Http::timeout(60)->get($url);
        if ($img->failed()) {
            throw new RuntimeException("Couldn't download the image from fal.ai.");
        }

        return $img->body();
    }

    /** @return array{0: string, 1: string} extension and MIME from the magic bytes */
    public static function sniff(string $bytes): array
    {
        return match (true) {
            str_starts_with($bytes, "\x89PNG") => ['png', 'image/png'],
            str_starts_with($bytes, "\xFF\xD8") => ['jpg', 'image/jpeg'],
            str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP' => ['webp', 'image/webp'],
            default => throw new RuntimeException('The image service returned something that isn’t an image.'),
        };
    }

    private function record(string $provider, string $model, float $started, ?string $error = null): void
    {
        try {
            AiUsage::create([
                'feature' => 'thumbnail', 'engine' => $provider, 'model' => $model,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'ok' => $error === null, 'error' => $error ? mb_substr($error, 0, 250) : null,
            ]);
        } catch (Throwable) {
        }
    }
}
