<?php

namespace App\Packages\Topview\API;

use Illuminate\Http\JsonResponse;

/**
 * @todo api is not shared fully yet for product avatar.
 */
class ProductAvatar
{
    public function __construct(protected BaseApiClient $client) {}

    /**
     * product avatar category query
     *
     * @see https://apifox.com/apidoc/shared/115a0d85-28a5-479d-8b8b-83d7898a7246/api-297455986
     */
    public function categoryQuery(): JsonResponse
    {
        $res = $this->client->request('get', 'v1/product_avatar/category/list');

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * public product avatar query
     *
     * @see https://apifox.com/apidoc/shared/115a0d85-28a5-479d-8b8b-83d7898a7246/api-297455987
     */
    public function publicProductAvatarQuery(?array $params = []): JsonResponse
    {
        $res = $this->client->request('get', 'v1/product_avatar/public_avatar/query', $params);

        return $this->client->jsonStatusResponse($res);
    }
}
