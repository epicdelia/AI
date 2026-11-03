<?php

namespace App\Packages\FalAI\Enums\Kling;

use App\Concerns\HasEnumConvert;

enum DurationEnum: string
{
    use HasEnumConvert;

    case DURATION_5S = '5';
    case DURATION_10S = '10';

    public function label(): string
    {
        return match ($this) {
            self::DURATION_5S  => '5s',
            self::DURATION_10S => '10s'
        };
    }
}
