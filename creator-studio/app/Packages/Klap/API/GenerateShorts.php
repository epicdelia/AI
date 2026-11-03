<?php

namespace App\Packages\Klap\API;

use App\Packages\Klap\Constracts\TaskOperationInterface;
use Illuminate\Http\JsonResponse;

/**
 * Generate shorts
 *
 * @see https://docs.klap.app/usecases/generate-shorts
 */
class GenerateShorts implements TaskOperationInterface
{
    public function __construct(protected BaseApiClient $client) {}

    // submit a video for precessing
    public function submitTask(array $params): JsonResponse
    {
        $res = $this->client->request('post', 'tasks/video-to-shorts', $params);

        return $this->client->jsonStatusResponse($res);
    }

    // poll the task status
    public function checkTask(string $taskId, ?array $extraParam = []): JsonResponse
    {
        $res = $this->client->request('get', "tasks/$taskId");

        return $this->client->jsonStatusResponse($res);
    }

    // generated shorts list
    public function getListGeneratedShorts(string $folderId): JsonResponse
    {
        $res = $this->client->request('get', "projects/$folderId");

        return $this->client->jsonStatusResponse($res);
    }

    // get url to preview a short
    public function getPreviewLink(string $projectId): string
    {
        return "https://klap.app/player/$projectId";
    }
}
