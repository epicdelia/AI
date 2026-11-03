<?php

namespace App\Packages\Topview\API;

use Illuminate\Http\JsonResponse;

/**
 * @todo api is not shared fully yet for product avatar.
 */
class GeneralQuery
{
    public function __construct(protected BaseApiClient $client) {}

    /**
     * caption list
     *
     * @see https://apifox.com/apidoc/shared/115a0d85-28a5-479d-8b8b-83d7898a7246/api-289343428
     */
    public function captionList(): JsonResponse
    {
        $res = $this->client->request('get', 'v1/caption/list');

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * voice query
     *
     * @see https://apifox.com/apidoc/shared/115a0d85-28a5-479d-8b8b-83d7898a7246/api-273646924
     */
    public function voiceQuery(?array $params = []): JsonResponse
    {
        $res = $this->client->request('get', 'v1/voice/query', $params);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * ai avatar query
     *
     * @see https://apifox.com/apidoc/shared/115a0d85-28a5-479d-8b8b-83d7898a7246/api-273646925
     */
    public function aiAvatarQuery(?array $params = [])
    {
        $res = $this->client->request('get', 'v1/aiavatar/query', $params);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * check notice url
     *
     * @see https://apifox.com/apidoc/shared/115a0d85-28a5-479d-8b8b-83d7898a7246/api-298323513;
     */
    public function checkNoticeUrl(string $noticeUrl): JsonResponse
    {
        $res = $this->client->request('post', 'notice/notice_url/check', compact('noticeUrl'));

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * ethnicity query
     *
     * @see https://apifox.com/apidoc/shared/115a0d85-28a5-479d-8b8b-83d7898a7246/api-296774803
     */
    public function ethnicityQuery(array $params): JsonResponse
    {
        $res = $this->client->request('get', 'v1/ethnicity/list', $params);

        return $this->client->jsonStatusResponse($res);
    }
}
