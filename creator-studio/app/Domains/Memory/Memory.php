<?php

namespace App\Domains\Memory;

use App\Domains\Toolkit\Models\Document;
use App\Models\Idea;
use App\Models\Note;
use App\Models\Script;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Memory: search across her own past work so the AI can reuse her real phrasing and notice
 * "you already made this". Ported from MagicAI's processEmbeddings() + VectorService:
 * the same overlapping windows (2,000 characters every ~1,000) and cosine ranking, but
 * multibyte-safe (MagicAI used byte offsets, which split accented characters), batched
 * embeddings, skip-if-unchanged hashing, and a keyword fallback when no embeddings
 * provider is available.
 */
class Memory
{
    public const WINDOW = 2000;
    public const STEP = 1000;

    private const STOPWORDS = ['the', 'and', 'for', 'that', 'this', 'with', 'you', 'your', 'are', 'was', 'but', 'not', 'have', 'from',
        'they', 'what', 'about', 'just', 'like', 'its', "it's", 'can', 'all', 'how', 'why', 'when', 'who', 'did', 'does', 'our', 'out',
        'has', 'had', 'will', 'would', 'there', 'their', 'them', 'then', 'than', 'into', 'some', 'any', 'more', 'very', 'really'];

    public function __construct(private Embeddings $embeddings) {}

    /** The text Memory stores for each kind of source, or null if it shouldn't be indexed. */
    public static function sourcesFor(Model $model): array
    {
        return match (true) {
            $model instanceof Script => array_filter([
                'script' => trim((string) $model->body) !== '' ? [$model->title, $model->body] : null,
                'transcript' => trim((string) $model->transcript) !== '' ? [$model->title.' (what I said on camera)', $model->transcript] : null,
            ]),
            $model instanceof Document => ['document' => [$model->title, $model->content]],
            $model instanceof Note => ['note' => [mb_substr(strtok(trim($model->polished), "\n") ?: 'Dictated note', 0, 120), $model->polished]],
            $model instanceof Idea => ['idea' => [$model->title, $model->asMarkdown()]],
            default => [],
        };
    }

    /** Index (or re-index) one model. Unchanged text is skipped. Returns chunks written. */
    public function index(Model $model): int
    {
        $written = 0;
        $kinds = self::sourcesFor($model);
        $allKinds = $model instanceof Script ? ['script', 'transcript'] : array_keys($kinds);
        foreach ($allKinds as $kind) {
            if (! isset($kinds[$kind])) {
                $this->forget($kind, $model->getKey());

                continue;
            }
            [$title, $text] = $kinds[$kind];
            $written += $this->indexText($kind, $model->getKey(), (string) $title, (string) $text);
        }

        return $written;
    }

    public function indexText(string $type, int $id, string $title, string $text): int
    {
        $text = trim($text);
        $hash = sha1($title."\n".$text.'|'.(Embeddings::model() ?? 'keywords'));
        $existing = MemoryChunk::where('source_type', $type)->where('source_id', $id)->first();
        if ($existing && $existing->hash === $hash) {
            return 0;
        }

        $chunks = self::chunk($text);
        $vectors = [];
        $model = null;
        if ($chunks && Embeddings::available()) {
            try {
                $vectors = $this->embeddings->embed(array_map(fn ($c) => "{$title}\n\n{$c}", $chunks));
                $model = Embeddings::model();
            } catch (Throwable) {
                $vectors = []; // keyword search still works; the next re-index will retry
                $hash = 'retry';
            }
        }

        $this->forget($type, $id);
        foreach ($chunks as $i => $content) {
            MemoryChunk::create([
                'source_type' => $type, 'source_id' => $id, 'title' => mb_substr($title, 0, 255), 'position' => $i,
                'content' => $content, 'vector' => isset($vectors[$i]) ? json_encode($vectors[$i]) : null,
                'model' => isset($vectors[$i]) ? $model : null, 'hash' => $hash,
            ]);
        }

        return count($chunks);
    }

    public function forget(string $type, int $id): void
    {
        MemoryChunk::where('source_type', $type)->where('source_id', $id)->delete();
    }

    public function forgetModel(Model $model): void
    {
        $types = $model instanceof Script ? ['script', 'transcript'] : array_keys(self::sourcesFor($model));
        foreach ($types as $t) {
            $this->forget($t, $model->getKey());
        }
    }

    /** Overlapping windows, cut at whitespace so words aren't split. */
    public static function chunk(string $text): array
    {
        $text = trim(preg_replace("/[ \t]+/u", ' ', $text));
        $len = mb_strlen($text);
        if ($len <= 10) {
            return [];
        }
        if ($len <= self::WINDOW) {
            return [$text];
        }

        $chunks = [];
        for ($start = 0; $start < $len; $start += self::STEP) {
            $piece = mb_substr($text, $start, self::WINDOW);
            if ($start + self::WINDOW < $len && ($cut = mb_strrpos($piece, ' ')) > self::WINDOW * 0.7) {
                $piece = mb_substr($piece, 0, $cut);
            }
            if ($start > 0 && ($sp = mb_strpos($piece, ' ')) !== false && $sp < 40) {
                $piece = mb_substr($piece, $sp + 1); // don't start mid-word
            }
            $chunks[] = trim($piece);
            if ($start + self::WINDOW >= $len) {
                break;
            }
        }

        return array_values(array_filter($chunks, fn ($c) => mb_strlen($c) > 10));
    }

    /**
     * @param  array<int, string>  $types  limit to these source types
     * @return array<int, array{source_type: string, source_id: int, title: string, content: string, score: float}>
     */
    public function search(string $query, int $limit = 5, array $types = [], ?array $exclude = null): array
    {
        $query = trim($query);
        if ($query === '' || ! MemoryChunk::exists()) {
            return [];
        }
        $q = MemoryChunk::query();
        if ($types) {
            $q->whereIn('source_type', $types);
        }
        if ($exclude) { // [type, id] of the item being worked on, so it doesn't find itself
            $q->whereNot(fn ($w) => $w->whereIn('source_type', (array) $exclude[0])->where('source_id', $exclude[1]));
        }

        $terms = self::terms($query);
        $queryVector = null;
        if (Embeddings::available() && (clone $q)->whereNotNull('vector')->exists()) {
            try {
                $queryVector = $this->embeddings->embed([$query])[0];
            } catch (Throwable) {
                $queryVector = null;
            }
        }

        $scored = [];
        foreach ($q->get() as $chunk) {
            $kw = self::keywordScore($terms, $chunk->title.' '.$chunk->content);
            $vec = $queryVector && $chunk->vector ? Embeddings::cosine($queryVector, $chunk->vectorArray()) : null;
            $score = $vec !== null ? $vec + 0.05 * min($kw, 4) : $kw;
            if ($vec === null && $kw <= 0) {
                continue;
            }
            $scored[] = ['chunk' => $chunk, 'score' => $score];
        }
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        // One result per source, best chunk wins.
        $out = [];
        foreach ($scored as $row) {
            $c = $row['chunk'];
            $key = $c->source_type.':'.$c->source_id;
            if (isset($out[$key])) {
                continue;
            }
            $out[$key] = [
                'source_type' => $c->source_type, 'source_id' => $c->source_id, 'title' => $c->title,
                'content' => $c->content, 'score' => round($row['score'], 4), 'semantic' => $queryVector !== null && $c->vector !== null,
            ];
            if (count($out) >= $limit) {
                break;
            }
        }

        return array_values($out);
    }

    /** Matches as a prompt block, MagicAI-style ("content\n\n\n" per match), with titles. */
    public function context(string $query, int $limit = 4, array $types = [], ?array $exclude = null, int $minScore = 0): string
    {
        $hits = array_filter($this->search($query, $limit, $types, $exclude), fn ($h) => $h['semantic'] ? $h['score'] >= 0.3 : $h['score'] >= max(1, $minScore));
        if (! $hits) {
            return '';
        }

        return implode("\n\n\n", array_map(fn ($h) => "[{$h['source_type']}: {$h['title']}]\n".mb_substr($h['content'], 0, 1500), $hits));
    }

    public static function terms(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}\'’]{3,}/u', mb_strtolower($text), $m);

        return array_values(array_unique(array_diff($m[0], self::STOPWORDS)));
    }

    private static function keywordScore(array $terms, string $text): float
    {
        if (! $terms) {
            return 0;
        }
        $hay = mb_strtolower($text);
        $score = 0.0;
        foreach ($terms as $t) {
            $n = mb_substr_count($hay, $t);
            if ($n > 0) {
                $score += 1 + log($n); // presence matters most, repeats a little
            }
        }

        return $score;
    }

    /** Index everything (used by `studio:memory` and after changing the embeddings provider). */
    public function reindexAll(): int
    {
        $n = 0;
        foreach ([Script::class, Document::class, Note::class, Idea::class] as $class) {
            $class::query()->chunkById(50, function ($rows) use (&$n) {
                foreach ($rows as $row) {
                    $n += $this->index($row);
                }
            });
        }

        return $n;
    }
}
