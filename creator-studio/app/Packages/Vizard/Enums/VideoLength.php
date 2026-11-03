<?php

namespace App\Packages\Vizard\Enums;

use App\Concerns\HasEnumConvert;

enum VideoLength: int
{
    use HasEnumConvert;

    case AUTO = 0;
    case LESS_30 = 1;
    case BETWEEN_30_60 = 2;
    case BETWEEN_60_90 = 3;
    case BETWEEN_90_3MIN = 4;

    public function label(): string
    {
        return match ($this) {
            self::AUTO            => 'Automatically chosen',
            self::LESS_30         => 'Less than 30 seconds',
            self::BETWEEN_30_60   => '30 to 60 seconds',
            self::BETWEEN_60_90   => '60 to 90 seconds',
            self::BETWEEN_90_3MIN => '90 seconds to 3 minutes'
        };
    }
}
