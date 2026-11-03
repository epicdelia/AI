<?php

namespace App\Packages\Creatify\Api;

use Illuminate\Http\JsonResponse;

class Lipsyncs
{
    public function __construct(protected BaseApiClient $client) {}

    /**
     * API that converts text or audio files to vivid videos of a single people speaking.
     *
     * @see http://docs.creatify.ai/api-reference/lipsyncs/post-apilipsyncs#body-background-asset-image-url
     */
    public function createLipsyncTask(array $params): JsonResponse
    {
        $res = $this->client->request('post', 'api/lipsyncs/', $params);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * Get lipsync items
     *
     * @see https://docs.creatify.ai/api-reference/lipsyncs/get-apilipsyncs
     */
    public function getLipsyncItems(?string $ids = null): JsonResponse
    {
        $res = $this->client->request('get', 'api/lipsyncs/', $ids ? ['ids' => $ids] : []);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * Get lipsync item by id
     *
     * @see https://docs.creatify.ai/api-reference/lipsyncs/get-apilipsyncs-
     */
    public function getLipsyncById(string $id): JsonResponse
    {
        $res = $this->client->request('get', "api/lipsyncs/$id/");

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * Generate Preview of Lipsync
     *
     * @see https://docs.creatify.ai/api-reference/lipsyncs/post-apilipsyncs-preview
     */
    public function generatePreviewOfLipsync(array $params): JsonResponse
    {
        $res = $this->client->request('post', 'api/lipsyncs/preview/', $params);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * Redner lipsync task
     *
     * @see https://docs.creatify.ai/api-reference/lipsyncs/post-apilipsyncs-render
     */
    public function renderLipsyncTask(string $id): JsonResponse
    {
        $res = $this->client->request('post', "api/lipsyncs/$id/");

        return $this->client->jsonStatusResponse($res);
    }
}
