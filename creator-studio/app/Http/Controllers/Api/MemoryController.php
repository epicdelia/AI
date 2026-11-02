<?php

namespace App\Http\Controllers\Api;

use App\Domains\Memory\Embeddings;
use App\Domains\Memory\Memory;
use App\Domains\Memory\MemoryChunk;
use App\Http\Controllers\Controller;
use App\Models\Idea;
use App\Models\Note;
use App\Models\Script;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Memory: search across her own past work ("have I made this before?").
 */
class MemoryController extends Controller
{
    private const TYPES = ['script', 'transcript', 'document', 'note', 'idea'];

    public function search(Request $request, Memory $memory): JsonResponse
    {
        $data = $request->validate([
            'q' => 'required|string|max:500',
            'types' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);
        $types = array_values(array_intersect(self::TYPES, explode(',', (string)($data['types'] ?? ''))));
        $hits = $memory->search($data['q'], (int)($data['limit'] ?? 8), $types);

        return response()->json([
            'results' => array_map(fn ($h) => $h + [
                'link' => self::link($h),
            ] + [
                'excerpt' => self::excerpt($h['content'], $data['q']),
            ], $hits),
        ]);
    }

    public function status(): JsonResponse
    {
        return response()->json([
            'chunks' => MemoryChunk::count(),
            'semantic_chunks' => MemoryChunk::whereNotNull('vector')->count(),
            'sources' => MemoryChunk::query()->selectRaw('source_type, count(distinct source_id) as n')->groupBy('source_type')->pluck('n', 'source_type'),
            'semantic' => Embeddings::available(),
            'model' => Embeddings::model(),
        ]);
    }

    public function reindex(Request $request, Memory $memory): JsonResponse
    {
        @set_time_limit(600);
        if ($request->boolean('fresh')) {
            MemoryChunk::query()->delete();
        }
        $n = $memory->reindexAll();

        return response()->json([
            'message' => "Memory updated: {$n} chunk(s) indexed.",
        ] + $this->status()->getData(true));
    }

    /**
     * Where in the app a hit lives.
     */
    private static function link(array $hit): ?string
    {
        return match ($hit['source_type']) {
            'script', 'transcript' => Script::whereKey($hit['source_id'])->exists() ? "/scripts/{$hit['source_id']}" : null,
            'document' => '/library',
            'note' => Note::whereKey($hit['source_id'])->exists() ? '/flow' : null,
            'idea' => Idea::whereKey($hit['source_id'])->exists() ? '/ideas' : null,
            default => null,
        };
    }

    /**
     * ~280 characters around the first matching term.
     */
    private static function excerpt(string $content, string $query): string
    {
        $pos = null;
        foreach (Memory::terms($query) as $t) {
            $p = mb_stripos($content, $t);
            if ($p !== false && ($pos === null || $p < $pos)) {
                $pos = $p;
            }
        }
        $start = max(0, ($pos ?? 0) - 100);

        return ($start > 0 ? '…' : '') . trim(mb_substr($content, $start, 280)) . (mb_strlen($content) > $start + 280 ? '…' : '');
    }
}
