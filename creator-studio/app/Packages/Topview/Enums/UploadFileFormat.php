<?php

namespace App\Packages\Topview\Enums;

use App\Concerns\HasEnumConvert;

enum UploadFileFormat: string
{
    use HasEnumConvert;

    case MP4 = 'mp4';
    case MOV = 'mov';
    case PNG = 'png';
    case JPG = 'jpg';
    case BMP = 'bmp';
    case WEBP = 'webp';
    case MP3 = 'mp3';
    case WAV = 'wav';
    case M4A = 'm4a';

    // get file type whether it is video or image
    public function getType()
    {
        return match ($this) {
            self::MP4, self::MOV, self::M4A, self::MP3, self::WAV => 'video',
            self::PNG, self::JPG, self::BMP, self::WEBP => 'image',
        };
    }

    // accept type for each enum value
    public function accept(): string
    {
        return match ($this) {
            self::MP4  => 'video/mp4',
            self::MOV  => 'video/quicktime',
            self::PNG  => 'image/png',
            self::JPG  => 'image/jpeg',
            self::BMP  => 'image/bmp',
            self::WEBP => 'image/webp',
            self::MP3  => 'audio/mpeg',
            self::WAV  => 'audio/wav',
            self::M4A  => 'audio/mp4'
        };
    }

    // return all accepts type with string
    public static function accepts(): string
    {
        return implode(', ', array_map(fn ($case) => $case->accept(), self::cases()));
    }
}
