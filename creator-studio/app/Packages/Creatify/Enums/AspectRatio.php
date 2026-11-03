<?php

namespace App\Packages\Creatify\Enums;

use App\Concerns\HasEnumConvert;

enum AspectRatio: string
{
    use HasEnumConvert;

    case RATIO_16_9 = '16x9';
    case RATIO_1_1 = '1x1';
    case RATIO_9_16 = '9x16';

    /**
     * get label from enum
     */
    public function label(): string
    {
        return match ($this) {
            self::RATIO_16_9 => 'Ratio 16:9',
            self::RATIO_1_1  => 'Ratio 1:1',
            self::RATIO_9_16 => 'Ratio 9:16'
        };
    }
}
