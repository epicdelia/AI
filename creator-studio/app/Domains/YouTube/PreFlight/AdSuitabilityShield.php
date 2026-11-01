<?php

namespace App\Domains\YouTube\PreFlight;

use App\Models\Deal;

/**
 * YouTube Ad Suitability & Advertiser-Friendly Guidelines Self-Certification Shield.
 * Evaluates video metadata and script transcripts against Google's 11 Monetization Guidelines:
 * Green Dollar (safe for all brands), Yellow Dollar (limited/no ads), or Red Dollar (strike risk).
 */
class AdSuitabilityShield
{
    private const HIGH_RISK_KEYWORDS = [
        'kill', 'murder', 'suicide', 'bomb', 'terrorist', 'nazi', 'genocide',
        'cocaine', 'heroin', 'methamphetamine', 'pirate bay', 'warez', 'keygen',
        'hack account', 'steal credentials', 'ddos attack script',
    ];

    private const PROFANITY_KEYWORDS = [
        'fuck', 'shit', 'bitch', 'asshole', 'motherfucker', 'cunt', 'dick', 'pussy',
    ];

    private const SPONSOR_DISCLOSURE_PHRASES = [
        'sponsored by', 'thanks to', 'partnered with', '#ad', '#sponsored',
        'in partnership with', 'supported by', 'brought to you by',
    ];

    /**
     * Audit metadata and text for advertiser suitability and FTC compliance.
     *
     * @param  array{title: string, description?: string, body?: string, deal_id?: ?int}  $params
     * @return array{
     *     monetization_rating: string,
     *     monetization_score: int,
     *     flagged_guidelines: array<int, array{guideline: string, severity: string, quote: string, advice: string}>,
     *     has_paid_promotion: bool,
     *     paid_watermark_synced: bool
     * }
     */
    public function audit(array $params): array
    {
        $title = (string) ($params['title'] ?? '');
        $description = (string) ($params['description'] ?? '');
        $body = (string) ($params['body'] ?? '');
        $dealId = $params['deal_id'] ?? null;

        $text = strtolower("{$title} {$description} {$body}");
        $titleLower = strtolower($title);

        $flags = [];
        $score = 100;

        // 1. High Risk & Harmful acts check
        foreach (self::HIGH_RISK_KEYWORDS as $keyword) {
            if (str_contains($text, $keyword)) {
                $flags[] = [
                    'guideline' => 'Dangerous Acts / Sensitive Issues',
                    'severity' => 'high',
                    'quote' => $keyword,
                    'advice' => "Remove or contextually soften reference to '{$keyword}' to prevent yellow dollar demonetization.",
                ];
                $score -= 35;
            }
        }

        // 2. Profanity check (Heavy penalty in title or opening)
        foreach (self::PROFANITY_KEYWORDS as $profanity) {
            if (str_contains($titleLower, $profanity)) {
                $flags[] = [
                    'guideline' => 'Inappropriate Language in Title',
                    'severity' => 'high',
                    'quote' => $profanity,
                    'advice' => "Profanity in the title ('{$profanity}') triggers an immediate automated limited ads flag. Keep titles clean.",
                ];
                $score -= 30;
            } elseif (str_contains($text, $profanity)) {
                $flags[] = [
                    'guideline' => 'Inappropriate Language in Content',
                    'severity' => 'medium',
                    'quote' => $profanity,
                    'advice' => "Mild/strong vulgarity detected. Ensure it does not occur in the first 30 seconds of video playback.",
                ];
                $score -= 15;
            }
        }

        // 3. Brand Deal & FTC Sponsor Disclosure Check
        $hasPaidPromotion = false;
        $paidWatermarkSynced = false;

        $hasSponsorPhrase = false;
        foreach (self::SPONSOR_DISCLOSURE_PHRASES as $phrase) {
            if (str_contains($text, $phrase)) {
                $hasSponsorPhrase = true;
                break;
            }
        }

        if ($dealId) {
            $deal = Deal::find($dealId);
            if ($deal) {
                $hasPaidPromotion = true;
                if ($hasSponsorPhrase) {
                    $paidWatermarkSynced = true;
                } else {
                    $flags[] = [
                        'guideline' => 'FTC & YouTube Paid Promotion Disclosure',
                        'severity' => 'medium',
                        'quote' => 'Missing explicit sponsor disclosure',
                        'advice' => "This video is linked to brand deal '{$deal->brand_name}'. Add 'Sponsored by {$deal->brand_name}' in the description and enable YouTube's paid promotion badge.",
                    ];
                    $score -= 10;
                }
            }
        } elseif ($hasSponsorPhrase) {
            $hasPaidPromotion = true;
            $paidWatermarkSynced = true;
        }

        $score = max(0, min(100, $score));

        $rating = match (true) {
            $score >= 90 => 'green',
            $score >= 60 => 'yellow',
            default => 'red',
        };

        return [
            'monetization_rating' => $rating,
            'monetization_score' => $score,
            'flagged_guidelines' => $flags,
            'has_paid_promotion' => $hasPaidPromotion,
            'paid_watermark_synced' => $paidWatermarkSynced,
        ];
    }
}

