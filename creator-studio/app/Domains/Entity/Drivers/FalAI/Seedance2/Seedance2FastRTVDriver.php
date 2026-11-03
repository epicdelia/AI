<?php

declare(strict_types=1);

namespace App\Domains\Entity\Drivers\FalAI\Seedance2;

use App\Domains\Entity\BaseDriver;
use App\Domains\Entity\Concerns\Calculate\HasTextToVideo;
use App\Domains\Entity\Concerns\Input\HasInputVideo;
use App\Domains\Entity\Contracts\Calculate\WithTextToVideoInterface;
use App\Domains\Entity\Contracts\Input\WithInputVideoInterface;
use App\Domains\Entity\Enums\EntityEnum;

class Seedance2FastRTVDriver extends BaseDriver implements WithInputVideoInterface, WithTextToVideoInterface
{
    use HasInputVideo;
    use HasTextToVideo;

    public function enum(): EntityEnum
    {
        return EntityEnum::SEEDANCE_2_FAST_RTV;
    }
}
