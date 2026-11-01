<?php

namespace App\Domains\Deals;

use App\Models\Deal;

/**
 * How good a website lead looks, at a glance: 0-100 from what the form told her (a work email,
 * a company, the project type, a real brief with a budget or a date, a booked call, a tracked
 * link). A rule of thumb, labelled as one, with the reasons, so she answers the best ones first.
 */
class LeadScore
{
    private const FREE_MAIL = ['gmail.com', 'googlemail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'live.com', 'icloud.com', 'me.com', 'aol.com', 'proton.me', 'protonmail.com', 'gmx.com', 'yandex.com', 'mail.com'];

    private const TYPE_POINTS = ['mvp_build' => 25, 'retainer' => 22, 'brand_deal' => 20, 'speaking' => 15, 'consulting' => 12, 'other' => 0];

    /** @return array{score: int, label: string, reasons: list<string>, basis: string}|null */
    public static function for(Deal $deal): ?array
    {
        if (! $deal->source || ! in_array($deal->stage, ['inquiry', 'pitching', 'negotiating'], true)) {
            return null; // only website leads still being won
        }
        $score = 10;
        $why = [];
        $domain = strtolower((string) substr(strrchr((string) $deal->contact_email, '@') ?: '', 1));
        if ($domain !== '' && ! in_array($domain, self::FREE_MAIL, true)) {
            $score += 20;
            $why[] = "work email (@{$domain})";
        }
        if ($deal->brand_name && ! str_ends_with($deal->brand_name, ' Inquiry')) {
            $score += 8;
            $why[] = 'named a company';
        }
        $first = is_array($deal->deliverables) ? ($deal->deliverables[0] ?? []) : [];
        $type = $first['project_type'] ?? (($first['platform'] ?? null) === 'stripe' ? 'checkout' : null);
        if ($type === 'checkout') {
            $score += 25;
            $why[] = 'started a checkout';
        } elseif ($type && isset(self::TYPE_POINTS[$type])) {
            $score += self::TYPE_POINTS[$type];
            if (self::TYPE_POINTS[$type] >= 20) {
                $why[] = str_replace('_', ' ', $type);
            }
        }
        $brief = (string) preg_replace('/^.*Notes from client:\s*/s', '', (string) $deal->notes);
        if (mb_strlen($brief) >= 120 && ! str_starts_with($brief, 'None')) {
            $score += 12;
            $why[] = 'a real brief';
        }
        if (preg_match('/\$\s?\d|\b(?:budget|\d+\s?k|usd|eur|funded|seed|series [a-c]|deadline|launch|by (?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)\w*|q[1-4])\b/i', $brief)) {
            $score += 13;
            $why[] = 'mentions budget or timing';
        }
        if ($deal->meeting_at) {
            $score += 8;
            $why[] = 'booked a call';
        }
        if (! empty($deal->attribution['item'])) {
            $score += 4;
            $why[] = 'came from your content';
        }
        $score = max(1, min(100, $score));

        return [
            'score' => $score,
            'label' => $score >= 70 ? 'Strong fit' : ($score >= 45 ? 'Worth a reply today' : 'Light on detail'),
            'reasons' => $why,
            'basis' => 'rule of thumb',
        ];
    }
}
