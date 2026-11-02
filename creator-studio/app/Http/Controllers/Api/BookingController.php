<?php

namespace App\Http\Controllers\Api;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Landing\Attribution;
use App\Domains\Landing\BookingSlots;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    /**
     * What a visitor (or a signed-in client) can ask for.
     */
    public const PROJECT_LABELS = [
        'mvp_build' => 'MVP Build (2-4 Weeks)',
        'retainer' => 'Advisory Retainer ($3,500/mo)',
        'brand_deal' => 'Brand Partnership & Sponsorship',
        'speaking' => 'Conference Keynote or Workshop',
        'consulting' => 'Architecture & AI Technical Consulting',
        'other' => 'General Project Discussion',
    ];

    /**
     * Her rule-of-thumb value per project type, for the pipeline (never shown to the client).
     */
    public const PROJECT_RATES = [
        'mvp_build' => 12000,
        'retainer' => 3500,
        'brand_deal' => 5000,
        'speaking' => 7500,
        'consulting' => 2000,
        'other' => 1500,
    ];

    /**
     * Open intro-call times for the landing page (empty when she hasn't turned them on).
     */
    public function slots(): JsonResponse
    {
        return response()->json(BookingSlots::open());
    }

    /**
     * Book an intro call with Delia (@Tech.Unicorn). With booking hours on, the visitor picks a
     * real open `slot`; otherwise they suggest a preferred date and time.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'company' => 'nullable|string|max:150',
            'project_type' => 'required|string|in:' . implode(',', array_keys(self::PROJECT_LABELS)),
            'slot' => 'nullable|date',
            'preferred_date' => 'required_without:slot|nullable|string|max:50',
            'preferred_time' => 'required_without:slot|nullable|string|max:50',
            'notes' => 'nullable|string|max:2000',
            'service' => 'nullable|string|max:100', // the landing-page service card it came from
        ] + Attribution::rules());
        $service = ($data['service'] ?? null) ?: null;

        $meetingAt = null;
        if (!empty($data['slot'])) {
            $meetingAt = rescue(fn () => CarbonImmutable::parse($data['slot'])->utc(), null, false);
            if (!$meetingAt || !BookingSlots::isOpen($meetingAt)) {
                return response()->json([
                    'message' => 'That time was just taken. Please pick another one.',
                    'errors' => [
                        'slot' => ['That time is no longer open.'],
                    ],
                ], 422);
            }
        }

        $company = ($data['company'] ?? null) ?: ($data['name'] . ' Inquiry');
        $label = self::PROJECT_LABELS[$data['project_type']] ?? 'Intro Call';
        $estRate = self::PROJECT_RATES[$data['project_type']] ?? 2000;
        $bookingRef = 'CAL-' . strtoupper(Str::random(6));
        $from = Attribution::resolve($data['attribution'] ?? null, $request->getHost());

        $tz = Setting::get('timezone') ?: 'UTC';
        $when = $meetingAt
            ? $meetingAt->setTimezone($tz)->format('D j M Y, H:i T') . ' (' . $meetingAt->format('H:i') . ' UTC)'
            : "{$data['preferred_date']} at {$data['preferred_time']}";

        $deal = Deal::create([
            'brand_name' => $company,
            'contact_name' => $data['name'],
            'contact_email' => $data['email'],
            'stage' => 'inquiry',
            'rate' => $estRate,
            'currency' => 'USD',
            'payment_status' => 'pending',
            'deliverables' => [
                array_filter([
                    'platform' => 'meeting',
                    'format' => 'Intro Call: ' . $label,
                    'service' => $service,
                    'project_type' => $data['project_type'],
                ]),
            ],
            'key_talking_points' => ($meetingAt ? 'Booked' : 'Requested') . ": {$when}. Topic: {$label}",
            'notes' => 'Intro call ' . ($meetingAt ? 'booked' : 'requested') . " via website (ref {$bookingRef}). " . ($meetingAt ? 'Send a calendar invite.' : 'Confirm the time by email.') . ' Notes from client: ' . (($data['notes'] ?? null) ?: 'None provided.'),
            'meeting_at' => $meetingAt,
            'source' => $from['source'],
            'attribution' => $from['attribution'],
        ]);

        AgentEvent::log('lead', ($meetingAt ? 'Intro call booked' : 'Intro call request') . ": {$data['name']} ({$data['email']}), " . ($service ?: $label) . ", {$when}.");

        // Nothing is sent from here: she confirms the slot herself, so the visitor is told exactly that.
        return response()->json([
            'success' => true,
            'status' => $meetingAt ? 'booked' : 'requested',
            'name' => $data['name'],
            'project_type' => $data['project_type'],
            'booking_ref' => $bookingRef,
            'deal_id' => $deal->id,
            'appointment' => [
                'date' => $meetingAt ? $meetingAt->setTimezone($tz)->toDateString() : $data['preferred_date'],
                'time' => $meetingAt ? $meetingAt->setTimezone($tz)->format('H:i T') : $data['preferred_time'],
                'start' => $meetingAt?->toIso8601ZuluString(),
                'minutes' => $meetingAt ? BookingSlots::config()['minutes'] : null,
                'service' => $label,
            ],
            'message' => $meetingAt
                ? "You're booked! Delia will send a calendar invite to {$data['email']}, usually within one business day."
                : "Request received! Delia will confirm your time at {$data['email']}, usually within one business day.",
        ]);
    }
}
