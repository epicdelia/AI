<?php

namespace App\Services;

/**
 * Real YouTube chapter times without an AI call: each script section's opening words are
 * found in the timestamped recording transcript ("[1:23] text" blocks, about 30 seconds each),
 * and the time inside a block is estimated from where the words start. Without a timestamped
 * transcript it falls back to ScriptStructure's spoken-pace estimate, and says so.
 */
class ChapterAligner
{
    public const MIN_GAP = 10;     // YouTube wants each chapter to be 10 seconds or longer
    public const MIN_SCORE = 0.35; // share of a section's opening words that must be found

    /**
     * @return array<int, array{start: int, end: ?int, words: array<int, string>}>
     */
    public static function blocks(string $transcript): array
    {
        preg_match_all('/\[(?:(\d+):)?(\d{1,2}):(\d{2})\]\s*([^\[]*)/u', $transcript, $m, PREG_SET_ORDER);
        $blocks = [];
        foreach ($m as $b) {
            $blocks[] = [
                'start' => (int)$b[1] * 3600 + (int)$b[2] * 60 + (int)$b[3],
                'end' => null,
                'words' => self::words($b[4], 1),
            ];
        }
        foreach ($blocks as $i => &$b) {
            $b['end'] = $blocks[$i + 1]['start'] ?? null;
        }

        return $blocks;
    }

    /**
     * Lower-case words of at least $min letters (short words match everywhere, so they're noise).
     */
    public static function words(string $text, int $min = 4): array
    {
        preg_match_all('/[\p{L}\p{N}\']+/u', mb_strtolower($text), $m);

        return array_values(array_filter($m[0], fn ($w) => mb_strlen($w) >= $min));
    }

    /**
     * @return array{source: string, chapters: ?string, matched: int, sections: int}
     *         source is "transcript" (real times) or "estimate" (from the script's length)
     */
    public static function align(string $body, ?string $transcript): array
    {
        $structure = ScriptStructure::analyse($body);
        $sections = array_values(array_filter($structure['sections'], fn ($s) => $s['words'] > 0));
        $blocks = self::blocks((string)$transcript);

        if (count($blocks) >= 3 && count($sections) >= 3) {
            $lines = [];
            $matched = 0;
            $from = 0;
            $last = -self::MIN_GAP;
            foreach ($sections as $k => $sec) {
                $time = $k === 0 ? 0 : self::find(array_slice(self::words($sec['opening']), 0, 15), $blocks, $from);
                if ($time === null || $time - $last < self::MIN_GAP) {
                    continue;
                }
                if ($k > 0) {
                    $matched++;
                }
                $lines[] = ScriptStructure::clock($time) . ' ' . ScriptStructure::chapterTitle($sec['title']);
                $last = $time;
            }
            if (count($lines) >= 3) {
                return [
                    'source' => 'transcript',
                    'chapters' => implode("\n", $lines),
                    'matched' => $matched,
                    'sections' => count($sections),
                ];
            }
        }

        return [
            'source' => 'estimate',
            'chapters' => $structure['chapters'],
            'matched' => 0,
            'sections' => count($sections),
        ];
    }

    /**
     * Seconds where these words start, searching blocks from $from onward (advanced on a match).
     */
    private static function find(array $want, array $blocks, int &$from): ?int
    {
        if (count($want) < 2) {
            return null;
        }
        $best = null;
        $bestScore = 0.0;
        for ($i = $from; $i < count($blocks); $i++) {
            // A section can start near the end of a block, so look at this block plus the next.
            $hay = array_merge($blocks[$i]['words'], $blocks[$i + 1]['words'] ?? []);
            $found = count(array_intersect(array_unique($want), $hay));
            $score = $found / count(array_unique($want));
            if ($score > $bestScore + 0.001) {
                [$best, $bestScore] = [$i, $score];
            }
        }
        if ($best === null || $bestScore < self::MIN_SCORE) {
            return null;
        }
        $from = $best;

        // Where in the block do the words begin? Interpolate across the block's duration.
        $b = $blocks[$best];
        $pos = null;
        foreach ($b['words'] as $j => $w) {
            if (in_array($w, $want, true)) {
                $pos = $j;
                break;
            }
        }
        if ($pos === null) { // it starts in the next block
            return $blocks[$best + 1]['start'] ?? $b['start'];
        }
        $span = ($b['end'] ?? $b['start'] + 30) - $b['start'];

        return $b['start'] + (int)floor($span * $pos / max(1, count($b['words'])));
    }
}
