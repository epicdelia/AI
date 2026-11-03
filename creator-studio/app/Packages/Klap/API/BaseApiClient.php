<?php

namespace App\Packages\Klap\API;

use App\Packages\Klap\Concerns\HasAuth;
use App\Packages\Klap\Concerns\HasStatusResponse;
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
        return Http::withToken($this->API_KEY)->withHeaders($this->getHeaders())->{$method}($this->getFullUrl($url), $params);
    }

    /**
     * get headers
     */
    protected function getHeaders(?array $headers = []): array
    {
        $defaultHeaders = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        return array_merge($defaultHeaders, $headers);
    }
}
