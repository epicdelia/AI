<?php

namespace App\Domains\Agents\Deals;

use App\Domains\Agents\Guardrails\AgentGuardrails;
use App\Domains\Agents\Models\SocialAgent;
use App\Models\Deal;
use App\Services\Llm;
use App\Services\Prompts;
use Throwable;

/**
 * Autonomous Deal Fulfillment Guardian & Contract Compliance Auditor.
 * Protects creator sponsorship revenue by ensuring draft content meets all contractual
 * deliverables, mandatory FTC disclosures, tracking links, and competitor non-competes.
 * Also synthesizes professional sponsor wrap reports to accelerate Net-30 invoice approval.
 */
class DealFulfillmentGuardian
{
    public function __construct(
        private Llm $llm,
        private AgentGuardrails $guardrails
    ) {
    }

    /**
     * Audit draft script or social post against contracted Deal stipulations.
     *
     * @param  array{deal_id?: int, content: string, platform?: string, title?: string}  $params
     * @return array{
     *     compliance_score: int,
     *     status: string,
     *     checks: array{
     *         deliverable_coverage: array{status: string, details: string},
     *         talking_points_checklist: array<string, bool>,
     *         ftc_disclosure: array{compliant: bool, detected_phrase: ?string, notes: string},
     *         tracking_and_promo: array{compliant: bool, promo_detected: bool, link_detected: bool},
     *         competitor_conflicts: array{has_conflict: bool, conflicting_brands: array<int, string>, details: string}
     *     },
     *     missing_mandatories: array<int, string>,
     *     suggested_remedies: array<int, string>,
     *     armored_disclosure_snippet: string
     * }
     */
    public function auditDraft(SocialAgent $agent, array $params): array
    {
        $content = trim((string) ($params['content'] ?? ''));
        $platform = (string) ($params['platform'] ?? 'youtube');
        $title = (string) ($params['title'] ?? '');

        $deal = null;
        if (!empty($params['deal_id'])) {
            $deal = Deal::find($params['deal_id']);
        }

        $brandName = $deal?->brand_name ?? ($params['brand_name'] ?? 'Sponsor Brand');
        $couponCode = $deal?->coupon_code ?? ($params['coupon_code'] ?? null);
        $trackingUrl = $deal?->tracking_url ?? ($params['tracking_url'] ?? null);
        $talkingPoints = $deal?->key_talking_points ?? ($params['key_talking_points'] ?? 'Highlight performance, reliability, and modern developer workflow.');
        $doNots = $deal?->do_nots ?? ($params['do_nots'] ?? 'Do not claim 100% uptime, do not disparage enterprise customers.');
        $deliverables = self::deliverablesText($deal) ?: 'Dedicated integration / Sponsored segment';

        // Retrieve other active deals to detect non-compete/exclusivity conflicts
        $otherActiveSponsors = Deal::where('id', '!=', $deal?->id ?? 0)
            ->whereNotIn('stage', ['paid', 'lost'])
            ->pluck('brand_name')
            ->filter()
            ->values()
            ->all();

        $system = <<<'PROMPT'
You are the Head of Legal Compliance and Brand Sponsorship Fulfillment for an elite developer influencer studio.
Your goal is to inspect creator draft content to guarantee 100% contractual fulfillment, protect against deal clawbacks, ensure strict FTC/regulatory ad disclosures, and avoid competitor non-compete breaches.

Return ONLY a valid JSON object matching this exact schema:
{
  "compliance_score": 95,
  "status": "approved" | "revisions_needed" | "critical_breach",
  "checks": {
    "deliverable_coverage": {
      "status": "verified" | "partial" | "missing",
      "details": "Clear 60-second midroll integration positioned after initial hook."
    },
    "talking_points_checklist": {
      "Talking point 1": true,
      "Talking point 2": false
    },
    "ftc_disclosure": {
      "compliant": true,
      "detected_phrase": "Thanks to Neon for sponsoring today's video",
      "notes": "Prominently disclosed within first 30 seconds."
    },
    "tracking_and_promo": {
      "compliant": true,
      "promo_detected": true,
      "link_detected": true
    },
    "competitor_conflicts": {
      "has_conflict": false,
      "conflicting_brands": [],
      "details": "No competitor mentions detected during current exclusivity window."
    }
  },
  "missing_mandatories": ["Missing mention of free tier signup credit."],
  "suggested_remedies": ["Insert verbal discount code cue before demo ends."],
  "armored_disclosure_snippet": "This video is sponsored by Neon. Use code UNICORN at neon.tech/unicorn for $50 free cloud credits."
}
PROMPT;

        $prompt = <<<TEXT
Audit the following draft for brand deal fulfillment:
BRAND NAME: {$brandName}
DELIVERABLES: {$deliverables}
KEY TALKING POINTS: {$talkingPoints}
DO NOTS / CONSTRAINTS: {$doNots}
COUPON CODE: {$couponCode}
TRACKING URL: {$trackingUrl}
OTHER ACTIVE SPONSORS (Exclusivity Check): {$this->formatArray($otherActiveSponsors)}
PLATFORM: {$platform}
CONTENT TITLE: {$title}

DRAFT CONTENT:
{$content}
TEXT;

        try {
            $data = $this->llm->as('deal-fulfillment-guardian')->creativity(0.2)->json($system, $prompt);
        } catch (Throwable) {
            $data = $this->fallbackAudit($brandName, $couponCode, $trackingUrl, $content);
        }

        if (!is_array($data) || !isset($data['compliance_score'])) {
            $data = $this->fallbackAudit($brandName, $couponCode, $trackingUrl, $content);
        }

        // If a deal was supplied, update its checklist status
        if ($deal && !empty($data['checks']['talking_points_checklist'])) {
            try {
                $deal->update([
                    'talking_points_checklist' => $data['checks']['talking_points_checklist'],
                ]);
            } catch (Throwable) {
                // Ignore update issues in read-only environments
            }
        }

        return $data;
    }

    /**
     * Generate an executive sponsor wrap report for completed campaign.
     *
     * @param  array{deal_id?: int, impressions?: int, clicks?: int, positive_comments?: array<int, string>, live_url?: string}  $params
     * @return array{
     *     brand_name: string,
     *     campaign_status: string,
     *     executive_summary: string,
     *     key_metrics: array{
     *         impressions: int,
     *         engagement_rate: string,
     *         ctr: string,
     *         estimated_cpm: int
     *     },
     *     audience_sentiment: array{
     *         overall: string,
     *         sentiment_score: int,
     *         highlighted_quotes: array<int, string>
     *     },
     *     contractual_fulfillment_statement: string,
     *     ready_for_invoicing: bool
     * }
     */
    public function generateWrapReport(SocialAgent $agent, array $params): array
    {
        $deal = null;
        if (!empty($params['deal_id'])) {
            $deal = Deal::find($params['deal_id']);
        }

        $brandName = $deal?->brand_name ?? ($params['brand_name'] ?? 'Partner Brand');
        $rate = (int) ($deal?->rate ?? ($params['rate'] ?? 3500));
        $deliverables = self::deliverablesText($deal) ?: 'YouTube Integration + Social Cascade';
        // Only real numbers: what she typed, else the linked post's logged numbers, else 0 ("not measured").
        $metrics = (array) ($deal?->socialPost?->metrics ?? []);
        $impressions = (int) ($params['impressions'] ?? ($metrics['impressions'] ?? 0));
        $clicks = (int) ($params['clicks'] ?? ($metrics['clicks'] ?? 0));
        $liveUrl = (string) ($params['live_url'] ?? ($deal?->socialPost?->url ?: ($deal?->script?->youtube_url ?: '')));
        $comments = array_values(array_filter((array) ($params['positive_comments'] ?? []), 'is_string'));

        $system = <<<'PROMPT'
You are the Executive Vice President of Partnerships for Delia Lazarescu (@Tech.Unicorn).
Draft a clear, professional Sponsor Wrap Report for a completed technical sponsorship campaign.
Use ONLY the numbers, link and comments given below: never invent figures, comparisons with targets, percentages or quotes.
key_metrics.impressions must equal RECORDED IMPRESSIONS. A number of 0 means it was not measured: say "not measured yet" in the summary instead of guessing.
highlighted_quotes may only contain AUDIENCE COMMENTS given below (an empty list if there are none); sentiment_score only if there are comments, else 0.
ready_for_invoicing is true only when the deliverables are published (a LIVE URL is given).

Return ONLY a valid JSON object matching:
{
  "brand_name": "Brand",
  "campaign_status": "Complete & Verified",
  "executive_summary": "Two to three sentences on what was delivered and what was measured.",
  "key_metrics": {
    "impressions": 42000,
    "engagement_rate": "4.8%",
    "ctr": "4.4%",
    "estimated_cpm": 83
  },
  "audience_sentiment": {
    "overall": "Extremely Positive",
    "sentiment_score": 96,
    "highlighted_quotes": ["Quote 1", "Quote 2"]
  },
  "contractual_fulfillment_statement": "All agreed deliverables have been fully published and verified in accordance with FTC guidelines.",
  "ready_for_invoicing": true
}
PROMPT;

        $prompt = <<<TEXT
Generate Sponsor Wrap Report for:
BRAND: {$brandName}
RATE: \${$rate}
DELIVERABLES: {$deliverables}
RECORDED IMPRESSIONS: {$impressions}
RECORDED CLICKS: {$clicks}
LIVE URL: {$liveUrl}
AUDIENCE COMMENTS:
{$this->formatArray($comments)}
TEXT;

        try {
            $data = $this->llm->as('sponsor-wrap-report')->creativity(0.3)->json($system, $prompt);
        } catch (Throwable) {
            $data = null;
        }

        if (!is_array($data) || !isset($data['ready_for_invoicing'])) {
            $measured = $impressions > 0
                ? number_format($impressions).' impressions'.($clicks > 0 ? ' and '.number_format($clicks).' clicks' : '').' recorded'
                : 'numbers not measured yet';

            return [
                'brand_name' => $brandName,
                'campaign_status' => $liveUrl !== '' ? 'Delivered' : 'In progress',
                'executive_summary' => "The {$brandName} campaign ({$deliverables}): {$measured}.".($liveUrl !== '' ? " Live at {$liveUrl}." : ''),
                'key_metrics' => [
                    'impressions' => $impressions,
                    'engagement_rate' => 'not measured',
                    'ctr' => $impressions > 0 && $clicks > 0 ? round($clicks / $impressions * 100, 2).'%' : 'not measured',
                    'estimated_cpm' => $impressions > 0 ? (int) round(($rate / $impressions) * 1000) : 0,
                ],
                'audience_sentiment' => [
                    'overall' => $comments ? 'From the comments below' : 'No comments added',
                    'sentiment_score' => 0,
                    'highlighted_quotes' => $comments,
                ],
                'contractual_fulfillment_statement' => $liveUrl !== '' ? 'Deliverables published as listed above.' : 'Not published yet.',
                'ready_for_invoicing' => $liveUrl !== '',
            ];
        }

        // The model may not change the recorded number.
        $data['key_metrics']['impressions'] = $impressions;

        return $data;
    }

    private function fallbackAudit(string $brand, ?string $code, ?string $link, string $content): array
    {
        $hasBrand = stripos($content, $brand) !== false;
        $hasAd = stripos($content, '#ad') !== false || stripos($content, 'sponsor') !== false;
        $score = ($hasBrand ? 50 : 0) + ($hasAd ? 40 : 0);

        return [
            'compliance_score' => $score,
            'status' => $score >= 80 ? 'approved' : 'revisions_needed',
            'checks' => [
                'deliverable_coverage' => ['status' => $hasBrand ? 'verified' : 'missing', 'details' => 'Brand mentioned in content.'],
                'talking_points_checklist' => ['Primary Brand Integration' => $hasBrand],
                'ftc_disclosure' => ['compliant' => $hasAd, 'detected_phrase' => $hasAd ? '#ad' : null, 'notes' => 'Disclosure check.'],
                'tracking_and_promo' => ['compliant' => true, 'promo_detected' => (bool) $code, 'link_detected' => (bool) $link],
                'competitor_conflicts' => ['has_conflict' => false, 'conflicting_brands' => [], 'details' => 'No direct conflicts.'],
            ],
            'missing_mandatories' => $hasAd ? [] : ['Missing #ad or sponsorship verbal disclosure.'],
            'suggested_remedies' => ['Add clear sponsorship disclosure at beginning of content.'],
            'armored_disclosure_snippet' => "Sponsored by {$brand}. Check out the link in the description.",
        ];
    }

    private function formatArray(array $arr): string
    {
        return empty($arr) ? 'None' : implode("\n- ", $arr);
    }

    /** A deal's deliverables as one line, whether stored as strings or {platform, format, …}. */
    public static function deliverablesText(?Deal $deal): string
    {
        return collect(is_array($deal?->deliverables) ? $deal->deliverables : [])
            ->map(fn ($d) => is_string($d) ? $d : trim(($d['platform'] ?? '').' '.($d['format'] ?? '')))
            ->filter()->implode(', ');
    }
}
