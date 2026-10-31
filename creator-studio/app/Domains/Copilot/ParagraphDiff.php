<?php

namespace App\Domains\Copilot;

/**
 * Paragraph-level diff for whole-script rewrites, so she can accept or reject each changed
 * passage instead of all-or-nothing. Paragraphs are split on blank lines and aligned with a
 * longest-common-subsequence table (scripts are a few hundred paragraphs at most).
 */
class ParagraphDiff
{
    /** @return array<int, string> */
    public static function split(string $text): array
    {
        $parts = preg_split('/\R\s*\R/u', trim(str_replace("\r\n", "\n", $text))) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));
    }

    /**
     * Ordered hunks: ['type' => 'same', 'before' => [...]] or
     * ['type' => 'change', 'index' => n, 'before' => [...], 'after' => [...]].
     */
    public static function hunks(string $before, string $after): array
    {
        $a = self::split($before);
        $b = self::split($after);
        $n = count($a);
        $m = count($b);

        // LCS lengths from the end, so we can walk forward.
        $l = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $l[$i][$j] = $a[$i] === $b[$j] ? $l[$i + 1][$j + 1] + 1 : max($l[$i + 1][$j], $l[$i][$j + 1]);
            }
        }

        $hunks = [];
        $change = null;
        $flush = function () use (&$hunks, &$change) {
            if ($change && ($change['before'] || $change['after'])) {
                $change['index'] = count(array_filter($hunks, fn ($h) => $h['type'] === 'change'));
                $hunks[] = $change;
            }
            $change = null;
        };

        $i = $j = 0;
        while ($i < $n || $j < $m) {
            if ($i < $n && $j < $m && $a[$i] === $b[$j]) {
                $flush();
                $last = array_key_last($hunks);
                if ($last !== null && $hunks[$last]['type'] === 'same') {
                    $hunks[$last]['before'][] = $a[$i];
                } else {
                    $hunks[] = ['type' => 'same', 'before' => [$a[$i]]];
                }
                $i++;
                $j++;
            } else {
                $change ??= ['type' => 'change', 'before' => [], 'after' => []];
                if ($j < $m && ($i >= $n || $l[$i][$j + 1] >= $l[$i + 1][$j])) {
                    $change['after'][] = $b[$j++];
                } else {
                    $change['before'][] = $a[$i++];
                }
            }
        }
        $flush();

        return $hunks;
    }

    /** Only the changed hunks, for the panel's per-passage review. */
    public static function changes(string $before, string $after): array
    {
        return array_values(array_map(
            fn ($h) => ['index' => $h['index'], 'before' => implode("\n\n", $h['before']), 'after' => implode("\n\n", $h['after'])],
            array_filter(self::hunks($before, $after), fn ($h) => $h['type'] === 'change'),
        ));
    }

    /** Rebuild the text keeping the original paragraphs except for the accepted change hunks. */
    public static function merge(string $before, string $after, array $accepted): string
    {
        $accepted = array_map('intval', $accepted);
        $out = [];
        foreach (self::hunks($before, $after) as $h) {
            if ($h['type'] === 'same') {
                array_push($out, ...$h['before']);
            } else {
                array_push($out, ...(in_array($h['index'], $accepted, true) ? $h['after'] : $h['before']));
            }
        }

        return implode("\n\n", $out);
    }
}
