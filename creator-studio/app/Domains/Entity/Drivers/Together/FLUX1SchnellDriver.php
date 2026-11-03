<?php

declare(strict_types=1);

namespace App\Domains\Entity\Drivers\Together;

use App\Domains\Entity\BaseDriver;
use App\Domains\Entity\Concerns\Calculate\HasImages;
use App\Domains\Entity\Concerns\Input\HasInputImage;
use App\Domains\Entity\Contracts\Calculate\WithImagesInterface;
use App\Domains\Entity\Contracts\Input\WithInputImageInterface;
use App\Domains\Entity\Enums\EntityEnum;

class FLUX1SchnellDriver extends BaseDriver implements WithImagesInterface, WithInputImageInterface
{
    use HasImages;
    use HasInputImage;

    public function enum(): EntityEnum
    {
        return EntityEnum::BLACK_FOREST_LABS_FLUX_1_SCHNELL;
    }
}
