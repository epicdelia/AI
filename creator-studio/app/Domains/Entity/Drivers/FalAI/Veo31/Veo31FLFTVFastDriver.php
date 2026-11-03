<?php

namespace App\Domains\Entity\Drivers\FalAI\Veo31;

use App\Domains\Entity\BaseDriver;
use App\Domains\Entity\Concerns\Calculate\HasTextToVideo;
use App\Domains\Entity\Concerns\Input\HasInputVideo;
use App\Domains\Entity\Contracts\Calculate\WithTextToVideoInterface;
use App\Domains\Entity\Contracts\Input\WithInputVideoInterface;
use App\Domains\Entity\Enums\EntityEnum;

class Veo31FLFTVFastDriver extends BaseDriver implements WithInputVideoInterface, WithTextToVideoInterface
{
    use HasInputVideo;
    use HasTextToVideo;

    public function enum(): EntityEnum
    {
        return EntityEnum::VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO_FAST;
    }
}
