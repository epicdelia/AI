<?php

namespace App\Packages\Creatify\Api;

use Illuminate\Http\JsonResponse;

class Workspace
{
    public function __construct(protected BaseApiClient $client) {}

    /**
     * Get Remaining Credits
     *
     * @see https://docs.creatify.ai/api-reference/workspace/get-remainingcredits
     */
    public function getRemainingCredits(): JsonResponse
    {
        $res = $this->client->request('get', 'api/remaining_credits/');

        return $this->client->jsonStatusResponse($res);
    }
}
