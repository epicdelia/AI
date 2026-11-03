<?php

namespace App\Packages\Creatify\Enums;

use App\Concerns\HasEnumConvert;

enum Language: string
{
    use HasEnumConvert;

    case AR = 'ar';
    case BG = 'bg';
    case CS = 'cs';
    case DA = 'da';
    case DE = 'de';
    case EL = 'el';
    case EN = 'en';
    case ES = 'es';
    case FI = 'fi';
    case FR = 'fr';
    case HI = 'hi';
    case HR = 'hr';
    case ID = 'id';
    case IT = 'it';
    case JA = 'ja';
    case KO = 'ko';
    case MS = 'ms';
    case NL = 'nl';
    case PL = 'pl';
    case PT = 'pt';
    case RO = 'ro';
    case RU = 'ru';
    case SK = 'sk';
    case SV = 'sv';
    case TA = 'ta';
    case TL = 'tl';
    case TR = 'tr';
    case UK = 'uk';
    case ZH = 'zk';

    /**
     * get label from enum
     */
    public function label(): string
    {
        return match ($this) {
            self::AR => 'Arabic',
            self::BG => 'Bularian',
            self::CS => 'Czech',
            self::DA => 'Danish',
            self::DE => 'German',
            self::EL => 'Greek',
            self::EN => 'English',
            self::ES => 'Spanish',
            self::FI => 'Finnish',
            self::FR => 'French',
            self::HI => 'Hindi',
            self::HR => 'Croatian',
            self::ID => 'Indonesian',
            self::IT => 'Italian',
            self::JA => 'Japanese',
            self::KO => 'Korean',
            self::MS => 'Malay',
            self::NL => 'Dutch',
            self::PL => 'Polish',
            self::PT => 'Portugues',
            self::RO => 'Romanian',
            self::RU => 'Russian',
            self::SK => 'Slovak',
            self::SV => 'Swedish',
            self::TA => 'Tamil',
            self::TL => 'Tagalog',
            self::TR => 'Turkish',
            self::UK => 'Ukrainian',
            self::ZH => 'Chinese'
        };
    }
}
