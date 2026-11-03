<?php

namespace App\Packages\FalAI\Concerns;

use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response as FacadesResponse;

trait HasStatusResponse
{
    /**
     * response with status for api response handle
     */
    public function jsonStatusResponse(Response $res): JsonResponse
    {
        if ($res->successful()) {
            return FacadesResponse::json([
                'status'  => 'success',
                'resData' => $res->json(),
            ]);
        }

        return FacadesResponse::json([
            'status'  => 'error',
            'message' => __('Something went wrong. Please contact support for assistance.'),
            'resData' => $res->json(),
        ], $res->status());
    }
}
