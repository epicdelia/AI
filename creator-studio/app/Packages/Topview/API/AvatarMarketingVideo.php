<?php

namespace App\Packages\Topview\API;

use Illuminate\Http\JsonResponse;

class AvatarMarketingVideo
{
    public function __construct(protected BaseApiClient $client) {}

    /**
     * submit task
     *
     *
     * @see https://apifox.com/apidoc/shared/115a0d85-28a5-479d-8b8b-83d7898a7246/api-273646907
     *
     * @param  array  $params  // use enums: AspectRatio, Language, VideoLength
     */
    public function submitTask(array $params): JsonResponse
    {
        $res = $this->client->request('post', 'v1/m2v/task/submit', $params);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * query task
     *
     * @see https://apifox.com/apidoc/shared/115a0d85-28a5-479d-8b8b-83d7898a7246/api-273646908
     */
    public function queryTask(string $taskId): JsonResponse
    {
        $res = $this->client->request('get', 'v1/m2v/task/query', ['taskId' => $taskId]);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * export
     *
     * @see https://apifox.com/apidoc/shared/115a0d85-28a5-479d-8b8b-83d7898a7246/api-273646909
     */
    public function export(string $taskId, ?int $scriptId = 0): JsonResponse
    {
        $res = $this->client->request('post', 'v1/m2v/export', compact('taskId', 'scriptId'));

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * list script content
     *
     * @see https://apifox.com/apidoc/shared/115a0d85-28a5-479d-8b8b-83d7898a7246/api-273646905
     */
    public function listScriptContent(string $taskId): JsonResponse
    {
        $res = $this->client->request('get', 'v1/m2v/script/list', ['taskId' => $taskId]);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * update script content
     *
     * @see https://apifox.com/apidoc/shared/115a0d85-28a5-479d-8b8b-83d7898a7246/api-273646906
     */
    public function updateScriptContent(array $params): JsonResponse
    {
        $res = $this->client->request('post', 'v1/m2v/script/update', $params);

        return $this->client->jsonStatusResponse($res);
    }
}
