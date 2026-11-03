<?php

namespace App\Packages\Creatify\Api;

use Illuminate\Http\JsonResponse;

class Avatar
{
    public function __construct(protected BaseApiClient $client) {}

    /**
     * get all avaiblae avatars
     *
     * @see https://docs.creatify.ai/api-reference/personas/get-apipersonas
     */
    public function getAllAvailableAvatars(?array $params = []): JsonResponse
    {
        $res = $this->client->request('get', 'api/personas/', $params);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * Get all avaialble avatars with pagination
     *
     *
     * @see https://docs.creatify.ai/api-reference/personas/get-apipersonas-paginated
     *
     * @param  array  $params  //prvoide page and page_size for parameter
     */
    public function getAllAvailableAvatarsWithPagination(array $params): JsonResponse
    {
        $res = $this->client->request('get', 'api/personas/paginated/', $params);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * get all custom avatars
     *
     * @see https://docs.creatify.ai/api-reference/personas/get-apipersonas-v2
     */
    public function getAllCustomAvatars(?array $params = []): JsonResponse
    {
        $res = $this->client->request('get', 'api/personas_v2/', $params);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * get avatar by id
     *
     * @see https://docs.creatify.ai/api-reference/personas/get-apipersonas-
     */
    public function getAvatarById(string $id): JsonResponse
    {
        $res = $this->client->request('get', "api/personas/$id/");

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * create custom avatar
     *
     * @see https://docs.creatify.ai/api-reference/personas/post-apipersonas
     */
    public function createCustomAvatar(array $params): JsonResponse
    {
        $res = $this->client->request('post', 'api/personas_v2/', $params);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * Get the number of Custom Avatars you can create
     *
     * @see https://docs.creatify.ai/api-reference/personas/get-apipersonas-count
     */
    public function getCustomAvatarCreationAvailabilityByNumber(): JsonResponse
    {
        $res = $this->client->request('get', 'api/personas_v2/count/');

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * delete custom avatar
     *
     * @see https://docs.creatify.ai/api-reference/personas/delete-apipersonas-
     */
    public function deleteCustomAvatar(string $id): JsonResponse
    {
        $res = $this->client->request('delete', "api/personas/$id/");

        return $this->client->jsonStatusResponse($res);
    }
}
