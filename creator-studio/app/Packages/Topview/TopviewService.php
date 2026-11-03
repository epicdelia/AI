<?php

namespace App\Packages\Topview;

use App\Packages\Topview\API\AvatarMarketingVideo;
use App\Packages\Topview\API\BaseApiClient;
use App\Packages\Topview\API\GeneralQuery;
use App\Packages\Topview\API\ProductAvatar;
use App\Packages\Topview\API\Scraper;
use App\Packages\Topview\API\Upload;
use App\Packages\Topview\API\VideoAvatar;

class TopviewService
{
    protected BaseApiClient $client;

    public function __construct(
        string $topview_uid,
        string $topview_api_key,
        ?string $baseUrl = 'https://api.topview.ai'
    ) {
        $this->client = new BaseApiClient($topview_uid, $topview_api_key, $baseUrl);
    }

    /**
     * avatar marketing video
     */
    public function avatarMarketingVideo(): AvatarMarketingVideo
    {
        return new AvatarMarketingVideo($this->client);
    }

    /**
     * general querys
     */
    public function generalQuery(): GeneralQuery
    {
        return new GeneralQuery($this->client);
    }

    /**
     * product avatar
     */
    public function productAvatar(): ProductAvatar
    {
        return new ProductAvatar($this->client);
    }

    /**
     * scraper
     */
    public function scraper(): Scraper
    {
        return new Scraper($this->client);
    }

    /**
     * upload
     */
    public function upload(): Upload
    {
        return new Upload($this->client);
    }

    /**
     * video avatar
     */
    public function videoAvatar(): VideoAvatar
    {
        return new VideoAvatar($this->client);
    }
}
