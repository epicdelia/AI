<?php

namespace App\Packages\FalAI\Models;

use App\Packages\FalAI\API\BaseApiClient;
use App\Packages\FalAI\Contracts\TextToVideoModelInterface;
use Illuminate\Http\JsonResponse;

/**
 * VEED Video Background Removal model
 *
 * @see https://fal.ai/models/veed/video-background-removal/api
 */
class VideoBackgroundRemoval implements TextToVideoModelInterface
{
    private const ENDPOINT = 'veed/video-background-removal';

    public function __construct(protected BaseApiClient $client) {}

    public function submit(array $params): JsonResponse
    {
        $res = $this->client->request('post', self::ENDPOINT, $params);

        return $this->client->jsonStatusResponse($res);
    }

    public function checkStatus(string $requestId): JsonResponse
    {
        $res = $this->client->request('get', self::ENDPOINT . "/requests/$requestId/status");

        return $this->client->jsonStatusResponse($res);
    }

    public function getResult(string $requestId): JsonResponse
    {
        $res = $this->client->request('get', self::ENDPOINT . "/requests/$requestId");

        return $this->client->jsonStatusResponse($res);
    }
}
