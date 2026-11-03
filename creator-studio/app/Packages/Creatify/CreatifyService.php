<?php

namespace App\Packages\Creatify;

use App\Packages\Creatify\Api\AIScripts;
use App\Packages\Creatify\Api\Avatar;
use App\Packages\Creatify\Api\BaseApiClient;
use App\Packages\Creatify\Api\Links;
use App\Packages\Creatify\Api\LinkToVideos;
use App\Packages\Creatify\Api\Lipsyncs;
use App\Packages\Creatify\Api\Musics;
use App\Packages\Creatify\Api\Voices;
use App\Packages\Creatify\Api\Workspace;
use InvalidArgumentException;

class CreatifyService
{
    protected BaseApiClient $client;

    public function __construct(
        ?string $apiId,
        ?string $apiKey,
        string $baseUrl = 'https://api.creatify.ai/'
    ) {
        if (empty($apiId) || empty($apiKey)) {
            throw new InvalidArgumentException('API ID and API Key must be provided.');
        }

        $this->client = new BaseApiClient($apiId, $apiKey, $baseUrl);
    }

    public function links(): Links
    {
        return new Links($this->client);
    }

    public function avatar(): Avatar
    {
        return new Avatar($this->client);
    }

    public function aiScripts(): AIScripts
    {
        return new AIScripts($this->client);
    }

    public function linkToVideos(): LinkToVideos
    {
        return new LinkToVideos($this->client);
    }

    public function lipsyncs(): Lipsyncs
    {
        return new Lipsyncs($this->client);
    }

    public function musics(): Musics
    {
        return new Musics($this->client);
    }

    public function voices(): Voices
    {
        return new Voices($this->client);
    }

    public function workspace(): Workspace
    {
        return new Workspace($this->client);
    }
}
