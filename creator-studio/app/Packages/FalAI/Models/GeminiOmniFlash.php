<?php

namespace App\Packages\FalAI\Models;

use App\Domains\Entity\Enums\EntityEnum;
use App\Packages\FalAI\API\BaseApiClient;
use App\Packages\FalAI\Contracts\TextToVideoModelInterface;
use Illuminate\Http\JsonResponse;

/**
 * Google Gemini Omni Flash - Text-to-Video, Image-to-Video, and Reference-to-Video generation.
 *
 * Creates video with synchronized audio from text input, grounded in Gemini's world knowledge.
 *
 * @see https://fal.ai/models/google/gemini-omni-flash
 * @see https://fal.ai/models/google/gemini-omni-flash/image-to-video
 * @see https://fal.ai/models/google/gemini-omni-flash/reference-to-video
 */
class GeminiOmniFlash implements TextToVideoModelInterface
{
    public function __construct(
        protected BaseApiClient $client,
        protected EntityEnum $model
    ) {}

    /**
     * Submit task to generate video.
     *
     * @param  array  $params  Parameters vary by model type (TTV, ITV, RTV)
     */
    public function submit(array $params): JsonResponse
    {
        $endpoint = $this->model->value;
        $res = $this->client->request('post', $endpoint, $params);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * Check status of submitted task.
     */
    public function checkStatus(string $requestId): JsonResponse
    {
        $res = $this->client->request('get', "google/gemini-omni-flash/requests/$requestId/status");

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * Get the final result.
     */
    public function getResult(string $requestId): JsonResponse
    {
        $res = $this->client->request('get', "google/gemini-omni-flash/requests/$requestId");

        return $this->client->jsonStatusResponse($res);
    }
}
