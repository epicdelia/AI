<?php

namespace App\Services;

/**
 * Deterministic retention check for a script, from the structure rules in Prompts::scriptSystem():
 * cold open within 30 seconds, chapters with a re-hook every 60 to 90 seconds, a [VISUAL] or
 * [B-ROLL] note on every beat, a short CTA. Free and instant, like the AI-tell linter, and it
 * also gives estimated YouTube chapter timestamps.
 */
class ScriptStructure
{
    public const WPM = 150;                 // spoken words per minute (same as Script::minutes)
    public const COLD_OPEN_SECONDS = 30;
    public const CHAPTER_MAX_SECONDS = 180; // longer than this with no heading is a retention dip
    public const NO_VISUAL_SECONDS = 90;    // talking with no visual change for longer than this
    public const CTA_SECONDS = 15;

    /** Sections the script writer appends after the script (not spoken). */
    private const TAIL = '/^#{1,3}\s*(title options|thumbnail( text)? options|chapters|claims list)\b/i';

    private const CUE = '/\[(?:VISUAL|B-?ROLL|SFX|MUSIC|CUT|GRAPHIC|ON[- ]?SCREEN|TEXT ON SCREEN|SCREEN|EDIT|ZOOM)\b[^\]]*\]/i';

    public static function seconds(int $words): int
    {
        return (int) round($words / self::WPM * 60);
    }

    public static function clock(int $seconds): string
    {
        return intdiv($seconds, 60).':'.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{sections: array, flags: array, total_seconds: int, chapters: ?string, placeholders: array{story: int, verify: int}}
     */
    public static function analyse(string $body): array
    {
        $lines = preg_split('/\R/u', $body);
        $sections = [];
        $current = null;
        $story = $verify = 0;
        $firstStory = $firstVerify = null;
        $stretch = ['words' => 0, 'from' => null];
        $flags = [];

        $close = function () use (&$current, &$sections) {
            if ($current && ($current['words'] > 0 || $current['title'] !== 'Intro')) {
                $sections[] = $current;
            }
            $current = null;
        };
        $endStretch = function (int $line) use (&$stretch, &$flags) {
            $secs = self::seconds($stretch['words']);
            if ($secs > self::NO_VISUAL_SECONDS && $stretch['from']) {
                $flags[] = ['kind' => 'visual', 'line' => $stretch['from'], 'message' => 'About '.self::clock($secs).' of talking with no [VISUAL] or [B-ROLL] note (lines '.$stretch['from'].' to '.$line.'). Give the editor something to cut to.'];
            }
            $stretch = ['words' => 0, 'from' => null];
        };

        foreach ($lines as $i => $raw) {
            $n = $i + 1;
            $line = trim($raw);
            if (preg_match(self::TAIL, $line)) {
                break;
            }
            if (preg_match('/^#{1,6}\s+(.+)$/u', $line, $m)) {
                $close();
                $current = ['title' => trim($m[1], " #*"), 'line' => $n, 'words' => 0, 'cues' => 0];
                continue;
            }
            $current ??= ['title' => 'Intro', 'line' => $n, 'words' => 0, 'cues' => 0];

            $story += $s = preg_match_all('/\[YOUR STORY\b/i', $line);
            $verify += $v = preg_match_all('/\[VERIFY\b/i', $line);
            if ($s && ! $firstStory) {
                $firstStory = $n;
            }
            if ($v && ! $firstVerify) {
                $firstVerify = $n;
            }

            $cues = preg_match_all(self::CUE, $line);
            $current['cues'] += $cues;
            if ($cues) {
                $endStretch($n);
            }
            // Brackets are directions or placeholders, not words she says.
            $words = str_word_count(preg_replace('/\[[^\]]*\]/u', ' ', $line));
            if ($words) {
                $current['words'] += $words;
                $stretch['from'] ??= $n;
                $stretch['words'] += $words;
            }
        }
        $close();
        $endStretch(count($lines));

        $start = 0;
        foreach ($sections as &$sec) {
            $sec['start'] = $start;
            $sec['seconds'] = self::seconds($sec['words']);
            $start += $sec['seconds'];
        }
        unset($sec);
        $total = $start;

        if ($sections) {
            $first = $sections[0];
            if ($first['seconds'] > self::COLD_OPEN_SECONDS + 5) {
                $flags[] = ['kind' => 'open', 'line' => $first['line'], 'message' => "The opening (“{$first['title']}”) runs about ".self::clock($first['seconds']).'. Pay off the title within 30 seconds, then set up.'];
            }
            $last = end($sections);
            if (count($sections) > 1 && preg_match('/\b(cta|outro|subscribe|call to action|end ?screen)\b/i', $last['title']) && $last['seconds'] > self::CTA_SECONDS + 5) {
                $flags[] = ['kind' => 'cta', 'line' => $last['line'], 'message' => 'The CTA runs about '.self::clock($last['seconds']).'. Keep it to 15 seconds: viewers leave as soon as it starts.'];
            }
            foreach ($sections as $k => $sec) {
                if ($k > 0 && $sec['seconds'] > self::CHAPTER_MAX_SECONDS) {
                    $flags[] = ['kind' => 'long', 'line' => $sec['line'], 'message' => "“{$sec['title']}” runs ".self::clock($sec['seconds']).' in one go. Split it, or add a re-hook (a new question or a reveal) halfway.'];
                }
            }
            if ($total >= 240 && count($sections) < 4) {
                $flags[] = ['kind' => 'chapters', 'line' => $sections[0]['line'], 'message' => 'Only '.count($sections).' section(s) for '.self::clock($total).'. Break the body into 3 to 5 chapters, each opening with a mini-hook.'];
            }
        }
        if ($story || $verify) {
            $parts = array_filter([$story ? "{$story} [YOUR STORY]" : null, $verify ? "{$verify} [VERIFY]" : null]);
            $flags[] = ['kind' => 'gaps', 'line' => min(array_filter([$firstStory, $firstVerify])), 'message' => implode(' and ', $parts).' left to fill before filming.'];
        }
        usort($flags, fn ($a, $b) => $a['line'] <=> $b['line']);

        return [
            'sections' => $sections,
            'flags' => $flags,
            'total_seconds' => $total,
            'chapters' => self::chapters($sections),
            'placeholders' => ['story' => $story, 'verify' => $verify],
        ];
    }

    /** YouTube chapter lines (0:00 first, at least 3 chapters, each 10 seconds or more), or null. */
    private static function chapters(array $sections): ?string
    {
        $usable = array_values(array_filter($sections, fn ($s) => $s['seconds'] >= 10));
        if (count($usable) < 3) {
            return null;
        }
        $usable[0]['start'] = 0;

        return implode("\n", array_map(
            fn ($s) => self::clock($s['start']).' '.preg_replace('/^(chapter|part)\s*\d+\s*[:.\-]\s*/i', '', $s['title']),
            $usable,
        ));
    }
}
