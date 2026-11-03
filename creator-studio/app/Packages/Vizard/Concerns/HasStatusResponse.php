<?php

namespace App\Packages\Vizard\Concerns;

use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response as FacadesResponse;

trait HasStatusResponse
{
    /**
     * response with status for api response handle
     */
    public function jsonStatusResponse(Response $res): JsonResponse
    {
        if ($res->successful()) {
            $resData = $res->json();
            if ($resData['code'] > 4002 && $resData['code'] < 4007) {
                return FacadesResponse::json([
                    'status'  => 'error',
                    'message' => $resData['errMsg'] ?? __('Something went wrong. Please contact support for assistance.'),
                ]);
            }

            return FacadesResponse::json([
                'status'  => 'success',
                'resData' => $resData,
            ]);
        }

        Log::error('Vizard error response', [
            'status' => $res->status(),
            'body'   => $res->body(),
        ]);

        return FacadesResponse::json([
            'status'  => 'error',
            'message' => __('Something went wrong. Please contact support for assistance.'),
        ], $res->status());
    }
}
