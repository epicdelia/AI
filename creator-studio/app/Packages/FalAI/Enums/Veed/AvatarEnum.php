<?php

namespace App\Packages\FalAI\Enums\Veed;

use App\Concerns\HasEnumConvert;

enum AvatarEnum: string
{
    use HasEnumConvert;

    case EMILY_PRIMARY = 'emily_primary';
    case EMILY_SIDE = 'emily_side';
    case MARCUS_PRIMARY = 'marcus_primary';
    case MARCUS_SIDE = 'marcus_side';
    case AISHA_WALKING = 'aisha_walking';
    case ELENA_PRIMARY = 'elena_primary';
    case ELENA_SIDE = 'elena_side';

    public function label(): string
    {
        return match ($this) {
            self::EMILY_PRIMARY      => 'Emily Primary',
            self::EMILY_SIDE         => 'Emily Side',
            self::MARCUS_PRIMARY     => 'Marcus Primary',
            self::MARCUS_SIDE        => 'Marcus Side',
            self::AISHA_WALKING      => 'Aisha Walking',
            self::ELENA_PRIMARY      => 'Elena Primary',
            self::ELENA_SIDE         => 'Elena Side',
        };
    }

    public function image(): string
    {
        return match ($this) {
            self::EMILY_PRIMARY  => 'emily-primary.png',
            self::EMILY_SIDE     => 'emily-side.png',
            self::MARCUS_PRIMARY => 'marcus-primary.png',
            self::MARCUS_SIDE    => 'marcus-side.png',
            self::AISHA_WALKING  => 'aisha-walking.png',
            self::ELENA_PRIMARY  => 'elena-primary.png',
            self::ELENA_SIDE     => 'elena-side.png',
        };
    }
}
