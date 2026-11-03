<?php

namespace App\Packages\Topview\API;

use Illuminate\Http\JsonResponse;

class VideoAvatar
{
    public function __construct(protected BaseApiClient $client) {}

    /**
     * submit task
     *
     * @see https://apifox.com/apidoc/shared/115a0d85-28a5-479d-8b8b-83d7898a7246/api-293375166
     */
    public function submitTask(array $params): JsonResponse
    {
        $res = $this->client->request('post', 'v1/video_avatar/task/submit', $params);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * query task
     *
     * @see https://apifox.com/apidoc/shared/115a0d85-28a5-479d-8b8b-83d7898a7246/api-293377750
     */
    public function queryTask(string $taskId, bool $needCloudFrontUrl = true): JsonResponse
    {
        $res = $this->client->request('get', 'v1/video_avatar/task/query', compact('taskId', 'needCloudFrontUrl'));

        return $this->client->jsonStatusResponse($res);
    }
}
