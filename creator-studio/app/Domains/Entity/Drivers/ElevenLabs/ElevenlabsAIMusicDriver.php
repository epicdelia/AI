<?php

declare(strict_types=1);

namespace App\Domains\Entity\Drivers\ElevenLabs;

use App\Domains\Entity\BaseDriver;
use App\Domains\Entity\Concerns\Calculate\HasMinutes;
use App\Domains\Entity\Concerns\Input\HasInputMinute;
use App\Domains\Entity\Contracts\Calculate\WithMinuteInterface;
use App\Domains\Entity\Contracts\Input\WithInputMinuteInterface;
use App\Domains\Entity\Enums\EntityEnum;

class ElevenlabsAIMusicDriver extends BaseDriver implements WithInputMinuteInterface, WithMinuteInterface
{
    use HasInputMinute;
    use HasMinutes;

    public function enum(): EntityEnum
    {
        return EntityEnum::ELEVENLABS_AI_MUSIC;
    }
}
