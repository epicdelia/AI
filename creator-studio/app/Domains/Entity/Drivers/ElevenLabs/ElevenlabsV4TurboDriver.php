<?php

declare(strict_types=1);

namespace App\Domains\Entity\Drivers\ElevenLabs;

use App\Domains\Entity\Enums\EntityEnum;

class ElevenlabsV4TurboDriver extends ElevenlabsDriver
{
    public function enum(): EntityEnum
    {
        return EntityEnum::ELEVENLABS_V4_TURBO;
    }
}

