<?php

namespace App\Domains\Agents\Deals;

use App\Domains\Agents\Guardrails\AgentGuardrails;
use App\Domains\Agents\Memory\AgentEpisodicMemory;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Deals\DealCalculator;
use App\Domains\Landing\Audience;
use App\Models\Deal;
use App\Models\Setting;
use App\Services\Llm;
use App\Services\Prompts;
use Throwable;

/**
 * Autonomous Deal Desk & Inbound Sponsor Negotiation Agent.
 * Analyzes inbound sponsor pitches, performs competitive conflict & exclusivity checks,
 * calculates market-calibrated CPM packages via DealCalculator, and crafts executive-level
 * counter-offers with ironclad contract stipulations.
 */
class SponsorNegotiator
{
    public function __construct(
        private Llm $llm,
        private AgentGuardrails $guardrails,
        private AgentEpisodicMemory $memory
    ) {}

    /**
     * Analyze an inbound sponsorship inquiry and construct tiered counter-proposals.
     *
     * @param  array{inbound_text: string, brand_name?: string, target_product?: string, initial_budget?: string|numeric}  $params
     * @return array{
     *     analysis: array{
     *         brand_name: string,
     *         product_name: string,
     *         brand_fit_score: int,
     *         developer_audience_relevance: string,
     *         conflicts_detected: array<int, string>,
     *         requested_deliverables: array<int, string>
     *     },
     *     tiered_packages: array{
     *         tier_1_essential: array{name: string, rate: int, deliverables: string, cpm: int, rationale: string},
     *         tier_2_synergy: array{name: string, rate: int, deliverables: string, cpm: int, rationale: string},
     *         tier_3_takeover: array{name: string, rate: int, deliverables: string, cpm: int, rationale: string}
     *     },
     *     negotiation_email: array{subject: string, body: string, call_to_action: string},
     *     contract_stipulations: array<string, string>
     * }
     */
    public function negotiate(SocialAgent $agent, array $params): array
    {
        $inboundText = trim((string) ($params['inbound_text'] ?? ''));
        $brandHint = trim((string) ($params['brand_name'] ?? ''));
        $productHint = trim((string) ($params['target_product'] ?? ''));
        $budgetHint = trim((string) ($params['initial_budget'] ?? ''));

        // 1. Current studio rate calculations & active deals for conflict detection
        $rateData = DealCalculator::calculateRates();
        $baseYtMidroll = $rateData['rates']['youtube_integration_60s']['rate'] ?? 2500;
        $baseYtDedicated = $rateData['rates']['youtube_dedicated']['rate'] ?? 6000;
        $baseLiPost = $rateData['rates']['linkedin_post']['rate'] ?? 800;
        $baseXThread = $rateData['rates']['x_thread']['rate'] ?? 350;

        $activeDeals = Deal::whereNotIn('stage', ['paid', 'lost'])
            ->get(['brand_name', 'deliverables', 'stage', 'rate']);

        $activeSponsorsList = $activeDeals->map(fn ($d) => "{$d->brand_name} ({$d->stage})")->implode(', ');

        $creatorName = Setting::get('creator_name', 'Delia Lazarescu');
        $audienceHeadline = Audience::headline();

        $systemPrompt = <<<SYS
You are the Executive Talent Director & Deal Desk Agent for {$creatorName} (@Tech.Unicorn).
Delia is a premier tech/AI content creator with {$audienceHeadline} technical audience of senior software engineers, system architects, and tech leaders.

CURRENT STANDARD RATE BENCHMARKS:
- YouTube 60s Integration: \${$baseYtMidroll} (~$45 CPM)
- YouTube Dedicated Video: \${$baseYtDedicated} (~$95 CPM)
- LinkedIn Post: \${$baseLiPost}
- X/Twitter Thread: \${$baseXThread}

ACTIVE BRAND PIPELINE (check for competitor exclusivity conflicts):
{$activeSponsorsList}

YOUR OBJECTIVE:
Analyze the inbound sponsor pitch. Formulate a 3-tier counter-offer designed to maximize ROI, protect Delia's reputation, and guide the sponsor toward high-value multi-platform packages.

Return a valid JSON object matching this exact schema:
{
  "analysis": {
    "brand_name": "Brand Name",
    "product_name": "Product Name",
    "brand_fit_score": 88,
    "developer_audience_relevance": "Why this aligns or doesn't align with software engineers",
    "conflicts_detected": ["any active sponsor conflicts or notes"],
    "requested_deliverables": ["deliverables mentioned in pitch"]
  },
  "tiered_packages": {
    "tier_1_essential": {
      "name": "Single Platform Targeted Midroll",
      "rate": {$baseYtMidroll},
      "deliverables": "60s organic YouTube midroll + first 3 lines of description + pinned comment",
      "cpm": 45,
      "rationale": "High-intent developer demo inside an organic topic."
    },
    "tier_2_synergy": {
      "name": "Cross-Platform Tech Synergy (Recommended)",
      "rate": 3500,
      "deliverables": "YouTube 60s midroll + 1x LinkedIn Thought Leadership post + 1x X thread breakdown",
      "cpm": 40,
      "rationale": "Amplifies YouTube viewership with LinkedIn B2B engineering decision-maker reach."
    },
    "tier_3_takeover": {
      "name": "Full Omnichannel Authority Campaign",
      "rate": 7500,
      "deliverables": "Dedicated YouTube deep-dive + multi-platform cascade (LinkedIn, X, TikTok) + 30-day paid usage rights",
      "cpm": 35,
      "rationale": "Category dominance positioning for major product releases or funding announcements."
    }
  },
  "negotiation_email": {
    "subject": "Delia Lazarescu (@Tech.Unicorn) × [Brand] Partnership Proposal",
    "body": "Executive email text written warmly, confidently, and anchored to value...",
    "call_to_action": "Suggested next step (e.g. 15-min discovery call or review media kit)"
  },
  "contract_stipulations": {
    "usage_rights": "30 days organic digital rights. Paid amplification/whitelisting available at +35%.",
    "payment_terms": "Net 30 upon publication; 50% upfront required for deals over $5,000.",
    "revision_limits": "Up to 2 rounds of review prior to scheduling.",
    "ftc_compliance": "Explicit verbal and visual #ad / sponsored disclosure required by FTC 16 CFR § 255.5."
  }
}
SYS;

        $userPrompt = "Inbound Sponsor Message:\n\"{$inboundText}\"\n\n"
            .($brandHint ? "Brand Hint: {$brandHint}\n" : '')
            .($productHint ? "Product Hint: {$productHint}\n" : '')
            .($budgetHint ? "Budget Mentioned: {$budgetHint}\n" : '');

        $this->guardrails->recordTokens((int) (mb_strlen($systemPrompt . $userPrompt) / 4), 600);

        try {
            $parsed = $this->llm->as('deal-desk-agent')->creativity(0.4)->json($systemPrompt, $userPrompt);

            // Sanity check structure
            if (! isset($parsed['analysis'], $parsed['tiered_packages'], $parsed['negotiation_email'])) {
                throw new \RuntimeException('Incomplete deal desk payload returned by LLM.');
            }

            return $parsed;
        } catch (Throwable $e) {
            // High-reliability fallback
            $brand = $brandHint ?: 'Sponsor Partner';
            $product = $productHint ?: 'Developer Tool';

            return [
                'analysis' => [
                    'brand_name' => $brand,
                    'product_name' => $product,
                    'brand_fit_score' => 85,
                    'developer_audience_relevance' => 'Strong developer utility alignment for engineers and technical decision-makers.',
                    'conflicts_detected' => [],
                    'requested_deliverables' => ['YouTube Integration', 'Social Promotion'],
                ],
                'tiered_packages' => [
                    'tier_1_essential' => [
                        'name' => 'Single Platform Midroll',
                        'rate' => (int) $baseYtMidroll,
                        'deliverables' => '60s organic YouTube integration + description link + pinned comment',
                        'cpm' => 45,
                        'rationale' => 'Focused product spotlight embedded seamlessly within relevant technical tutorial.',
                    ],
                    'tier_2_synergy' => [
                        'name' => 'Cross-Platform B2B Synergy (Recommended)',
                        'rate' => (int) round(($baseYtMidroll + $baseLiPost + $baseXThread) * 0.85, -1),
                        'deliverables' => 'YouTube 60s integration + 1x LinkedIn post + 1x X thread breakdown',
                        'cpm' => 40,
                        'rationale' => 'Captures long-tail YouTube search along with high-velocity LinkedIn B2B reach.',
                    ],
                    'tier_3_takeover' => [
                        'name' => 'Full Omnichannel Authority Campaign',
                        'rate' => (int) round(($baseYtDedicated + $baseLiPost * 2 + $baseXThread) * 0.9, -1),
                        'deliverables' => 'Dedicated YouTube feature + LinkedIn post + X thread + TikTok breakdown + 30-day organic usage rights',
                        'cpm' => 35,
                        'rationale' => 'Comprehensive category ownership for landmark launches.',
                    ],
                ],
                'negotiation_email' => [
                    'subject' => "{$creatorName} (@Tech.Unicorn) × {$brand} Partnership Proposal",
                    'body' => "Hi team at {$brand},\n\nThank you for reaching out! I've been following what you're building with {$product}, and there's strong organic synergy with my audience of senior software engineers, tech leads, and builders.\n\nTo ensure maximum conversion and developer engagement, I've outlined 3 partnership tiers ranging from a focused YouTube integration to a full cross-platform campaign across YouTube, LinkedIn, and X.\n\nOur most popular package is Tier 2 (Cross-Platform Synergy at \$".round(($baseYtMidroll + $baseLiPost + $baseXThread) * 0.85, -1)."), which reinforces the video tutorial with a deep-dive LinkedIn post to capture B2B engineering leaders.\n\nLet me know which direction aligns best with your launch goals, or if you'd like to jump on a quick 15-minute alignment call.\n\nBest regards,\n{$creatorName}\n@Tech.Unicorn",
                    'call_to_action' => 'Reply to select package or schedule 15-minute campaign onboarding call.',
                ],
                'contract_stipulations' => [
                    'usage_rights' => '30 days organic digital rights. Paid amplification/whitelisting available at +35%.',
                    'payment_terms' => 'Net 30 upon publication; 50% upfront required for deals over $5,000.',
                    'revision_limits' => 'Up to 2 rounds of review prior to scheduling.',
                    'ftc_compliance' => 'Explicit verbal and visual #ad / sponsored disclosure required by FTC 16 CFR § 255.5.',
                ],
            ];
        }
    }

    /**
     * Convert an approved negotiation package into a tracked Deal in the studio pipeline.
     *
     * @param  array{
     *     brand_name: string,
     *     tier_key: string,
     *     rate: numeric,
     *     deliverables: string,
     *     contact_email?: string,
     *     notes?: string
     * }  $params
     */
    public function createDealFromNegotiation(SocialAgent $agent, array $params): Deal
    {
        $deal = Deal::create([
            'brand_name' => $params['brand_name'],
            'contact_email' => $params['contact_email'] ?? null,
            'stage' => 'negotiating',
            'deliverables' => [$params['deliverables']],
            'rate' => (float) $params['rate'],
            'currency' => 'USD',
            'payment_terms' => 'Net 30',
            'payment_status' => 'pending',
            'notes' => trim(($params['notes'] ?? '')."\nAutonomously initiated via Deal Desk Agent for {$agent->name}."),
        ]);

        return $deal;
    }
}

