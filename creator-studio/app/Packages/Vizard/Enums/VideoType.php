<?php

namespace App\Packages\Vizard\Enums;

use App\Concerns\HasEnumConvert;

enum VideoType: int
{
    use HasEnumConvert;

    case REMOTE_VIDEO_FILE = 1;
    case YOUTUBE = 2;
    case GOOGLE_DRIVE = 3;
    case VIMEO = 4;
    case STREAMYARD = 5;
    case TIKTOK = 6;
    case TWITTER = 7;
    case RUMBLE = 8;
    case TWITCH = 9;
    case LOOM = 10;
    case FACEBOOK = 11;
    case LINKEDIN = 12;

    public function label(): string
    {
        return match ($this) {
            self::REMOTE_VIDEO_FILE => 'Remote video file',
            self::YOUTUBE           => 'YouTube',
            self::GOOGLE_DRIVE      => 'Google Drive',
            self::VIMEO             => 'Vimeo',
            self::STREAMYARD        => 'StreamYard',
            self::TIKTOK            => 'TikTok',
            self::TWITTER           => 'Twitter',
            self::RUMBLE            => 'Rumble',
            self::TWITCH            => 'Twitch',
            self::LOOM              => 'Loom',
            self::FACEBOOK          => 'Facebook',
            self::LINKEDIN          => 'LinkedIn',
        };
    }
}
