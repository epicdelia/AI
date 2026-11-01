<?php

namespace App\Http\Controllers\Api;

use App\Domains\Privacy\DataProcessingAgreement;
use App\Domains\Privacy\PiiRedactor;
use App\Domains\Privacy\PrivacyAccessLogger;
use App\Domains\Privacy\SubProcessorRegistry;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrivacyController extends Controller
{
    /**
     * Public / Authenticated DPA template or deal-specific DPA.
     */
    public function dpa(Request $request): JsonResponse
    {
        $dealId = $request->query('deal_id');
        $deal = $dealId ? Deal::find($dealId) : null;

        if (! $deal) {
            $deal = new Deal([
                'id' => 0,
                'brand_name' => $request->query('company', 'Enterprise Partner'),
                'contact_name' => 'Authorized Legal Representative',
                'contact_email' => 'legal@client.test',
            ]);
        }

        return response()->json([
            'title' => 'Data Processing Agreement (GDPR Article 28)',
            'version' => DataProcessingAgreement::VERSION,
            'markdown' => DataProcessingAgreement::dpaMarkdown($deal, $request->user()),
        ]);
    }

    /**
     * Public Sub-Processors Directory.
     */
    public function subprocessors(Request $request): JsonResponse
    {
        return response()->json([
            'title' => 'Authorized Sub-Processors Directory',
            'subprocessors' => SubProcessorRegistry::all(),
            'markdown' => SubProcessorRegistry::markdown(),
        ]);
    }

    /**
     * Subscribe to 30-day sub-processor change notifications.
     */
    public function subscribeSubprocessor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email|max:190',
            'company' => 'nullable|string|max:150',
        ]);

        $res = SubProcessorRegistry::subscribe($data['email'], $data['company'] ?? null);

        return response()->json($res);
    }

    /**
     * Test / live text PII and credentials sanitizer.
     */
    public function redact(Request $request): JsonResponse
    {
        $data = $request->validate([
            'text' => 'required|string|max:50000',
        ]);

        $res = PiiRedactor::redact($data['text']);

        return response()->json($res);
    }

    /**
     * Immutable Client Data Access Transparency Log (GDPR Article 30 RoPA).
     */
    public function accessLog(Request $request): JsonResponse
    {
        $user = $request->user();
        $logs = PrivacyAccessLogger::historyForUser($user);

        return response()->json([
            'title' => 'Data Access & Processing Transparency Log',
            'logs' => $logs,
        ]);
    }
}

