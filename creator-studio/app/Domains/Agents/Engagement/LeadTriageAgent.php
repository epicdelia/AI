<?php

namespace App\Domains\Agents\Engagement;

use App\Domains\Agents\Guardrails\AgentGuardrails;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Engagement\Models\EngagementItem;
use App\Models\Deal;
use App\Models\Setting;
use App\Services\Llm;
use App\Services\Prompts;
use Throwable;

/**
 * Autonomous Community Lead Triage & Revenue Pipeline Agent.
 * Scans incoming comments and DMs from YouTube, LinkedIn, X, Instagram, and TikTok
 * to identify high-ticket commercial opportunities (sponsorships, architecture consulting,
 * speaking keynotes, customer inquiries) and automatically routes them to the Deal Desk pipeline.
 */
class LeadTriageAgent
{
    public const COMMERCIAL_INTENTS = [
        'sponsor_lead' => 'Brand Sponsorship / Advertising Partner ($2k - $25k)',
        'consulting_advisory' => 'Architecture Advisory / Technical Consulting ($3k - $15k)',
        'speaking_invite' => 'Conference Keynote / Podcast Guest ($1.5k - $7.5k)',
        'product_buyer' => 'Digital Product / Workshop / Booking ($100 - $1.5k)',
        'general_audience' => 'General Viewer Feedback / Technical Question ($0)',
    ];

    public function __construct(
        private Llm $llm,
        private AgentGuardrails $guardrails
    ) {}

    /**
     * Triage incoming engagement messages for commercial lead value.
     *
     * @param  array{messages?: array<int, array{id?: mixed, text: string, author?: string, platform?: string}>, item_id?: int}  $params
     * @return array{
     *     total_screened: int,
     *     leads_detected: int,
     *     total_pipeline_value: int,
     *     leads: array<int, array{
     *         item_id: ?int,
     *         author: string,
     *         platform: string,
     *         text: string,
     *         intent: string,
     *         lead_score: int,
     *         priority: string,
     *         estimated_value_usd: int,
     *         draft_response: string,
     *         suggested_action: string
     *     }>
     * }
     */
    public function triage(SocialAgent $agent, array $params): array
    {
        $messages = $params['messages'] ?? [];

        if (empty($messages) && ! empty($params['item_id'])) {
            $item = EngagementItem::find($params['item_id']);
            if ($item) {
                $messages[] = [
                    'id' => $item->id,
                    'text' => $item->text,
                    'author' => $item->author ?? 'Anonymous',
                    'platform' => $item->platform,
                ];
            }
        }

        // If no explicit messages provided, fetch the newest unhandled engagement items
        if (empty($messages)) {
            $items = EngagementItem::whereIn('status', ['new', 'drafted'])
                ->where('intent', '!=', 'spam')
                ->latest('received_at')
                ->take(10)
                ->get();

            foreach ($items as $it) {
                $messages[] = [
                    'id' => $it->id,
                    'text' => $it->text,
                    'author' => $it->author ?: 'Audience Member',
                    'platform' => $it->platform,
                ];
            }
        }

        if (empty($messages)) {
            return [
                'total_screened' => 0,
                'leads_detected' => 0,
                'total_pipeline_value' => 0,
                'leads' => [],
            ];
        }

        $creatorName = Setting::get('creator_name', 'Delia Lazarescu');

        $systemPrompt = <<<SYS
You are the Revenue Operations & Community Lead Triage Agent for {$creatorName} (@Tech.Unicorn).
Delia is a high-authority software engineering creator. Many viewer comments and DMs contain lucrative inbound business inquiries that creators miss.

COMMERCIAL INTENT CATEGORIES:
- "sponsor_lead": Brand manager or marketing agency inquiring about sponsoring videos, newsletters, or social posts ($2,000 - $25,000).
- "consulting_advisory": Engineering leader or startup founder wanting Delia to review their architecture or consult for their team ($3,000 - $15,000).
- "speaking_invite": Event organizer inviting Delia to speak at a conference or tech summit ($1,500 - $7,500).
- "product_buyer": High-intent follower asking how to hire her, buy a course, or access consultation bookings ($150 - $1,500).
- "general_audience": Standard feedback, technical debate, or compliments ($0).

For each message, determine if it has commercial intent.
If yes, calculate an estimated value, lead score (0-100), and write a personalized, warm, professional executive reply that guides them to the next step.

Return a valid JSON object matching this schema:
{
  "leads": [
    {
      "index": 0,
      "intent": "sponsor_lead|consulting_advisory|speaking_invite|product_buyer|general_audience",
      "lead_score": 92,
      "priority": "urgent|high|normal|low",
      "estimated_value_usd": 3500,
      "draft_response": "Personalized reply written warmly in Delia's voice...",
      "suggested_action": "e.g. Forward to Deal Desk and send Media Kit"
    }
  ]
}
SYS;

        $messageList = '';
        foreach ($messages as $idx => $m) {
            $messageList .= "[{$idx}] Author: {$m['author']} ({$m['platform']})\nText: \"{$m['text']}\"\n\n";
        }

        $this->guardrails->recordTokens((int) (mb_strlen($systemPrompt . $messageList) / 4), 600);

        try {
            $parsed = $this->llm->as('lead-triage-agent')->creativity(0.3)->json($systemPrompt, $messageList);
            $rawLeads = $parsed['leads'] ?? [];
        } catch (Throwable $e) {
            $rawLeads = [];
        }

        $leads = [];
        $totalPipeline = 0;

        foreach ($messages as $idx => $orig) {
            $found = null;
            foreach ($rawLeads as $rl) {
                if (($rl['index'] ?? -1) === $idx) {
                    $found = $rl;
                    break;
                }
            }

            $intent = $found['intent'] ?? 'general_audience';
            $val = (int) ($found['estimated_value_usd'] ?? 0);
            $score = (int) ($found['lead_score'] ?? 50);

            // Fallback heuristic if LLM did not classify
            if (! $found) {
                $textLower = strtolower($orig['text']);
                if (preg_match('/(sponsor|partner|collab|rate card|media kit|budget)/i', $textLower)) {
                    $intent = 'sponsor_lead';
                    $val = 3000;
                    $score = 88;
                } elseif (preg_match('/(consult|advisor|hire you|hourly rate|review our)/i', $textLower)) {
                    $intent = 'consulting_advisory';
                    $val = 4500;
                    $score = 85;
                } elseif (preg_match('/(speaker|keynote|conference|summit|podcast guest)/i', $textLower)) {
                    $intent = 'speaking_invite';
                    $val = 2000;
                    $score = 80;
                }
            }

            $isLead = in_array($intent, ['sponsor_lead', 'consulting_advisory', 'speaking_invite', 'product_buyer'], true);

            if ($isLead) {
                $totalPipeline += $val;
            }

            $leads[] = [
                'item_id' => $orig['id'] ?? null,
                'author' => $orig['author'],
                'platform' => $orig['platform'],
                'text' => $orig['text'],
                'intent' => $intent,
                'lead_score' => $score,
                'priority' => $found['priority'] ?? ($score >= 85 ? 'high' : 'normal'),
                'estimated_value_usd' => $val,
                'draft_response' => $found['draft_response'] ?? "Hi {$orig['author']}, thanks for reaching out! I'd love to explore this. Could you send details to my partnerships desk or grab 15 minutes on my booking calendar?",
                'suggested_action' => $found['suggested_action'] ?? ($isLead ? 'Escalate to Deals Pipeline' : 'Reply with standard FAQ'),
            ];
        }

        $detectedCount = count(array_filter($leads, fn ($l) => $l['intent'] !== 'general_audience'));

        return [
            'total_screened' => count($messages),
            'leads_detected' => $detectedCount,
            'total_pipeline_value' => $totalPipeline,
            'leads' => $leads,
        ];
    }

    /**
     * Escalate a triaged lead into a formal Deal in the studio pipeline.
     *
     * @param  array{author: string, intent: string, estimated_value: numeric, text: string, contact_email?: string}  $leadData
     */
    public function escalateToDeal(SocialAgent $agent, array $leadData): Deal
    {
        $brandName = trim((string) ($leadData['author'] ?? 'Inbound Lead'));
        $rate = max(0, (float) ($leadData['estimated_value'] ?? 2500));
        $intent = $leadData['intent'] ?? 'sponsor_lead';

        $deliverables = match ($intent) {
            'consulting_advisory' => ['Architecture Advisory & Systems Review'],
            'speaking_invite' => ['Keynote Presentation / Technical Panel'],
            'product_buyer' => ['Private Workshop / Advisory Session'],
            default => ['YouTube Midroll Integration + Social Post'],
        };

        $deal = Deal::create([
            'brand_name' => $brandName,
            'contact_name' => $brandName,
            'contact_email' => $leadData['contact_email'] ?? null,
            'stage' => 'lead',
            'deliverables' => $deliverables,
            'rate' => $rate,
            'currency' => 'USD',
            'payment_status' => 'pending',
            'notes' => "Triaged from community engagement by Lead Triage Agent.\nOriginal inquiry: \"{$leadData['text']}\"",
        ]);

        return $deal;
    }
}

