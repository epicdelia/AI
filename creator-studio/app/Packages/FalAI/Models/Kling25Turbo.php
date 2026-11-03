<?php

namespace App\Packages\FalAI\Models;

use App\Domains\Entity\Enums\EntityEnum;
use App\Packages\FalAI\API\BaseApiClient;
use App\Packages\FalAI\Contracts\TextToVideoModelInterface;
use Illuminate\Http\JsonResponse;

/**
 * Kling video v2.5 Turbo model - supports multiple generation modes
 *
 * @see https://fal.ai/models/fal-ai/kling-video/v2.5-turbo/pro/text-to-video/api
 * @see https://fal.ai/models/fal-ai/kling-video/v2.5-turbo/standard/image-to-video/api
 * @see https://fal.ai/models/fal-ai/kling-video/v2.5-turbo/pro/image-to-video/api
 */
class Kling25Turbo implements TextToVideoModelInterface
{
    public function __construct(
        protected BaseApiClient $client,
        protected EntityEnum $model
    ) {}

    /**
     * Submit task to generate the video
     *
     * @param  array  $params  Parameters for video generation
     */
    public function submit(array $params): JsonResponse
    {
        $endpoint = $this->getEndpoint($this->model);
        $res = $this->client->request('post', $endpoint, $params);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * Check status of submitted task
     */
    public function checkStatus(string $requestId): JsonResponse
    {
        $res = $this->client->request('get', "fal-ai/kling-video/requests/$requestId/status");

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * Get the final result
     */
    public function getResult(string $requestId): JsonResponse
    {
        $res = $this->client->request('get', "fal-ai/kling-video/requests/$requestId");

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * Get the appropriate API endpoint based on EntityEnum
     */
    protected function getEndpoint(EntityEnum $model): string
    {
        return match ($model) {
            EntityEnum::KLING_2_5_TURBO_PRO_TTV      => 'fal-ai/kling-video/v2.5-turbo/pro/text-to-video',
            EntityEnum::KLING_2_5_TURBO_STANDARD_ITV => 'fal-ai/kling-video/v2.5-turbo/standard/image-to-video',
            EntityEnum::KLING_2_5_TURBO_PRO_ITV      => 'fal-ai/kling-video/v2.5-turbo/pro/image-to-video',
            default                                  => 'fal-ai/kling-video/v2.5-turbo/standard/text-to-video',
        };
    }
}
