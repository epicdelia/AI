<?php

namespace App\Packages\Topview\API;

use App\Packages\Topview\Concerns\HasAuth;
use App\Packages\Topview\Concerns\HasStatusResponse;
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
        string $topview_uid,
        string $topwview_api_key,
        string $apiBaseUrl
    ) {
        $this->apiBaseUrl = $apiBaseUrl;
        $this->authFrom($topview_uid, $topwview_api_key);
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
        return Http::withToken($this->authToken())->withHeaders($this->getHeaders())->{$method}($this->getFullUrl($url), $params);
    }

    /**
     * file upload to s3
     */
    public function fileUploadToS3(string $filePath, string $uploadUrl): Response
    {
        $mimeType = mime_content_type($filePath);
        $headers = ['Content-Type' => $mimeType];

        return Http::withHeaders($this->getHeaders($headers))->send('put', $uploadUrl, ['body' => file_get_contents($filePath)]);
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
