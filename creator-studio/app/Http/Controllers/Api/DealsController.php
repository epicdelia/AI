<?php

namespace App\Http\Controllers\Api;

use App\Domains\Deals\DealCalculator;
use App\Domains\Deals\MediaKit;
use App\Domains\Deals\PostCampaignReport;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Setting;
use App\Services\Llm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DealsController extends Controller
{
    /**
     * List deals with filters and high-level pipeline totals.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Deal::with(['script:id,title', 'socialPost:id,platform,format,title'])
            ->orderBy('created_at', 'desc');

        if ($stage = $request->input('stage')) {
            $query->where('stage', $stage);
        }

        $deals = $query->get();

        // Pipeline financials
        $totalPipeline = Deal::whereNotIn('stage', ['paid'])->sum('rate');
        $confirmed = Deal::whereIn('stage', ['contracted', 'production', 'in_review', 'scheduled', 'invoiced', 'paid'])->sum('rate');
        $received = Deal::where('stage', 'paid')->sum('rate');
        $pendingInvoice = Deal::where('stage', 'invoiced')->sum('rate');

        $byStage = [];
        foreach (Deal::STAGES as $st) {
            $byStage[$st] = Deal::where('stage', $st)->count();
        }

        return response()->json([
            'deals' => $deals,
            'stages' => Deal::STAGES,
            'stats' => [
                'total_pipeline' => round((float) $totalPipeline, 2),
                'confirmed_revenue' => round((float) $confirmed, 2),
                'received_revenue' => round((float) $received, 2),
                'pending_invoices' => round((float) $pendingInvoice, 2),
                'active_count' => Deal::whereNotIn('stage', ['paid'])->count(),
                'by_stage' => $byStage,
            ],
        ]);
    }

    /**
     * Create a new sponsorship deal.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'brand_name' => 'required|string|max:150',
            'contact_name' => 'nullable|string|max:150',
            'contact_email' => 'nullable|email|max:150',
            'stage' => 'nullable|in:'.implode(',', Deal::STAGES),
            'deliverables' => 'nullable|array',
            'rate' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'payment_terms' => 'nullable|string|max:100',
            'payment_status' => 'nullable|string|in:pending,invoiced,paid',
            'coupon_code' => 'nullable|string|max:50',
            'tracking_url' => 'nullable|string|max:500',
            'key_talking_points' => 'nullable|string|max:2000',
            'talking_points_checklist' => 'nullable|array',
            'do_nots' => 'nullable|string|max:1000',
            'script_id' => 'nullable|exists:scripts,id',
            'social_post_id' => 'nullable|exists:social_posts,id',
            'notes' => 'nullable|string|max:3000',
        ]);

        $deal = Deal::create($data);

        return response()->json([
            'message' => 'Deal created for '.$deal->brand_name,
            'deal' => $deal->load(['script:id,title', 'socialPost:id,platform,format,title']),
        ], 201);
    }

    /**
     * Show single deal.
     */
    public function show(Deal $deal): JsonResponse
    {
        return response()->json([
            'deal' => $deal->load(['script:id,title', 'socialPost:id,platform,format,title']),
        ]);
    }

    /**
     * Update deal.
     */
    public function update(Request $request, Deal $deal): JsonResponse
    {
        $data = $request->validate([
            'brand_name' => 'sometimes|required|string|max:150',
            'contact_name' => 'nullable|string|max:150',
            'contact_email' => 'nullable|email|max:150',
            'stage' => 'sometimes|in:'.implode(',', Deal::STAGES),
            'deliverables' => 'nullable|array',
            'rate' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'payment_terms' => 'nullable|string|max:100',
            'payment_status' => 'nullable|string|in:pending,invoiced,paid',
            'coupon_code' => 'nullable|string|max:50',
            'tracking_url' => 'nullable|string|max:500',
            'key_talking_points' => 'nullable|string|max:2000',
            'talking_points_checklist' => 'nullable|array',
            'do_nots' => 'nullable|string|max:1000',
            'script_id' => 'nullable|exists:scripts,id',
            'social_post_id' => 'nullable|exists:social_posts,id',
            'notes' => 'nullable|string|max:3000',
            'published_at' => 'nullable|date',
            'invoiced_at' => 'nullable|date',
            'paid_at' => 'nullable|date',
        ]);

        if (isset($data['stage'])) {
            if ($data['stage'] === 'paid' && ! $deal->paid_at) {
                $data['paid_at'] = now();
                $data['payment_status'] = 'paid';
            } elseif ($data['stage'] === 'invoiced' && ! $deal->invoiced_at) {
                $data['invoiced_at'] = now();
                $data['payment_status'] = 'invoiced';
            } elseif ($data['stage'] === 'scheduled' && ! $deal->published_at) {
                $data['published_at'] = now();
            }
        }

        $deal->update($data);

        return response()->json([
            'message' => 'Deal updated',
            'deal' => $deal->load(['script:id,title', 'socialPost:id,platform,format,title']),
        ]);
    }

    /**
     * Delete deal.
     */
    public function destroy(Deal $deal): JsonResponse
    {
        $name = $deal->brand_name;
        $deal->delete();

        return response()->json(['message' => 'Deal with '.$name.' deleted']);
    }

    /**
     * Recommended rate card calculations.
     */
    public function rateCard(): JsonResponse
    {
        return response()->json(DealCalculator::calculateRates());
    }

    /**
     * Public media kit dataset.
     */
    public function mediaKit(): JsonResponse
    {
        return response()->json(MediaKit::data());
    }

    /**
     * AI-drafted brand pitch or response email.
     */
    public function generatePitch(Request $request, Llm $llm): JsonResponse
    {
        $data = $request->validate([
            'brand_name' => 'required|string|max:150',
            'product_name' => 'nullable|string|max:150',
            'inquiry_text' => 'nullable|string|max:3000',
            'desired_deliverables' => 'nullable|string|max:500',
            'tone' => 'nullable|string|in:professional,friendly_creator,confident_negotiator',
        ]);

        $brand = $data['brand_name'];
        $product = !empty($data['product_name']) ? $data['product_name'] : $brand;
        $inquiry = !empty($data['inquiry_text']) ? $data['inquiry_text'] : 'Brand reached out wanting to partner on upcoming content.';
        $deliverables = !empty($data['desired_deliverables']) ? $data['desired_deliverables'] : 'YouTube 60s Integration + LinkedIn Post';
        $creator = Setting::get('creator_name', 'Delia Lazarescu');

        $system = <<<PROMPT
You are an executive talent manager and creator agent for {$creator} (@Tech.Unicorn).
She is a top tech creator with 150k+ combined audience across YouTube, LinkedIn, TikTok, Instagram, and X.
Her audience consists of software engineers, tech leads, AI practitioners, and engineering leaders.

Task: Write a concise, confident, high-converting partnership reply / pitch email to the sponsor ({$brand}).
Rules:
- Be warm, professional, and clear on value.
- Mention her relevant technical audience alignment with {$product}.
- Outline the proposed package ({$deliverables}).
- Invite them to review her Media Kit and propose a quick call or next steps.
- Maintain honest, non-spammy phrasing. No cheesy marketer clichés.
- Return a JSON object with:
  "subject": a clean email subject line (e.g. "Delia Lazarescu (@Tech.Unicorn) × {$brand} Partnership")
  "body": the email body text
PROMPT;

        $user = "Brand: {$brand}\nProduct: {$product}\nInbound message / context: {$inquiry}\nProposed deliverables: {$deliverables}";

        try {
            $res = $llm->as('deal:pitch')->json($system, $user);
            return response()->json([
                'pitch' => $res,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'pitch' => [
                    'subject' => "Delia Lazarescu (@Tech.Unicorn) × {$brand} Partnership",
                    'body' => "Hi team at {$brand},\n\nThanks so much for reaching out! I've been following what you're building with {$product}, and there's strong alignment with my community of software engineers, AI practitioners, and tech leads across YouTube, LinkedIn, and TikTok.\n\nFor a partnership, a great fit would be {$deliverables}. This allows us to showcase {$product} hands-on in an authentic, high-retention format.\n\nI've attached my Media Kit with current engagement numbers and audience demographics. Let me know if you'd like to schedule a quick 10-minute chat this week to align on campaign goals.\n\nBest regards,\n{$creator}\n@Tech.Unicorn",
                ],
            ]);
        }
    }

    /**
     * AI-drafted sponsor integration segment for a script.
     */
    public function generateSponsorScript(Request $request, Deal $deal, Llm $llm): JsonResponse
    {
        $data = $request->validate([
            'video_topic' => 'required|string|max:300',
            'duration_seconds' => 'nullable|integer|min:30|max:120',
        ]);

        $brand = $deal->brand_name;
        $points = $deal->key_talking_points ?: 'Hands-on developer tool demo, highlight time saved, free trial link in description.';
        $doNots = $deal->do_nots ?: 'None specified.';
        $code = $deal->coupon_code ? "Use code {$deal->coupon_code}" : 'Link in description';
        $link = $deal->tracking_url ?: 'link in the description and pinned comment';
        $topic = $data['video_topic'];
        $duration = $data['duration_seconds'] ?? 60;
        $words = (int) round(($duration / 60) * 150);

        $system = <<<PROMPT
You are a YouTube scriptwriter for Delia (@Tech.Unicorn).
Write a natural, high-converting {$duration}-second sponsor integration (~{$words} spoken words) for {$brand} to be inserted into a video about "{$topic}".

Rules:
1. Transition Hook: Must segue naturally out of the video topic (no jarring "now a word from our sponsor").
2. Core Segment: Highlight the real problem {$brand} solves for engineers/creators.
3. Call to Action: Direct viewers to {$link} and {$code}.
4. FTC Compliance: Include a clear verbal or on-screen [SPONSORED / AD] disclosure cue.
5. Respect Do-Nots: {$doNots}.
Return JSON with:
"transition_in": the lead-in line from the previous video section,
"script_body": the verbatim spoken sponsor text with [VISUAL] cues for b-roll/screen recordings,
"transition_out": the smooth bridge back to the video topic,
"checklist": array of talking points satisfied.
PROMPT;

        $user = "Brand: {$brand}\nTalking points: {$points}\nCode: {$code}\nLink: {$link}";

        try {
            $res = $llm->as('deal:sponsor_script')->json($system, $user);
            return response()->json([
                'segment' => $res,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'segment' => [
                    'transition_in' => "And when you're scaling an architecture like this, the biggest bottleneck isn't the code—it's having the right tooling in place. Which brings us to today's sponsor, {$brand}.",
                    'script_body' => "[VISUAL: Screen recording of {$brand} interface]\nIf you've ever struggled with debugging distributed workflows, {$brand} gives you instant visibility into your entire pipeline. [YOUR STORY: Share 5-second personal workflow tip]. You can get started for free today, and with my code {$code}, you'll get an extended trial at the link below in the description.",
                    'transition_out' => "Now, let's get back into the second design pattern you need to know.",
                    'checklist' => ["Natural segue from {$topic}", "Clear value proposition", "CTA with {$code}", "Visual demo cues"],
                ],
            ]);
        }
    }

    /**
     * Verified Post-Campaign Wrap-Up Report for brand sponsors.
     */
    public function campaignReport(Deal $deal): JsonResponse
    {
        return response()->json([
            'report' => PostCampaignReport::generate($deal),
        ]);
    }
}
