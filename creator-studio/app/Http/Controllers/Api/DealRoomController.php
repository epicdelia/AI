<?php

namespace App\Http\Controllers\Api;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Deals\CallPrep;
use App\Domains\Deals\PipelineInsights;
use App\Domains\Deals\ProposalWriter;
use App\Domains\Workflows\Engine;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Services\Prompts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Closing: the client's private deal page (/deal/<token>: the proposal, Accept with their typed
 * name, Pay through Stripe), which tells her when it's opened and accepted; a prep brief before
 * intro calls; marking a deal lost with a reason; and what her closed deals say.
 */
class DealRoomController extends Controller
{
    // ------------------------------------------------------------ public (the client)

    public function show(string $token): JsonResponse
    {
        $deal = self::byToken($token);
        $first = ! $deal->room_viewed_at || $deal->room_viewed_at->lt(now()->subHour());
        $deal->forceFill(['room_views' => $deal->room_views + 1, 'room_viewed_at' => now()])->saveQuietly();
        if ($first && ! $deal->accepted_at) {
            AgentEvent::log('lead', "{$deal->brand_name} opened your proposal.");
            Engine::fire('deal_opened', Engine::dealContext($deal, ['what' => 'proposal']), "room:{$deal->id}:".now()->format('YmdH'));
        }
        $p = $deal->proposal;

        return response()->json([
            'creator' => Prompts::name(),
            'client' => $deal->brand_name,
            'contact' => $deal->contact_name,
            'proposal' => $p,
            'markdown' => $p ? ProposalWriter::markdown($p) : null,
            'amount' => (float) ($p['investment']['amount'] ?? $deal->rate),
            'currency' => $p['investment']['currency'] ?? ($deal->currency ?: 'USD'),
            'accepted_at' => $deal->accepted_at?->toIso8601String(),
            'accepted_by' => $deal->accepted_by,
            'paid' => $deal->payment_status === 'paid',
            'can_pay' => StripePaymentController::configured() && (float) $deal->rate > 0,
            'meeting_at' => $deal->meeting_at?->toIso8601String(),
        ]);
    }

    /** "I accept": their typed name and the time, and the deal moves to Contracted. */
    public function accept(Request $request, string $token): JsonResponse
    {
        $deal = self::byToken($token);
        $data = $request->validate(['name' => 'required|string|min:2|max:120', 'agree' => 'accepted']);

        return self::acceptDeal($deal, $data['name']);
    }

    /** Shared with the client dashboard: the same Accept, from the deal room or signed in. */
    public static function acceptDeal(Deal $deal, string $name): JsonResponse
    {
        if (! $deal->accepted_at) {
            $before = array_search($deal->stage, Deal::STAGES, true);
            $deal->update(['accepted_at' => now(), 'accepted_by' => $name, 'last_contacted_at' => now()]
                + ($before !== false && $before < array_search('contracted', Deal::STAGES, true) ? ['stage' => 'contracted'] : []));
            AgentEvent::log('lead', "{$deal->brand_name} accepted your proposal ({$name}).");
        }

        return response()->json(['accepted_at' => $deal->accepted_at->toIso8601String(), 'accepted_by' => $deal->accepted_by,
            'message' => 'Accepted. Thank you! '.explode(' ', Prompts::name())[0].' will be in touch about next steps.']);
    }

    /** Pay the proposal's amount with Stripe Checkout (the webhook marks it paid). */
    public function pay(Request $request, string $token): JsonResponse
    {
        $deal = self::byToken($token);
        if (! $deal->room_token) {
            abort(404);
        }

        return self::checkout($deal, '/deal/'.$deal->room_token);
    }

    /** Shared with the client dashboard: Stripe Checkout for the deal, back to $returnTo. */
    public static function checkout(Deal $deal, string $returnTo): JsonResponse
    {
        if ($deal->payment_status === 'paid') {
            return response()->json(['message' => 'This is already paid. Thank you!'], 409);
        }
        if (! StripePaymentController::configured() || (float) $deal->rate <= 0) {
            return response()->json(['message' => 'Online payment isn’t available for this proposal: you’ll receive an invoice by email.'], 409);
        }
        $p = $deal->proposal ?? [];
        $session = StripePaymentController::startCheckout($deal, [
            'id' => 'deal_room',
            'name' => $p['title'] ?? $deal->brand_name,
            'description' => isset($p['summary']) ? mb_substr($p['summary'], 0, 300) : null,
            'amount' => (int) round((float) $deal->rate * 100),
            'currency' => strtolower($deal->currency ?: 'usd'),
            'interval' => ($p['plan'] ?? null) === 'retainer' ? 'month' : null,
        ], $deal->contact_email, $returnTo);

        return $session
            ? response()->json(['checkout_url' => $session['url']])
            : response()->json(['message' => 'Payment couldn’t start. Please try again in a minute.'], 502);
    }

    // ------------------------------------------------------------ hers

    public function link(Deal $deal): JsonResponse
    {
        if (! $deal->room_token) {
            $deal->forceFill(['room_token' => Str::random(32)])->saveQuietly();
        }

        return response()->json(['url' => url('/deal/'.$deal->room_token), 'views' => $deal->room_views, 'viewed_at' => $deal->room_viewed_at?->toIso8601String(),
            'accepted_at' => $deal->accepted_at?->toIso8601String(), 'accepted_by' => $deal->accepted_by, 'has_proposal' => (bool) $deal->proposal]);
    }

    public function prep(Deal $deal, CallPrep $prep): JsonResponse
    {
        @set_time_limit(180);

        return response()->json(['prep' => $prep->brief($deal)]);
    }

    public function lost(Request $request, Deal $deal): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', Rule::in(array_keys(Deal::LOST_REASONS))], 'note' => 'nullable|string|max:1000']);
        $deal->update(['stage' => 'lost', 'lost_reason' => $data['reason']]
            + (! empty($data['note']) ? ['notes' => trim(($deal->notes ? $deal->notes."\n\n" : '').now()->format('j M').': Lost ('.Deal::LOST_REASONS[$data['reason']]."): {$data['note']}")] : []));

        return response()->json(['message' => 'Marked as lost. It counts towards your win rate.', 'deal' => $deal->fresh()]);
    }

    public function insights(Request $request): JsonResponse
    {
        $days = (int) $request->integer('days', 365);

        return response()->json(PipelineInsights::summary(in_array($days, [90, 180, 365, 730], true) ? $days : 365));
    }

    private static function byToken(string $token): Deal
    {
        abort_unless(strlen($token) >= 20, 404);

        return Deal::where('room_token', $token)->firstOrFail();
    }
}
