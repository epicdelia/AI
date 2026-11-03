<?php

namespace App\Packages\Vizard\Enums;

use App\Concerns\HasEnumConvert;

enum Ratio: int
{
    use HasEnumConvert;

    case CLIP_1 = 1; // 9:16
    case CLIP_2 = 2; // 1:1
    case CLIP_3 = 3; // 4:5
    case CLIP_4 = 4; // 16:9

    public function label(): string
    {
        return match ($this) {
            self::CLIP_1 => '9:16 (Vertical)',
            self::CLIP_2 => '1:1 (Square)',
            self::CLIP_3 => '4:5 (Portrait)',
            self::CLIP_4 => '16:9 (Horizontal)',
        };
    }

    public function platforms(): string
    {
        return match ($this) {
            self::CLIP_1 => 'TikTok, Instagram Reels, Shorts',
            self::CLIP_2 => 'Instagram Feed, Facebook Feed',
            self::CLIP_3 => 'Instagram Feed (optimized height)',
            self::CLIP_4 => 'YouTube, LinkedIn, Twitter',
        };
    }
}
