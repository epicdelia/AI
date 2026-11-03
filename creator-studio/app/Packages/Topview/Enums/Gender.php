<?php

namespace App\Packages\Topview\Enums;

use App\Concerns\HasEnumConvert;

enum Gender: string
{
    use HasEnumConvert;

    case FEMALE = 'female';
    case MALE = 'male';

    public function label(): string
    {
        return match ($this) {
            self::FEMALE => 'female',
            self::MALE   => 'male'
        };
    }
}
