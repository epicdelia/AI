<?php

namespace App\Packages\Topview\Enums;

use App\Concerns\HasEnumConvert;

enum VideoLength: int
{
    use HasEnumConvert;

    case ONE = 1;
    case TWO = 2;
    case THREE = 3;
    case FOUR = 4;

    public function label(): string
    {
        return match ($this) {
            self::ONE   => '30 - 50s',
            self::TWO   => '15 - 30s',
            self::THREE => '30 - 45s',
            self::FOUR  => '45 - 60s'
        };
    }
}
