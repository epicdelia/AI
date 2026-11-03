<?php

namespace App\Packages\FalAI;

use App\Domains\Entity\Enums\EntityEnum;
use App\Packages\FalAI\API\BaseApiClient;
use App\Packages\FalAI\Contracts\TextToVideoModelInterface;
use App\Packages\FalAI\Models\FastVeo3;
use App\Packages\FalAI\Models\GeminiOmniFlash;
use App\Packages\FalAI\Models\GrokImagineVideo;
use App\Packages\FalAI\Models\Kling;
use App\Packages\FalAI\Models\Kling25Turbo;
use App\Packages\FalAI\Models\Kling26Pro;
use App\Packages\FalAI\Models\KlingV3;
use App\Packages\FalAI\Models\Seedance2;
use App\Packages\FalAI\Models\Veed;
use App\Packages\FalAI\Models\Veo3;
use App\Packages\FalAI\Models\Veo31;
use App\Packages\FalAI\Models\VideoBackgroundRemoval;
use InvalidArgumentException;

class FalAIService
{
    protected BaseApiClient $client;

    public function __construct(
        string $falKey,
        string $apiBaseUrl = 'https://queue.fal.run'
    ) {
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 3600);

        $this->client = new BaseApiClient($falKey, $apiBaseUrl);
    }

    /** text to video model */
    public function textToVideoModel(EntityEnum $model): TextToVideoModelInterface
    {
        return match ($model) {
            EntityEnum::KLING_VIDEO => new Kling($this->client),
            EntityEnum::VEED        => new Veed($this->client),
            EntityEnum::VEO_3       => new Veo3($this->client),
            EntityEnum::VEO_3_FAST  => new FastVeo3($this->client),
            EntityEnum::VEO_3_1_TEXT_TO_VIDEO,
            EntityEnum::VEO_3_1_TEXT_TO_VIDEO_FAST,
            EntityEnum::VEO_3_1_IMAGE_TO_VIDEO,
            EntityEnum::VEO_3_1_IMAGE_TO_VIDEO_FAST,
            EntityEnum::VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO,
            EntityEnum::VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO_FAST,
            EntityEnum::VEO_3_1_REFERENCE_TO_VIDEO => new Veo31($this->client),
            EntityEnum::VEO_3_1_LITE_TEXT_TO_VIDEO,
            EntityEnum::VEO_3_1_LITE_IMAGE_TO_VIDEO,
            EntityEnum::VEO_3_1_LITE_FIRST_LAST_FRAME_TO_VIDEO => new Veo31($this->client),
            EntityEnum::KLING_2_5_TURBO_PRO_TTV,
            EntityEnum::KLING_2_5_TURBO_STANDARD_ITV,
            EntityEnum::KLING_2_5_TURBO_PRO_ITV    => new Kling25Turbo($this->client, $model),
            EntityEnum::KLING_2_6_PRO_TTV,
            EntityEnum::KLING_2_6_PRO_ITV,
            EntityEnum::KLING_2_6_PRO_MOTION_CONTROL,
            EntityEnum::KLING_2_6_STANDARD_MOTION_CONTROL => new Kling26Pro($this->client, $model),
            EntityEnum::KLING_3_PRO_TTV,
            EntityEnum::KLING_3_PRO_ITV,
            EntityEnum::KLING_3_STANDARD_TTV,
            EntityEnum::KLING_3_STANDARD_ITV       => new KlingV3($this->client, $model),
            EntityEnum::GROK_IMAGINE_VIDEO_TTV,
            EntityEnum::GROK_IMAGINE_VIDEO_ITV     => new GrokImagineVideo($this->client, $model),
            EntityEnum::SEEDANCE_2_TTV,
            EntityEnum::SEEDANCE_2_ITV,
            EntityEnum::SEEDANCE_2_RTV,
            EntityEnum::SEEDANCE_2_FAST_TTV,
            EntityEnum::SEEDANCE_2_FAST_ITV,
            EntityEnum::SEEDANCE_2_FAST_RTV        => new Seedance2($this->client, $model),
            EntityEnum::GEMINI_OMNI_FLASH_TTV,
            EntityEnum::GEMINI_OMNI_FLASH_ITV,
            EntityEnum::GEMINI_OMNI_FLASH_RTV      => new GeminiOmniFlash($this->client, $model),
            EntityEnum::VIDEO_BACKGROUND_REMOVAL   => new VideoBackgroundRemoval($this->client),
            default                                => throw new InvalidArgumentException("Model {$model->value} is not supported."),

        };
    }
}
