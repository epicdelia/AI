<?php

namespace App\Packages\FalAI\Models;

use App\Domains\Entity\Enums\EntityEnum;
use App\Packages\FalAI\API\BaseApiClient;
use App\Packages\FalAI\Contracts\TextToVideoModelInterface;
use Illuminate\Http\JsonResponse;

/**
 * Kling Video v3 model supporting pro/standard and text/image modes.
 *
 * @see https://fal.ai/models/fal-ai/kling-video/v3/pro/text-to-video/api
 * @see https://fal.ai/models/fal-ai/kling-video/v3/pro/image-to-video/api
 * @see https://fal.ai/models/fal-ai/kling-video/v3/standard/text-to-video/api
 * @see https://fal.ai/models/fal-ai/kling-video/v3/standard/image-to-video/api
 */
class KlingV3 implements TextToVideoModelInterface
{
    public function __construct(
        protected BaseApiClient $client,
        protected EntityEnum $model
    ) {}

    public function submit(array $params): JsonResponse
    {
        $endpoint = $this->getEndpoint($this->model);
        $res = $this->client->request('post', $endpoint, $params);

        return $this->client->jsonStatusResponse($res);
    }

    public function checkStatus(string $requestId): JsonResponse
    {
        $res = $this->client->request('get', "fal-ai/kling-video/requests/$requestId/status");

        return $this->client->jsonStatusResponse($res);
    }

    public function getResult(string $requestId): JsonResponse
    {
        $res = $this->client->request('get', "fal-ai/kling-video/requests/$requestId");

        return $this->client->jsonStatusResponse($res);
    }

    protected function getEndpoint(EntityEnum $model): string
    {
        return match ($model) {
            EntityEnum::KLING_3_PRO_TTV      => 'fal-ai/kling-video/v3/pro/text-to-video',
            EntityEnum::KLING_3_PRO_ITV      => 'fal-ai/kling-video/v3/pro/image-to-video',
            EntityEnum::KLING_3_STANDARD_TTV => 'fal-ai/kling-video/v3/standard/text-to-video',
            EntityEnum::KLING_3_STANDARD_ITV => 'fal-ai/kling-video/v3/standard/image-to-video',
            default                          => 'fal-ai/kling-video/v3/pro/text-to-video',
        };
    }
}
