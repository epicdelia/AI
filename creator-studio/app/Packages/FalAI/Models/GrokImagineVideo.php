<?php

namespace App\Packages\FalAI\Models;

use App\Domains\Entity\Enums\EntityEnum;
use App\Packages\FalAI\API\BaseApiClient;
use App\Packages\FalAI\Contracts\TextToVideoModelInterface;
use Illuminate\Http\JsonResponse;

/**
 * Grok Imagine Video - Text-to-Video and Image-to-Video generation
 *
 * @see https://fal.ai/models/xai/grok-imagine-video/text-to-video
 * @see https://fal.ai/models/xai/grok-imagine-video/image-to-video
 */
class GrokImagineVideo implements TextToVideoModelInterface
{
    public function __construct(
        protected BaseApiClient $client,
        protected EntityEnum $model
    ) {}

    /**
     * Submit task to generate video
     *
     * @param  array  $params  Parameters: prompt, duration, aspect_ratio, resolution, image_url (ITV only)
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
        $res = $this->client->request('get', "xai/grok-imagine-video/requests/$requestId/status");

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * Get the final result
     */
    public function getResult(string $requestId): JsonResponse
    {
        $res = $this->client->request('get', "xai/grok-imagine-video/requests/$requestId");

        return $this->client->jsonStatusResponse($res);
    }

    protected function getEndpoint(EntityEnum $model): string
    {
        return match ($model) {
            EntityEnum::GROK_IMAGINE_VIDEO_TTV => 'xai/grok-imagine-video/text-to-video',
            default                            => 'xai/grok-imagine-video/image-to-video',
        };
    }
}
