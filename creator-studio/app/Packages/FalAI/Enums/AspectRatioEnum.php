<?php

namespace App\Packages\FalAI\Enums;

use App\Concerns\HasEnumConvert;

enum AspectRatioEnum: string
{
    use HasEnumConvert;

    case RATIO_16_9 = '16:9';
    case RATIO_1_1 = '1:1';
    case RATIO_9_16 = '9:16';
}
