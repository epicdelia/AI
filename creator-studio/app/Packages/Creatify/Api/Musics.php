<?php

namespace App\Packages\Creatify\Api;

use Illuminate\Http\JsonResponse;

class Musics
{
    public function __construct(protected BaseApiClient $client) {}

    /**
     * Get music categories
     *
     * @see https://docs.creatify.ai/api-reference/musics/get-api-music-categories
     */
    public function getMusicCategories(): JsonResponse
    {
        $res = $this->client->request('get', 'api/music_categories/');

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * Get musics
     *
     * @see https://docs.creatify.ai/api-reference/musics/get-api-musics
     */
    public function getMusics(?array $params = []): JsonResponse
    {
        $res = $this->client->request('get', 'api/musics/', $params);

        return $this->client->jsonStatusResponse($res);
    }
}
