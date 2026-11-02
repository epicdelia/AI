<?php

namespace App\Domains\Search;

use App\Domains\Engine\EngineManager;
use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Usage\AiUsage;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Real-time web search, ported from MagicAI's AIChatController "realtime" branch:
 * Serper (google.serper.dev/search, X-API-KEY) or Perplexity (sonar via chat completions).
 *
 * Unlike the original, the key never leaves the server (MagicAI base64-split the Serper key
 * into the page for client-side calls), and both providers return the same normalised shape.
 */
class WebSearch
{
    /**
     * @var callable|null test hook: fn(string): array
     */
    public static $fake = null;

    public static function provider(): ?string
    {
        $pref = Setting::get('search_provider', 'auto');
        $serper = Setting::has('serper_api_key');
        $perplexity = Setting::has('perplexity_api_key')
            || app(EngineManager::class)->current() === EngineEnum::PERPLEXITY && Setting::has('llm_api_key');

        return match (true) {
            $pref === 'off' => null,
            $pref === 'serper' && $serper, $pref === 'auto' && $serper => 'serper',
            $pref === 'perplexity' && $perplexity, $pref === 'auto' && $perplexity => 'perplexity',
            default => null,
        };
    }

    public static function available(): bool
    {
        return self::$fake !== null || self::provider() !== null;
    }

    /**
     * @return array{provider: string, answer: ?string, results: array<int, array{title: string, link: string, snippet: string, date: ?string}>}
     */
    public function search(string $query, int $count = 6): array
    {
        $query = mb_substr(trim($query), 0, 400);
        if (self::$fake) {
            return (self::$fake)($query) + [
                'provider' => 'fake',
                'answer' => null,
            ];
        }

        $provider = self::provider();
        if (!$provider) {
            throw new RuntimeException('Web search is off. Add a Serper or Perplexity key in Settings.');
        }

        $started = microtime(true);

        try {
            $out = $provider === 'serper' ? $this->serper($query, $count) : $this->perplexity($query);
            $this->record($provider, $started);

            return $out;
        } catch (Throwable $e) {
            $this->record($provider, $started, $e->getMessage());

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Results as compact lines for a prompt.
     */
    public static function forPrompt(array $search): string
    {
        $lines = [];
        if (!empty($search['answer'])) {
            $lines[] = 'Summary from the search engine: ' . mb_substr($search['answer'], 0, 1500);
        }
        foreach ($search['results'] as $i => $r) {
            $lines[] = '[' . ($i + 1) . "] {$r['title']} ({$r['link']})" . ($r['date'] ? " {$r['date']}" : '') . ($r['snippet'] ? ": {$r['snippet']}" : '');
        }

        return $lines ? implode("\n", $lines) : '(no results)';
    }

    private function serper(string $query, int $count): array
    {
        $resp = Http::timeout(20)->connectTimeout(8)
            ->withHeaders([
                'X-API-KEY' => (string)Setting::get('serper_api_key'),
                'Content-Type' => 'application/json',
            ])
            ->post('https://google.serper.dev/search', [
                'q' => $query,
                'num' => max(3, min(10, $count)),
            ]);
        if ($resp->failed()) {
            throw new RuntimeException("Web search failed ({$resp->status()}): " . mb_substr((string)($resp->json('message') ?? $resp->body()), 0, 200));
        }

        $results = collect($resp->json('organic') ?? [])->take($count)->map(fn ($r) => [
            'title' => (string)($r['title'] ?? ''),
            'link' => (string)($r['link'] ?? ''),
            'snippet' => (string)($r['snippet'] ?? ''),
            'date' => $r['date'] ?? null,
        ])->filter(fn ($r) => $r['link'] !== '')->values()->all();

        $box = $resp->json('answerBox');
        $answer = is_array($box) ? ($box['answer'] ?? $box['snippet'] ?? null) : null;

        return [
            'provider' => 'serper',
            'answer' => $answer,
            'results' => $results,
        ];
    }

    private function perplexity(string $query): array
    {
        $key = Setting::get('perplexity_api_key') ?: Setting::get('llm_api_key');
        $resp = Http::timeout(60)->connectTimeout(8)->withToken((string)$key)
            ->post('https://api.perplexity.ai/chat/completions', [
                'model' => 'sonar',
                'messages' => [[
                    'role' => 'user',
                    'content' => $query,
                ]],
            ]);
        if ($resp->failed()) {
            throw new RuntimeException("Web search failed ({$resp->status()}): " . mb_substr((string)($resp->json('error.message') ?? $resp->body()), 0, 200));
        }

        // Newer responses carry search_results; older ones only a list of citation URLs.
        $results = collect($resp->json('search_results') ?? [])->map(fn ($r) => [
            'title' => (string)($r['title'] ?? $r['url'] ?? ''),
            'link' => (string)($r['url'] ?? ''),
            'snippet' => (string)($r['snippet'] ?? ''),
            'date' => $r['date'] ?? null,
        ]);
        if ($results->isEmpty()) {
            $results = collect($resp->json('citations') ?? [])->map(fn ($url) => [
                'title' => (string)$url,
                'link' => (string)$url,
                'snippet' => '',
                'date' => null,
            ]);
        }

        return [
            'provider' => 'perplexity',
            'answer' => $resp->json('choices.0.message.content'),
            'results' => $results->filter(fn ($r) => $r['link'] !== '')->values()->all(),
        ];
    }

    private function record(string $provider, float $started, ?string $error = null): void
    {
        try {
            AiUsage::create([
                'feature' => 'web_search',
                'engine' => $provider,
                'model' => $provider === 'serper' ? 'google' : 'sonar',
                'unit' => 'search',
                'quantity' => 1,
                'duration_ms' => (int)round((microtime(true) - $started) * 1000),
                'ok' => $error === null,
                'error' => $error ? mb_substr($error, 0, 250) : null,
            ]);
        } catch (Throwable) {
            // stats never block a search
        }
    }
}
