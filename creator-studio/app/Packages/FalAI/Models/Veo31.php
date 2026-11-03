<?php

namespace App\Packages\FalAI\Models;

use App\Packages\FalAI\API\BaseApiClient;
use App\Packages\FalAI\Contracts\TextToVideoModelInterface;
use Illuminate\Http\JsonResponse;

/**
 * Veo3.1 model to generate video from text prompt
 *
 * @see https://fal.ai/models/fal-ai/veo3.1/api
 */
class Veo31 implements TextToVideoModelInterface
{
    public function __construct(protected BaseApiClient $client) {}

    /**
     * Submit task to generate the video
     *
     * Supported modes with their endpoints:
     * - text-to-video: fal-ai/veo3.1 (parent app path, no sub-endpoint)
     * - text-to-video-fast: fal-ai/veo3.1/fast (parent path, no sub-endpoint)
     * - first-last-frame-to-video: fal-ai/veo3.1/first-last-frame-to-video
     * - first-last-frame-to-video-fast: fal-ai/veo3.1/fast/first-last-frame-to-video
     * - image-to-video: fal-ai/veo3.1/image-to-video
     * - image-to-video-fast: fal-ai/veo3.1/fast/image-to-video
     * - reference-to-video: fal-ai/veo3.1/reference-to-video
     *
     * @param  array  $params  Parameters for video generation (must include 'mode')
     */
    public function submit(array $params): JsonResponse
    {
        // Build the correct endpoint based on mode
        $endpoint = $this->buildEndpoint($params['mode']);

        // Remove mode from params as it's now in the endpoint
        unset($params['mode']);

        $res = $this->client->request('post', $endpoint, $params);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * Check status of submitted task
     *
     * @param  string  $requestId  The request ID from submit response
     */
    public function checkStatus(string $requestId): JsonResponse
    {
        $res = $this->client->request('get', "fal-ai/veo3.1/requests/$requestId/status");

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * Get the final result
     *
     * @param  string  $requestId  The request ID from submit response
     */
    public function getResult(string $requestId): JsonResponse
    {
        $res = $this->client->request('get', "fal-ai/veo3.1/requests/$requestId");

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * Build the correct endpoint URL based on the mode
     *
     * @param  string  $mode  The generation mode
     *
     * @return string The complete endpoint path
     */
    protected function buildEndpoint(string $mode): string
    {
        // text-to-video variants live at the parent app path on fal, not under /text-to-video.
        return match ($mode) {
            'veo3.1/text-to-video'      => 'fal-ai/veo3.1',
            'veo3.1/fast/text-to-video' => 'fal-ai/veo3.1/fast',
            default                     => str_starts_with($mode, 'fal-ai/') ? $mode : "fal-ai/$mode",
        };
    }
}
