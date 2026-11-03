<?php

namespace App\Domains\Entity\Drivers\FalAI;

use App\Domains\Entity\BaseDriver;
use App\Domains\Entity\Concerns\Calculate\HasImageToVideo;
use App\Domains\Entity\Concerns\Input\HasInputVideo;
use App\Domains\Entity\Contracts\Calculate\WithImageToVideoInterface;
use App\Domains\Entity\Contracts\Input\WithInputVideoInterface;
use App\Domains\Entity\Enums\EntityEnum;

class GrokImagineVideoITVDriver extends BaseDriver implements WithImageToVideoInterface, WithInputVideoInterface
{
    use HasImageToVideo;
    use HasInputVideo;

    public function enum(): EntityEnum
    {
        return EntityEnum::GROK_IMAGINE_VIDEO_ITV;
    }
}
