<?php

namespace App\Packages\Creatify\Enums;

use App\Concerns\HasEnumConvert;

enum VideoLength: int
{
    use HasEnumConvert;

    case LENGTH_15 = 15;
    case LENGTH_30 = 30;
    case LENGTH_60 = 60;

    /**
     * get label from enum
     */
    public function label(): string
    {
        return match ($this) {
            self::LENGTH_15 => '15s',
            self::LENGTH_30 => '30s',
            self::LENGTH_60 => '60s'
        };
    }
}
