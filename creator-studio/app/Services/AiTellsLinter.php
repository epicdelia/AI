<?php

namespace App\Services;

/**
 * Deterministic AI-tell linter for scripts and posts (port of review/ai_tells.py).
 *
 * Runs before any AI reviewer: it's free, it never drifts, and it catches the patterns AI
 * reviewers are worst at noticing in their own output. HARD findings block the script.
 */
class AiTellsLinter
{
    public const HARD = 'HARD';
    public const WARN = 'WARN';

    public const BANNED = [
        "in today's fast-paced world", 'game-changer', 'game changer', 'revolutionizing',
        'revolutionize', 'delve', 'navigate the landscape', 'unprecedented', 'harness the power',
        "it's no secret", 'in the world of', 'buckle up', "let's dive in", 'dive into',
        'in conclusion', 'tapestry', 'testament to', 'seamlessly', 'elevate your',
        'unlock the power', 'unleash', 'a deep dive', 'ever-evolving', 'landscape of',
    ];

    public const SIGNATURE = [
        'absolutely insane', 'absolutely crazy', 'absolutely amazing', 'obsessed with',
        'highly recommend', "don't judge me", 'life-changing', "it's for science",
        'sketchy', 'hello world, hello friends', 'the world is your oyster',
    ];

    /**
     * @return array<int, array{0: string, 1: string, 2: string}> [severity, name, regex]
     */
    public static function rules(): array
    {
        $banned = implode('|', array_map(fn ($p) => preg_quote($p, '/'), self::BANNED));

        return [
            [self::HARD, 'em dash', '/—/u'],
            [self::HARD, 'spaced en dash used as em dash', '/\s–\s/u'],
            [self::HARD, 'banned phrase', '/\b(' . $banned . ')/iu'],
            [self::HARD, "'leverage' as a verb", '/\bleverag(e|es|ing|ed)\s+(the|your|our|this|these|AI|it)\b/iu'],
            [self::WARN, "'not X, it's Y' reversal", "/\b(isn't|is not|it's not|not) (just |only |about )?[^.,;!?]{1,40}[,;] (it's|it is|but)\b/iu"],
            [self::WARN, 'stock setup phrase', "/\b(here's the (thing|kicker|catch)|let that sink in|the best part\?|spoiler( alert)?:|plot twist:|but here's where it gets)/iu"],
            [self::WARN, 'fake-suspense question', '/\bThe (result|answer|catch|kicker|twist|verdict|secret)\?/iu'],
            [self::WARN, 'essay transition', '/(^|[.!?]\s+)(Moreover|Furthermore|Additionally|Ultimately|Notably|Importantly),/u'],
            [self::WARN, 'one-word tricolon', '/\b(\w+), (\w+),? and (\w+)[.!]/iu'],
            [self::WARN, 'missing contraction', '/\b(I am|you are|we are|they are|do not|does not|did not|is not|are not|was not|it is|that is|there is|cannot|will not|would not|should not|could not|have not|has not)\b/iu'],
        ];
    }

    /**
     * @return array<int, array{severity: string, line: int, rule: string, match: string}>
     */
    public static function lint(string $text): array
    {
        $findings = [];
        $lines = preg_split('/\R/u', $text);
        foreach ($lines as $i => $line) {
            foreach (self::rules() as [$severity, $name, $pattern]) {
                if (preg_match_all($pattern, $line, $m)) {
                    foreach ($m[0] as $match) {
                        $findings[] = [
                            'severity' => $severity,
                            'line' => $i + 1,
                            'rule' => $name,
                            'match' => trim($match),
                        ];
                    }
                }
            }
        }

        $lower = mb_strtolower($text);
        $sig = array_sum(array_map(fn ($p) => substr_count($lower, $p), self::SIGNATURE));
        if ($sig > 3) {
            $findings[] = [
                'severity' => self::WARN,
                'line' => 0,
                'rule' => 'signature phrases overused',
                'match' => "{$sig} found, cap is 3",
            ];
        }

        $first = '';
        foreach ($lines as $line) {
            if (trim($line) !== '' && !str_starts_with($line, '#')) {
                $first = trim($line);
                break;
            }
        }
        if (preg_match('/^So\b/u', $first)) {
            $findings[] = [
                'severity' => self::WARN,
                'line' => 1,
                'rule' => "opens with 'So'",
                'match' => mb_substr($first, 0, 40),
            ];
        }

        $exclaims = substr_count($text, '!');
        $words = max(count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY)), 1);
        if ($exclaims / $words > 0.04) {
            $findings[] = [
                'severity' => self::WARN,
                'line' => 0,
                'rule' => 'exclamation density',
                'match' => "{$exclaims} in {$words} words",
            ];
        }

        return $findings;
    }

    public static function hasHard(array $findings): bool
    {
        foreach ($findings as $f) {
            if ($f['severity'] === self::HARD) {
                return true;
            }
        }

        return false;
    }
}
