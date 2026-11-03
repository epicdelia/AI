<?php

namespace App\Domains\Landing;

use App\Http\Controllers\Api\StripePaymentController;
use App\Models\Deal;
use App\Models\Setting;

/**
 * "04: Packages & Pricing": one source for the landing page cards and Stripe checkout. The copy
 * lives here; her dashboard ("Packages & pricing") sets each plan's price, whether it shows and
 * which one is "Most popular", and sees what each plan brings in. Checkout charges the same price
 * the card shows (the MVP deposit is always half the build price).
 */
class Pricing
{
    public const SETTING = 'landing_pricing';

    /**
     * Plan => its Stripe package (and deposit package), booking type and card copy.
     */
    public const PLANS = [
        'strategy' => [
            'name' => 'Strategy Call',
            'package' => 'advisory_session',
            'book' => 'consulting',
            'per' => '/session',
            'clarify' => 'one-time private call',
            'desc' => 'A private 90-minute architecture review, code audit and AI product roadmap.',
            'cta' => 'Book session',
            'trust' => '100% satisfaction guarantee',
            'badge' => [
                'icon' => 'play',
                'label' => 'Google Meet 1:1',
            ],
            'metrics' => [['Duration', '90 min', 'private session'], ['Seats', '1:1', 'your team'], ['Turnaround', '24h', 'to book'], ['Support', '7 days', 'async follow-up']],
            'perks' => ['Full recording & transcript', 'Architecture summary report', 'Follow-up Q&A for 7 days'],
            'inherits' => null,
            'benefits' => [
                ['90-minute private strategy call', 'included'], ['Full code & architecture audit', 'included'], ['AI model selection & roadmap', 'included'],
                ['7-day async follow-up', 'included'], ['Ongoing weekly on-call access', 'absent'], ['Full production MVP builds', 'absent'],
            ],
        ],
        'retainer' => [
            'name' => 'Retainer',
            'package' => 'retainer',
            'book' => 'retainer',
            'per' => '/mo',
            'clarify' => 'billed monthly, cancel anytime',
            'desc' => 'An ex-Google engineer on call for your product and engineering team.',
            'cta' => 'Start retainer',
            'trust' => 'Cancel anytime, no lock-in',
            'badge' => [
                'icon' => 'sparkle',
                'label' => 'Cloud & AI advisory',
            ],
            'metrics' => [['Strategy calls', '2', 'every month'], ['Async reviews', 'Unlimited', 'code & PRs'], ['Response', '24h', 'guaranteed'], ['Direct line', 'Slack / TG', 'priority access']],
            'perks' => ['Bi-weekly deep-dive strategy calls', 'Architecture & PR code reviews', 'Engineering hiring vetting'],
            'inherits' => 'Everything in Strategy Call, and:',
            'benefits' => [
                ['2 deep-dive strategy calls every month', 'added'], ['Unlimited async PR & code reviews', 'added'], ['Direct Slack/Telegram access, 24h response', 'added'],
                ['Build-vs-buy and AI model evaluations', 'added'], ['Cancel anytime, no minimum', 'included'], ['Dedicated 2 to 4 week MVP builds', 'absent'],
            ],
        ],
        'mvp' => [
            'name' => 'MVP Build',
            'package' => 'mvp_full',
            'deposit' => 'mvp_deposit',
            'book' => 'mvp_build',
            'per' => 'fixed',
            'clarify' => 'fixed scope delivery',
            'desc' => 'Your idea, live in 2 to 4 weeks: UX design, AI build and cloud deploy.',
            'cta' => 'Pay in full',
            'trust' => '30-day post-launch warranty included',
            'badge' => [
                'icon' => 'zap',
                'label' => 'Production cloud deploy',
            ],
            'metrics' => [['Timeline', '2 to 4 wks', 'production ready'], ['Live demos', 'Weekly', 'Friday reviews'], ['IP ownership', '100%', 'all source & code'], ['Warranty', '30 days', 'after launch']],
            'perks' => ['Full UX design, database & responsive app', 'Cloud deployment with your domain & SSL', '30 days of post-launch engineering support'],
            'inherits' => 'Everything in Retainer, and:',
            'benefits' => [
                ['Complete product: UX, AI build & cloud deployment', 'added'], ['Weekly Friday demos of real progress', 'added'], ['Production codebase with clean documentation', 'added'],
                ['100% ownership of code, IP & accounts', 'added'], ['30-day post-launch bug warranty', 'added'], ['Fixed price, no surprise invoices', 'included'],
            ],
        ],
        'brand' => [
            'name' => 'Brand Deal',
            'package' => 'brand_partnership',
            'book' => 'brand_deal',
            'per' => '/campaign',
            'clarify' => 'multi-platform campaign',
            'desc' => 'Your product explained to an engaged audience of {audience} tech professionals.',
            'cta' => 'Book campaign',
            'trust' => 'Full digital ad rights included',
            'badge' => [
                'icon' => 'users',
                'label' => '{audience} audience reach',
            ],
            'metrics' => [], // her top networks from Public reach, filled in below
            'perks' => ['Concept, script & high-retention video', 'Native cross-platform distribution', 'Digital advertising usage rights'],
            'inherits' => 'Dedicated sponsor production, including:',
            'benefits' => [
                ['High-retention technical integration & video', 'added'], ['{reach}', 'added'], ['Full digital ad commercial rights', 'added'],
                ['Post-campaign performance report', 'added'], ['Tracked links in bio & descriptions', 'included'],
            ],
        ],
    ];

    /**
     * Her overrides: [plan => ['price' => dollars, 'visible' => bool, 'popular' => bool]].
     */
    public static function overrides(): array
    {
        $saved = json_decode((string)Setting::get(self::SETTING, '{}'), true);

        return is_array($saved) ? $saved : [];
    }

    /**
     * A plan's price in dollars (her override, else the Stripe package's).
     */
    public static function price(string $plan): int
    {
        $o = self::overrides()[$plan]['price'] ?? null;

        return $o !== null ? (int)$o : intdiv(StripePaymentController::PACKAGES[self::PLANS[$plan]['package']]['amount'], 100);
    }

    public static function popularPlan(): string
    {
        foreach (self::overrides() as $plan => $o) {
            if (!empty($o['popular']) && isset(self::PLANS[$plan])) {
                return $plan;
            }
        }

        return 'mvp';
    }

    /**
     * A Stripe package with the amount her cards show (the MVP deposit is half the build).
     */
    public static function package(string $id): array
    {
        $pkg = StripePaymentController::PACKAGES[$id];
        foreach (self::PLANS as $plan => $p) {
            if ($p['package'] === $id) {
                $pkg['amount'] = self::price($plan) * 100;
            } elseif (($p['deposit'] ?? null) === $id) {
                $pkg['amount'] = intdiv(self::price($plan) * 100, 2);
            }
        }

        return $pkg;
    }

    public static function packages(): array
    {
        return array_map(fn ($id) => self::package($id), array_combine(array_keys(StripePaymentController::PACKAGES), array_keys(StripePaymentController::PACKAGES)));
    }

    /**
     * The public cards, in order, with live audience numbers and build-slot availability.
     */
    public static function publicPlans(): array
    {
        $overrides = self::overrides();
        $popular = self::popularPlan();
        $reach = collect(Audience::reach()['networks'])->reject(fn ($n) => $n['hidden'])->sortByDesc('count')->values();
        $headline = Audience::headline();
        $availability = ClientJourney::availability();

        $plans = [];
        foreach (self::PLANS as $key => $p) {
            if (($overrides[$key]['visible'] ?? true) === false) {
                continue;
            }
            $fill = fn ($s) => str_replace('{audience}', $headline, $s);
            $card = [
                'key' => $key,
                'name' => $p['name'],
                'package' => $p['package'],
                'book' => $p['book'],
                'price' => self::price($key),
                'per' => $p['per'],
                'clarify' => $p['clarify'],
                'desc' => $fill($p['desc']),
                'cta' => $p['cta'],
                'trust' => $p['trust'],
                'badge' => [
                    'icon' => $p['badge']['icon'],
                    'label' => $fill($p['badge']['label']),
                ],
                'metrics' => array_map(fn ($m) => [
                    'label' => $m[0],
                    'value' => $m[1],
                    'sub' => $m[2],
                ], $p['metrics']),
                'perks' => $p['perks'],
                'inherits' => $p['inherits'],
                'benefits' => array_map(fn ($b) => [
                    'text' => $b[0],
                    'kind' => $b[1],
                ], $p['benefits']),
                'popular' => $key === $popular,
            ];
            if (isset($p['deposit'])) {
                $card['deposit'] = [
                    'package' => $p['deposit'],
                    'price' => intdiv(self::price($key), 2),
                ];
                $card['availability'] = $availability['show'] ? [
                    'status' => $availability['status'],
                    'label' => $availability['label'],
                ] : null;
            }
            if ($key === 'brand') {
                $top = $reach->take(3);
                $card['metrics'] = $top->map(fn ($n) => [
                    'label' => $n['label'],
                    'value' => $n['count_label'],
                    'sub' => $n['unit'],
                    'network' => $n['key'],
                ])->all();
                $card['metrics'][] = [
                    'label' => 'Total reach',
                    'value' => $headline,
                    'sub' => $reach->count() . ' networks',
                ];
                $line = $top->map(fn ($n) => "{$n['count_label']} {$n['label']}")->all();
                $text = count($line) > 1 ? implode(', ', array_slice($line, 0, -1)) . ' & ' . end($line) . ' reach' : ($line[0] ?? $headline) . ' reach';
                $card['benefits'] = array_map(fn ($b) => $b['text'] === '{reach}' ? [
                    'text' => $text,
                    'kind' => $b['kind'],
                ] : $b, $card['benefits']);
            }
            $plans[] = $card;
        }

        return $plans;
    }

    /**
     * Per plan, what the website brought in: checkouts started or invoice requests, how many were
     * paid and the money paid, plus intro calls booked from that card.
     */
    public static function stats(): array
    {
        $byPackage = [];
        foreach (self::PLANS as $key => $p) {
            $byPackage[$p['package']] = $key;
            if (isset($p['deposit'])) {
                $byPackage[$p['deposit']] = $key;
            }
        }
        $names = [];
        foreach (StripePaymentController::PACKAGES as $id => $pkg) {
            $names[$pkg['name']] = $id;
        }
        $out = array_fill_keys(array_keys(self::PLANS), [
            'requests' => 0,
            'paid' => 0,
            'revenue' => 0.0,
            'calls' => 0,
        ]);

        foreach (Deal::get(['deliverables', 'payment_status', 'rate']) as $deal) {
            $d = $deal->deliverables[0] ?? [];
            if (($d['platform'] ?? null) === 'stripe') {
                $plan = $byPackage[$d['package'] ?? ($names[$d['format'] ?? ''] ?? '')] ?? null;
                if ($plan) {
                    $out[$plan]['requests']++;
                    if ($deal->payment_status === 'paid') {
                        $out[$plan]['paid']++;
                        $out[$plan]['revenue'] += (float)$deal->rate;
                    }
                }
            } elseif (preg_match('/^Pricing: (.+)$/', (string)($d['service'] ?? ''), $m)) {
                foreach (self::PLANS as $key => $p) {
                    if ($p['name'] === $m[1]) {
                        $out[$key]['calls']++;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Her dashboard: every plan (hidden ones too) with price, visibility, popularity and results.
     */
    public static function dashboard(): array
    {
        $overrides = self::overrides();
        $stats = self::stats();
        $popular = self::popularPlan();

        return [
            'plans' => array_map(fn ($key) => [
                'key' => $key,
                'name' => self::PLANS[$key]['name'],
                'per' => self::PLANS[$key]['per'],
                'price' => self::price($key),
                'default_price' => intdiv(StripePaymentController::PACKAGES[self::PLANS[$key]['package']]['amount'], 100),
                'visible' => ($overrides[$key]['visible'] ?? true) !== false,
                'popular' => $key === $popular,
            ] + $stats[$key], array_keys(self::PLANS)),
            'revenue' => array_sum(array_column($stats, 'revenue')),
            'requests' => array_sum(array_column($stats, 'requests')),
        ];
    }

    /**
     * Save prices, visibility and the popular plan (exactly one; at least one plan stays visible).
     */
    public static function save(array $plans): array
    {
        $current = self::overrides();
        $popular = null;
        foreach ($plans as $key => $p) {
            if (!isset(self::PLANS[$key])) {
                continue;
            }
            $current[$key] = [
                'price' => isset($p['price']) ? max(0, (int)$p['price']) : ($current[$key]['price'] ?? null),
                'visible' => (bool)($p['visible'] ?? true),
                'popular' => false,
            ];
            if (!empty($p['popular'])) {
                $popular = $key;
            }
        }
        if ($popular) {
            $current[$popular]['popular'] = true;
        }
        Setting::put(self::SETTING, json_encode($current));

        return self::dashboard();
    }
}
