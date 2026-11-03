<?php

namespace App\Http\Controllers\Api;

use App\Domains\Clients\ReviewAndOnboarding;
use App\Domains\Deals\Billing;
use App\Domains\Deals\DealRight;
use App\Domains\Deals\Rights;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Her side of getting paid and keeping work moving: payment plan, deposit and review
 * deadline per deal; usage rights per deal; her defaults (late fee, rights pricing, review
 * days, default payment plan); and the onboarding steps per service.
 */
class DealTermsController extends Controller
{
    public function show(Deal $deal): JsonResponse
    {
        return response()->json($this->payload($deal));
    }

    public function update(Request $request, Deal $deal): JsonResponse
    {
        $data = $request->validate([
            'payment_plan' => 'nullable|in:' . implode(',', array_keys(Billing::PLANS)),
            'deposit_percent' => 'nullable|integer|min:0|max:90',
            'invoice_due_on' => 'nullable|date',
            'review_due_on' => 'nullable|date',
        ]);
        abort_if(isset($data['deposit_percent']) && $deal->deposit_paid_at && (int)$data['deposit_percent'] !== (int)$deal->deposit_percent, 422, 'The deposit is paid already.');
        if (array_key_exists('review_due_on', $data)) {
            $data['review_reminders'] = 0;
            $data['review_reminded_on'] = null;
        }
        if (array_key_exists('invoice_due_on', $data)) {
            $data['pay_reminders'] = 0;
            $data['pay_reminded_on'] = null;
        }
        if (array_key_exists('payment_plan', $data) && $deal->invoiced_at && !array_key_exists('invoice_due_on', $data)) {
            $deal->payment_plan = $data['payment_plan'];
            $data['invoice_due_on'] = Billing::dueDate($deal);
        }
        if (array_key_exists('deposit_percent', $data)) {
            $data['deposit_percent'] = (int)($data['deposit_percent'] ?? 0);
        }
        $deal->forceFill($data)->save();

        return response()->json($this->payload($deal->fresh()));
    }

    public function addRight(Request $request, Deal $deal): JsonResponse
    {
        $data = $request->validate([
            'kind' => 'required|in:' . implode(',', array_keys(Rights::KINDS)),
            'scope' => 'nullable|string|max:200',
            'days' => 'required|integer|in:' . implode(',', Rights::DAYS),
            'price' => 'nullable|numeric|min:0|max:10000000',
        ]);
        $price = isset($data['price']) ? (float)$data['price'] : Rights::suggestedPrice($deal, $data['kind'], (int)$data['days']);
        $start = $deal->published_at;
        DealRight::create($data + [
            'deal_id' => $deal->id,
            'price' => $price,
            'starts_on' => $start?->toDateString(),
            'ends_on' => $start?->copy()->addDays((int)$data['days'])->toDateString(),
        ]);

        return response()->json($this->payload($deal), 201);
    }

    public function updateRight(Request $request, Deal $deal, int $right): JsonResponse
    {
        $r = DealRight::where('deal_id', $deal->id)->findOrFail($right);
        $data = $request->validate([
            'scope' => 'nullable|string|max:200',
            'price' => 'nullable|numeric|min:0|max:10000000',
            'ends_on' => 'nullable|date',
            'extend' => 'nullable|boolean',
        ]);
        if ($data['extend'] ?? false) {
            Rights::extend($r);
        }
        unset($data['extend']);
        if (array_key_exists('ends_on', $data)) {
            $data['reminded_at'] = null;
            $data['status'] = 'active';
        }
        $r->update($data);

        return response()->json($this->payload($deal));
    }

    public function deleteRight(Deal $deal, int $right): JsonResponse
    {
        DealRight::where('deal_id', $deal->id)->findOrFail($right)->delete();

        return response()->json($this->payload($deal));
    }

    /**
     * Her defaults. Every money number is off until she sets it.
     */
    public function defaults(): JsonResponse
    {
        return response()->json([
            'late_fee_percent' => Billing::lateFeePercent(),
            'default_payment_plan' => Setting::get('default_payment_plan') ?: null,
            'review_days' => (int)(Setting::get('review_days') ?: 3),
            'rights_pricing' => (json_decode((string)Setting::get('rights_pricing', ''), true) ?: []) + ['usage' => 0, 'whitelisting' => 0, 'exclusivity' => 0],
            'plans' => array_keys(Billing::PLANS),
            'rights_kinds' => Rights::KINDS,
            'rights_days' => Rights::DAYS,
        ]);
    }

    public function saveDefaults(Request $request): JsonResponse
    {
        $data = $request->validate([
            'late_fee_percent' => 'nullable|numeric|min:0|max:10',
            'default_payment_plan' => 'nullable|in:' . implode(',', array_keys(Billing::PLANS)),
            'review_days' => 'nullable|integer|min:1|max:30',
            'rights_pricing' => 'nullable|array',
            'rights_pricing.usage' => 'nullable|numeric|min:0|max:500',
            'rights_pricing.whitelisting' => 'nullable|numeric|min:0|max:500',
            'rights_pricing.exclusivity' => 'nullable|numeric|min:0|max:500',
        ]);
        if (array_key_exists('late_fee_percent', $data)) {
            Setting::put('late_fee_percent', (string)(float)($data['late_fee_percent'] ?? 0));
        }
        if (array_key_exists('default_payment_plan', $data)) {
            Setting::put('default_payment_plan', $data['default_payment_plan'] ?? '');
        }
        if (array_key_exists('review_days', $data)) {
            Setting::put('review_days', (string)($data['review_days'] ?? 3));
        }
        if (array_key_exists('rights_pricing', $data)) {
            Setting::put('rights_pricing', json_encode(array_map('floatval', array_intersect_key((array)$data['rights_pricing'], Rights::KINDS))));
        }

        return $this->defaults();
    }

    public function onboardingSteps(): JsonResponse
    {
        $all = ReviewAndOnboarding::allSteps();

        return response()->json([
            'default' => ReviewAndOnboarding::stepsFor('*'),
            'services' => collect(BookingController::PROJECT_LABELS)->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'steps' => ReviewAndOnboarding::stepsFor($key),
                'custom' => array_key_exists($key, $all),
            ])->values(),
            'types' => ReviewAndOnboarding::STEP_TYPES,
        ]);
    }

    public function saveOnboardingSteps(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service' => 'required|string|max:60',
            'steps' => 'present|array|max:12',
            'steps.*.label' => 'nullable|string|max:200',
            'steps.*.type' => 'nullable|string',
        ]);

        return response()->json([
            'steps' => ReviewAndOnboarding::saveSteps($data['service'], $data['steps']),
        ]);
    }

    private function payload(Deal $deal): array
    {
        return [
            'billing' => Billing::summary($deal),
            'review_due_on' => $deal->review_due_on?->toDateString(),
            'review_overdue' => ReviewAndOnboarding::reviewOverdue($deal),
            'onboarding' => ReviewAndOnboarding::checklist($deal),
            'rights' => DealRight::where('deal_id', $deal->id)->orderBy('id')->get()->map(fn ($r) => Rights::row($r))->values(),
            'suggested_prices' => collect(Rights::KINDS)->keys()->mapWithKeys(fn ($k) => [$k => collect(Rights::DAYS)->mapWithKeys(fn ($d) => [$d => Rights::suggestedPrice($deal, $k, $d)])]),
        ];
    }
}
