<?php

namespace App\Packages\Creatify\Api;

use App\Packages\Creatify\Concerns\HasAuth;
use App\Packages\Creatify\Concerns\HasStatusResponse;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class BaseApiClient
{
    use HasAuth;
    use HasStatusResponse;

    /**
     * api base url
     */
    private string $apiBaseUrl;

    public function __construct(
        string $apiId,
        string $apiKey,
        string $apiBaseUrl = 'https://api.creatify.ai'
    ) {
        $this->apiBaseUrl = $apiBaseUrl;
        $this->authFrom($apiId, $apiKey);
    }

    /**
     * get full api endpoint
     */
    public function getFullUrl(string $url): string
    {
        return rtrim($this->apiBaseUrl, '/') . '/' . ltrim($url, '/');
    }

    /**
     * general request
     */
    public function request(string $method, string $url, ?array $params = []): Response
    {
        return Http::withHeaders($this->getHeaders())->{$method}($this->getFullUrl($url), $params);
    }

    /**
     * get headers
     */
    protected function getHeaders(?array $headers = []): array
    {
        $authHeaders = $this->authHeader();
        $defaultHeaders = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        return array_merge($authHeaders, $defaultHeaders, $headers);
    }
}
