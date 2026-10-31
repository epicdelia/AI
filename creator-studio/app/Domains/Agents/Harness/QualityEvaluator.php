<?php

namespace App\Domains\Agents\Harness;

use App\Services\AiTellsLinter;

/**
 * Multi-dimensional quality evaluator for agent-generated social posts.
 * Provides an objective rubric for hook strength, authentic voice, retention pacing,
 * AI cliche avoidance, and brand safety.
 */
class QualityEvaluator
{
    /** Sweet spots for post character length per platform. */
    private const SWEET_SPOTS = [
        'linkedin' => [500, 1600],
        'x' => [80, 270],
        'threads' => [80, 450],
        'instagram' => [120, 1000],
        'tiktok' => [40, 150],
        'youtube' => [60, 500],
    ];

    /**
     * Evaluate a social draft against the quality harness rubric.
     *
     * @param  array{platform?: string, format?: string, body?: string, parts?: array}|string  $draft
     * @param  string|null  $platformFallback
     * @return array{overall_score: int, rubric: array{hook: int, voice: int, retention: int, cliche_freedom: int, safety: int}, hard_tells_caught: array<int, string>, feedback: array<int, string>}
     */
    public static function evaluate(array|string $draft, ?string $platformFallback = null): array
    {
        if (is_string($draft)) {
            $draft = [
                'body' => $draft,
                'platform' => $platformFallback ?? 'linkedin',
            ];
        }

        $platform = $draft['platform'] ?? $platformFallback ?? 'linkedin';
        $body = trim((string) ($draft['body'] ?? ''));
        $parts = (array) ($draft['parts'] ?? []);
        $fullText = trim($body."\n\n".implode("\n\n", $parts));

        $feedback = [];
        $hardTells = [];

        // 1. Hook Score (0-100)
        $lines = array_values(array_filter(explode("\n", $fullText), fn ($l) => trim($l) !== ''));
        $firstLine = $lines[0] ?? '';
        $hookLen = mb_strlen($firstLine);

        $hookScore = 70;
        if ($hookLen > 0 && $hookLen <= 140) {
            $hookScore += 15;
        } elseif ($hookLen > 200) {
            $hookScore -= 20;
            $feedback[] = 'Hook line exceeds 200 characters and will be truncated on mobile feeds.';
        }

        if (preg_match('/^\d+|\b\d+(?:%|x|k|\+)?\b/i', $firstLine)) {
            $hookScore += 15; // starts or features a specific number
        }
        if (str_contains($firstLine, '?')) {
            $hookScore += 5;
        }
        $hookScore = (int) max(10, min(100, $hookScore));

        // 2. Voice & Authenticity Score (0-100)
        $voiceScore = 75;
        if (preg_match('/\b(I|I\'m|I’ve|my|we|built|tested|failed|shipped|learned)\b/i', $fullText)) {
            $voiceScore += 15;
        } else {
            $voiceScore -= 10;
            $feedback[] = 'Lacks first-person experience markers (I, my, tested, shipped).';
        }

        // Penalty for generic corporate buzzwords
        $bannedBuzzwords = ['game-changer', 'supercharge', 'synergy', 'paradigm shift', 'testament', 'revolutionize', 'delve', 'unlocking', 'landscape'];
        foreach ($bannedBuzzwords as $bw) {
            if (stripos($fullText, $bw) !== false) {
                $voiceScore -= 12;
                $hardTells[] = $bw;
                $feedback[] = "Contains generic corporate buzzword: \"{$bw}\".";
            }
        }
        $voiceScore = (int) max(10, min(100, $voiceScore));

        // 3. Retention & Pacing Score (0-100)
        $retentionScore = 75;
        $sweet = self::SWEET_SPOTS[$platform] ?? [100, 1000];
        $charCount = mb_strlen($fullText);

        if ($charCount >= $sweet[0] && $charCount <= $sweet[1]) {
            $retentionScore += 15;
        } else {
            $retentionScore -= 10;
            $feedback[] = "Length ({$charCount} chars) is outside the sweet spot for {$platform} ({$sweet[0]}-{$sweet[1]} chars).";
        }

        // Paragraph rhythm: check if any paragraph exceeds 4 lines
        $paragraphs = preg_split('/\n\s*\n/', $body);
        $denseParagraphs = 0;
        foreach ($paragraphs as $p) {
            if (count(explode("\n", trim($p))) > 4) {
                $denseParagraphs++;
            }
        }
        if ($denseParagraphs > 0) {
            $retentionScore -= 10;
            $feedback[] = 'Formatting contains dense multi-line blocks; break paragraphs into 1-2 punchy lines.';
        }
        $retentionScore = (int) max(10, min(100, $retentionScore));

        // 4. AI Cliche & Tell Freedom (0-100)
        $findings = AiTellsLinter::lint($fullText);
        $hardCount = 0;
        $warnCount = 0;
        foreach ($findings as $f) {
            if ($f['severity'] === AiTellsLinter::HARD) {
                $hardCount++;
                $hardTells[] = $f['match'];
                $feedback[] = "Hard AI tell: {$f['rule']} (\"{$f['match']}\").";
            } else {
                $warnCount++;
            }
        }
        $clicheFreedom = (int) max(10, min(100, 100 - ($hardCount * 25) - ($warnCount * 5)));

        // 5. Safety & Disclosure (0-100)
        $safetyScore = 100;
        if (preg_match('/\b(sponsored|partner|partnered|affiliate)\b/i', $fullText) && ! preg_match('/(#ad|sponsored by|thanks to)/i', $fullText)) {
            $safetyScore -= 30;
            $feedback[] = 'Potential promotional claim lacks clear FTC disclosure (#ad or "sponsored by").';
        }

        $overall = (int) round(
            ($hookScore * 0.25) +
            ($voiceScore * 0.25) +
            ($retentionScore * 0.20) +
            ($clicheFreedom * 0.20) +
            ($safetyScore * 0.10)
        );

        return [
            'overall_score' => max(1, min(100, $overall)),
            'rubric' => [
                'hook' => $hookScore,
                'voice' => $voiceScore,
                'retention' => $retentionScore,
                'cliche_freedom' => $clicheFreedom,
                'safety' => $safetyScore,
            ],
            'hard_tells_caught' => array_values(array_unique($hardTells)),
            'feedback' => $feedback,
        ];
    }
}
