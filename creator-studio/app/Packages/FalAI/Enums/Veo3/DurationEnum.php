<?php

namespace App\Packages\FalAI\Enums\Veo3;

use App\Concerns\HasEnumConvert;

enum DurationEnum: string
{
    use HasEnumConvert;

    case DURATION_8S = '8s';
}
