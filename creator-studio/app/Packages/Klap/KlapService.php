<?php

namespace App\Packages\Klap;

use App\Packages\Klap\API\BaseApiClient;
use App\Packages\Klap\API\ExportShort;
use App\Packages\Klap\API\GenerateShorts;

class KlapService
{
    protected BaseApiClient $client;

    public function __construct(
        string $apiKey,
        string $apiBaseUrl = 'https://api.klap.app/v2'
    ) {
        $this->client = new BaseApiClient($apiKey, $apiBaseUrl);
    }

    // export short proxy
    public function exportShort(): ExportShort
    {
        return new ExportShort($this->client);
    }

    // generate short proxy
    public function generateShorts(): GenerateShorts
    {
        return new GenerateShorts($this->client);
    }
}
