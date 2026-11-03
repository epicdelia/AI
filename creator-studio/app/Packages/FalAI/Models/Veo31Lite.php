<?php

namespace App\Packages\FalAI\Models;

use App\Packages\FalAI\API\BaseApiClient;
use App\Packages\FalAI\Contracts\TextToVideoModelInterface;
use Illuminate\Http\JsonResponse;

/**
 * Veo3.1 Lite model to generate video from text prompt
 *
 * @see https://fal.ai/models/fal-ai/veo3.1/lite/api
 */
class Veo31Lite implements TextToVideoModelInterface
{
    public function __construct(protected BaseApiClient $client) {}

    /**
     * Submit task to generate the video
     *
     * Supported modes with their endpoints:
     * - text-to-video: fal-ai/veo3.1/lite
     * - image-to-video: fal-ai/veo3.1/lite/image-to-video
     * - first-last-frame-to-video: fal-ai/veo3.1/lite/first-last-frame-to-video
     *
     * @param  array  $params  Parameters for video generation (must include 'mode')
     */
    public function submit(array $params): JsonResponse
    {
        $endpoint = $this->buildEndpoint($params['mode']);

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
        $res = $this->client->request('get', "fal-ai/veo3.1/lite/requests/$requestId/status");

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * Get the final result
     *
     * @param  string  $requestId  The request ID from submit response
     */
    public function getResult(string $requestId): JsonResponse
    {
        $res = $this->client->request('get', "fal-ai/veo3.1/lite/requests/$requestId");

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
        if (! str_starts_with($mode, 'fal-ai/')) {
            $mode = "fal-ai/$mode";
        }

        return $mode;
    }
}
