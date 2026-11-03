<?php

namespace App\Packages\FalAI\Models;

use App\Domains\Entity\Enums\EntityEnum;
use App\Packages\FalAI\API\BaseApiClient;
use App\Packages\FalAI\Contracts\TextToVideoModelInterface;
use Illuminate\Http\JsonResponse;

/**
 * Seedance 2.0 - Text-to-Video, Image-to-Video, and Reference-to-Video generation
 *
 * Supports both standard and fast variants.
 *
 * @see https://fal.ai/models/bytedance/seedance-2.0/text-to-video
 * @see https://fal.ai/models/bytedance/seedance-2.0/image-to-video
 * @see https://fal.ai/models/bytedance/seedance-2.0/reference-to-video
 */
class Seedance2 implements TextToVideoModelInterface
{
    public function __construct(
        protected BaseApiClient $client,
        protected EntityEnum $model
    ) {}

    /**
     * Submit task to generate video
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
     * Check status of submitted task
     */
    public function checkStatus(string $requestId): JsonResponse
    {
        $res = $this->client->request('get', "bytedance/seedance-2.0/requests/$requestId/status");

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * Get the final result
     */
    public function getResult(string $requestId): JsonResponse
    {
        $res = $this->client->request('get', "bytedance/seedance-2.0/requests/$requestId");

        return $this->client->jsonStatusResponse($res);
    }
}
