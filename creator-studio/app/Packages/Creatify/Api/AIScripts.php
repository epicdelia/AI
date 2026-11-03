<?php

namespace App\Packages\Creatify\Api;

use Illuminate\Http\JsonResponse;

class AIScripts
{
    public function __construct(protected BaseApiClient $client) {}

    /**
     * API that generates AI-driven scripts based on either a URL or a combination of title and description.
     *
     * @see https://docs.creatify.ai/api-reference/ai-scripts/post-ai-scripts
     */
    public function generateAIScripts(array $params): JsonResponse
    {
        $res = $this->client->request('post', 'api/ai_scripts/', $params);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * Retrieve a list of videos
     *
     * @see https://docs.creatify.ai/api-reference/ai-scripts/get-ai-scripts
     */
    public function getAIScriptItems(?array $params = []): JsonResponse
    {
        $res = $this->client->request('get', 'api/ai_scripts/', $params);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * Get AI Script item by id
     *
     * @see https://docs.creatify.ai/api-reference/ai-scripts/get-ai-scripts-
     */
    public function getAIScriptItemById(string $id): JsonResponse
    {
        $res = $this->client->request('get', "api/ai_scripts/$id/");

        return $this->client->jsonStatusResponse($res);
    }
}
