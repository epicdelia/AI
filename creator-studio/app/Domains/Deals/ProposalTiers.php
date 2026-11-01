<?php

namespace App\Domains\Deals;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Security\Audit;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Multi-tier proposal engine (Good / Better / Best).
 * Empowers clients to toggle between Essential, Growth (Recommended), and Enterprise tiers,
 * dynamically recalculating scope, deliverables, and total contract investment.
 */
class ProposalTiers
{
    public const TIERS = [
        'essential' => [
            'name' => 'Essential Placement',
            'multiplier' => 1.0,
            'summary' => 'Dedicated sponsor integration with verified reach and permanent catalog inclusion.',
            'badge' => null,
            'features' => [
                'Primary 60-90s dedicated integration segment',
                'Prominent verbal callout and custom visual overlay',
                'Pinned comment with tracked sponsor link',
                'Permanent inclusion in video description',
                'Standard 14-day competitor non-compete window',
            ],
        ],
        'growth' => [
            'name' => 'Growth Showcase',
            'multiplier' => 1.35,
            'summary' => 'Comprehensive multi-format package designed for maximum developer conversion and cross-platform reach.',
            'badge' => 'Most Popular',
            'features' => [
                'Everything in Essential Placement',
                '2× Native Vertical Video cuts (TikTok, IG Reels, YouTube Shorts)',
                'Dedicated feature spotlight in weekly Tech Unicorn developer newsletter',
                'Live Verified Campaign ROI & click tracking dashboard',
                'Interactive deliverable review & timecode proofing access',
                'Extended 30-day competitor non-compete window',
            ],
        ],
        'enterprise' => [
            'name' => 'Enterprise Domination',
            'multiplier' => 2.10,
            'summary' => 'Flagship developer tool partnership with full commercial paid advertising rights and executive strategy session.',
            'badge' => 'Highest ROI',
            'features' => [
                'Full dedicated deep-dive video (12–18 minutes)',
                '90-Day Paid Ads & Spark Ads commercial whitelisting rights',
                'Cross-platform transmedia thread cascade (LinkedIn, X, Threads)',
                'Raw 4K production files and editable timeline project handover',
                '1-Hour private Developer Relations & GTM strategy call with Delia',
                'Guaranteed 48-hour expedited initial review turnaround',
                'Full 60-day competitor non-compete window',
            ],
        ],
    ];

    public static function for(Deal $deal): array
    {
        return self::forDeal($deal)['tiers'];
    }

    public static function forDeal(Deal $deal): array
    {
        $baseRate = (float) ($deal->rate > 0 ? $deal->rate : 5000.0);
        $currency = $deal->currency ?: 'USD';
        $selected = $deal->selected_tier ?: 'growth';

        $tiers = [];
        foreach (self::TIERS as $key => $t) {
            $amount = round($baseRate * $t['multiplier'], 0);
            $tiers[] = [
                'key' => $key,
                'name' => $t['name'],
                'amount' => $amount,
                'price' => $amount,
                'currency' => $currency,
                'summary' => $t['summary'],
                'badge' => $t['badge'],
                'features' => $t['features'],
                'is_selected' => ($key === $selected),
            ];
        }

        return [
            'selected_tier' => $selected,
            'currency' => $currency,
            'tiers' => $tiers,
        ];
    }

    public static function selectTier(Deal $deal, string $tierKey, ?User $by = null): array
    {
        if (! isset(self::TIERS[$tierKey])) {
            throw ValidationException::withMessages(['tier' => 'Invalid proposal tier selected.']);
        }

        $tierConfig = self::TIERS[$tierKey];
        $allTiers = self::forDeal($deal)['tiers'];
        $matching = collect($allTiers)->firstWhere('key', $tierKey);
        $amount = (float) $matching['amount'];

        // Recalculate deliverables for this tier
        $deliverables = [
            ['platform' => 'youtube', 'format' => 'Sponsored Integration ('.$tierConfig['name'].')', 'status' => 'in_progress'],
        ];

        if ($tierKey === 'growth') {
            $deliverables[] = ['platform' => 'youtube', 'format' => '2× Dedicated Vertical Shorts/Reels', 'status' => 'in_progress'];
            $deliverables[] = ['platform' => 'newsletter', 'format' => 'Developer Newsletter Feature', 'status' => 'in_progress'];
        } elseif ($tierKey === 'enterprise') {
            $deliverables[] = ['platform' => 'youtube', 'format' => 'Full Dedicated Video', 'status' => 'in_progress'];
            $deliverables[] = ['platform' => 'social', 'format' => 'Transmedia Social Cascade (LinkedIn/X)', 'status' => 'in_progress'];
            $deliverables[] = ['platform' => 'ads', 'format' => '90-Day Paid Spark Ads Licensing', 'status' => 'in_progress'];
            $deliverables[] = ['platform' => 'meeting', 'format' => 'VIP GTM Advisory Session', 'status' => 'in_progress'];
        }

        $proposal = $deal->proposal ?? [];
        if (! empty($proposal['investment'])) {
            $proposal['investment']['amount'] = $amount;
        }
        $proposal['tier_name'] = $tierConfig['name'];

        $deal->update([
            'selected_tier' => $tierKey,
            'rate' => $amount,
            'deliverables' => $deliverables,
            'proposal' => $proposal,
            'last_contacted_at' => now(),
        ]);

        Audit::log('deal_linked', $by, $deal, ['selected_tier' => $tierKey, 'amount' => $amount]);
        $who = $by?->name ?: ($deal->brand_name);
        AgentEvent::log('lead', "{$who} selected the {$tierConfig['name']} tier ($".number_format($amount).").");

        return [
            'ok' => true,
            'selected_tier' => $tierKey,
            'tier_name' => $tierConfig['name'],
            'new_rate' => $amount,
            'currency' => $deal->currency ?: 'USD',
            'deliverables' => $deliverables,
            'message' => "Updated to {$tierConfig['name']} ($".number_format($amount)." {$deal->currency}).",
        ];
    }
}
