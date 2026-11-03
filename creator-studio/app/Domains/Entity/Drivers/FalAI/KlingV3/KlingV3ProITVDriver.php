<?php

namespace App\Domains\Entity\Drivers\FalAI\KlingV3;

use App\Domains\Entity\BaseDriver;
use App\Domains\Entity\Concerns\Calculate\HasImageToVideo;
use App\Domains\Entity\Concerns\Input\HasInputVideo;
use App\Domains\Entity\Contracts\Calculate\WithImageToVideoInterface;
use App\Domains\Entity\Contracts\Input\WithInputVideoInterface;
use App\Domains\Entity\Enums\EntityEnum;

class KlingV3ProITVDriver extends BaseDriver implements WithImageToVideoInterface, WithInputVideoInterface
{
    use HasImageToVideo;
    use HasInputVideo;

    public function enum(): EntityEnum
    {
        return EntityEnum::KLING_3_PRO_ITV;
    }
}
