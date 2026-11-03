<?php

namespace App\Packages\Elevenlabs;

use App\Packages\Elevenlabs\API\BaseApiClient;
use App\Packages\Elevenlabs\Contracts\AiMusicInterface;
use App\Packages\Elevenlabs\Models\AiMusic;

class ElevenlabsService
{
    protected BaseApiClient $client;

    public function __construct(
        string $elevenlabsKey,
        string $apiBaseUrl = 'https://api.elevenlabs.io/v1'
    ) {
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 3600);

        $this->client = new BaseApiClient($elevenlabsKey, $apiBaseUrl);
    }

    /** text to video model */
    public function AiMusicModel(): AiMusicInterface
    {
        return new AiMusic($this->client);
    }
}
