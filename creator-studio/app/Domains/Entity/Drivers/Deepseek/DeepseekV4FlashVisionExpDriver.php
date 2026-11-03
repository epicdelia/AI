<?php

declare(strict_types=1);

namespace App\Domains\Entity\Drivers\Deepseek;

use App\Domains\Entity\BaseDriver;
use App\Domains\Entity\Concerns\Calculate\HasWords;
use App\Domains\Entity\Concerns\Input\HasInput;
use App\Domains\Entity\Contracts\Calculate\WithVisionPreviewInterface;
use App\Domains\Entity\Contracts\Calculate\WithWordsInterface;
use App\Domains\Entity\Contracts\Input\WithInputInterface;
use App\Domains\Entity\Enums\EntityEnum;

class DeepseekV4FlashVisionExpDriver extends BaseDriver implements WithInputInterface, WithWordsInterface, WithVisionPreviewInterface
{
    use HasInput;
    use HasWords;

    public function enum(): EntityEnum
    {
        return EntityEnum::DEEPSEEK_V4_FLASH_VISION_EXP;
    }
}

