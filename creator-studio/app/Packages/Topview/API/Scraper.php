<?php

namespace App\Packages\Topview\API;

use Illuminate\Http\JsonResponse;

class Scraper
{
    public function __construct(protected BaseApiClient $client) {}

    /**
     * submit scraper task
     *
     * @see https://apifox.com/apidoc/shared/115a0d85-28a5-479d-8b8b-83d7898a7246/api-289343426
     */
    public function submitScraperTask(string $productLink): JsonResponse
    {
        $res = $this->client->request('post', 'v1/scraper/task/submit', compact('productLink'));

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * query scraper task
     *
     * @see https://apifox.com/apidoc/shared/115a0d85-28a5-479d-8b8b-83d7898a7246/api-289343427
     */
    public function queryScraperTask(string $taskId): JsonResponse
    {
        $res = $this->client->request('get', 'v1/scraper/task/query', compact('taskId'));

        return $this->client->jsonStatusResponse($res);
    }
}
