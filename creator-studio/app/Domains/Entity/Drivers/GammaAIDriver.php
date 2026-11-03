<?php

declare(strict_types=1);

namespace App\Domains\Entity\Drivers;

use App\Domains\Entity\BaseDriver;
use App\Domains\Entity\Concerns\Calculate\HasPresentation;
use App\Domains\Entity\Concerns\Input\HasInputPresentation;
use App\Domains\Entity\Contracts\Calculate\WithPresentationInterface;
use App\Domains\Entity\Contracts\Input\WithInputPresentationInterface;
use App\Domains\Entity\Enums\EntityEnum;

class GammaAIDriver extends BaseDriver implements WithInputPresentationInterface, WithPresentationInterface
{
    use HasInputPresentation;
    use HasPresentation;

    public function enum(): EntityEnum
    {
        return EntityEnum::GAMMA_AI;
    }
}
