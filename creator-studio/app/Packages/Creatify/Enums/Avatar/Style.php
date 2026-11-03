<?php

namespace App\Packages\Creatify\Enums\Avatar;

use App\Concerns\HasEnumConvert;

enum Style: string
{
    use HasEnumConvert;

    case SELFIE = 'selfie';
    case PRESENTER = 'presenter';
    case OTHER = 'other';

    /**
     * get label from enum
     */
    public function label(): string
    {
        return match ($this) {
            self::SELFIE    => 'Selfie',
            self::PRESENTER => 'Presenter',
            self::OTHER     => 'Other'
        };
    }
}
