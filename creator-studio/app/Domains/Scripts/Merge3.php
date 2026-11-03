<?php

namespace App\Domains\Scripts;

/**
 * Line-based three-way merge for editing a script together: two people start from the same
 * revision (base); whoever saves second has their changes merged with the first person's
 * instead of overwriting them. Where both changed the same lines differently, both versions
 * are kept (mine first) and counted as a conflict, so nothing is ever lost.
 *
 * public/js/merge3.js is the same algorithm in the browser (for edits typed while a save
 * was in flight).
 */
class Merge3
{
    /**
     * @return array{text: string, conflicts: int}
     */
    public static function merge(string $base, string $mine, string $theirs): array
    {
        if ($mine === $theirs || $theirs === $base) {
            return ['text' => $mine, 'conflicts' => 0];
        }
        if ($mine === $base) {
            return ['text' => $theirs, 'conflicts' => 0];
        }
        $b = explode("\n", $base);
        $all = [];
        foreach (self::hunks($b, explode("\n", $mine)) as $h) {
            $all[] = $h + [3 => 'm'];
        }
        foreach (self::hunks($b, explode("\n", $theirs)) as $h) {
            $all[] = $h + [3 => 't'];
        }
        // By start; mine before theirs at the same start.
        usort($all, fn ($x, $y) => [$x[0], $x[3] === 'm' ? 0 : 1] <=> [$y[0], $y[3] === 'm' ? 0 : 1]);

        $out = [];
        $conflicts = 0;
        $pos = 0;
        $k = 0;
        $count = count($all);
        while ($k < $count) {
            // A region: this hunk plus every later hunk that overlaps it.
            $start = $all[$k][0];
            $end = $all[$k][1];
            $region = [$all[$k]];
            $k++;
            while ($k < $count && ($all[$k][0] < $end || $all[$k][0] === $start)) {
                $region[] = $all[$k];
                $end = max($end, $all[$k][1]);
                $k++;
            }
            array_push($out, ...array_slice($b, $pos, $start - $pos));
            $sides = array_unique(array_column($region, 3));
            $mineSide = self::apply($b, $start, $end, array_filter($region, fn ($h) => $h[3] === 'm'));
            $theirSide = self::apply($b, $start, $end, array_filter($region, fn ($h) => $h[3] === 't'));
            if (count($sides) === 1) {
                $only = reset($sides) === 'm' ? $mineSide : $theirSide;
                array_push($out, ...$only);
            } elseif ($mineSide === $theirSide) {
                array_push($out, ...$mineSide);
            } elseif (count($mineSide) === $end - $start && count($theirSide) === $end - $start) {
                // Same number of lines on all three: settle it line by line.
                for ($x = 0; $x < $end - $start; $x++) {
                    [$bl, $ml, $tl] = [$b[$start + $x], $mineSide[$x], $theirSide[$x]];
                    if ($ml === $tl || $tl === $bl) {
                        $out[] = $ml;
                    } elseif ($ml === $bl) {
                        $out[] = $tl;
                    } else {
                        array_push($out, $ml, $tl);
                        $conflicts++;
                    }
                }
            } else {
                array_push($out, ...$mineSide, ...$theirSide);
                $conflicts++;
            }
            $pos = $end;
        }
        array_push($out, ...array_slice($b, $pos));

        return ['text' => implode("\n", $out), 'conflicts' => $conflicts];
    }

    /**
     * One side's version of base[start, end) with its (non-overlapping) hunks applied.
     *
     * @param  array<int, string>  $b
     */
    private static function apply(array $b, int $start, int $end, array $hunks): array
    {
        $out = [];
        $pos = $start;
        foreach ($hunks as $h) {
            array_push($out, ...array_slice($b, $pos, $h[0] - $pos), ...$h[2]);
            $pos = $h[1];
        }
        array_push($out, ...array_slice($b, $pos, $end - $pos));

        return $out;
    }

    /**
     * The edits that turn $a into $b, as [start, end, replacement lines] over $a.
     *
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     * @return array<int, array{0: int, 1: int, 2: array<int, string>}>
     */
    public static function hunks(array $a, array $b): array
    {
        $n = count($a);
        $mLen = count($b);
        $pre = 0;
        while ($pre < $n && $pre < $mLen && $a[$pre] === $b[$pre]) {
            $pre++;
        }
        $suf = 0;
        while ($suf < $n - $pre && $suf < $mLen - $pre && $a[$n - 1 - $suf] === $b[$mLen - 1 - $suf]) {
            $suf++;
        }
        $a2 = array_slice($a, $pre, $n - $pre - $suf);
        $b2 = array_slice($b, $pre, $mLen - $pre - $suf);
        $x = count($a2);
        $y = count($b2);
        if ($x === 0 && $y === 0) {
            return [];
        }
        if ($x * $y > 4_000_000) {
            // Too big to diff line by line: one replacement of the changed middle.
            return [[$pre, $pre + $x, $b2]];
        }
        // LCS lengths from the end.
        $L = array_fill(0, $x + 1, array_fill(0, $y + 1, 0));
        for ($i = $x - 1; $i >= 0; $i--) {
            for ($j = $y - 1; $j >= 0; $j--) {
                $L[$i][$j] = $a2[$i] === $b2[$j] ? $L[$i + 1][$j + 1] + 1 : max($L[$i + 1][$j], $L[$i][$j + 1]);
            }
        }
        $hunks = [];
        $i = $j = 0;
        $hs = null;
        $repl = [];
        while ($i < $x || $j < $y) {
            if ($i < $x && $j < $y && $a2[$i] === $b2[$j]) {
                if ($hs !== null) {
                    $hunks[] = [$pre + $hs, $pre + $i, $repl];
                    $hs = null;
                    $repl = [];
                }
                $i++;
                $j++;
            } elseif ($j < $y && ($i >= $x || $L[$i][$j + 1] >= $L[$i + 1][$j])) {
                $hs ??= $i;
                $repl[] = $b2[$j];
                $j++;
            } else {
                $hs ??= $i;
                $i++;
            }
        }
        if ($hs !== null) {
            $hunks[] = [$pre + $hs, $pre + $i, $repl];
        }

        return $hunks;
    }
}
