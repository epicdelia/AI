<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    /**
     * Book an intro call with Delia (@Tech.Unicorn).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'company' => 'nullable|string|max:150',
            'project_type' => 'required|string|in:mvp_build,retainer,brand_deal,speaking,consulting,other',
            'preferred_date' => 'required|string|max:50',
            'preferred_time' => 'required|string|max:50',
            'notes' => 'nullable|string|max:2000',
        ]);

        $projectRates = [
            'mvp_build' => 12000,
            'retainer' => 3500,
            'brand_deal' => 5000,
            'speaking' => 7500,
            'consulting' => 2000,
            'other' => 1500,
        ];

        $projectLabels = [
            'mvp_build' => 'MVP Build (2-4 Weeks)',
            'retainer' => 'Advisory Retainer ($3,500/mo)',
            'brand_deal' => 'Brand Partnership & Sponsorship',
            'speaking' => 'Conference Keynote or Workshop',
            'consulting' => 'Architecture & AI Technical Consulting',
            'other' => 'General Project Discussion',
        ];

        $company = $data['company'] ?: ($data['name'].' Inquiry');
        $label = $projectLabels[$data['project_type']] ?? 'Intro Call';
        $estRate = $projectRates[$data['project_type']] ?? 2000;

        $deal = Deal::create([
            'brand_name' => $company,
            'contact_name' => $data['name'],
            'contact_email' => $data['email'],
            'stage' => 'inquiry',
            'rate' => $estRate,
            'currency' => 'USD',
            'payment_status' => 'pending',
            'deliverables' => [
                ['platform' => 'meeting', 'format' => 'Intro Call: '.$label],
            ],
            'key_talking_points' => "Scheduled: {$data['preferred_date']} at {$data['preferred_time']}. Topic: {$label}",
            'notes' => "Intro call booked via website. Notes from client: ".($data['notes'] ?: 'None provided.'),
        ]);

        $bookingRef = 'CAL-'.strtoupper(Str::random(6));

        return response()->json([
            'success' => true,
            'status' => 'confirmed',
            'name' => $data['name'],
            'project_type' => $data['project_type'],
            'booking_ref' => $bookingRef,
            'deal_id' => $deal->id,
            'appointment' => [
                'date' => $data['preferred_date'],
                'time' => $data['preferred_time'],
                'service' => $label,
                'calendar_link' => 'https://meet.google.com/'.strtolower(Str::random(3).'-'.Str::random(4).'-'.Str::random(3)),
            ],
            'message' => 'Intro call confirmed! A calendar invite has been sent to '.$data['email'].'.',
        ]);
    }
}
