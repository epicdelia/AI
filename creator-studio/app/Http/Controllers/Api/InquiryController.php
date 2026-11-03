<?php

namespace App\Http\Controllers\Api;

use App\Domains\Clients\ClientDeal;
use App\Domains\Deals\InquiryAutopilot;
use App\Domains\Deals\SponsoredCopy;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Her side of the inquiry autopilot (rules, drafted replies) and of sponsored-copy approval,
 * plus the client's approve / ask-for-changes on that copy.
 */
class InquiryController extends Controller
{
    public function rules(): JsonResponse
    {
        return response()->json(InquiryAutopilot::rules() + [
            'budgets' => array_keys(InquiryAutopilot::BUDGETS),
            'project_type_labels' => BookingController::PROJECT_LABELS,
            'rate_card_reviewed_at' => Setting::get('rate_card_reviewed_at') ?: null,
            'rate_card_stale' => InquiryAutopilot::rateCardStale(),
        ]);
    }

    public function saveRules(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => 'required|boolean',
            'mode' => 'required|in:draft,send',
            'project_types' => 'nullable|array',
            'project_types.*' => 'in:' . implode(',', array_keys(BookingController::PROJECT_LABELS)),
            'min_budget' => 'nullable|integer|min:0|max:10000000',
            'blocked_words' => 'nullable|array|max:50',
            'blocked_words.*' => 'string|max:60',
            'min_lead_days' => 'nullable|integer|min:0|max:365',
            'starting_price' => 'nullable|string|max:60',
            'pursue_note' => 'nullable|string|max:1000',
            'pass_note' => 'nullable|string|max:1000',
            'rate_card_reviewed' => 'nullable|boolean',
        ]);
        if ($data['rate_card_reviewed'] ?? false) {
            Setting::put('rate_card_reviewed_at', now()->toIso8601String());
        }
        unset($data['rate_card_reviewed']);
        Setting::put('inquiry_rules', json_encode(array_merge(InquiryAutopilot::rules(), array_filter($data, fn ($v) => $v !== null))));

        return $this->rules();
    }

    public function sendReply(Request $request, Deal $deal): JsonResponse
    {
        $data = $request->validate(['body' => 'nullable|string|max:10000']);
        $body = trim((string)($data['body'] ?? $deal->auto_reply_body));
        abort_if($body === '', 422, 'There’s no reply to send.');
        $ok = InquiryAutopilot::send($deal, $body);

        return response()->json(['ok' => $ok, 'message' => $ok ? 'Reply sent.' : 'The email couldn’t be sent.']);
    }

    public function discardReply(Deal $deal): JsonResponse
    {
        $deal->forceFill(['auto_reply' => null, 'auto_reply_body' => null])->saveQuietly();

        return response()->json(['ok' => true]);
    }

    // ---- sponsored copy ---------------------------------------------------------------------

    public function copy(Deal $deal): JsonResponse
    {
        return response()->json($this->copyPayload($deal));
    }

    public function sendCopy(Request $request, Deal $deal): JsonResponse
    {
        $data = $request->validate(['text' => 'required|string|max:20000', 'due_on' => 'nullable|date']);
        SponsoredCopy::send($deal, $data['text'], $data['due_on'] ?? null);

        return response()->json($this->copyPayload($deal->fresh()));
    }

    public function overrideCopy(Request $request, Deal $deal): JsonResponse
    {
        $data = $request->validate(['on' => 'required|boolean']);
        $deal->forceFill(['copy_override' => $data['on']])->saveQuietly();

        return response()->json($this->copyPayload($deal->fresh()));
    }

    public function clientApprove(Request $request, int $deal): JsonResponse
    {
        $d = $this->clientDeal($request, $deal);
        SponsoredCopy::approve($d, $request->user()->name);

        return response()->json(['copy' => $this->clientCopy($d->fresh())]);
    }

    public function clientChanges(Request $request, int $deal): JsonResponse
    {
        $data = $request->validate(['note' => 'required|string|min:5|max:5000']);
        $d = $this->clientDeal($request, $deal);
        SponsoredCopy::requestChanges($d, $request->user()->name, trim($data['note']));

        return response()->json(['copy' => $this->clientCopy($d->fresh())]);
    }

    public static function clientCopy(Deal $d): ?array
    {
        if (!$d->copy_status) {
            return null;
        }

        return [
            'text' => $d->copy_text,
            'status' => $d->copy_status,
            'note' => $d->copy_note,
            'due_on' => $d->copy_due_on?->toDateString(),
            'approved_at' => $d->copy_approved_at?->toIso8601String(),
        ];
    }

    private function clientDeal(Request $request, int $id): Deal
    {
        $teamIds = $request->user()->teamIds();
        $d = Deal::whereIn('user_id', $teamIds)->find($id);
        abort_unless($d, 404);

        return $d;
    }

    private function copyPayload(Deal $d): array
    {
        return [
            'copy' => self::clientCopy($d),
            'override' => (bool)$d->copy_override,
            'suggested' => SponsoredCopy::draftFor($d),
            'linked_client' => (bool)$d->user_id,
            'fit' => $d->fit_verdict ? ['score' => $d->fit_score, 'verdict' => $d->fit_verdict, 'reasons' => $d->fit_reasons] : null,
            'auto_reply' => $d->auto_reply ? ['status' => $d->auto_reply, 'body' => $d->auto_reply_body] : null,
            'client_status' => ClientDeal::status($d)['label'],
        ];
    }
}
