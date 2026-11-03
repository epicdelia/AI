<?php

namespace App\Packages\FalAI\Models;

use App\Packages\FalAI\API\BaseApiClient;
use App\Packages\FalAI\Contracts\TextToVideoModelInterface;
use Illuminate\Http\JsonResponse;

/**
 * Veo3 model to generate video from text prompt
 *
 * @see https://fal.ai/models/fal-ai/veo3/api
 */
class Veo3 implements TextToVideoModelInterface
{
    public function __construct(protected BaseApiClient $client) {}

    /**
     * submit task to generate the video
     *
     * @see App\Packages\FalAI\Enums\AspectRatioEnum, App\Packages\FalAI\Enums\Veo3\DurationEnum
     */
    public function submit(array $params): JsonResponse
    {
        $res = $this->client->request('post', 'fal-ai/veo3', $params);

        return $this->client->jsonStatusResponse($res);
    }

    // check status of submitted task
    public function checkStatus(string $requestId): JsonResponse
    {
        $res = $this->client->request('get', "fal-ai/veo3/requests/$requestId/status");

        return $this->client->jsonStatusResponse($res);
    }

    // get the final result
    public function getResult(string $requestId): JsonResponse
    {
        $res = $this->client->request('get', "fal-ai/veo3/requests/$requestId");

        return $this->client->jsonStatusResponse($res);
    }
}
