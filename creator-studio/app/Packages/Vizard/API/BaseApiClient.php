<?php

namespace App\Packages\Vizard\API;

use App\Packages\Vizard\Concerns\HasAuth;
use App\Packages\Vizard\Concerns\HasStatusResponse;
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
        string $apiKey,
        string $apiBaseUrl
    ) {
        $this->apiBaseUrl = $apiBaseUrl;
        $this->authFrom($apiKey);
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
