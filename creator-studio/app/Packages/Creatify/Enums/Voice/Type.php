<?php

namespace App\Packages\Creatify\Enums\Voice;

use App\Concerns\HasEnumConvert;

enum Type: string
{
    use HasEnumConvert;

    case TEXT = 'text';

    /**
     * get label from enum
     */
    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Text'
        };
    }
}
