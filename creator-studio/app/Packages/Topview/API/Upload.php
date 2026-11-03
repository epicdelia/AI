<?php

namespace App\Packages\Topview\API;

use Illuminate\Http\JsonResponse;

class Upload
{
    public function __construct(protected BaseApiClient $client) {}

    /**
     * get upload credential
     *
     *
     * @see https://apifox.com/apidoc/shared/115a0d85-28a5-479d-8b8b-83d7898a7246/api-273646910
     *
     * @param  string  $format  //reference: App\Packages\Topview\Enums\UploadFileFormat
     */
    public function getUploadCredential(string $format): JsonResponse
    {
        $res = $this->client->request('get', 'v1/upload/credential', ['format' => $format]);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * get upload check
     *
     * @see https://apifox.com/apidoc/shared/115a0d85-28a5-479d-8b8b-83d7898a7246/api-305508119
     */
    public function getUploadCheck(string $fileId): JsonResponse
    {
        $res = $this->client->request('get', 'v1/upload/check', ['fileId' => $fileId]);

        return $this->client->jsonStatusResponse($res);
    }

    /**
     * file upload to s3
     *
     *
     * @see https://apifox.com/apidoc/shared/115a0d85-28a5-479d-8b8b-83d7898a7246/doc-6310461
     *
     * @param  string  $filePath  //Local file path to upload
     * @param  string  $uploadUrl  // you can get this url from getUploadCredential
     */
    public function uploadToS3(string $filePath, string $uploadUrl): JsonResponse
    {
        $res = $this->client->fileUploadToS3($filePath, $uploadUrl);

        return $this->client->jsonStatusResponse($res);
    }
}
