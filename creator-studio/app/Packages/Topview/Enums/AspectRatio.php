<?php

namespace App\Packages\Topview\Enums;

use App\Concerns\HasEnumConvert;

enum AspectRatio: string
{
    use HasEnumConvert;

    case RATIO_9_16 = '9:16';
    case RATIO_3_4 = '3:4';
    case RATIO_1_1 = '1:1';
    case RATIO_4_3 = '4:3';
    case RATIO_16_9 = '16:9';
    case ALL = 'all';

    public function label(): string
    {
        return match ($this) {
            self::RATIO_9_16 => '9:16',
            self::RATIO_3_4  => '3:4',
            self::RATIO_1_1  => '1:1',
            self::RATIO_4_3  => '4:3',
            self::RATIO_16_9 => '16:9',
            self::ALL        => 'all'
        };
    }
}
