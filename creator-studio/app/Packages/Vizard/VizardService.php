<?php

namespace App\Packages\Vizard;

use App\Packages\Vizard\API\BaseApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @see https://docs.vizard.ai/docs/quickstart
 */
class VizardService
{
    public BaseApiClient $client;

    public function __construct(string $apiKey, ?string $baseUrl = null)
    {
        $this->client = new BaseApiClient($apiKey, $baseUrl ?? 'https://elb-api.vizard.ai/hvizard-server-front/open-api/v1');
    }

    // submit a long video for clipping
    public function submitVideoForClipping(array $params): JsonResponse
    {
        $res = $this->client->request('post', 'project/create', $params);

        return $this->client->jsonStatusResponse($res);
    }

    // retrieve clip
    public function retrieveClips(string $projectId)
    {
        $res = $this->client->request('get', "project/query/$projectId");

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * generate caption
     *
     * @see https://docs.vizard.ai/docs/generate-ai-social-caption
     */
    public function generateCaption(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'finalVideoId' => 'required|string',
        ]);

        $res = $this->client->request('post', '', $validated);

        return $this->client->jsonStatusResponse($res);
    }
}
