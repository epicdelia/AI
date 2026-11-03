<?php

namespace App\Packages\Vizard\Enums;

use App\Concerns\HasEnumConvert;

enum Language: string
{
    use HasEnumConvert;

    case EN = 'en';
    case AR = 'ar';
    case BG = 'bg';
    case HR = 'hr';
    case CS = 'cs';
    case DA = 'da';
    case NL = 'nl';
    case FI = 'fi';
    case FR = 'fr';
    case DE = 'de';
    case EL = 'el';
    case IW = 'iw';
    case HI = 'hi';
    case HU = 'hu';
    case ID = 'id';
    case IT = 'it';
    case JA = 'ja';
    case KO = 'ko';
    case LT = 'lt';
    case MAL = 'mal';
    case ZH = 'zh';
    case ZH_TW = 'zh-TW';
    case NO = 'no';
    case PL = 'pl';
    case PT = 'pt';
    case RO = 'ro';
    case RU = 'ru';
    case SR = 'sr';
    case SK = 'sk';
    case ES = 'es';
    case SV = 'sv';
    case TR = 'tr';
    case UK = 'uk';
    case VI = 'vi';

    public function label(): string
    {
        return match ($this) {
            self::EN     => 'English',
            self::AR     => 'Arabic (عربي)',
            self::BG     => 'Bulgarian (български)',
            self::HR     => 'Croatian (Hrvatski)',
            self::CS     => 'Czech (čeština)',
            self::DA     => 'Danish (Dansk)',
            self::NL     => 'Dutch (Nederlands)',
            self::FI     => 'Finnish (Suomi)',
            self::FR     => 'French (Français)',
            self::DE     => 'German (Deutsch)',
            self::EL     => 'Greek (Ελληνικά)',
            self::IW     => 'Hebrew (עִברִית)',
            self::HI     => 'Hindi (हिंदी)',
            self::HU     => 'Hungarian (Magyar nyelv)',
            self::ID     => 'Indonesian (Bahasa Indonesia)',
            self::IT     => 'Italian (Italiano)',
            self::JA     => 'Japanese (日本語)',
            self::KO     => 'Korean (한국어)',
            self::LT     => 'Lithuanian (Lietuvių kalba)',
            self::MAL    => 'Malay (Melayu)',
            self::ZH     => 'Mandarin - Simplified (简体)',
            self::ZH_TW  => 'Mandarin - Traditional (繁體)',
            self::NO     => 'Norwegian (Norsk)',
            self::PL     => 'Polish (Polski)',
            self::PT     => 'Portuguese (Português)',
            self::RO     => 'Romanian (Limba română)',
            self::RU     => 'Russian (Pусский)',
            self::SR     => 'Serbian (Српски)',
            self::SK     => 'Slovak (Slovenský)',
            self::ES     => 'Spanish (Español)',
            self::SV     => 'Swedish (Svenska)',
            self::TR     => 'Turkish (Türkçe)',
            self::UK     => 'Ukrainian (Україна)',
            self::VI     => 'Vietnamese (Tiếng Việt)',
        };
    }
}
