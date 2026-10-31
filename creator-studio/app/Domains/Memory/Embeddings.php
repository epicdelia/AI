<?php

namespace App\Domains\Memory;

use App\Domains\Engine\EngineManager;
use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Usage\AiUsage;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Text embeddings for Memory search (MagicAI used OpenAI ada-002 one chunk per call; this
 * batches chunks and works with any provider that has an OpenAI-style /embeddings endpoint).
 *
 * Order of preference: a dedicated OpenAI embedding key, then the main AI engine when it
 * offers embeddings. Anthropic, DeepSeek, xAI etc. don't, so Memory falls back to keywords.
 */
class Embeddings
{
    public const BATCH = 64;

    /** @var callable|null test hook: fn(array $texts): array<int, array<float>> */
    public static $fake = null;

    private const DEFAULT_MODELS = [
        'openai' => 'text-embedding-3-small',
        'gemini' => 'gemini-embedding-001',
        'together' => 'BAAI/bge-base-en-v1.5',
    ];

    /** @return array{base: string, key: string, model: string}|null */
    public static function config(): ?array
    {
        if (self::$fake) {
            return ['base' => 'fake', 'key' => 'fake', 'model' => 'fake-embed'];
        }
        $custom = trim((string) Setting::get('embedding_model', ''));

        if (Setting::has('embedding_api_key')) {
            return ['base' => 'https://api.openai.com/v1', 'key' => (string) Setting::get('embedding_api_key'), 'model' => $custom ?: self::DEFAULT_MODELS['openai']];
        }

        $engine = app(EngineManager::class)->current();
        if (! Setting::has('llm_api_key')) {
            return null;
        }
        $model = $custom ?: (self::DEFAULT_MODELS[$engine->value] ?? null);
        $supports = in_array($engine, [EngineEnum::OPENAI, EngineEnum::GEMINI, EngineEnum::TOGETHER], true)
            || ($engine === EngineEnum::CUSTOM && $custom !== '');
        if (! $supports || ! $model) {
            return null;
        }
        $base = $engine === EngineEnum::CUSTOM ? rtrim((string) Setting::get('llm_base_url'), '/') : $engine->baseUrl();

        return ['base' => $base, 'key' => (string) Setting::get('llm_api_key'), 'model' => $model];
    }

    public static function available(): bool
    {
        return self::config() !== null;
    }

    public static function model(): ?string
    {
        return self::config()['model'] ?? null;
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<float>>  one vector per text, same order
     */
    public function embed(array $texts): array
    {
        $cfg = self::config();
        if (! $cfg) {
            throw new RuntimeException('No embeddings provider configured.');
        }
        if (self::$fake) {
            return (self::$fake)(array_values($texts));
        }

        $vectors = [];
        $started = microtime(true);
        try {
            foreach (array_chunk(array_values($texts), self::BATCH) as $batch) {
                $resp = Http::timeout(60)->connectTimeout(8)->withToken($cfg['key'])
                    ->post($cfg['base'].'/embeddings', ['model' => $cfg['model'], 'input' => $batch]);
                if ($resp->failed()) {
                    throw new RuntimeException("Embeddings failed ({$resp->status()}): ".mb_substr((string) ($resp->json('error.message') ?? $resp->body()), 0, 200));
                }
                $rows = collect($resp->json('data') ?? [])->sortBy('index')->pluck('embedding')->all();
                if (count($rows) !== count($batch)) {
                    throw new RuntimeException('Embeddings provider returned the wrong number of vectors.');
                }
                array_push($vectors, ...$rows);
            }
            $this->record($cfg['model'], $started);
        } catch (Throwable $e) {
            $this->record($cfg['model'], $started, $e->getMessage());

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }

        return $vectors;
    }

    /** Cosine similarity, ported from MagicAI's VectorService (guarded against zero vectors). */
    public static function cosine(array $v1, array $v2): float
    {
        $dot = $n1 = $n2 = 0.0;
        foreach ($v1 as $i => $value) {
            $other = $v2[$i] ?? 0.0;
            $dot += $value * $other;
            $n1 += $value * $value;
            $n2 += $other * $other;
        }
        $den = sqrt($n1) * sqrt($n2);

        return $den > 0 ? $dot / $den : 0.0;
    }

    private function record(string $model, float $started, ?string $error = null): void
    {
        try {
            AiUsage::create([
                'feature' => 'memory:embed', 'engine' => 'embeddings', 'model' => $model, 'unit' => 'request', 'quantity' => 1,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'ok' => $error === null, 'error' => $error ? mb_substr($error, 0, 250) : null,
            ]);
        } catch (Throwable) {
        }
    }
}
