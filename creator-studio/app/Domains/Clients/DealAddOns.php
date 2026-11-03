<?php

namespace App\Domains\Clients;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Security\Audit;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Modular high-margin add-ons and upsells for active sponsor deals.
 * Allows clients to self-serve high-ROI extensions:
 * - Paid usage / Whitelisting rights
 * - Rush turnaround
 * - Extra Short / Reel cuts
 * - Newsletter features
 */
class DealAddOns
{
    public const CATALOG = [
        'paid_usage_90d' => [
            'name' => '90-Day Paid Ads & Whitelisting Rights',
            'description' => 'Full commercial rights to run Spark Ads / Meta Paid Ads using the creator assets for 90 days.',
            'price' => 1500.0,
        ],
        'extra_reels' => [
            'name' => '2× Dedicated Vertical Shorts/Reels',
            'description' => 'Two extra native vertical cuts optimized for TikTok, Instagram Reels, and YouTube Shorts.',
            'price' => 1200.0,
        ],
        'newsletter_feature' => [
            'name' => 'Dedicated Newsletter Feature',
            'description' => 'Primary sponsor placement and callout in the weekly Tech Unicorn developer newsletter.',
            'price' => 1800.0,
        ],
        'rush_turnaround' => [
            'name' => '48-Hour Expedited Delivery',
            'description' => 'Guaranteed initial review draft delivered within 48 hours of asset handover.',
            'price' => 1250.0,
        ],
        'raw_footage_4k' => [
            'name' => 'Raw 4K Footage & Project Files',
            'description' => 'Full delivery of raw 4K camera files, color-graded b-roll, and editable timeline project.',
            'price' => 950.0,
        ],
    ];

    public static function catalog(): array
    {
        return array_values(array_map(fn ($k, $v) => [
            'key' => $k,
        ] + $v, array_keys(self::CATALOG), self::CATALOG));
    }

    public static function apply(Deal $deal, string $key, User $by): array
    {
        if (!isset(self::CATALOG[$key])) {
            throw ValidationException::withMessages([
                'add_on' => 'Invalid add-on package selected.',
            ]);
        }

        $item = self::CATALOG[$key];
        $currentAddOns = $deal->add_ons ?? [];

        // Check if already applied
        if (collect($currentAddOns)->contains('key', $key)) {
            throw ValidationException::withMessages([
                'add_on' => 'This add-on is already included on this project.',
            ]);
        }

        $applied = [
            'key' => $key,
            'name' => $item['name'],
            'price' => $item['price'],
            'added_at' => now()->toIso8601String(),
            'added_by' => $by->name,
        ];

        $currentAddOns[] = $applied;
        $newRate = (float)$deal->rate + (float)$item['price'];

        // Also append deliverable if applicable
        $deliverables = $deal->deliverables ?? [];
        $deliverables[] = [
            'platform' => 'add-on',
            'format' => $item['name'],
            'status' => 'in_progress',
            'due_date' => now()->addDays(7)->toDateString(),
        ];

        $deal->update([
            'rate' => $newRate,
            'add_ons' => $currentAddOns,
            'deliverables' => $deliverables,
            'last_contacted_at' => now(),
        ]);

        Audit::log('deal_linked', $by, $deal, [
            'add_on' => $key,
            'price' => $item['price'],
        ]);
        AgentEvent::log('lead', "{$by->name} added {$item['name']} (+" . number_format($item['price']) . " {$deal->currency}) to {$deal->brand_name}.");

        return [
            'deal_id' => $deal->id,
            'add_on' => $applied,
            'new_rate' => $newRate,
            'message' => "Added {$item['name']} to project deliverables. New total: " . number_format($newRate) . " {$deal->currency}.",
        ];
    }
}
