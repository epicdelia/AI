<?php

namespace App\Domains\Entity\Drivers\Vizard;

use App\Domains\Entity\BaseDriver;
use App\Domains\Entity\Concerns\Calculate\HasVideoToVideo;
use App\Domains\Entity\Concerns\Input\HasInputVideo;
use App\Domains\Entity\Contracts\Calculate\WithTextToVideoInterface;
use App\Domains\Entity\Contracts\Calculate\WithVideoToVideoInterface;
use App\Domains\Entity\Contracts\Input\WithInputVideoInterface;
use App\Domains\Entity\Enums\EntityEnum;

class AIClipVizardDriver extends BaseDriver implements WithInputVideoInterface, WithTextToVideoInterface, WithVideoToVideoInterface
{
    use HasInputVideo;
    use HasVideoToVideo;

    public function enum(): EntityEnum
    {
        return EntityEnum::AI_CLIP_VIZARD;
    }
}
