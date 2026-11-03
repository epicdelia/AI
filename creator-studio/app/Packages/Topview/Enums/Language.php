<?php

namespace App\Packages\Topview\Enums;

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
    case FIL = 'fil';
    case FI = 'fi';
    case FR = 'fr';
    case DE = 'de';
    case EL = 'el';
    case HI = 'hi';
    case HU = 'hu';
    case ID = 'id';
    case IT = 'it';
    case JA = 'ja';
    case KO = 'ko';
    case MS = 'ms';
    case NB = 'nb';
    case PL = 'pl';
    case PT = 'pt';
    case RO = 'ro';
    case RU = 'ru';
    case ZH_CN = 'zh-CN';
    case SK = 'sk';
    case ES = 'es';
    case SV = 'sv';
    case ZH_HANT = 'zh-Hant';
    case TR = 'tr';
    case UK = 'uk';
    case VI = 'vi';
    case TH = 'th';

    public function label(): string
    {
        return match ($this) {
            self::EN      => 'English',
            self::AR      => 'Arabic (العربية)',
            self::BG      => 'Bulgarian (Български)',
            self::HR      => 'Croatian (Hrvatski)',
            self::CS      => 'Czech (Čeština)',
            self::DA      => 'Danish (Dansk)',
            self::NL      => 'Dutch (Nederlands)',
            self::FIL     => 'Filipino (Filipino)',
            self::FI      => 'Finnish (Suomi)',
            self::FR      => 'French (Français)',
            self::DE      => 'German (Deutsch)',
            self::EL      => 'Greek (Ελληνικά)',
            self::HI      => 'Hindi (हिन्दी)',
            self::HU      => 'Hungarian (Magyar)',
            self::ID      => 'Indonesian (Bahasa Indonesia)',
            self::IT      => 'Italian (Italiano)',
            self::JA      => 'Japanese (日本語)',
            self::KO      => 'Korean (한국어)',
            self::MS      => 'Malay (Bahasa Melayu)',
            self::NB      => 'Norwegian (Norsk)',
            self::PL      => 'Polish (Polski)',
            self::PT      => 'Portuguese (Português)',
            self::RO      => 'Romanian (Română)',
            self::RU      => 'Russian (Русский)',
            self::ZH_CN   => 'Simplified Chinese (简体中文)',
            self::SK      => 'Slovak (Slovenčina)',
            self::ES      => 'Spanish (Español)',
            self::SV      => 'Swedish (Svenska)',
            self::ZH_HANT => 'Traditional Chinese (繁體中文)',
            self::TR      => 'Turkish (Türkçe)',
            self::UK      => 'Ukrainian (Українська)',
            self::VI      => 'Vietnamese (Tiếng Việt)',
            self::TH      => 'Thai (ภาษาไทย)',
        };
    }
}
