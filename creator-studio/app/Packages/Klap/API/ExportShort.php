<?php

namespace App\Packages\Klap\API;

use App\Packages\Klap\Constracts\TaskOperationInterface;
use Illuminate\Http\JsonResponse;

/**
 * Generate shorts
 *
 * @see https://docs.klap.app/usecases/generate-shorts
 */
class ExportShort implements TaskOperationInterface
{
    public function __construct(protected BaseApiClient $client) {}

    // submit a video for precessing
    public function submitTask(array $params): JsonResponse
    {
        $folderId = $params['folderId'];
        $projectId = $params['projectId'];

        $res = $this->client->request('post', "projects/$folderId/$projectId/exports");

        return $this->client->jsonStatusResponse($res);
    }

    // poll the task status
    public function checkTask(string $taskId, ?array $extraParam = []): JsonResponse
    {
        $folderId = $extraParam['folderId'];
        $projectId = $extraParam['projectId'];

        $res = $this->client->request('get', "projects/$folderId/$projectId/exports/$taskId");

        return $this->client->jsonStatusResponse($res);
    }
}
