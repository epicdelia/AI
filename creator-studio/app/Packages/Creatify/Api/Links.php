<?php

namespace App\Packages\Creatify\Api;

use Illuminate\Http\JsonResponse;

class Links
{
    public function __construct(protected BaseApiClient $client) {}

    /**
     * Retrieve a list of videos, with an optional ids param to filter vdieo by their UUIDs.
     *
     * @see https://docs.creatify.ai/api-reference/links/get-apilinks
     */
    public function getExistingLinks(?string $ids = null): JsonResponse
    {
        $res = $this->client->request('get', 'api/links/', $ids ? ['ids' => $ids] : []);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * API that creates a link from url. Creating a link through this endpoint costs 1 credit.
     *
     * @see https://docs.creatify.ai/api-reference/links/post-apilinks
     */
    public function createLink(string $url): JsonResponse
    {
        $res = $this->client->request('post', 'api/links/', ['url' => $url]);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * API that creates a link from parameters.
     *
     * @see https://docs.creatify.ai/api-reference/links/post-apilink_with_params
     */
    public function createLinkWithParams(array $params): JsonResponse
    {
        $res = $this->client->request('post', 'api/links/link_with_params/', $params);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * API that updates a link.
     *
     * @see https://docs.creatify.ai/api-reference/links/put-apilinks-
     */
    public function updateLink(string $id, array $params): JsonResponse
    {
        $res = $this->client->request('put', "api/links/$id/", $params);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * get link by id
     *
     * @see https://docs.creatify.ai/api-reference/links/get-apilinks-
     */
    public function getLinkById(string $id): JsonResponse
    {
        $res = $this->client->request('get', "api/links/$id/");

        return $this->client->jsonStatusResponse($res);
    }
}
