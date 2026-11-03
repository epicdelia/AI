<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\EnumTo;
use App\Enums\Traits\StringBackedEnumTrait;

enum AITokenType: string implements Contracts\WithStringBackedEnum
{
    use EnumTo;
    use StringBackedEnumTrait;

    case WORD = 'word';
    case IMAGE = 'image';

    case CHARACTER = 'character';
    case MINUTE = 'minute';
    case SECOND = 'second';
    case IMAGE_TO_VIDEO = 'image_to_video';
    case TEXT_TO_SPEECH = 'text_to_speech';
    case SPEECH_TO_TEXT = 'speech_to_text';
    case TEXT_TO_VIDEO = 'text_to_video';

    case VIDEO_TO_VIDEO = 'video_to_video';
    case VISION = 'vision';
    case PLAGIARISM = 'plagiarism';

    case PRESENTATION = 'presentation';

    public function label(): string
    {
        return match ($this) {
            self::WORD           => __('Word'),
            self::IMAGE          => __('Image'),
            self::SECOND         => __('Second'),
            self::MINUTE         => __('Minute'),
            self::CHARACTER      => __('Character'),
            self::IMAGE_TO_VIDEO => __('Image to Video'),
            self::TEXT_TO_SPEECH => __('Text to Speech'),
            self::SPEECH_TO_TEXT => __('Speech to Text'),
            self::TEXT_TO_VIDEO  => __('Text to Video'),
            self::VIDEO_TO_VIDEO => __('Video to Video'),
            self::VISION         => __('Vision'),
            self::PLAGIARISM     => __('Plagiarism'),
            self::PRESENTATION   => __('Presentation'),
        };
    }
}
