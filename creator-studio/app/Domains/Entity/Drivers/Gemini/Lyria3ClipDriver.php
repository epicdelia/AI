<?php

declare(strict_types=1);

namespace App\Domains\Entity\Drivers\Gemini;

use App\Domains\Entity\BaseDriver;
use App\Domains\Entity\Concerns\Calculate\HasSecond;
use App\Domains\Entity\Concerns\Input\HasInputSecond;
use App\Domains\Entity\Contracts\Calculate\WithSecondInterface;
use App\Domains\Entity\Contracts\Input\WithInputSecondInterface;
use App\Domains\Entity\Enums\EntityEnum;

class Lyria3ClipDriver extends BaseDriver implements WithInputSecondInterface, WithSecondInterface
{
    use HasInputSecond;
    use HasSecond;

    public function enum(): EntityEnum
    {
        return EntityEnum::LYRIA_3_CLIP;
    }
}
