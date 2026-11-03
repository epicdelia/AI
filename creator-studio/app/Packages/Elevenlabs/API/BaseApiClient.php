<?php

declare(strict_types=1);

namespace App\Packages\Elevenlabs\API;

use App\Packages\Elevenlabs\Concerns\HasAuth;
use App\Packages\Elevenlabs\Concerns\HasStatusResponse;
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
        string $elevenlabsKey,
        string $apiBaseUrl
    ) {
        $this->apiBaseUrl = $apiBaseUrl;
        $this->authFrom($elevenlabsKey);
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
        return Http::withHeaders($this->getHeaders())->timeout(3000)->{$method}($this->getFullUrl($url), $params);
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
