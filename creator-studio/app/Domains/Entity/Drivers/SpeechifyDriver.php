<?php

declare(strict_types=1);

namespace App\Domains\Entity\Drivers;

use App\Domains\Entity\BaseDriver;
use App\Domains\Entity\Concerns\Calculate\HasCharacters;
use App\Domains\Entity\Concerns\Input\HasInput;
use App\Domains\Entity\Contracts\Calculate\WithCharsInterface;
use App\Domains\Entity\Contracts\Input\WithInputInterface;
use App\Domains\Entity\Enums\EntityEnum;

class SpeechifyDriver extends BaseDriver implements WithCharsInterface, WithInputInterface
{
    use HasCharacters;
    use HasInput;

    public function enum(): EntityEnum
    {
        return EntityEnum::Speechify;
    }
}
