<?php

declare(strict_types=1);

namespace App\Domains\Engine\Concerns;

use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

trait HasStatusJsonResponse
{
    /**
     * response with status
     */
    protected function statusJsonResponse(
        Response $res,
        string $errorMessage
    ): JsonResponse {
        if ($res->successful()) {
            $resData = $res->json();

            return response()->json([
                'status'  => 'success',
                'resData' => $resData,
            ]);
        }

        Log::error($errorMessage, [$res->body()]);

        return response()->json([
            'status'  => 'error',
            'message' => 'Something went wrong',
        ]);
    }
}
