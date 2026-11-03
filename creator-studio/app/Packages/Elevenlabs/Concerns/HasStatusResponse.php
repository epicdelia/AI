<?php

namespace App\Packages\Elevenlabs\Concerns;

use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response as FacadesResponse;
use Illuminate\Support\Facades\Storage;

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

        Log::error('Elevenlabs API error response', [
            'status' => $res->status(),
            'body'   => $res->body(),
        ]);

        return FacadesResponse::json([
            'status'  => 'error',
            'message' => __('Something went wrong. Please contact support for assistance.'),
        ], $res->status());
    }

    public function binaryStatusResponse(Response $res): JsonResponse
    {
        if ($res->successful()) {
            // Save file to storage
            $filename = 'music/' . uniqid('ai_music_') . '.mp3';
            Storage::disk('public')->put($filename, $res->body());

            return response()->json([
                'status'   => 'success',
                'file_url' => $filename,
            ]);
        }

        Log::error('Elevenlabs API error response', [
            'status' => $res->status(),
            'body'   => $res->body(),
        ]);

        return response()->json([
            'status'  => 'error',
            'message' => __('Something went wrong. Please contact support for assistance.'),
        ], $res->status());
    }
}
