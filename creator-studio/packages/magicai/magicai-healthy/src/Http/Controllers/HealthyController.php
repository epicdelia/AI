<?php

namespace MagicAI\Healthy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MagicAI\Healthy\Services\ExtensionReportService;

class HealthyController extends Controller
{
    public function __construct(
        public ExtensionReportService $service
    ) {}

    public function checkSelected(Request $request): JsonResponse
    {
        if ($request->get('extensions')) {
            $extensions = explode(',', $request->get('extensions'));
        } else {
            return response()->json(['error' => 'No extensions provided'], 400);
        }

        return response()->json($this->service->generateLicenseReportSelected($extensions));
    }

    public function checkAll(): JsonResponse
    {
        return response()->json($this->service->generateLicenseReport());
    }
}
