<?php

namespace App\Packages\Elevenlabs\Models;

use App\Packages\Elevenlabs\API\BaseApiClient;
use App\Packages\Elevenlabs\Contracts\AiMusicInterface;
use Illuminate\Http\JsonResponse;

class AiMusic implements AiMusicInterface
{
    public function __construct(protected BaseApiClient $client)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 3600);
    }

    public function submit(array $params): JsonResponse
    {
        $res = $this->client->request('post', 'music', $params);

        return $this->client->binaryStatusResponse($res);
    }
}
