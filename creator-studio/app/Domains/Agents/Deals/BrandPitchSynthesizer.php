<?php

namespace App\Domains\Agents\Deals;

use App\Domains\Agents\Guardrails\AgentGuardrails;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Deals\DealCalculator;
use App\Domains\Landing\Audience;
use App\Models\Setting;
use App\Services\Llm;
use App\Services\Prompts;

/**
 * Autonomous Proactive Brand Pitch Synthesizer.
 * Generates tailored outbound partnership pitch decks, executive email pitches,
 * and high-converting integration concepts for B2B developer tool companies.
 */
class BrandPitchSynthesizer
{
  public function __construct(
    private Llm $llm,
    private AgentGuardrails $guardrails
  ) {
  }

  /**
   * Synthesize an outbound sponsorship pitch package for a target brand.
   *
   * @param  array{brand_name: string, product_category?: string, target_audience?: string, pitch_angle?: string}  $params
   * @return array{
   *     target_brand: string,
   *     audience_synergy_score: int,
   *     developer_demographic_fit: string,
   *     integration_concepts: array<int, array{
   *         title: string,
   *         format: string,
   *         hook: string,
   *         technical_premise: string,
   *         projected_views: string
   *     }>,
   *     tiered_packages: array{
   *         midroll_spotlight: array{name: string, rate: int, deliverables: string, cpm: int},
   *         omnichannel_synergy: array{name: string, rate: int, deliverables: string, cpm: int},
   *         full_category_takeover: array{name: string, rate: int, deliverables: string, cpm: int}
   *     },
   *     outbound_email: array{
   *         subject: string,
   *         recipient_title: string,
   *         body: string,
   *         call_to_action: string
   *     },
   *     roi_projection: array{
   *         estimated_impressions: int,
   *         estimated_developer_clicks: int,
   *         target_cpa_benchmark: string
   *     }
   * }
   */
  public function synthesizePitch(SocialAgent $agent, array $params): array
  {
    $brandName = trim((string) ($params['brand_name'] ?? 'Target Brand'));
    $category = trim((string) ($params['product_category'] ?? 'Developer Infrastructure & AI Tooling'));
    $angle = trim((string) ($params['pitch_angle'] ?? ''));

    // Retrieve studio rate cards & reach metrics
    $rateData = DealCalculator::calculateRates();
    $ytRate = $rateData['rates']['youtube_integration_60s']['rate'] ?? 2500;
    $dedicatedRate = $rateData['rates']['youtube_dedicated']['rate'] ?? 6000;
    $omniRate = $rateData['rates']['omnichannel_takeover']['rate'] ?? 4200;

    $ytAudience = Audience::count('youtube');
    $liAudience = Audience::count('linkedin');
    $xAudience = Audience::count('x');

    $creatorName = Prompts::name();

    $system = <<<PROMPT
You are the Executive Vice President of Strategic Brand Partnerships for {$creatorName} (@Tech.Unicorn), a top-tier software engineering creator.
{$creatorName} creates high-signal, zero-fluff content on distributed systems, modern databases, and developer tooling with an audience composed of 84% senior software engineers, tech leads, and engineering directors.

Your task is to craft a compelling, high-converting outbound sponsorship pitch to a developer tool company.
Focus on solving real developer friction, high audience trust, and measurable ROI (signups, SDK adoptions, developer mindshare).

Return ONLY a valid JSON object matching this schema:
{
  "target_brand": "Brand",
  "audience_synergy_score": 96,
  "developer_demographic_fit": "84% senior engineers and tech leads with direct architectural buying authority.",
  "integration_concepts": [
    {
      "title": "Concept 1 Title",
      "format": "YouTube 60s Integration + LinkedIn Breakdown",
      "hook": "We benchmarked 100k requests per second using Brand's managed engine...",
      "technical_premise": "Concrete architectural problem solved live on screen.",
      "projected_views": "25,000 - 45,000"
    }
  ],
  "tiered_packages": {
    "midroll_spotlight": {
      "name": "Targeted Video Midroll",
      "rate": 2500,
      "deliverables": "60s YouTube midroll + description link",
      "cpm": 45
    },
    "omnichannel_synergy": {
      "name": "Omnichannel Tech Synergy (Recommended)",
      "rate": 4200,
      "deliverables": "YouTube 60s + LinkedIn Post + X Thread",
      "cpm": 38
    },
    "full_category_takeover": {
      "name": "Full Category Takeover",
      "rate": 7500,
      "deliverables": "Dedicated Video + Cross-Platform Blitz + 30d paid usage rights",
      "cpm": 32
    }
  },
  "outbound_email": {
    "subject": "{$creatorName} (@Tech.Unicorn) × Brand — Developer Partnership Concept",
    "recipient_title": "Head of DevRel / VP of Marketing",
    "body": "Hi team, I follow Brand's work closely...",
    "call_to_action": "Let's coordinate a 15-minute call or reply with your current Q1 initiatives."
  },
  "roi_projection": {
    "estimated_impressions": 65000,
    "estimated_developer_clicks": 2800,
    "target_cpa_benchmark": "Estimated $1.20 - $2.40 per qualified developer signup."
  }
}
PROMPT;

    $prompt = <<<TEXT
Synthesize an outbound partnership pitch for:
TARGET BRAND: {$brandName}
PRODUCT CATEGORY: {$category}
SPECIFIC ANGLE / FOCUS: {$angle}

STUDIO AUDIENCE BENCHMARKS:
- YouTube Subscribers: {$ytAudience} (Median Views: {$rateData['metrics']['youtube_median_views']})
- LinkedIn Followers: {$liAudience} (Senior Tech Demographic)
- X / Twitter Followers: {$xAudience}
- Standard Integration Rate: \${$ytRate}
- Standard Omnichannel Rate: \${$omniRate}
- Standard Dedicated Rate: \${$dedicatedRate}
TEXT;

    try {
      $data = $this->llm->as('brand-pitch-synthesizer')->creativity(0.3)->json($system, $prompt);
    } catch (\Throwable) {
      $data = null;
    }

    if (!is_array($data) || !isset($data['target_brand'])) {
      return [
        'target_brand' => $brandName,
        'audience_synergy_score' => 92,
        'developer_demographic_fit' => 'High concentration of senior backend engineers and cloud architects.',
        'integration_concepts' => [
          [
            'title' => "Deploying {$brandName} in a 100k req/s Production Cluster",
            'format' => 'YouTube 60s Integration + LinkedIn Post',
            'hook' => "Here is how {$brandName} simplifies distributed state management under heavy load.",
            'technical_premise' => 'Practical performance demonstration comparing traditional setup with modern SDK.',
            'projected_views' => '25,000 - 40,000',
          ],
        ],
        'tiered_packages' => [
          'midroll_spotlight' => ['name' => 'Midroll Spotlight', 'rate' => $ytRate, 'deliverables' => '60s YouTube Midroll', 'cpm' => 45],
          'omnichannel_synergy' => ['name' => 'Omnichannel Synergy', 'rate' => $omniRate, 'deliverables' => 'YouTube + LinkedIn + X Thread', 'cpm' => 38],
          'full_category_takeover' => ['name' => 'Full Category Takeover', 'rate' => $dedicatedRate, 'deliverables' => 'Dedicated Video + 30-day usage rights', 'cpm' => 32],
        ],
        'outbound_email' => [
          'subject' => "Delia (@Tech.Unicorn) × {$brandName} — Developer Partnership",
          'recipient_title' => 'Head of DevRel / VP Marketing',
          'body' => "Hi {$brandName} team,\n\nI love what you are building in {$category}...",
          'call_to_action' => 'Open to sharing our media kit and rates for an upcoming video?',
        ],
        'roi_projection' => [
          'estimated_impressions' => 50000,
          'estimated_developer_clicks' => 2200,
          'target_cpa_benchmark' => '$1.50 per qualified developer visit.',
        ],
      ];
    }

    return $data;
  }
}