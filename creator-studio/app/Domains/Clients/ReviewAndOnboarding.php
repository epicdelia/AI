<?php

namespace App\Domains\Clients;

use App\Models\Deal;
use App\Models\Setting;
use Illuminate\Validation\ValidationException;

/**
 * Two client-side nudges that keep work moving:
 *
 * Review deadlines: work sent for sign-off ("in review") has a due date; the client gets a
 * reminder the day before and on the day (at most two), and she sees overdue reviews.
 *
 * Onboarding: once a request is accepted or paid, the client sees a short checklist (her
 * steps per service). Steps tied to something real tick themselves: files uploaded, brief
 * answered, call booked, brand vault filled. "Custom" steps the client ticks by hand.
 */
class ReviewAndOnboarding
{
    public const MAX_REVIEW_REMINDERS = 2;

    public const STEP_TYPES = ['upload', 'intake', 'call', 'vault', 'custom'];

    public const DEFAULT_STEPS = [
        ['key' => 'assets', 'label' => 'Upload your brand assets (logo, product shots, guidelines)', 'type' => 'upload'],
        ['key' => 'brief', 'label' => 'Answer the brief questions', 'type' => 'intake'],
        ['key' => 'vault', 'label' => 'Fill in your Brand Vault (colours, links, do’s and don’ts)', 'type' => 'vault'],
        ['key' => 'kickoff', 'label' => 'Book the kickoff call', 'type' => 'call'],
        ['key' => 'billing', 'label' => 'Confirm invoice details (company name, VAT number)', 'type' => 'custom'],
    ];

    // ---- Review deadlines ---------------------------------------------------------------

    public static function reviewOverdue(Deal $deal): bool
    {
        return $deal->stage === 'in_review' && $deal->review_due_on !== null && $deal->review_due_on->copy()->endOfDay()->isPast();
    }

    /**
     * Daily: the day before the due date, then on the due date. Returns reminders sent.
     */
    public static function remindReviews(): int
    {
        $sent = 0;
        Deal::where('stage', 'in_review')->whereNotNull('review_due_on')
            ->where('review_reminders', '<', self::MAX_REVIEW_REMINDERS)
            ->whereDate('review_due_on', '<=', now()->addDay()->toDateString())
            ->get()->each(function (Deal $deal) use (&$sent) {
                if ($deal->review_reminded_on?->isToday()) {
                    return;
                }
                $due = $deal->review_due_on;
                $when = $due->isToday() ? 'today' : ($due->isTomorrow() ? 'tomorrow' : 'on ' . $due->toFormattedDateString());
                ClientNotifier::email($deal, "Ready for your review: {$deal->brand_name}",
                    "The work on “" . ClientDeal::summary($deal)['title'] . "” is waiting for your sign-off, due {$when}. Approve it or ask for changes in one click.");
                $deal->forceFill(['review_reminders' => $deal->review_reminders + 1, 'review_reminded_on' => now()->toDateString()])->saveQuietly();
                $sent++;
            });

        return $sent;
    }

    // ---- Onboarding ---------------------------------------------------------------------

    /**
     * @return array<int, array{key: string, label: string, type: string}>
     */
    public static function stepsFor(?string $service): array
    {
        $all = json_decode((string)Setting::get('onboarding_steps', ''), true) ?: [];

        return $all[$service ?? ''] ?? $all['*'] ?? self::DEFAULT_STEPS;
    }

    public static function allSteps(): array
    {
        return json_decode((string)Setting::get('onboarding_steps', ''), true) ?: [];
    }

    public static function saveSteps(string $service, array $steps): array
    {
        if ($service !== '*' && !array_key_exists($service, \App\Http\Controllers\Api\BookingController::PROJECT_LABELS)) {
            throw ValidationException::withMessages(['service' => 'Unknown service.']);
        }
        $clean = [];
        foreach (array_slice($steps, 0, 12) as $i => $s) {
            $label = trim((string)($s['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $clean[] = [
                'key' => preg_replace('/[^a-z0-9_]/', '', strtolower((string)($s['key'] ?? ''))) ?: 's' . ($i + 1),
                'label' => mb_substr($label, 0, 200),
                'type' => in_array($s['type'] ?? 'custom', self::STEP_TYPES, true) ? $s['type'] : 'custom',
            ];
        }
        $all = self::allSteps();
        $all[$service] = $clean;
        Setting::put('onboarding_steps', json_encode($all));

        return $clean;
    }

    public static function active(Deal $deal): bool
    {
        return ($deal->accepted_at || $deal->deposit_paid_at || $deal->payment_status === 'paid'
            || in_array($deal->stage, ['contracted', 'production'], true)) && !in_array($deal->stage, ['lost'], true);
    }

    /**
     * The checklist with each step's state.
     *
     * @return array{active: bool, done: int, total: int, steps: array<int, array>}
     */
    public static function checklist(Deal $deal): array
    {
        $service = $deal->deliverables[0]['project_type'] ?? null;
        $ticked = (array)($deal->onboarding ?? []);
        $steps = [];
        foreach (self::stepsFor($service) as $s) {
            $auto = match ($s['type']) {
                'upload' => $deal->files()->where('from_admin', false)->exists(),
                'intake' => !empty($deal->intake),
                'call' => $deal->meeting_at !== null,
                'vault' => $deal->user_id && \App\Domains\Clients\Models\ClientBrandVault::where('user_id', $deal->user_id)->exists(),
                default => false,
            };
            $steps[] = $s + [
                'done' => $auto || isset($ticked[$s['key']]),
                'auto' => $s['type'] !== 'custom',
            ];
        }

        return [
            'active' => self::active($deal),
            'done' => count(array_filter($steps, fn ($s) => $s['done'])),
            'total' => count($steps),
            'steps' => $steps,
        ];
    }

    public static function tick(Deal $deal, string $key, bool $done): array
    {
        $step = collect(self::stepsFor($deal->deliverables[0]['project_type'] ?? null))->firstWhere('key', $key);
        abort_unless($step && $step['type'] === 'custom', 422, 'That step ticks itself when it’s done.');
        $ticked = (array)($deal->onboarding ?? []);
        if ($done) {
            $ticked[$key] = now()->toIso8601String();
        } else {
            unset($ticked[$key]);
        }
        $deal->forceFill(['onboarding' => $ticked ?: null])->saveQuietly();

        return self::checklist($deal->fresh());
    }
}
