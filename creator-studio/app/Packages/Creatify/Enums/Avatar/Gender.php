<?php

namespace App\Packages\Creatify\Enums\Avatar;

use App\Concerns\HasEnumConvert;

enum Gender: string
{
    use HasEnumConvert;

    case MALE = 'm';
    case FEMALE = 'f';
    case NONBINARY = 'nb';

    /**
     * get label from enum
     */
    public function label(): string
    {
        return match ($this) {
            self::MALE      => 'Male',
            self::FEMALE    => 'Female',
            self::NONBINARY => 'Non Binary'
        };
    }
}
