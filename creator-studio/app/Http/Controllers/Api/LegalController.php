<?php

namespace App\Http\Controllers\Api;

use App\Domains\Legal\LegalDocs;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalController extends Controller
{
    public function privacy(Request $request): JsonResponse
    {
        return response()->json(LegalDocs::privacyPolicy());
    }

    public function terms(Request $request): JsonResponse
    {
        return response()->json(LegalDocs::termsAndConditions());
    }
}

