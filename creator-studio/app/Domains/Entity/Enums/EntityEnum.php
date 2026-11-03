<?php

declare(strict_types=1);

namespace App\Domains\Entity\Enums;

use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Entity\Contracts\Calculate\WithCharsInterface;
use App\Domains\Entity\Contracts\Calculate\WithImagesInterface;
use App\Domains\Entity\Contracts\Calculate\WithImageToVideoInterface;
use App\Domains\Entity\Contracts\Calculate\WithMinuteInterface;
use App\Domains\Entity\Contracts\Calculate\WithPlagiarismInterface;
use App\Domains\Entity\Contracts\Calculate\WithPresentationInterface;
use App\Domains\Entity\Contracts\Calculate\WithSecondInterface;
use App\Domains\Entity\Contracts\Calculate\WithSpeechToTextInterface;
use App\Domains\Entity\Contracts\Calculate\WithTextToSpeechInterface;
use App\Domains\Entity\Contracts\Calculate\WithTextToVideoInterface;
use App\Domains\Entity\Contracts\Calculate\WithVideoToVideoInterface;
use App\Domains\Entity\Contracts\Calculate\WithVisionPreviewInterface;
use App\Domains\Entity\Contracts\Calculate\WithWordsInterface;
use App\Domains\Entity\Drivers\AiCaptionsDriver;
use App\Domains\Entity\Drivers\AiMlMinimax;
use App\Domains\Entity\Drivers\Anthropic;
use App\Domains\Entity\Drivers\AzureDriver;
use App\Domains\Entity\Drivers\AzureOpenaiDriver;
use App\Domains\Entity\Drivers\ClipDropDriver;
use App\Domains\Entity\Drivers\Creatify;
use App\Domains\Entity\Drivers\Deepseek;
use App\Domains\Entity\Drivers\ElevenLabs;
use App\Domains\Entity\Drivers\FalAI;
use App\Domains\Entity\Drivers\FreepikDriver;
use App\Domains\Entity\Drivers\GammaAIDriver;
use App\Domains\Entity\Drivers\Gemini;
use App\Domains\Entity\Drivers\GoogleDriver;
use App\Domains\Entity\Drivers\HeygenDriver;
use App\Domains\Entity\Drivers\Klap;
use App\Domains\Entity\Drivers\NovitaDriver;
use App\Domains\Entity\Drivers\OpenAI;
use App\Domains\Entity\Drivers\OpenRouter;
use App\Domains\Entity\Drivers\PebblelyDriver;
use App\Domains\Entity\Drivers\PerplexityDriver;
use App\Domains\Entity\Drivers\PexelsDriver;
use App\Domains\Entity\Drivers\PiAPI\MidjourneyDriver;
use App\Domains\Entity\Drivers\PixabayDriver;
use App\Domains\Entity\Drivers\PlagiarismCheckDriver;
use App\Domains\Entity\Drivers\SerperDriver;
use App\Domains\Entity\Drivers\SpeechifyDriver;
use App\Domains\Entity\Drivers\StableDiffusion;
use App\Domains\Entity\Drivers\SynthesiaDriver;
use App\Domains\Entity\Drivers\Together;
use App\Domains\Entity\Drivers\Topview;
use App\Domains\Entity\Drivers\UnsplashDriver;
use App\Domains\Entity\Drivers\VideoDubbingDriver;
use App\Domains\Entity\Drivers\Vizard;
use App\Domains\Entity\Drivers\XAI;
use App\Enums\AITokenType;
use App\Enums\Traits\EnumTo;
use App\Enums\Traits\SluggableEnumTrait;
use App\Enums\Traits\StringBackedEnumTrait;
use Illuminate\Support\Collection;

enum EntityEnum: string
{
    use EnumTo;
    use SluggableEnumTrait;
    use StringBackedEnumTrait;

    // Anthropic
    case CLAUDE_FABLE_5 = 'claude-fable-5';
    case CLAUDE_SONNET_5 = 'claude-sonnet-5';
    case CLAUDE_SONNET_4_5 = 'claude-sonnet-4-5-20250929';
    case CLAUDE_SONNET_4_6 = 'claude-sonnet-4-6';
    case CLAUDE_SONNET_4 = 'claude-sonnet-4-20250514';
    case CLAUDE_3_7_SONNET = 'claude-3-7-sonnet-20250219';
    case CLAUDE_3_5_SONNET_V2 = 'claude-3-5-sonnet-20241022';
    case CLAUDE_3_5_SONNET = 'claude-3-5-sonnet-20240620';
    case CLAUDE_3_SONNET = 'claude-3-sonnet-20240229';
    case CLAUDE_OPUS_4_8 = 'claude-opus-4-8';
    case CLAUDE_OPUS_4_7 = 'claude-opus-4-7';
    case CLAUDE_OPUS_4_6 = 'claude-opus-4-6';
    case CLAUDE_OPUS_4_5 = 'claude-opus-4-5-20251101';
    case CLAUDE_OPUS_4_1 = 'claude-opus-4-1-20250805';
    case CLAUDE_OPUS_4 = 'claude-opus-4-20250514';
    case CLAUDE_3_OPUS = 'claude-3-opus-20240229';

    case CLAUDE_3_5_HAIKU = 'claude-3-haiku-20241022';
    case CLAUDE_3_HAIKU = 'claude-3-haiku-20240307';
    case CLAUDE_2_1 = 'claude-2.1';
    case CLAUDE_2_0 = 'claude-2.0';

    // Embeding models for Anthropic
    case VOYAGE_2 = 'voyage-2';

    case VOYAGE_LARGE_2 = 'voyage-large-2';

    case VOYAGE_CODE_2 = 'voyage-code-2';
    // OpenAI
    case DAVINCI = 'davinci-002';

    case TEXT_DAVINCI_003 = 'text-davinci-003';

    case GPT_3_5_TURBO = 'gpt-3.5-turbo';

    case GPT_3_5_TURBO_0125 = 'gpt-3.5-turbo-0125';

    case GPT_3_5_TURBO_1106 = 'gpt-3.5-turbo-1106';

    case GPT_4 = 'gpt-4';

    case GPT_4_TURBO = 'gpt-4-turbo';

    case GPT_4_1106_PREVIEW = 'gpt-4-1106-preview';

    case GPT_4_0125_PREVIEW = 'gpt-4-0125-preview';

    case GPT_4_O = 'gpt-4o';

    case GPT_4_O_MINI = 'gpt-4o-mini';

    case GPT_4_O_SEARCH_PREVIEW = 'gpt-4o-search-preview';
    case GPT_4_O_MINI_SEARCH_PREVIEW = 'gpt-4o-mini-search-preview';

    case GPT_4_O1_PREVIEW = 'o1-preview';

    case GPT_4_O1_MINI = 'o1-mini';

    case GPT_O_1 = 'o1';

    case GPT_O_03_mini = 'o3-mini';

    case GPT_4_O_REALTIME_PREVIEW = 'gpt-4o-realtime-preview-2024-12-17';

    case GPT_REALTIME = 'gpt-realtime';

    case GPT_4_1 = 'gpt-4.1';

    case GPT_4_1_MINI = 'gpt-4.1-mini';

    case GPT_4_1_NANO = 'gpt-4.1-nano';

    case GPT_O_4_MINI = 'o4-mini';

    case GPT_O_3 = 'o3';

    case GPT_5 = 'gpt-5';

    case GPT_5_MINI = 'gpt-5-mini';

    case GPT_5_NANO = 'gpt-5-nano';

    case GPT_5_CHAT = 'gpt-5-chat-latest';

    case GPT_5_PRO = 'gpt-5-pro';

    case GPT_5_1 = 'gpt-5.1';

    case GPT_5_1_CHAT = 'gpt-5.1-chat-latest';

    case GPT_5_2 = 'gpt-5.2';
    case GPT_5_2_PRO = 'gpt-5.2-pro';

    case GPT_5_3_CHAT = 'gpt-5.3-chat-latest';

    case GPT_5_4 = 'gpt-5.4';

    case GPT_5_4_MINI = 'gpt-5.4-mini';
    case GPT_5_4_NANO = 'gpt-5.4-nano';

    case GPT_5_5 = 'gpt-5.5';
    case GPT_5_6_SOL = 'gpt-5.6-sol';
    case GPT_5_6_TERRA = 'gpt-5.6-terra';
    case GPT_5_6_LUNA = 'gpt-5.6-luna';
    case O3_DEEP_RESEARCH = 'o3-deep-research';
    case O4_MINI_DEEP_RESEARCH = 'o4-mini-deep-research';

    case SORA_2 = 'sora-2';

    case SORA_2_PRO = 'sora-2-pro';

    // Embedding models for openai
    case TEXT_EMBEDDING_ADA_002 = 'text-embedding-ada-002';

    case TEXT_EMBEDDING_3_SMALL = 'text-embedding-3-small';

    case TEXT_EMBEDDING_3_LARGE = 'text-embedding-3-large';

    // Stable Diffusion
    case IMAGE_TO_VIDEO = 'image-to-video';

    case STABLE_DIFFUSION_XL_1024_V_1_0 = 'stable-diffusion-xl-1024-v1-0';

    case STABLE_DIFFUSION_V_1_6 = 'stable-diffusion-v1-6';

    case SD_3 = 'sd3';

    case SD_3_TURBO = 'sd3-turbo';

    case SD_3_MEDIUM = 'sd3-medium';

    case SD_3_LARGE = 'sd3-large';

    case SD_3_LARGE_TURBO = 'sd3-large-turbo';

    case SD_3_5_LARGE = 'sd3.5-large';

    case SD_3_5_LARGE_TURBO = 'sd3.5-large-turbo';

    case SD_3_5_MEDIUM = 'sd3.5-medium';

    case CORE = 'core';

    case ULTRA = 'ultra';

    case AWS_BEDROCK = 'aws_bedrock';

    // Gemini
    case GEMINI_2_5_FLASH_PREVIEW_05_20 = 'gemini-2.5-flash-preview-05-20';
    case GEMINI_3_PRO_PREVIEW = 'gemini-3-pro-preview';
    case GEMINI_3_1_PRO_PREVIEW = 'gemini-3.1-pro-preview';
    case GEMINI_2_5_PRO = 'gemini-2.5-pro';
    case GEMINI_DEEP_RESEARCH = 'gemini-deep-research';
    case GEMINI_2_0_FLASH = 'gemini-2.0-flash';
    case GEMINI_2_0_FLASH_LITE = 'gemini-2.0-flash-lite';
    case GEMINI_1_5_PRO = 'gemini-1.5-pro';
    case GEMINI_EMBEDDING_EXP = 'gemini-embedding-exp';
    case GEMINI_1_5_FLASH = 'gemini-1.5-flash';
    case GEMINI_3_FLASH = 'gemini-3-flash-preview';
    case GEMINI_3_1_FLASH_LIVE_PREVIEW = 'gemini-3.1-flash-live-preview';
    case GEMINI_TEXT_EMBEDDING_004 = 'text-embedding-004';

    case CLIPDROP = 'clipdrop';

    case NOVITA = 'novita';

    case FREEPIK = 'freepik';

    case PLAGIARISMCHECK = 'plagiarismcheck';

    case SYNTHESIA = 'synthesia';

    case HEYGEN = 'heygen';

    case VIDEO_DUBBING = 'video-dubbing';

    case AI_CAPTIONS = 'ai-captions';

    case PEBBLELY = 'pebblely';

    case DEEPSEEK_CHAT = 'deepseek-chat';

    case DEEPSEEK_REASONER = 'deepseek-reasoner';

    case UNSPLASH = 'unsplash';

    case PEXELS = 'pexels';

    case PIXABAY = 'pixabay';

    case ELEVENLABS = 'elevenlabs';
    case ELEVENLABS_V3 = 'eleven_v3';

    case ELEVENLABS_VOICE_CHATBOT = 'elevenlabs-voice-chatbot';

    case ISOLATOR = 'isolator';

    case ELEVENLABS_AI_MUSIC = 'elevenlabs-ai-music';

    case LYRIA_3_CLIP = 'lyria-3-clip';

    case LYRIA_3_PRO = 'lyria-3-pro';

    case GOOGLE = 'google';

    case AZURE = 'azure';

    case AZURE_OPENAI = 'azure-openai';

    case Speechify = 'speechify';

    case SERPER = 'serper';

    case PERPLEXITY = 'perplexity';

    case WHISPER_1 = 'whisper-1';

    case DALL_E_2 = 'dall-e-2';

    case DALL_E_3 = 'dall-e-3';

    case GPT_IMAGE_1 = 'gpt-image-1';
    case GPT_IMAGE_1_5 = 'gpt-image-1.5';
    case GPT_IMAGE_2 = 'gpt-image-2';

    case TTS_1 = 'tts-1';
    case TTS_1_HD = 'tts-1-hd';
    case GROK_2_1212 = 'grok-2-1212';
    case GROK_2_VISION_1212 = 'grok-2-vision-1212';
    case GROK_3 = 'grok-3';
    case GROK_3_MINI = 'grok-3-mini';
    case GROK_3_FAST = 'grok-3-fast';
    case GROK_3_MINI_FAST = 'grok-3-mini-fast';
    case GROK_4 = 'grok-4-0709';
    case GROK_4_FAST = 'grok-4-fast-reasoning';
    case GROK_4_1_FAST_REASONING = 'grok-4-1-fast-reasoning';
    case GROK_4_1_FAST_NON_REASONING = 'grok-4-1-fast-non-reasoning';

    case GAMMA_AI = 'gamma-ai';
    case VEED = 'veed';
    case VEED_FABRIC = 'veed/fabric-1.0';

    case VEO_2 = 'veo2';
    case VEO_3 = 'veo3';
    case VEO_3_1_TEXT_TO_VIDEO = 'veo3.1/text-to-video';
    case VEO_3_1_TEXT_TO_VIDEO_FAST = 'veo3.1/fast/text-to-video';
    case VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO = 'veo3.1/first-last-frame-to-video';
    case VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO_FAST = 'veo3.1/fast/first-last-frame-to-video';
    case VEO_3_1_IMAGE_TO_VIDEO = 'veo3.1/image-to-video';
    case VEO_3_1_IMAGE_TO_VIDEO_FAST = 'veo3.1/fast/image-to-video';
    case VEO_3_1_REFERENCE_TO_VIDEO = 'veo3.1/reference-to-video';
    case VEO_3_1_LITE_TEXT_TO_VIDEO = 'veo3.1/lite';
    case VEO_3_1_LITE_IMAGE_TO_VIDEO = 'veo3.1/lite/image-to-video';
    case VEO_3_1_LITE_FIRST_LAST_FRAME_TO_VIDEO = 'veo3.1/lite/first-last-frame-to-video';
    case VEO_3_FAST = 'veo3-fast';

    case NANO_BANANA = 'nano-banana';

    case NANO_BANANA_EDIT = 'nano-banana/edit';

    case NANO_BANANA_PRO = 'nano-banana-pro';

    case NANO_BANANA_PRO_EDIT = 'nano-banana-pro/edit';

    case NANO_BANANA_2 = 'nano-banana-2';

    case NANO_BANANA_2_EDIT = 'nano-banana-2/edit';

    case GROK_IMAGINE_IMAGE = 'xai/grok-imagine-image';

    case GROK_IMAGINE_IMAGE_EDIT = 'xai/grok-imagine-image/edit';

    case GROK_IMAGINE_VIDEO_TTV = 'xai/grok-imagine-video/text-to-video';

    case GROK_IMAGINE_VIDEO_ITV = 'xai/grok-imagine-video/image-to-video';

    case SEEDREAM_4 = 'seedream/v4/text-to-image';

    case SEEDREAM_4_EDIT = 'seedream/v4/edit';

    case SEEDANCE_2_TTV = 'bytedance/seedance-2.0/text-to-video';

    case SEEDANCE_2_ITV = 'bytedance/seedance-2.0/image-to-video';

    case SEEDANCE_2_RTV = 'bytedance/seedance-2.0/reference-to-video';

    case SEEDANCE_2_FAST_TTV = 'bytedance/seedance-2.0/fast/text-to-video';

    case SEEDANCE_2_FAST_ITV = 'bytedance/seedance-2.0/fast/image-to-video';

    case SEEDANCE_2_FAST_RTV = 'bytedance/seedance-2.0/fast/reference-to-video';

    case GEMINI_OMNI_FLASH_TTV = 'google/gemini-omni-flash';

    case GEMINI_OMNI_FLASH_ITV = 'google/gemini-omni-flash/image-to-video';

    case GEMINI_OMNI_FLASH_RTV = 'google/gemini-omni-flash/reference-to-video';

    case FLUX_PRO = 'flux-pro';

    case FLUX_PRO_KONTEXT_MAX_MULTI = 'flux-pro/kontext/max/multi';

    case FLUX_PRO_KONTEXT_TEXT_TO_IMAGE = 'flux-pro/kontext/text-to-image';

    case FLUX_PRO_KONTEXT = 'flux-pro/kontext';

    case IMAGEN_4 = 'imagen4';

    case IDEOGRAM = 'ideogram-v2';

    case FLUX_PRO_1_1 = 'flux-pro/v1.1';

    case FLUX_REALISM = 'flux-realism';

    case FLUX_SCHNELL = 'flux/schnell';

    case FLUX_2_FLEX = 'flux-2-flex';

    case FLUX_2_FLEX_EDIT = 'flux-2-flex/edit';

    case KLING = 'kling';

    case KLING_2_1 = 'klingV21';

    case KLING_2_5_TURBO_PRO_TTV = 'kling-2.5-turbo/pro/text-to-video';
    case KLING_2_5_TURBO_PRO_ITV = 'kling-2.5-turbo/pro/image-to-video';
    case KLING_2_5_TURBO_STANDARD_ITV = 'kling-2.5-turbo/standard/image-to-video';

    case KLING_2_6_PRO_TTV = 'kling-video/v2.6/pro/text-to-video';
    case KLING_2_6_PRO_ITV = 'kling-video/v2.6/pro/image-to-video';
    case KLING_2_6_PRO_MOTION_CONTROL = 'kling-video/v2.6/pro/motion-control';
    case KLING_2_6_STANDARD_MOTION_CONTROL = 'kling-video/v2.6/standard/motion-control';
    case KLING_3_PRO_TTV = 'kling-video/v3/pro/text-to-video';
    case KLING_3_PRO_ITV = 'kling-video/v3/pro/image-to-video';
    case KLING_3_STANDARD_TTV = 'kling-video/v3/standard/text-to-video';
    case KLING_3_STANDARD_ITV = 'kling-video/v3/standard/image-to-video';

    case KLING_IMAGE = 'klingImage';

    case KLING_VIDEO = 'kling-video';

    case LUMA_DREAM_MACHINE = 'luma-dream-machine';

    case HAIPER = 'haiper';

    case MINIMAX = 'minimax';

    case MUSIC_01 = 'music-01';
    // Open router
    case ANTHROPIC_CLAUDE_3_5_HAIKU_20241022 = 'anthropic/claude-3-5-haiku-20241022';
    case ANTHROPIC_CLAUDE_3_5_HAIKU_20241022_SELF_MODERATED = 'anthropic/claude-3-5-haiku-20241022:beta';
    case ANTHROPIC_CLAUDE_3_5_HAIKU = 'anthropic/claude-3-5-haiku';
    case ANTHROPIC_CLAUDE_3_5_HAIKU_SELF_MODERATED = 'anthropic/claude-3-5-haiku:beta';
    case LUMIMAID_V02_70B = 'neversleep/llama-3.1-lumimaid-70b';
    case MAGNUM_V4_72B = 'anthracite-org/magnum-v4-72b';
    case XAI_GROK_BETA = 'x-ai/grok-beta';
    case MINISTRAL_8B = 'mistralai/ministral-8b';
    case MINISTRAL_3B = 'mistralai/ministral-3b';
    case QWEN25_7B_INSTRUCT = 'qwen/qwen-2.5-7b-instruct';
    case NVIDIA_LLAMA_31_NEMOTRON_70B_INSTRUCT = 'nvidia/llama-3.1-nemotron-70b-instruct';
    case INFLECTION_3_PI = 'inflection/inflection-3-pi';
    case INFLECTION_3_PRODUCTIVITY = 'inflection/inflection-3-productivity';
    case LIQUID_LFM_40B_MOE_FREE = 'liquid/lfm-40b:free';
    case LIQUID_LFM_40B_MOB = 'liquid/lfm-40b';
    case ROCINANTE_12B = 'thedrummer/rocinante-12b';
    case EVA_QWEN25_14B = 'eva-unit-01/eva-qwen-2.5-14b';
    case MAGNUM_V2_72B = 'anthracite-org/magnum-v2-72b';
    case META_LLAMA32_3B_INSTRUCT_FREE = 'meta-llama/llama-3.2-3b-instruct:free';
    case META_LLAMA32_1B_INSTRUCT_FREE = 'meta-llama/llama-3.2-1b-instruct:free';
    case META_LLAMA32_3B_INSTRUCT = 'meta-llama/llama-3.2-3b-instruct';
    case META_LLAMA32_1B_INSTRUCT = 'meta-llama/llama-3.2-1b-instruct';
    case PERPLEXITY_LLAMA_31_SONAR_405B_ONLINE = 'perplexity/llama-3.1-sonar-huge-128k-online';
    case PERPLEXITY_LLAMA_31_SONAR_70B_ONLINE = 'perplexity/llama-3.1-sonar-large-128k-online';
    case PERPLEXITY_LLAMA_31_SONAR_70B = 'perplexity/llama-3.1-sonar-large-128k-chat';
    case PERPLEXITY_LLAMA_31_SONAR_8B_ONLINE = 'perplexity/llama-3.1-sonar-small-128k-online';
    case PERPLEXITY_LLAMA_31_SONAR_8B = 'perplexity/llama-3.1-sonar-small-128k-chat';

    case MIDJOURNEY = 'midjourney';

    // Fall AI video to video
    case VIDEO_UPSCALER = 'video-upscaler';

    case COGVIDEOX_5B = 'cogvideox-5b/video-to-video';

    case ANIMATEDIFF_V2V = 'animatediff-v2v';

    case FAST_ANIMATEDIFF_TURBO = 'fast-animatediff/turbo/video-to-video';

    case VIDEO_BACKGROUND_REMOVAL = 'veed/video-background-removal';

    case BLACK_FOREST_LABS_FLUX_1_SCHNELL = 'black-forest-labs/FLUX.1-schnell';

    // Ad ai video
    case AD_MARKETING_VIDEO = 'ad-marketing-video';

    // ad ai video with topview
    case AD_MARKETING_VIDEO_TOPVIEW = 'ad-marketing-video-topview';

    // ai clip video
    case AI_CLIP_VIZARD = 'ai-clip-vizard';
    case AI_CLIP_KLAP = 'ai-clip-klap';

    public static function listableCases(): Collection
    {
        return collect(self::cases())->map(
            fn ($case) => $case->creditBy()
        )->unique()->values();
    }

    public function isReasoningModel(): bool
    {
        return match ($this) {
            self::GPT_O_1,
            self::GPT_O_4_MINI,
            self::GPT_O_3,
            self::GPT_O_03_mini,
            self::GPT_5,
            self::GPT_5_CHAT,
            self::GPT_5_PRO,
            self::GPT_5_1,
            self::GPT_5_2,
            self::GPT_5_2_PRO,
            self::GPT_5_MINI,
            self::GPT_5_NANO,
            self::GPT_5_3_CHAT,
            self::GPT_5_4,
            self::GPT_5_4_MINI,
            self::GPT_5_4_NANO,
            self::GPT_5_6_SOL,
            self::GPT_5_6_TERRA,
            self::GPT_5_6_LUNA,
            self::GPT_5_5         => true,
            default               => false,
        };
    }

    public function isOSeriesModel(): bool
    {
        return match ($this) {
            self::GPT_O_1,
            self::GPT_O_4_MINI,
            self::GPT_O_3,
            self::GPT_O_03_mini => true,
            default             => false,
        };
    }

    /**
     * The reasoning.effort values this model accepts, in ascending order
     * (fastest first). Empty for non-reasoning models.
     *
     * @return array<int, string>
     */
    public function supportedReasoningEfforts(): array
    {
        return match ($this) {
            // GPT-5 original family: minimal, low, medium, high.
            self::GPT_5,
            self::GPT_5_CHAT,
            self::GPT_5_MINI,
            self::GPT_5_NANO      => ['minimal', 'low', 'medium', 'high'],

            // GPT-5.1+ family: none (default), low, medium, high.
            self::GPT_5_1,
            self::GPT_5_2,
            self::GPT_5_3_CHAT,
            self::GPT_5_4,
            self::GPT_5_4_MINI,
            self::GPT_5_4_NANO,
            self::GPT_5_6_SOL,
            self::GPT_5_6_TERRA,
            self::GPT_5_6_LUNA    => ['none', 'low', 'medium', 'high'],

            // Pro models: medium, high, xhigh.
            self::GPT_5_PRO,
            self::GPT_5_2_PRO     => ['medium', 'high', 'xhigh'],

            // GPT-5.5: none (default), low, medium, high, xhigh.
            self::GPT_5_5         => ['none', 'low', 'medium', 'high', 'xhigh'],

            // O-series: low, medium, high.
            self::GPT_O_1,
            self::GPT_O_4_MINI,
            self::GPT_O_3,
            self::GPT_O_03_mini   => ['low', 'medium', 'high'],

            default               => [],
        };
    }

    /**
     * Resolve a configured reasoning effort to the closest value this model accepts.
     * If the configured value is supported, it's returned as-is. Otherwise the
     * nearest supported value (by ordered-effort distance) is chosen.
     */
    public function resolveReasoningEffort(string $configured): string
    {
        $supported = $this->supportedReasoningEfforts();

        if (empty($supported)) {
            return $configured;
        }

        if (in_array($configured, $supported, true)) {
            return $configured;
        }

        $order = ['none', 'minimal', 'low', 'medium', 'high', 'xhigh'];
        $configuredIdx = array_search($configured, $order, true);
        if ($configuredIdx === false) {
            return $supported[0];
        }

        $bestEffort = $supported[0];
        $bestDistance = PHP_INT_MAX;
        foreach ($supported as $candidate) {
            $idx = array_search($candidate, $order, true);
            $distance = abs($idx - $configuredIdx);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestEffort = $candidate;
            }
        }

        return $bestEffort;
    }

    public function requiresMaxCompletionTokens(): bool
    {
        return $this->isReasoningModel();
    }

    public function creditBy(): self
    {
        // we are not using this for now, because we only show default model for user
        return match ($this) {
            // self::GPT_3_5_TURBO_0125,
            // self::GPT_3_5_TURBO_1106 => self::GPT_3_5_TURBO,
            default => $this
        };
    }

    public function creditIndex(): float
    {
        return 1.0;
    }

    /** @noinspection PhpDuplicateMatchArmBodyInspection */
    public function sharedCreditIndex(): float
    {
        return match ($this) {
            // Anthropic
            self::CLAUDE_3_HAIKU   => 0.0017,
            self::CLAUDE_3_5_HAIKU => 0.0053,
            self::CLAUDE_3_SONNET,
            self::CLAUDE_3_5_SONNET,
            self::CLAUDE_3_5_SONNET_V2,
            self::CLAUDE_3_7_SONNET,
            self::CLAUDE_SONNET_4,
            self::CLAUDE_SONNET_4_5,
            self::CLAUDE_SONNET_4_6,
            self::CLAUDE_SONNET_5 => 0.0200,
            self::CLAUDE_3_OPUS,
            self::CLAUDE_OPUS_4,
            self::CLAUDE_OPUS_4_1 => 0.0998,
            self::CLAUDE_OPUS_4_5,
            self::CLAUDE_OPUS_4_6,
            self::CLAUDE_OPUS_4_7,
            self::CLAUDE_OPUS_4_8 => 0.0333,
            self::CLAUDE_FABLE_5  => 0.0666,
            self::CLAUDE_2_1,
            self::CLAUDE_2_0 => 0.0319,

            // OpenAI GPT / o-series
            self::GPT_4_1_NANO => 0.0005,
            self::GPT_4_O_MINI,
            self::GPT_4_O_MINI_SEARCH_PREVIEW => 0.0008,
            self::GPT_4_1_MINI                => 0.0021,
            self::GPT_5_MINI,
            self::GPT_5_NANO,
            self::GPT_3_5_TURBO,
            self::GPT_3_5_TURBO_0125,
            self::GPT_3_5_TURBO_1106 => 0.0027,
            self::GPT_5_4_NANO,
            self::GPT_5_6_LUNA       => 0.0017,
            self::GPT_5_4_MINI,
            self::GPT_5_6_TERRA,
            self::GPT_5_3_CHAT => 0.0080,
            self::GPT_4_1,
            self::GPT_O_3,
            self::O3_DEEP_RESEARCH => 0.0106,
            self::GPT_4_O,
            self::GPT_4_O_SEARCH_PREVIEW,
            self::GPT_5,
            self::GPT_5_CHAT,
            self::GPT_5_1,
            self::GPT_5_1_CHAT => 0.0133,
            self::GPT_5_2      => 0.0186,
            self::GPT_5_4,
            self::GPT_5_6_SOL  => 0.0200,
            self::GPT_4_TURBO,
            self::GPT_4_1106_PREVIEW,
            self::GPT_4_0125_PREVIEW,
            self::GPT_5_5 => 0.0399,
            self::GPT_4,
            self::GPT_O_1,
            self::GPT_4_O1_PREVIEW => 0.0798,
            self::GPT_5_PRO        => 0.1596,
            self::GPT_5_2_PRO      => 0.2394,
            self::GPT_4_O1_MINI,
            self::GPT_O_03_mini,
            self::GPT_O_4_MINI,
            self::O4_MINI_DEEP_RESEARCH => 0.0059,
            self::DAVINCI,
            self::TEXT_DAVINCI_003 => 1.0,

            // Google Gemini
            self::GEMINI_2_0_FLASH_LITE => 0.0004,
            self::GEMINI_2_0_FLASH,
            self::GEMINI_1_5_FLASH               => 0.0005,
            self::GEMINI_2_5_FLASH_PREVIEW_05_20 => 0.0033,
            self::GEMINI_3_FLASH,
            self::GEMINI_3_1_FLASH_LIVE_PREVIEW => 0.0040,
            self::GEMINI_1_5_PRO                => 0.0067,
            self::GEMINI_2_5_PRO,
            self::GEMINI_DEEP_RESEARCH => 0.0133,
            self::GEMINI_3_PRO_PREVIEW,
            self::GEMINI_3_1_PRO_PREVIEW => 0.0160,

            // xAI Grok
            self::GROK_3_MINI,
            self::GROK_3_MINI_FAST,
            self::GROK_4_FAST,
            self::GROK_4_1_FAST_NON_REASONING => 0.0007,
            self::GROK_2_1212,
            self::GROK_2_VISION_1212,
            self::GROK_3,
            self::GROK_3_FAST => 0.0133,
            self::GROK_4,
            self::GROK_4_1_FAST_REASONING => 0.0200,

            // DeepSeek
            self::DEEPSEEK_CHAT     => 0.0015,
            self::DEEPSEEK_REASONER => 0.0029,

            // Images — cheap (credits / image)
            self::FLUX_SCHNELL,
            self::BLACK_FOREST_LABS_FLUX_1_SCHNELL => 3.0,
            self::STABLE_DIFFUSION_V_1_6           => 10.0,
            self::GPT_IMAGE_1                      => 11.0,
            self::DALL_E_2                         => 18.0,
            self::CORE,
            self::PEBBLELY,
            self::MIDJOURNEY => 15.0,
            self::SD_3,
            self::SD_3_TURBO,
            self::SD_3_MEDIUM => 35.0,
            self::DALL_E_3,
            self::IMAGEN_4,
            self::NANO_BANANA,
            self::GROK_IMAGINE_IMAGE,
            self::KLING_IMAGE,
            self::SEEDREAM_4,
            self::GPT_IMAGE_2,
            self::IDEOGRAM,
            self::FLUX_REALISM,
            self::FLUX_2_FLEX,
            self::SD_3_LARGE,
            self::SD_3_LARGE_TURBO,
            self::NOVITA,
            self::CLIPDROP,
            self::AWS_BEDROCK,
            self::FREEPIK => 40.0,
            self::FLUX_PRO,
            self::FLUX_PRO_1_1,
            self::FLUX_PRO_KONTEXT,
            self::FLUX_PRO_KONTEXT_TEXT_TO_IMAGE => 50.0,
            self::STABLE_DIFFUSION_XL_1024_V_1_0,
            self::SD_3_5_LARGE,
            self::SD_3_5_LARGE_TURBO,
            self::SD_3_5_MEDIUM => 65.0,
            self::ULTRA,
            self::GPT_IMAGE_1_5,
            self::NANO_BANANA_2,
            self::NANO_BANANA_EDIT,
            self::FLUX_PRO_KONTEXT_MAX_MULTI,
            self::FLUX_2_FLEX_EDIT => 80.0,
            self::NANO_BANANA_PRO  => 150.0,
            self::NANO_BANANA_PRO_EDIT,
            self::NANO_BANANA_2_EDIT,
            self::GROK_IMAGINE_IMAGE_EDIT,
            self::SEEDREAM_4_EDIT => 200.0,

            // Video (credits / second)
            self::VEO_3_1_LITE_TEXT_TO_VIDEO,
            self::VEO_3_1_LITE_IMAGE_TO_VIDEO,
            self::VEO_3_1_LITE_FIRST_LAST_FRAME_TO_VIDEO => 50.0,
            self::KLING_2_5_TURBO_PRO_TTV,
            self::KLING_2_5_TURBO_PRO_ITV,
            self::KLING_2_5_TURBO_STANDARD_ITV => 70.0,
            self::SORA_2,
            self::KLING_3_PRO_TTV,
            self::KLING_3_PRO_ITV,
            self::KLING_3_STANDARD_TTV,
            self::KLING_3_STANDARD_ITV => 100.0,
            self::VEO_3_1_TEXT_TO_VIDEO_FAST,
            self::VEO_3_1_IMAGE_TO_VIDEO_FAST,
            self::VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO_FAST,
            self::KLING,
            self::KLING_2_1,
            self::KLING_VIDEO,
            self::KLING_2_6_PRO_TTV,
            self::KLING_2_6_PRO_ITV,
            self::KLING_2_6_PRO_MOTION_CONTROL,
            self::KLING_2_6_STANDARD_MOTION_CONTROL,
            self::LUMA_DREAM_MACHINE,
            self::HAIPER,
            self::IMAGE_TO_VIDEO,
            self::VEO_2,
            self::VIDEO_UPSCALER,
            self::COGVIDEOX_5B,
            self::ANIMATEDIFF_V2V,
            self::FAST_ANIMATEDIFF_TURBO,
            self::SYNTHESIA,
            self::GROK_IMAGINE_VIDEO_TTV,
            self::GROK_IMAGINE_VIDEO_ITV,
            self::AD_MARKETING_VIDEO,
            self::AD_MARKETING_VIDEO_TOPVIEW,
            self::AI_CLIP_KLAP,
            self::AI_CLIP_VIZARD,
            self::VEED,
            self::VEED_FABRIC,
            self::VIDEO_BACKGROUND_REMOVAL => 150.0,
            self::VEO_3,
            self::VEO_3_FAST,
            self::VEO_3_1_TEXT_TO_VIDEO,
            self::VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO,
            self::VEO_3_1_IMAGE_TO_VIDEO,
            self::VEO_3_1_REFERENCE_TO_VIDEO => 200.0,
            self::SEEDANCE_2_FAST_TTV,
            self::SEEDANCE_2_FAST_ITV,
            self::SEEDANCE_2_FAST_RTV => 242.0,
            self::SORA_2_PRO          => 300.0,
            self::SEEDANCE_2_TTV,
            self::SEEDANCE_2_ITV,
            self::SEEDANCE_2_RTV => 400.0,
            self::GEMINI_OMNI_FLASH_TTV,
            self::GEMINI_OMNI_FLASH_ITV,
            self::GEMINI_OMNI_FLASH_RTV => 200.0,

            // TTS (credits / 1K chars)
            self::GOOGLE    => 4.0,
            self::Speechify => 10.0,
            self::TTS_1     => 15.0,
            self::AZURE     => 16.0,
            self::TTS_1_HD  => 30.0,
            self::ELEVENLABS,
            self::ELEVENLABS_V3 => 100.0,

            // Real-time audio / STT
            self::WHISPER_1 => 0.1,
            self::ISOLATOR  => 0.5,
            self::ELEVENLABS_VOICE_CHATBOT,
            self::GPT_4_O_REALTIME_PREVIEW,
            self::GPT_REALTIME,
            self::MINIMAX => 13.0,
            self::HEYGEN  => 17.0,

            // Embedding
            self::TEXT_EMBEDDING_ADA_002,
            self::TEXT_EMBEDDING_3_SMALL,
            self::TEXT_EMBEDDING_3_LARGE,
            self::VOYAGE_2,
            self::VOYAGE_LARGE_2,
            self::VOYAGE_CODE_2,
            self::GEMINI_EMBEDDING_EXP,
            self::GEMINI_TEXT_EMBEDDING_004 => 0.01,

            // Music
            self::MUSIC_01,
            self::ELEVENLABS_AI_MUSIC => 10.0,

            // Video dubbing
            self::VIDEO_DUBBING => 1.0,

            // AI captions
            self::AI_CAPTIONS => 5.0,

            // Search / utility
            self::SERPER,
            self::UNSPLASH,
            self::PEXELS,
            self::PIXABAY         => 1.0,
            self::PERPLEXITY      => 5.0,
            self::PLAGIARISMCHECK => 20.0,
            self::LYRIA_3_CLIP    => 40.0,
            self::LYRIA_3_PRO     => 80.0,
            self::GAMMA_AI        => 200.0,

            // OpenRouter
            self::ANTHROPIC_CLAUDE_3_5_HAIKU_20241022,
            self::ANTHROPIC_CLAUDE_3_5_HAIKU_20241022_SELF_MODERATED,
            self::ANTHROPIC_CLAUDE_3_5_HAIKU,
            self::ANTHROPIC_CLAUDE_3_5_HAIKU_SELF_MODERATED,
            self::LUMIMAID_V02_70B,
            self::MAGNUM_V4_72B,
            self::XAI_GROK_BETA,
            self::MINISTRAL_8B,
            self::MINISTRAL_3B,
            self::QWEN25_7B_INSTRUCT,
            self::NVIDIA_LLAMA_31_NEMOTRON_70B_INSTRUCT,
            self::INFLECTION_3_PI,
            self::INFLECTION_3_PRODUCTIVITY,
            self::LIQUID_LFM_40B_MOE_FREE,
            self::LIQUID_LFM_40B_MOB,
            self::ROCINANTE_12B,
            self::EVA_QWEN25_14B,
            self::MAGNUM_V2_72B,
            self::META_LLAMA32_3B_INSTRUCT_FREE,
            self::META_LLAMA32_1B_INSTRUCT_FREE,
            self::META_LLAMA32_3B_INSTRUCT,
            self::META_LLAMA32_1B_INSTRUCT,
            self::PERPLEXITY_LLAMA_31_SONAR_405B_ONLINE,
            self::PERPLEXITY_LLAMA_31_SONAR_70B_ONLINE,
            self::PERPLEXITY_LLAMA_31_SONAR_70B,
            self::PERPLEXITY_LLAMA_31_SONAR_8B_ONLINE,
            self::PERPLEXITY_LLAMA_31_SONAR_8B => 1.0,

            // Azure OpenAI
            self::AZURE_OPENAI => 1.0,

            // Default: derive from unitPrice
            default => max(0.1, round($this->unitPrice() / 0.00001, 2)),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::BLACK_FOREST_LABS_FLUX_1_SCHNELL => __('Black Forest Labs Flux 1 Schnell'),
            self::IMAGE_TO_VIDEO                   => __('AI Video'),
            self::STABLE_DIFFUSION_XL_1024_V_1_0   => __('Stable Diffusion XL 1.0'),
            self::STABLE_DIFFUSION_V_1_6           => __('Stable Diffusion 1.6'),
            self::SD_3                             => __('Stable Diffusion 3'),
            self::SD_3_TURBO                       => __('Stable Diffusion 3 turbo'),
            self::SD_3_MEDIUM                      => __('Stable Diffusion 3 Medium'),
            self::SD_3_LARGE                       => __('Stable Diffusion 3 Large'),
            self::SD_3_LARGE_TURBO                 => __('Stable Diffusion 3 Large Turbo'),
            self::SD_3_5_LARGE                     => __('Stable Diffusion 3.5 Large'),
            self::SD_3_5_LARGE_TURBO               => __('Stable Diffusion 3.5 Large Turbo'),
            self::SD_3_5_MEDIUM                    => __('Stable Diffusion 3.5 Medium'),
            self::CORE                             => __('Core'),
            self::ULTRA                            => __('Ultra'),
            self::AWS_BEDROCK                      => __('AWS Bedrock'),
            // OpenAI
            self::DAVINCI                     => __('Davinci 002 (Expensive &amp; Capable)'),
            self::TEXT_DAVINCI_003            => __('Davinci 003 (Expensive &amp; Capable)'),
            self::GPT_3_5_TURBO               => __('GPT 3.5-turbo (Most Expensive & Fastest & Most Capable)'),
            self::GPT_3_5_TURBO_0125          => __('GTP 3.5-turbo-0125 (Updated Knowleddge cutoff of Sep 2021, 16k)'),
            self::GPT_3_5_TURBO_1106          => __('GTP 3.5-turbo-1106 (Updated Knowleddge cutoff of Nov 2021, 16k)'),
            self::GPT_4_TURBO                 => __('GPT-4 Turbo (Most Expensive & Fastest & Most Capable)'),
            self::GPT_4                       => __('GPT-4 (Most Expensive & Fastest & Most Capable)'),
            self::GPT_4_1106_PREVIEW          => __('GPT-4-1106 Turbo (Updated Knowleddge cutoff of April 2023, 128k)'),
            self::GPT_4_0125_PREVIEW          => __('GPT-4-0125 Turbo (Updated Knowleddge cutoff of Dec 2023, 128k)'),
            self::TEXT_EMBEDDING_ADA_002      => __('Text Embedding Ada (Expensive &amp; Capable)'),
            self::TEXT_EMBEDDING_3_SMALL      => __('Text Embedding Small'),
            self::TEXT_EMBEDDING_3_LARGE      => __('Text Embedding Large'),
            self::WHISPER_1                   => __('WHISPER 1 The latest text to speech model, optimized for speed.'),
            self::DALL_E_2                    => __('DALL-E 2 The previous DALL·E model released in Nov 2022.'),
            self::DALL_E_3                    => __('DALL-E 3 The latest DALL·E model released in Nov 2023.'),
            self::GPT_IMAGE_1                 => __('GPT-IMAGE-1 The latest image model released in Nov 2025.'),
            self::GPT_IMAGE_1_5               => __('GPT-IMAGE-1.5 The latest image model released in Dec 2025.'),
            self::GPT_IMAGE_2                 => __('GPT-IMAGE-2 The latest image model with flexible resolutions and high-fidelity inputs.'),
            self::TTS_1                       => __('TTS 1 The latest text to speech model, optimized for speed.'),
            self::TTS_1_HD                    => __('TTS 1 HD The latest text to speech model, optimized for quality.'),
            self::GPT_4_O                     => __('GPT-4o Most advanced works for Vision, multimodal flagship model that’s cheaper and faster than GPT-4 Turbo.  (Updated Knowleddge cutoff of Oct 2023, 128k)'),
            self::GPT_4_O_MINI                => __('GPT-4o mini Our affordable and intelligent small model for fast, lightweight tasks. GPT-4o mini is cheaper and more capable than GPT-3.5 Turbo.'),
            self::GPT_4_O_SEARCH_PREVIEW      => __('GPT-4o Search Preview'),
            self::GPT_4_O_MINI_SEARCH_PREVIEW => __('GPT-4o Mini Search Preview'),
            self::GPT_4_O1_PREVIEW            => __('GPT o1-preview (Updated Knowledge cutoff of Dec 2023, 128k)'),
            self::GPT_4_O1_MINI               => __('GPT o1-mini (Updated Knowledge cutoff of Dec 2023, 128k)'),
            self::GPT_O_1                     => __('GPT o1 (Updated Knowledge cutoff of Dec 2023, 128k)'),
            self::GPT_O_03_mini               => __('GPT o3-mini (Updated Knowledge cutoff of October 2023, 200k)'),
            self::GPT_4_O_REALTIME_PREVIEW    => __('GPT-4o Realtime Preview (Updated Knowledge cutoff of December 2024, 128k)'),
            self::GPT_REALTIME                => __('GPT Realtime (GA, 32k)'),
            self::GPT_4_1                     => __('GPT-4.1 (Jun 01, 2024 knowledge cutoff, 32k max output tokens.)'),
            self::GPT_4_1_MINI                => __('GPT-4.1 Mini (Jun 01, 2024 knowledge cutoff, 32k max output tokens.)'),
            self::GPT_4_1_NANO                => __('GPT-4.1 Nano (Jun 01, 2024 knowledge cutoff, 32k max output tokens.)'),
            self::GPT_O_4_MINI                => __('GPT o4-mini (Jun 01, 2024 knowledge cutoff, 100k max output tokens.)'),
            self::GPT_O_3                     => __('GPT o3 (Jun 01, 2024 knowledge cutoff, 100k max output tokens.)'),
            self::GPT_5                       => __('GPT-5 (Oct 01, 2024 knowledge cutoff, 128k max output tokens.)'),
            self::GPT_5_MINI                  => __('GPT-5 Mini (May 31, 2024 knowledge cutoff, 128k max output tokens.)'),
            self::GPT_5_NANO                  => __('GPT-5 Nano (May 31, 2024 knowledge cutoff, 128k max output tokens.)'),
            self::GPT_5_CHAT                  => __('GPT-5 Chat (Sep 30, 2024 knowledge cutoff, 128k max output tokens.)'),
            self::GPT_5_PRO                   => __('GPT-5 Pro (Sep 30, 2024 knowledge cutoff, 272k max output tokens.)'),
            self::GPT_5_1                     => __('GPT-5.1 (Sep 30, 2024 knowledge cutoff, 128k max output tokens.)'),
            self::GPT_5_1_CHAT                => __('GPT-5.1 Chat (Sep 30, 2024 knowledge cutoff, 16k max output tokens.)'),
            self::GPT_5_2                     => __('GPT-5.2 (Aug 31, 2025 knowledge cutoff, 128k max output tokens.)'),
            self::GPT_5_2_PRO                 => __('GPT-5.2 Pro (Aug 31, 2025 knowledge cutoff, 128k max output tokens.)'),
            self::GPT_5_3_CHAT                => __('GPT-5.3 Instant (Aug 31, 2025 knowledge cutoff, 16k max output tokens.)'),
            self::GPT_5_4                     => __('GPT-5.4 (Aug 31, 2025 knowledge cutoff, 128k max output tokens, 1.05M context.)'),
            self::GPT_5_4_MINI                => __('GPT-5.4 Mini (Aug 31, 2025 knowledge cutoff, 128k max output tokens, 1.05M context.)'),
            self::GPT_5_4_NANO                => __('GPT-5.4 Nano (Aug 31, 2025 knowledge cutoff, 128k max output tokens, 1.05M context.)'),
            self::GPT_5_5                     => __('GPT-5.5 (Dec 01, 2025 knowledge cutoff, 128k max output tokens, 1.05M context.)'),
            self::GPT_5_6_SOL                 => __('GPT-5.6 Sol (Dec 01, 2025 knowledge cutoff, 128k max output tokens, 1.05M context.)'),
            self::GPT_5_6_TERRA               => __('GPT-5.6 Terra (Dec 01, 2025 knowledge cutoff, 128k max output tokens, 1.05M context.)'),
            self::GPT_5_6_LUNA                => __('GPT-5.6 Luna (Dec 01, 2025 knowledge cutoff, 128k max output tokens, 1.05M context.)'),

            self::O3_DEEP_RESEARCH             => __('o3 Deep Research (Multi-step web research with detailed reports)'),
            self::O4_MINI_DEEP_RESEARCH        => __('o4-mini Deep Research (Fast multi-step web research with reports)'),

            self::SORA_2                      => __('Sora 2 (Flagship video generation with synced audio)'),
            self::SORA_2_PRO                  => __('Sora 2 Pro (Most advanced synced-audio video generation)'),
            // Anthropic
            self::CLAUDE_FABLE_5       => __('Claude Fable 5'),
            self::CLAUDE_SONNET_5      => __('Claude Sonnet 5'),
            self::CLAUDE_SONNET_4_5    => __('Claude Sonnet 4.5'),
            self::CLAUDE_SONNET_4_6    => __('Claude Sonnet 4.6'),
            self::CLAUDE_OPUS_4_8      => __('Claude Opus 4.8'),
            self::CLAUDE_OPUS_4_7      => __('Claude Opus 4.7'),
            self::CLAUDE_OPUS_4_6      => __('Claude Opus 4.6'),
            self::CLAUDE_OPUS_4_5      => __('Claude Opus 4.5'),
            self::CLAUDE_OPUS_4_1      => __('Claude Opus 4.1'),
            self::CLAUDE_OPUS_4        => __('Claude Opus 4'),
            self::CLAUDE_SONNET_4      => __('Claude Sonnet 4'),
            self::CLAUDE_3_7_SONNET    => __('Claude 3.7 Sonnet'),
            self::CLAUDE_3_5_HAIKU     => __('Claude 3.5 Haiku'),
            self::CLAUDE_3_5_SONNET_V2 => __('Claude 3.5 Sonnet V2'),
            self::CLAUDE_3_5_SONNET    => __('Claude 3.5 Sonnet'),
            self::CLAUDE_3_SONNET      => __('Claude 3 Sonnet'),
            self::CLAUDE_3_OPUS        => __('Claude 3 Opus'),
            self::CLAUDE_3_HAIKU       => __('Claude 3 Haiku'),
            self::CLAUDE_2_1           => __('Claude 2.1'),
            self::CLAUDE_2_0           => __('Claude 2'),
            self::VOYAGE_2             => __('Voyage 2'),
            self::VOYAGE_LARGE_2       => __('Voyage Large 2'),
            self::VOYAGE_CODE_2        => __('Voyage Code 2'),
            self::CLIPDROP             => __('Clipdrop for Photo Studio'),
            self::NOVITA               => __('Novita for Photo Studio'),
            self::FREEPIK              => __('Novita for Image Editor'),
            self::PLAGIARISMCHECK      => __('Plagiarism Check'),
            self::SYNTHESIA            => __('Synthesia'),
            self::HEYGEN               => __('Heygen'),
            self::PEBBLELY             => __('Pebblely'),
            // Gemini
            self::GEMINI_3_PRO_PREVIEW             => __('Gemini 3 Pro Preview The most intelligent model family to date, built on a foundation of state-of-the-art reasoning.'),
            self::GEMINI_3_1_PRO_PREVIEW           => __('Gemini 3.1 Pro Preview Refined performance and reliability, thinking, multimodal, function calling, structured outputs.'),
            self::GEMINI_2_5_FLASH_PREVIEW_05_20   => __('Gemini 2.5 Flash Preview 05-20 Adaptive thinking, cost efficiency'),
            self::GEMINI_2_5_PRO                   => __('Gemini 2.5 Pro Preview Enhanced thinking and reasoning, multimodal understanding, advanced coding, and more'),
            self::GEMINI_DEEP_RESEARCH             => __('Gemini Deep Research (Multi-step web research with detailed reports)'),
            self::GEMINI_2_0_FLASH                 => __('Gemini 2.0 Flash Next generation features, speed, thinking, realtime streaming, and multimodal generation'),
            self::GEMINI_2_0_FLASH_LITE            => __('Gemini 2.0 Flash-Lite Cost efficiency and low latency'),
            self::GEMINI_1_5_PRO                   => __('Gemini 1.5 Pro Complex reasoning tasks requiring more intelligence'),
            self::GEMINI_1_5_FLASH                 => __('Gemini 1.5 Flash Fast and versatile performance across a diverse variety of tasks'),
            self::GEMINI_3_FLASH                   => __('Gemini 3 Flash Advanced reasoning, coding, and multimodal capabilities with high speed'),
            self::GEMINI_3_1_FLASH_LIVE_PREVIEW    => __('Gemini 3.1 Flash Live Preview Low-latency audio-to-audio model for real-time dialogue'),
            self::GEMINI_EMBEDDING_EXP             => __('Gemini Embedding Measuring the relatedness of text strings'),
            self::GEMINI_TEXT_EMBEDDING_004        => __('Gemini Text Embeding 004'),
            // Deepseek
            self::DEEPSEEK_CHAT     => __('Deepseek Chat'),
            self::DEEPSEEK_REASONER => __('Deepseek DeepSeek-R1'),
            // Unsplash
            self::UNSPLASH => __('Unsplash for AI Article Wizard'),
            // Pexels
            self::PEXELS => __('Pexels for AI Article Wizard'),
            // Pixabay
            self::PIXABAY => __('Pixabay for AI Article Wizard'),
            // Elevenlabs
            self::ELEVENLABS               => __('Elevenlabs for TTS'),
            self::ELEVENLABS_V3            => __('ElevenLabs v3 for TTS'),
            self::ELEVENLABS_VOICE_CHATBOT => __('Elevenlabs Voice Chatbots'),
            self::ISOLATOR                 => __('Voice Isolator (1 word = 5 used characters of elevenlabs) X 1 token'),
            self::ELEVENLABS_AI_MUSIC      => __('Elevenlabs for AI Music Pro'),
            // Lyria (Google Gemini)
            self::LYRIA_3_CLIP             => __('Google Lyria 3 Clip for AI Music Pro'),
            self::LYRIA_3_PRO              => __('Google Lyria 3 Pro for AI Music Pro'),
            // Google
            self::GOOGLE => __('Google for TTS'),
            // Azure
            self::AZURE        => __('Azure for TTS'),
            self::AZURE_OPENAI => __('Azure OpenAI Model'),
            // Speechify
            self::Speechify => __('Speechify for TTS'),
            // Serper
            self::SERPER => __('Serper for Realtime Data'),
            // Perplexity
            self::PERPLEXITY => __('Perplexity for Realtime Data'),
            // Midjourney
            self::MIDJOURNEY => __('Midjourney'),
            // X AI
            self::GROK_2_1212                 => __('Grok 2 1212'),
            self::GROK_2_VISION_1212          => __('Grok 2 Vision 1212'),
            self::GROK_3                      => __('Grok 3'),
            self::GROK_3_MINI                 => __('Grok 3 Mini'),
            self::GROK_3_FAST                 => __('Grok 3 Fast'),
            self::GROK_3_MINI_FAST            => __('Grok 3 Mini Fast'),
            self::GROK_4                      => __('Grok 4'),
            self::GROK_4_FAST                 => __('Grok 4 Fast'),
            self::GROK_4_1_FAST_REASONING     => __('Grok 4.1 Fast Reasoning'),
            self::GROK_4_1_FAST_NON_REASONING => __('Grok 4.1 Fast Non-Reasoning'),
            // Gamma AI
            self::GAMMA_AI => __('Gamma AI'),
            // FAL AI
            self::VEO_2                                  => __('Veo 2'),
            self::IMAGEN_4                               => __('Google Imagen 4'),
            self::VEED                                   => __('Veed'),
            self::VEED_FABRIC                            => __('Veed Fabric 1.0'),
            self::VEO_3                                  => __('Veo 3'),
            self::VEO_3_1_TEXT_TO_VIDEO                  => __('Veo 3.1 Text To Video'),
            self::VEO_3_1_TEXT_TO_VIDEO_FAST             => __('Fast Veo 3.1 Text To Video'),
            self::VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO      => __('Veo 3.1 First Last Frame To Video'),
            self::VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO_FAST => __('Fast Veo 3.1 First Last Frame To Video'),
            self::VEO_3_1_IMAGE_TO_VIDEO                 => __('Veo 3.1 Image To Video'),
            self::VEO_3_1_IMAGE_TO_VIDEO_FAST            => __('Fast Veo 3.1 Image To Video'),
            self::VEO_3_1_REFERENCE_TO_VIDEO             => __('Veo 3.1 Reference To Video'),
            self::VEO_3_1_LITE_TEXT_TO_VIDEO             => __('Veo 3.1 Lite Text To Video'),
            self::VEO_3_1_LITE_IMAGE_TO_VIDEO            => __('Veo 3.1 Lite Image To Video'),
            self::VEO_3_1_LITE_FIRST_LAST_FRAME_TO_VIDEO => __('Veo 3.1 Lite First Last Frame To Video'),
            self::VEO_3_FAST                             => __('Fast Veo 3'),
            self::KLING_VIDEO                            => __('Kling Video'),
            self::FLUX_PRO                               => __('Flux Pro'),
            self::NANO_BANANA                            => __('Nano Banana'),
            self::NANO_BANANA_EDIT                       => __('Nano Banana Edit'),
            self::NANO_BANANA_PRO                        => __('Nano Banana Pro'),
            self::NANO_BANANA_PRO_EDIT                   => __('Nano Banana Pro Edit'),
            self::NANO_BANANA_2                          => __('Nano Banana 2'),
            self::NANO_BANANA_2_EDIT                     => __('Nano Banana 2 Edit'),
            self::GROK_IMAGINE_IMAGE                     => __('Grok Imagine Image'),
            self::GROK_IMAGINE_IMAGE_EDIT                => __('Grok Imagine Image Edit'),
            self::GROK_IMAGINE_VIDEO_TTV                 => __('Grok Imagine Video Text-to-Video'),
            self::GROK_IMAGINE_VIDEO_ITV                 => __('Grok Imagine Video Image-to-Video'),
            self::SEEDREAM_4                             => __('SeeDream v4 '),
            self::SEEDREAM_4_EDIT                        => __('SeeDream v4 Edit'),
            self::SEEDANCE_2_TTV                         => __('Seedance 2.0 Text-to-Video'),
            self::SEEDANCE_2_ITV                         => __('Seedance 2.0 Image-to-Video'),
            self::SEEDANCE_2_RTV                         => __('Seedance 2.0 Reference-to-Video'),
            self::SEEDANCE_2_FAST_TTV                    => __('Seedance 2.0 Fast Text-to-Video'),
            self::SEEDANCE_2_FAST_ITV                    => __('Seedance 2.0 Fast Image-to-Video'),
            self::SEEDANCE_2_FAST_RTV                    => __('Seedance 2.0 Fast Reference-to-Video'),
            self::GEMINI_OMNI_FLASH_TTV                  => __('Gemini Omni Flash Text-to-Video'),
            self::GEMINI_OMNI_FLASH_ITV                  => __('Gemini Omni Flash Image-to-Video'),
            self::GEMINI_OMNI_FLASH_RTV                  => __('Gemini Omni Flash Reference-to-Video'),
            self::FLUX_PRO_KONTEXT_MAX_MULTI             => __('Flux Pro Kontext Max Multi'),
            self::FLUX_PRO_KONTEXT_TEXT_TO_IMAGE         => __('Flux Pro Kontext Max Text to Image'),
            self::FLUX_PRO_KONTEXT                       => __('Flux Pro Kontext Max'),
            self::IDEOGRAM                               => __('Ideogram V2'),
            self::FLUX_PRO_1_1                           => __('Flux Pro 1.1'),
            self::FLUX_REALISM                           => __('Flux Realism'),
            self::FLUX_SCHNELL                           => __('Flux Schnell'),
            self::FLUX_2_FLEX                            => __('Flux 2 Flex'),
            self::FLUX_2_FLEX_EDIT                       => __('Flux 2 Flex Edit'),
            self::KLING                                  => __('Kling 1.0'),
            self::KLING_2_1                              => __('Kling 2.1'),
            self::KLING_2_5_TURBO_PRO_TTV                => __('Kling 2.5 Turbo Pro Text to Video'),
            self::KLING_2_5_TURBO_PRO_ITV                => __('Kling 2.5 Turbo Pro Image to Video'),
            self::KLING_2_5_TURBO_STANDARD_ITV           => __('Kling 2.5 Turbo Standard Image to Video'),
            self::KLING_2_6_PRO_TTV                      => __('Kling Video v2.6 Pro Text to Video'),
            self::KLING_2_6_PRO_ITV                      => __('Kling Video v2.6 Pro Image to Video'),
            self::KLING_2_6_PRO_MOTION_CONTROL           => __('Kling Video v2.6 Pro Motion Control'),
            self::KLING_2_6_STANDARD_MOTION_CONTROL      => __('Kling Video v2.6 Standard Motion Control'),
            self::KLING_3_PRO_TTV                        => __('Kling Video v3 Pro Text to Video'),
            self::KLING_3_PRO_ITV                        => __('Kling Video v3 Pro Image to Video'),
            self::KLING_3_STANDARD_TTV                   => __('Kling Video v3 Standard Text to Video'),
            self::KLING_3_STANDARD_ITV                   => __('Kling Video v3 Standard Image to Video'),
            self::KLING_IMAGE                            => __('Kling Image to Video'),
            self::LUMA_DREAM_MACHINE                     => __('Luma Dream Machine'),
            self::HAIPER                                 => __('Haiper'),
            self::MINIMAX                                => __('Minimax'),
            self::VIDEO_UPSCALER                         => __('Video Upscaler'),
            self::COGVIDEOX_5B                           => __('Cogvideox 5B'),
            self::ANIMATEDIFF_V2V                        => __('Animatediff V2V'),
            self::FAST_ANIMATEDIFF_TURBO                 => __('Fast Animatediff Turbo'),
            self::VIDEO_BACKGROUND_REMOVAL               => __('Video Background Removal'),

            self::AD_MARKETING_VIDEO         => __('Ad Marketing Video'),
            self::AD_MARKETING_VIDEO_TOPVIEW => __('Topview Ad Video'),
            self::AI_CLIP_VIZARD             => __('Vizard AI Clip'),
            self::AI_CLIP_KLAP               => __('Klap AI Clip'),
            // AI/ML api minimax engine
            self::MUSIC_01 => __('Music 01'),
            // Open Router
            self::ANTHROPIC_CLAUDE_3_5_HAIKU_20241022                 => __('Anthropic: Claude 3.5 Haiku (2024-10-22)'),
            self::ANTHROPIC_CLAUDE_3_5_HAIKU_20241022_SELF_MODERATED  => __('Anthropic: Claude 3.5 Haiku (2024-10-22) (self-moderated)'),
            self::ANTHROPIC_CLAUDE_3_5_HAIKU                          => __('Anthropic: Claude 3.5 Haiku'),
            self::ANTHROPIC_CLAUDE_3_5_HAIKU_SELF_MODERATED           => __('Anthropic: Claude 3.5 Haiku (self-moderated)'),
            self::LUMIMAID_V02_70B                                    => __('Lumimaid v0.2 70B'),
            self::MAGNUM_V4_72B                                       => __('Magnum v4 72B'),
            self::XAI_GROK_BETA                                       => __('xAI: Grok Beta'),
            self::MINISTRAL_8B                                        => __('Ministral 8B'),
            self::MINISTRAL_3B                                        => __('Ministral 3B'),
            self::QWEN25_7B_INSTRUCT                                  => __('Qwen2.5 7B Instruct'),
            self::NVIDIA_LLAMA_31_NEMOTRON_70B_INSTRUCT               => __('NVIDIA: Llama 3.1 Nemotron 70B Instruct'),
            self::INFLECTION_3_PI                                     => __('Inflection: Inflection 3 Pi'),
            self::INFLECTION_3_PRODUCTIVITY                           => __('Inflection: Inflection 3 Productivity'),
            self::LIQUID_LFM_40B_MOE_FREE                             => __('Liquid: LFM 40B MoE (free)'),
            self::LIQUID_LFM_40B_MOB                                  => __('Liquid: LFM 40B MoE'),
            self::ROCINANTE_12B                                       => __('Rocinante 12B'),
            self::EVA_QWEN25_14B                                      => __('EVA Qwen2.5 14B'),
            self::MAGNUM_V2_72B                                       => __('Magnum v2 72B'),
            self::META_LLAMA32_3B_INSTRUCT_FREE                       => __('Meta: Llama 3.2 3B Instruct (free)'),
            self::META_LLAMA32_1B_INSTRUCT_FREE                       => __('Meta: Llama 3.2 1B Instruct (free)'),
            self::META_LLAMA32_3B_INSTRUCT                            => __('Meta: Llama 3.2 3B Instruct'),
            self::META_LLAMA32_1B_INSTRUCT                            => __('Meta: Llama 3.2 1B Instruct'),
            self::PERPLEXITY_LLAMA_31_SONAR_405B_ONLINE               => __('Perplexity: Llama 3.1 Sonar 405B Online'),
            self::PERPLEXITY_LLAMA_31_SONAR_70B_ONLINE                => __('Perplexity: Llama 3.1 Sonar 70B Online'),
            self::PERPLEXITY_LLAMA_31_SONAR_70B                       => __('Perplexity: Llama 3.1 Sonar 70B'),
            self::PERPLEXITY_LLAMA_31_SONAR_8B_ONLINE                 => __('Perplexity: Llama 3.1 Sonar 8B Online'),
            self::PERPLEXITY_LLAMA_31_SONAR_8B                        => __('Perplexity: Llama 3.1 Sonar 8B'),
            self::VIDEO_DUBBING                                       => __('Video Dubbing'),
            self::AI_CAPTIONS                                         => __('AI Captions'),
        };
    }

    public function isV2BetaSdEntity(): bool
    {
        return match ($this) {
            self::SD_3,
            self::SD_3_TURBO,
            self::SD_3_MEDIUM,
            self::SD_3_LARGE,
            self::SD_3_LARGE_TURBO,
            self::SD_3_5_LARGE,
            self::SD_3_5_LARGE_TURBO,
            self::SD_3_5_MEDIUM,
            self::CORE,
            self::ULTRA => true,
            default     => false,
        };
    }

    public function engine(): EngineEnum
    {
        return match ($this) {
            self::IMAGE_TO_VIDEO,
            self::STABLE_DIFFUSION_XL_1024_V_1_0,
            self::STABLE_DIFFUSION_V_1_6,
            self::SD_3,
            self::SD_3_TURBO,
            self::SD_3_MEDIUM,
            self::SD_3_LARGE,
            self::SD_3_LARGE_TURBO,
            self::SD_3_5_LARGE,
            self::SD_3_5_LARGE_TURBO,
            self::SD_3_5_MEDIUM,
            self::CORE,
            self::ULTRA,
            self::AWS_BEDROCK => EngineEnum::STABLE_DIFFUSION,
            // Together
            self::BLACK_FOREST_LABS_FLUX_1_SCHNELL => EngineEnum::TOGETHER,
            // OpenAI
            self::DAVINCI,
            self::TEXT_DAVINCI_003,
            self::GPT_3_5_TURBO,
            self::GPT_3_5_TURBO_0125,
            self::GPT_3_5_TURBO_1106,
            self::GPT_4_TURBO,
            self::GPT_4,
            self::GPT_4_1106_PREVIEW,
            self::GPT_4_0125_PREVIEW,
            self::TEXT_EMBEDDING_ADA_002,
            self::TEXT_EMBEDDING_3_SMALL,
            self::TEXT_EMBEDDING_3_LARGE,
            self::WHISPER_1,
            self::DALL_E_2,
            self::DALL_E_3,
            self::GPT_IMAGE_1,
            self::GPT_IMAGE_1_5,
            self::GPT_IMAGE_2,
            self::TTS_1,
            self::TTS_1_HD,
            self::GPT_4_O,
            self::GPT_4_O_MINI,
            self::GPT_4_O_SEARCH_PREVIEW,
            self::GPT_4_O_MINI_SEARCH_PREVIEW,
            self::GPT_4_O1_PREVIEW,
            self::GPT_4_O1_MINI,
            self::GPT_O_1,
            self::GPT_O_03_mini,
            self::GPT_4_O_REALTIME_PREVIEW,
            self::GPT_REALTIME,
            self::GPT_4_1,
            self::GPT_4_1_MINI,
            self::GPT_4_1_NANO,
            self::GPT_O_3,
            self::GPT_5,
            self::GPT_5_MINI,
            self::GPT_5_NANO,
            self::GPT_5_CHAT,
            self::GPT_5_PRO,
            self::GPT_5_1,
            self::GPT_5_1_CHAT,
            self::GPT_5_2,
            self::GPT_5_2_PRO,
            self::GPT_5_3_CHAT,
            self::GPT_5_4,
            self::GPT_5_4_MINI,
            self::GPT_5_4_NANO,
            self::GPT_5_5,
            self::GPT_5_6_SOL,
            self::GPT_5_6_TERRA,
            self::GPT_5_6_LUNA,
            self::O3_DEEP_RESEARCH,
            self::O4_MINI_DEEP_RESEARCH,
            self::SORA_2,
            self::SORA_2_PRO,
            self::GPT_O_4_MINI => EngineEnum::OPEN_AI,
            // Anthropic
            self::CLAUDE_FABLE_5,
            self::CLAUDE_SONNET_5,
            self::CLAUDE_SONNET_4_5,
            self::CLAUDE_SONNET_4_6,
            self::CLAUDE_OPUS_4_8,
            self::CLAUDE_OPUS_4_7,
            self::CLAUDE_OPUS_4_6,
            self::CLAUDE_OPUS_4_5,
            self::CLAUDE_OPUS_4_1,
            self::CLAUDE_SONNET_4,
            self::CLAUDE_OPUS_4,
            self::CLAUDE_3_7_SONNET,
            self::CLAUDE_3_5_HAIKU,
            self::CLAUDE_3_5_SONNET_V2,
            self::CLAUDE_3_5_SONNET,
            self::CLAUDE_3_SONNET,
            self::CLAUDE_3_OPUS,
            self::CLAUDE_3_HAIKU,
            self::CLAUDE_2_1,
            self::CLAUDE_2_0,
            self::VOYAGE_2,
            self::VOYAGE_LARGE_2,
            self::VOYAGE_CODE_2 => EngineEnum::ANTHROPIC,
            // Deepseek
            self::DEEPSEEK_CHAT, self::DEEPSEEK_REASONER => EngineEnum::DEEP_SEEK,
            // Clipdrop
            self::CLIPDROP => EngineEnum::CLIPDROP,
            // Novita
            self::NOVITA => EngineEnum::NOVITA,
            // Freepik
            self::FREEPIK => EngineEnum::FREEPIK,
            // Plagiarism Check
            self::PLAGIARISMCHECK => EngineEnum::PLAGIARISM_CHECK,
            // SYNTHESIA
            self::SYNTHESIA => EngineEnum::SYNTHESIA,
            // HEYGEN
            self::HEYGEN        => EngineEnum::HEYGEN,
            self::VIDEO_DUBBING => EngineEnum::HEYGEN,
            // AI Captions
            self::AI_CAPTIONS => EngineEnum::CAPTIONS,
            // Pebblely
            self::PEBBLELY => EngineEnum::PEBBLELY,
            // Gemini
            self::GEMINI_3_PRO_PREVIEW,
            self::GEMINI_3_1_PRO_PREVIEW,
            self::GEMINI_2_5_FLASH_PREVIEW_05_20,
            self::GEMINI_2_5_PRO,
            self::GEMINI_DEEP_RESEARCH,
            self::GEMINI_2_0_FLASH,
            self::GEMINI_2_0_FLASH_LITE,
            self::GEMINI_1_5_PRO,
            self::GEMINI_1_5_FLASH,
            self::GEMINI_3_FLASH,
            self::GEMINI_3_1_FLASH_LIVE_PREVIEW,
            self::GEMINI_EMBEDDING_EXP,
            self::GEMINI_TEXT_EMBEDDING_004,
            // Lyria (Google Gemini)
            self::LYRIA_3_CLIP,
            self::LYRIA_3_PRO => EngineEnum::GEMINI,
            // Unsplash
            self::UNSPLASH => EngineEnum::UNSPLASH,
            // Pexels
            self::PEXELS => EngineEnum::PEXELS,
            // Pixabay
            self::PIXABAY => EngineEnum::PIXABAY,
            // Elevenlabs
            self::ELEVENLABS,
            self::ELEVENLABS_V3,
            self::ELEVENLABS_VOICE_CHATBOT,
            self::ELEVENLABS_AI_MUSIC,
            self::ISOLATOR => EngineEnum::ELEVENLABS,
            // Google
            self::GOOGLE => EngineEnum::GOOGLE,
            // Azure
            self::AZURE,
            self::AZURE_OPENAI => EngineEnum::AZURE,
            // Speechify
            self::Speechify => EngineEnum::Speechify,
            // Serper
            self::SERPER => EngineEnum::SERPER,
            // Perplexity
            self::PERPLEXITY => EngineEnum::PERPLEXITY,
            // PIAPI
            self::MIDJOURNEY => EngineEnum::PI_API,
            // X AI
            self::GROK_2_1212, self::GROK_2_VISION_1212, self::GROK_3, self::GROK_3_MINI, self::GROK_3_FAST,
            self::GROK_3_MINI_FAST, self::GROK_4, self::GROK_4_FAST, self::GROK_4_1_FAST_REASONING, self::GROK_4_1_FAST_NON_REASONING => EngineEnum::X_AI,
            // Gamma AI
            self::GAMMA_AI => EngineEnum::GAMMA_AI,
            // FAL AI
            self::VEO_2, self::VEED, self::VEED_FABRIC,
            self::VEO_3_1_TEXT_TO_VIDEO, self::VEO_3_1_TEXT_TO_VIDEO_FAST, self::VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO, self::VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO_FAST, self::VEO_3_1_IMAGE_TO_VIDEO, self::VEO_3_1_IMAGE_TO_VIDEO_FAST, self::VEO_3_1_REFERENCE_TO_VIDEO,
            self::VEO_3_1_LITE_TEXT_TO_VIDEO, self::VEO_3_1_LITE_IMAGE_TO_VIDEO, self::VEO_3_1_LITE_FIRST_LAST_FRAME_TO_VIDEO,
            self::VEO_3, self::VEO_3_FAST,
            self::KLING_2_5_TURBO_PRO_TTV, self::KLING_2_5_TURBO_PRO_ITV, self::KLING_2_5_TURBO_STANDARD_ITV,
            self::KLING_VIDEO, self::VIDEO_UPSCALER, self::COGVIDEOX_5B, self::ANIMATEDIFF_V2V, self::FAST_ANIMATEDIFF_TURBO, self::VIDEO_BACKGROUND_REMOVAL, self::FLUX_PRO, self::NANO_BANANA, self::NANO_BANANA_EDIT, self::NANO_BANANA_PRO, self::NANO_BANANA_PRO_EDIT, self::NANO_BANANA_2, self::NANO_BANANA_2_EDIT, self::GROK_IMAGINE_IMAGE, self::GROK_IMAGINE_IMAGE_EDIT, self::GROK_IMAGINE_VIDEO_TTV, self::GROK_IMAGINE_VIDEO_ITV, self::SEEDREAM_4, self::SEEDREAM_4_EDIT, self::SEEDANCE_2_TTV, self::SEEDANCE_2_ITV, self::SEEDANCE_2_RTV, self::SEEDANCE_2_FAST_TTV, self::SEEDANCE_2_FAST_ITV, self::SEEDANCE_2_FAST_RTV, self::GEMINI_OMNI_FLASH_TTV, self::GEMINI_OMNI_FLASH_ITV, self::GEMINI_OMNI_FLASH_RTV, self::IMAGEN_4, self::FLUX_PRO_KONTEXT_MAX_MULTI, self::FLUX_PRO_KONTEXT_TEXT_TO_IMAGE, self::FLUX_PRO_KONTEXT, self::FLUX_PRO_1_1, self::FLUX_REALISM, self::FLUX_SCHNELL, self::IDEOGRAM,
            self::KLING, self::KLING_2_1, self::KLING_IMAGE, self::LUMA_DREAM_MACHINE, self::HAIPER, self::MINIMAX,
            self::KLING_2_6_PRO_TTV, self::KLING_2_6_PRO_ITV, self::KLING_2_6_PRO_MOTION_CONTROL, self::KLING_2_6_STANDARD_MOTION_CONTROL,
            self::KLING_3_PRO_TTV, self::KLING_3_PRO_ITV, self::KLING_3_STANDARD_TTV, self::KLING_3_STANDARD_ITV,
            self::FLUX_2_FLEX, self::FLUX_2_FLEX_EDIT => EngineEnum::FAL_AI,
            // Creatify
            self::AD_MARKETING_VIDEO => EngineEnum::CREATIFY,
            // Topview
            self::AD_MARKETING_VIDEO_TOPVIEW => EngineEnum::TOPVIEW,
            // AI CLIP
            self::AI_CLIP_KLAP   => EngineEnum::KLAP,
            self::AI_CLIP_VIZARD => EngineEnum::VIZARD,
            // AI/ML api minimax engine
            self::MUSIC_01 => EngineEnum::AI_ML_MINIMAX,
            // Open Router
            self::ANTHROPIC_CLAUDE_3_5_HAIKU_20241022,
            self::ANTHROPIC_CLAUDE_3_5_HAIKU_20241022_SELF_MODERATED,
            self::ANTHROPIC_CLAUDE_3_5_HAIKU,
            self::ANTHROPIC_CLAUDE_3_5_HAIKU_SELF_MODERATED,
            self::LUMIMAID_V02_70B,
            self::MAGNUM_V4_72B,
            self::XAI_GROK_BETA,
            self::MINISTRAL_8B,
            self::MINISTRAL_3B,
            self::QWEN25_7B_INSTRUCT,
            self::NVIDIA_LLAMA_31_NEMOTRON_70B_INSTRUCT,
            self::INFLECTION_3_PI,
            self::INFLECTION_3_PRODUCTIVITY,
            self::LIQUID_LFM_40B_MOE_FREE,
            self::LIQUID_LFM_40B_MOB,
            self::ROCINANTE_12B,
            self::EVA_QWEN25_14B,
            self::MAGNUM_V2_72B,
            self::META_LLAMA32_3B_INSTRUCT_FREE,
            self::META_LLAMA32_1B_INSTRUCT_FREE,
            self::META_LLAMA32_3B_INSTRUCT,
            self::META_LLAMA32_1B_INSTRUCT,
            self::PERPLEXITY_LLAMA_31_SONAR_405B_ONLINE,
            self::PERPLEXITY_LLAMA_31_SONAR_70B_ONLINE,
            self::PERPLEXITY_LLAMA_31_SONAR_70B,
            self::PERPLEXITY_LLAMA_31_SONAR_8B_ONLINE,
            self::PERPLEXITY_LLAMA_31_SONAR_8B => EngineEnum::OPEN_ROUTER,
        };
    }

    public function driverClass(): string
    {
        return match ($this) {
            // Together
            self::BLACK_FOREST_LABS_FLUX_1_SCHNELL => Together\FLUX1SchnellDriver::class,
            // Stable Diffusion
            self::IMAGE_TO_VIDEO                 => StableDiffusion\ImageToVideoDriver::class,
            self::STABLE_DIFFUSION_XL_1024_V_1_0 => StableDiffusion\XL1024V10Driver::class,
            self::STABLE_DIFFUSION_V_1_6         => StableDiffusion\V16Driver::class,
            self::SD_3                           => StableDiffusion\Sd3Driver::class,
            self::SD_3_TURBO                     => StableDiffusion\Sd3TurboDriver::class,
            self::SD_3_MEDIUM                    => StableDiffusion\Sd3MediumDriver::class,
            self::SD_3_LARGE                     => StableDiffusion\Sd3LargeDriver::class,
            self::SD_3_LARGE_TURBO               => StableDiffusion\Sd3LargeTurboDriver::class,
            self::SD_3_5_LARGE                   => StableDiffusion\Sd35LargeDriver::class,
            self::SD_3_5_LARGE_TURBO             => StableDiffusion\Sd35LargeTurboDriver::class,
            self::SD_3_5_MEDIUM                  => StableDiffusion\Sd35MediumDriver::class,
            self::CORE                           => StableDiffusion\CoreDriver::class,
            self::ULTRA                          => StableDiffusion\UltraDriver::class,
            self::AWS_BEDROCK                    => StableDiffusion\AwsBedrockDriver::class,
            // OpenAI
            self::DAVINCI                     => OpenAI\DavinciDriver::class,
            self::TEXT_DAVINCI_003            => OpenAI\TextDavinciDriver::class,
            self::GPT_3_5_TURBO               => OpenAI\GPT35TurboDriver::class,
            self::GPT_3_5_TURBO_0125          => OpenAI\GPT35Turbo0125Driver::class,
            self::GPT_3_5_TURBO_1106          => OpenAI\GPT35Turbo1106Driver::class,
            self::GPT_4_TURBO                 => OpenAI\GPT4TurboDriver::class,
            self::GPT_4                       => OpenAI\GPT4Driver::class,
            self::GPT_4_1106_PREVIEW          => OpenAI\GPT41106PreviewDriver::class,
            self::GPT_4_0125_PREVIEW          => OpenAI\GPT40125PreviewDriver::class,
            self::TEXT_EMBEDDING_ADA_002      => OpenAI\TextEmbeddingAdaDriver::class,
            self::TEXT_EMBEDDING_3_SMALL      => OpenAI\TextEmbedding3SmallDriver::class,
            self::TEXT_EMBEDDING_3_LARGE      => OpenAI\TextEmbedding3LargeDriver::class,
            self::WHISPER_1                   => OpenAI\Whisper1Driver::class,
            self::DALL_E_2                    => OpenAI\DallE2Driver::class,
            self::DALL_E_3                    => OpenAI\DallE3Driver::class,
            self::GPT_IMAGE_1                 => OpenAI\GptImage1Driver::class,
            self::GPT_IMAGE_1_5               => OpenAI\GptImage15Driver::class,
            self::GPT_IMAGE_2                 => OpenAI\GptImage2Driver::class,
            self::TTS_1                       => OpenAI\TTS1Driver::class,
            self::TTS_1_HD                    => OpenAI\TTS1HDDriver::class,
            self::GPT_4_O                     => OpenAI\GPT4ODriver::class,
            self::GPT_4_O_MINI                => OpenAI\GPT4OMiniDriver::class,
            self::GPT_4_O_SEARCH_PREVIEW      => OpenAI\GPT4OSearchPreviewDriver::class,
            self::GPT_4_O_MINI_SEARCH_PREVIEW => OpenAI\GPT4OMiniSearchPreviewDriver::class,
            self::GPT_4_O1_PREVIEW            => OpenAI\GPT4O1PreviewDriver::class,
            self::GPT_4_O1_MINI               => OpenAI\GPT4O1MiniDriver::class,
            self::GPT_O_1                     => OpenAI\GPTO1Driver::class,
            self::GPT_O_03_mini               => OpenAI\GPTO3MiniDriver::class,
            self::GPT_4_O_REALTIME_PREVIEW    => OpenAI\GPT4ORealtimePreviewDriver::class,
            self::GPT_REALTIME                => OpenAI\GPTRealtimeDriver::class,
            self::GPT_4_1                     => OpenAI\GPT41Driver::class,
            self::GPT_O_4_MINI                => OpenAI\GPTO4MiniDriver::class,
            self::GPT_4_1_NANO                => OpenAI\GPT41NanoDriver::class,
            self::GPT_4_1_MINI                => OpenAI\GPT41MiniDriver::class,
            self::GPT_O_3                     => OpenAI\GPTO3Driver::class,
            self::GPT_5                       => OpenAI\GPT5Driver::class,
            self::GPT_5_MINI                  => OpenAI\GPT5MiniDriver::class,
            self::GPT_5_NANO                  => OpenAI\GPT5NanoDriver::class,
            self::GPT_5_CHAT                  => OpenAI\GPT5ChatDriver::class,
            self::GPT_5_PRO                   => OpenAI\GPT5ProDriver::class,
            self::GPT_5_1                     => OpenAI\GPT51Driver::class,
            self::GPT_5_1_CHAT                => OpenAI\GPT51ChatDriver::class,
            self::GPT_5_2                     => OpenAI\GPT52Driver::class,
            self::GPT_5_2_PRO                 => OpenAI\GPT52ProDriver::class,
            self::GPT_5_3_CHAT                => OpenAI\GPT53ChatDriver::class,
            self::GPT_5_4                     => OpenAI\GPT54Driver::class,
            self::GPT_5_4_MINI                => OpenAI\GPT54MiniDriver::class,
            self::GPT_5_4_NANO                => OpenAI\GPT54NanoDriver::class,
            self::GPT_5_6_SOL                 => OpenAI\GPT56SolDriver::class,
            self::GPT_5_6_TERRA               => OpenAI\GPT56TerraDriver::class,
            self::GPT_5_6_LUNA                => OpenAI\GPT56LunaDriver::class,
            self::GPT_5_5                     => OpenAI\GPT55Driver::class,
            self::O3_DEEP_RESEARCH            => OpenAI\O3DeepResearchDriver::class,
            self::O4_MINI_DEEP_RESEARCH       => OpenAI\O4MiniDeepResearchDriver::class,
            self::SORA_2                      => OpenAI\Sora2Driver::class,
            self::SORA_2_PRO                  => OpenAI\Sora2ProDriver::class,

            // Anthropic
            self::CLAUDE_FABLE_5       => Anthropic\ClaudeFable5Driver::class,
            self::CLAUDE_SONNET_5      => Anthropic\ClaudeSonnet5Driver::class,
            self::CLAUDE_SONNET_4_5    => Anthropic\ClaudeSonnet45Driver::class,
            self::CLAUDE_SONNET_4_6    => Anthropic\ClaudeSonnet46Driver::class,
            self::CLAUDE_OPUS_4_8      => Anthropic\ClaudeOpus48Driver::class,
            self::CLAUDE_OPUS_4_7      => Anthropic\ClaudeOpus47Driver::class,
            self::CLAUDE_OPUS_4_6      => Anthropic\ClaudeOpus46Driver::class,
            self::CLAUDE_OPUS_4_5      => Anthropic\ClaudeOpus45Driver::class,
            self::CLAUDE_OPUS_4_1      => Anthropic\ClaudeOpus41Driver::class,
            self::CLAUDE_SONNET_4      => Anthropic\ClaudeSonnet4Driver::class,
            self::CLAUDE_OPUS_4        => Anthropic\ClaudeOpus4Driver::class,
            self::CLAUDE_3_7_SONNET    => Anthropic\Claude37SonnetDriver::class,
            self::CLAUDE_3_5_HAIKU     => Anthropic\Claude35HaikuDriver::class,
            self::CLAUDE_3_5_SONNET_V2 => Anthropic\Claude35SonnetV2Driver::class,
            self::CLAUDE_3_5_SONNET    => Anthropic\Claude35SonnetDriver::class,
            self::CLAUDE_3_SONNET      => Anthropic\Claude3SonnetDriver::class,
            self::CLAUDE_3_OPUS        => Anthropic\Claude3OpusDriver::class,
            self::CLAUDE_3_HAIKU       => Anthropic\Claude3HaikuDriver::class,
            self::CLAUDE_2_1           => Anthropic\Claude21Driver::class,
            self::CLAUDE_2_0           => Anthropic\Claude20Driver::class,
            self::VOYAGE_2             => Anthropic\Voyage2Driver::class,
            self::VOYAGE_LARGE_2       => Anthropic\VoyageLarge2Driver::class,
            self::VOYAGE_CODE_2        => Anthropic\VoyageCode2Driver::class,
            // Gemini
            self::GEMINI_3_PRO_PREVIEW             => Gemini\Gemini3ProPreviewDriver::class,
            self::GEMINI_3_1_PRO_PREVIEW           => Gemini\Gemini31ProPreviewDriver::class,
            self::GEMINI_2_5_FLASH_PREVIEW_05_20   => Gemini\Gemini25FlashPreview0417Driver::class,
            self::GEMINI_2_5_PRO                   => Gemini\Gemini25ProExp0325Driver::class,
            self::GEMINI_DEEP_RESEARCH             => Gemini\GeminiDeepResearchDriver::class,
            self::GEMINI_2_0_FLASH                 => Gemini\Gemini20FlashDriver::class,
            self::GEMINI_2_0_FLASH_LITE            => Gemini\Gemini20FlashLiteDriver::class,
            self::GEMINI_1_5_PRO                   => Gemini\Gemini15ProDriver::class,
            self::GEMINI_1_5_FLASH                 => Gemini\Gemini15FlashDriver::class,
            self::GEMINI_3_FLASH                   => Gemini\Gemini3FlashDriver::class,
            self::GEMINI_3_1_FLASH_LIVE_PREVIEW    => Gemini\Gemini31FlashLivePreviewDriver::class,
            self::GEMINI_EMBEDDING_EXP             => Gemini\GeminiEmbeddingExpDriver::class,
            self::GEMINI_TEXT_EMBEDDING_004        => Gemini\GeminiTextEmbeding004Driver::class,
            // Lyria (Google Gemini)
            self::LYRIA_3_CLIP                    => Gemini\Lyria3ClipDriver::class,
            self::LYRIA_3_PRO                     => Gemini\Lyria3ProDriver::class,
            // Deepseek
            self::DEEPSEEK_CHAT     => Deepseek\DeepseekChatDriver::class,
            self::DEEPSEEK_REASONER => Deepseek\DeepseekReasonerDriver::class,
            // Others
            self::CLIPDROP                 => ClipDropDriver::class,
            self::NOVITA                   => NovitaDriver::class,
            self::FREEPIK                  => FreepikDriver::class,
            self::PLAGIARISMCHECK          => PlagiarismCheckDriver::class,
            self::SYNTHESIA                => SynthesiaDriver::class,
            self::HEYGEN                   => HeygenDriver::class,
            self::VIDEO_DUBBING            => VideoDubbingDriver::class,
            self::AI_CAPTIONS              => AiCaptionsDriver::class,
            self::PEBBLELY                 => PebblelyDriver::class,
            self::UNSPLASH                 => UnsplashDriver::class,
            self::PEXELS                   => PexelsDriver::class,
            self::PIXABAY                  => PixabayDriver::class,
            self::ELEVENLABS               => ElevenLabs\ElevenlabsDriver::class,
            self::ELEVENLABS_V3            => ElevenLabs\ElevenlabsV3Driver::class,
            self::ELEVENLABS_VOICE_CHATBOT => ElevenLabs\ElevenlabsVoiceChatbotDriver::class,
            self::ISOLATOR                 => ElevenLabs\IsolatorDriver::class,
            self::ELEVENLABS_AI_MUSIC      => ElevenLabs\ElevenlabsAIMusicDriver::class,
            self::GOOGLE                   => GoogleDriver::class,
            self::AZURE                    => AzureDriver::class,
            self::AZURE_OPENAI             => AzureOpenaiDriver::class,
            self::Speechify                => SpeechifyDriver::class,
            self::SERPER                   => SerperDriver::class,
            self::PERPLEXITY               => PerplexityDriver::class,
            // PiAPI
            self::MIDJOURNEY => MidjourneyDriver::class,
            // X AI
            self::GROK_2_1212                 => XAI\Grok21212Driver::class,
            self::GROK_2_VISION_1212          => XAI\Grok2Vision1212Driver::class,
            self::GROK_3                      => XAI\Grok3Driver::class,
            self::GROK_3_MINI                 => XAI\Grok3MiniDriver::class,
            self::GROK_3_FAST                 => XAI\Grok3FastDriver::class,
            self::GROK_3_MINI_FAST            => XAI\Grok3MiniFastDriver::class,
            self::GROK_4                      => XAI\Grok4Driver::class,
            self::GROK_4_FAST                 => XAI\Grok4FastDriver::class,
            self::GROK_4_1_FAST_REASONING     => XAI\Grok41FastReasoningDriver::class,
            self::GROK_4_1_FAST_NON_REASONING => XAI\Grok41FastNonReasoningDriver::class,

            // Gamma AI
            self::GAMMA_AI => GammaAIDriver::class,
            // FAL AI
            self::VEO_2                                  => FalAI\Veo2Driver::class,
            self::VEO_3                                  => FalAI\Veo3Driver::class,
            self::VEO_3_FAST                             => FalAI\Veo3FastDriver::class,
            self::VEO_3_1_TEXT_TO_VIDEO                  => FalAI\Veo31\Veo31TTVDriver::class,
            self::VEO_3_1_TEXT_TO_VIDEO_FAST             => FalAI\Veo31\Veo31TTVFastDriver::class,
            self::VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO      => FalAI\Veo31\Veo31FLFTVDriver::class,
            self::VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO_FAST => FalAI\Veo31\Veo31FLFTVFastDriver::class,
            self::VEO_3_1_IMAGE_TO_VIDEO                 => FalAI\Veo31\Veo31ITVDriver::class,
            self::VEO_3_1_IMAGE_TO_VIDEO_FAST            => FalAI\Veo31\Veo31ITVFastDriver::class,
            self::VEO_3_1_REFERENCE_TO_VIDEO             => FalAI\Veo31\Veo31RTVDriver::class,
            self::VEO_3_1_LITE_TEXT_TO_VIDEO             => FalAI\Veo31\Veo31LiteTTVDriver::class,
            self::VEO_3_1_LITE_IMAGE_TO_VIDEO            => FalAI\Veo31\Veo31LiteITVDriver::class,
            self::VEO_3_1_LITE_FIRST_LAST_FRAME_TO_VIDEO => FalAI\Veo31\Veo31LiteFLFTVDriver::class,
            self::KLING_VIDEO                            => FalAI\KlingVideoDriver::class,
            self::VEED                                   => FalAI\VeedDriver::class,
            self::VEED_FABRIC                            => FalAI\VeedFabricDriver::class,
            self::FLUX_PRO                               => FalAI\FluxProDriver::class,
            self::NANO_BANANA                            => FalAI\NanoBananaDriver::class,
            self::NANO_BANANA_EDIT                       => FalAI\NanoBananaEditDriver::class,
            self::NANO_BANANA_PRO                        => FalAI\NanoBananaProDriver::class,
            self::NANO_BANANA_PRO_EDIT                   => FalAI\NanoBananaProEditDriver::class,
            self::NANO_BANANA_2                          => FalAI\NanoBanana2Driver::class,
            self::NANO_BANANA_2_EDIT                     => FalAI\NanoBanana2EditDriver::class,
            self::GROK_IMAGINE_IMAGE                     => FalAI\GrokImagineImageDriver::class,
            self::GROK_IMAGINE_IMAGE_EDIT                => FalAI\GrokImagineImageEditDriver::class,
            self::GROK_IMAGINE_VIDEO_TTV                 => FalAI\GrokImagineVideoTTVDriver::class,
            self::GROK_IMAGINE_VIDEO_ITV                 => FalAI\GrokImagineVideoITVDriver::class,
            self::SEEDREAM_4                             => FalAI\SeeDream4Driver::class,
            self::SEEDREAM_4_EDIT                        => FalAI\SeeDream4EditDriver::class,
            self::SEEDANCE_2_TTV                         => FalAI\Seedance2\Seedance2TTVDriver::class,
            self::SEEDANCE_2_ITV                         => FalAI\Seedance2\Seedance2ITVDriver::class,
            self::SEEDANCE_2_RTV                         => FalAI\Seedance2\Seedance2RTVDriver::class,
            self::SEEDANCE_2_FAST_TTV                    => FalAI\Seedance2\Seedance2FastTTVDriver::class,
            self::SEEDANCE_2_FAST_ITV                    => FalAI\Seedance2\Seedance2FastITVDriver::class,
            self::SEEDANCE_2_FAST_RTV                    => FalAI\Seedance2\Seedance2FastRTVDriver::class,
            self::GEMINI_OMNI_FLASH_TTV                  => FalAI\GeminiOmniFlash\GeminiOmniFlashTTVDriver::class,
            self::GEMINI_OMNI_FLASH_ITV                  => FalAI\GeminiOmniFlash\GeminiOmniFlashITVDriver::class,
            self::GEMINI_OMNI_FLASH_RTV                  => FalAI\GeminiOmniFlash\GeminiOmniFlashRTVDriver::class,
            self::IMAGEN_4                               => FalAI\Imagen4Driver::class,
            self::FLUX_PRO_KONTEXT_MAX_MULTI             => FalAI\FluxProKontextMaxMultiDriver::class,
            self::FLUX_PRO_KONTEXT_TEXT_TO_IMAGE         => FalAI\FluxProKontextTextToImageDriver::class,
            self::FLUX_PRO_KONTEXT                       => FalAI\FluxProKontextDriver::class,
            self::IDEOGRAM                               => FalAI\IdeogramDriver::class,
            self::FLUX_PRO_1_1                           => FalAI\FluxPro11Driver::class,
            self::FLUX_REALISM                           => FalAI\FluxRealismDriver::class,
            self::FLUX_SCHNELL                           => FalAI\FluxSchnellDriver::class,
            self::FLUX_2_FLEX                            => FalAI\FluxTwoFlexDriver::class,
            self::FLUX_2_FLEX_EDIT                       => FalAI\FluxTwoFlexEditDriver::class,
            self::KLING                                  => FalAI\KlingDriver::class,
            self::KLING_2_1                              => FalAI\KlingV21Driver::class,
            self::KLING_2_5_TURBO_PRO_TTV                => FalAI\Kling25Turbo\Kling25TurboProTTVDriver::class,
            self::KLING_2_5_TURBO_PRO_ITV                => FalAI\Kling25Turbo\Kling25TurboProITVDriver::class,
            self::KLING_2_5_TURBO_STANDARD_ITV           => FalAI\Kling25Turbo\Kling25TurboStandardITVDriver::class,
            self::KLING_2_6_PRO_TTV                      => FalAI\Kling26Pro\KlingV26ProTTVDriver::class,
            self::KLING_2_6_PRO_ITV                      => FalAI\Kling26Pro\KlingV26ProITVDriver::class,
            self::KLING_2_6_PRO_MOTION_CONTROL           => FalAI\Kling26Pro\KlingV26ProMotionControlDriver::class,
            self::KLING_2_6_STANDARD_MOTION_CONTROL      => FalAI\Kling26Standard\KlingV26StandardMotionControlDriver::class,
            self::KLING_3_PRO_TTV                        => FalAI\KlingV3\KlingV3ProTTVDriver::class,
            self::KLING_3_PRO_ITV                        => FalAI\KlingV3\KlingV3ProITVDriver::class,
            self::KLING_3_STANDARD_TTV                   => FalAI\KlingV3\KlingV3StandardTTVDriver::class,
            self::KLING_3_STANDARD_ITV                   => FalAI\KlingV3\KlingV3StandardITVDriver::class,
            self::KLING_IMAGE                            => FalAI\KlingImageDriver::class,
            self::LUMA_DREAM_MACHINE                     => FalAI\LumaDreamMachineDriver::class,
            self::HAIPER                                 => FalAI\HaiperDriver::class,
            self::MINIMAX                                => FalAI\MinimaxDriver::class,
            self::VIDEO_UPSCALER                         => FalAI\VideoUpscalerDriver::class,
            self::COGVIDEOX_5B                           => FalAI\Cogvideox5bDriver::class,
            self::ANIMATEDIFF_V2V                        => FalAI\AnimatediffV2vDriver::class,
            self::FAST_ANIMATEDIFF_TURBO                 => FalAI\FastAnimatediffTurboDriver::class,
            self::VIDEO_BACKGROUND_REMOVAL               => FalAI\VideoBackgroundRemovalDriver::class,
            // CREATIFY
            self::AD_MARKETING_VIDEO => Creatify\AdMarketingVideoDriver::class,
            // Topview
            self::AD_MARKETING_VIDEO_TOPVIEW => Topview\AdMarketingVideoTopviewDriver::class,
            // Ai clip
            self::AI_CLIP_KLAP   => Klap\AIClipKlapDriver::class,
            self::AI_CLIP_VIZARD => Vizard\AIClipVizardDriver::class,
            // AI/ML api minimax engine
            self::MUSIC_01 => AiMlMinimax\Music01Driver::class,
            // OpenRouter
            self::ANTHROPIC_CLAUDE_3_5_HAIKU_20241022                => OpenRouter\AnthropicClaude35Haiku20241022::class,
            self::ANTHROPIC_CLAUDE_3_5_HAIKU_20241022_SELF_MODERATED => OpenRouter\AnthropicClaude35Haiku20241022SelfModerated::class,
            self::ANTHROPIC_CLAUDE_3_5_HAIKU                         => OpenRouter\AnthropicClaude35Haiku::class,
            self::ANTHROPIC_CLAUDE_3_5_HAIKU_SELF_MODERATED          => OpenRouter\AnthropicClaude35HaikuSelfModerated::class,
            self::LUMIMAID_V02_70B                                   => OpenRouter\LumimaidV0270B::class,
            self::MAGNUM_V4_72B                                      => OpenRouter\MagnumV472B::class,
            self::XAI_GROK_BETA                                      => OpenRouter\XaiGrokBeta::class,
            self::MINISTRAL_8B                                       => OpenRouter\Ministral8B::class,
            self::MINISTRAL_3B                                       => OpenRouter\Ministral3B::class,
            self::QWEN25_7B_INSTRUCT                                 => OpenRouter\Qwen257BInstruct::class,
            self::NVIDIA_LLAMA_31_NEMOTRON_70B_INSTRUCT              => OpenRouter\NvidiaLlama31Nemotron70BInstruct::class,
            self::INFLECTION_3_PI                                    => OpenRouter\Inflection3Pi::class,
            self::INFLECTION_3_PRODUCTIVITY                          => OpenRouter\Inflection3Productivity::class,
            self::LIQUID_LFM_40B_MOE_FREE                            => OpenRouter\LiquidLfm40BMoeFree::class,
            self::LIQUID_LFM_40B_MOB                                 => OpenRouter\LiquidLfm40BMob::class,
            self::ROCINANTE_12B                                      => OpenRouter\Rocinante12B::class,
            self::EVA_QWEN25_14B                                     => OpenRouter\EvaQwen2514B::class,
            self::MAGNUM_V2_72B                                      => OpenRouter\MagnumV272B::class,
            self::META_LLAMA32_3B_INSTRUCT_FREE                      => OpenRouter\MetaLlama323BInstructFree::class,
            self::META_LLAMA32_1B_INSTRUCT_FREE                      => OpenRouter\MetaLlama321BInstructFree::class,
            self::META_LLAMA32_3B_INSTRUCT                           => OpenRouter\MetaLlama323BInstruct::class,
            self::META_LLAMA32_1B_INSTRUCT                           => OpenRouter\MetaLlama321BInstruct::class,
            self::PERPLEXITY_LLAMA_31_SONAR_405B_ONLINE              => OpenRouter\PerplexityLlama31Sonar405BOnline::class,
            self::PERPLEXITY_LLAMA_31_SONAR_70B_ONLINE               => OpenRouter\PerplexityLlama31Sonar70BOnline::class,
            self::PERPLEXITY_LLAMA_31_SONAR_70B                      => OpenRouter\PerplexityLlama31Sonar70B::class,
            self::PERPLEXITY_LLAMA_31_SONAR_8B_ONLINE                => OpenRouter\PerplexityLlama31Sonar8BOnline::class,
            self::PERPLEXITY_LLAMA_31_SONAR_8B                       => OpenRouter\PerplexityLlama31Sonar8B::class,
        };
    }

    /** @noinspection PhpDuplicateMatchArmBodyInspection */
    public function unitPrice(): float
    {
        return match ($this) {
            // Images & Video Models
            self::BLACK_FOREST_LABS_FLUX_1_SCHNELL => 0.01,
            self::IMAGE_TO_VIDEO                   => 0.5,
            self::STABLE_DIFFUSION_XL_1024_V_1_0   => 0.05,
            self::STABLE_DIFFUSION_V_1_6           => 0.03,
            self::SD_3, self::ULTRA, self::SD_3_TURBO => 0.06,
            self::SD_3_MEDIUM        => 0.06,
            self::SD_3_LARGE         => 0.10,
            self::SD_3_LARGE_TURBO   => 0.15,
            self::SD_3_5_LARGE       => 0.15,
            self::SD_3_5_LARGE_TURBO => 0.20,
            self::SD_3_5_MEDIUM      => 0.10,
            self::CORE               => 0.03,
            self::AWS_BEDROCK        => 0.05,

            // OpenAI
            self::DAVINCI, self::GPT_3_5_TURBO_1106, self::GPT_3_5_TURBO => 0.00000266,
            self::TEXT_DAVINCI_003   => 0.0000266,
            self::GPT_3_5_TURBO_0125 => 0.000001995,
            self::GPT_4_TURBO, self::GPT_4_1106_PREVIEW, self::GPT_4_0125_PREVIEW, self::TTS_1_HD => 0.0000399,
            self::GPT_4                  => 0.0000798,
            self::TEXT_EMBEDDING_ADA_002 => 0.0000000665,
            self::TEXT_EMBEDDING_3_SMALL => 0.000000027,
            self::TEXT_EMBEDDING_3_LARGE => 0.0000000665,
            self::WHISPER_1              => 0.000000798,
            self::DALL_E_2               => 0.04,
            self::DALL_E_3               => 0.08,
            self::GPT_IMAGE_1            => 0.042,
            self::GPT_IMAGE_1_5          => 0.20,
            self::GPT_IMAGE_2            => 0.211,
            self::TTS_1, self::GPT_4_O => 0.00001995,
            self::GPT_4_O_MINI                => 0.000000798,
            self::GPT_4_O_SEARCH_PREVIEW      => 0.0000133,
            self::GPT_4_O_MINI_SEARCH_PREVIEW => 0.000000798,
            self::GPT_4_O1_PREVIEW            => 0.0000798,
            self::GPT_4_O1_MINI               => 0.000005852,
            self::GPT_O_1                     => 0.0000798,
            self::GPT_O_03_mini               => 0.000005852,
            self::GPT_4_O_REALTIME_PREVIEW    => 0.0000266,
            self::GPT_REALTIME                => 0.0000266,
            self::GPT_4_1                     => 0.00001064,
            self::GPT_4_1_NANO                => 0.000000665,
            self::GPT_4_1_MINI                => 0.00000266,
            self::GPT_O_4_MINI                => 0.000005852,
            self::GPT_O_3                     => 0.0000665,
            self::GPT_5                       => 0.000013330,
            self::GPT_5_MINI                  => 0.000002670,
            self::GPT_5_NANO                  => 0.000000530,
            self::GPT_5_CHAT                  => 0.00001000,
            self::GPT_5_PRO                   => 0.00016,
            self::GPT_5_1                     => 0.0000133,
            self::GPT_5_1_CHAT                => 0.0000133,
            self::GPT_5_2                     => 0.00175,
            self::GPT_5_2_PRO                 => 0.021,
            self::GPT_5_3_CHAT                => 0.000014,
            self::GPT_5_4                     => 0.025,
            self::GPT_5_4_MINI                => 0.00250,
            self::GPT_5_4_NANO                => 0.000500,
            self::GPT_5_6_SOL                 => 0.025,
            self::GPT_5_6_TERRA               => 0.00250,
            self::GPT_5_6_LUNA                => 0.000500,
            self::GPT_5_5                     => 0.000040,
            self::O3_DEEP_RESEARCH            => 0.0000665,
            self::O4_MINI_DEEP_RESEARCH       => 0.000005852,
            self::SORA_2                      => 0.10,
            self::SORA_2_PRO                  => 0.50,

            // Anthropic
            self::CLAUDE_FABLE_5, self::CLAUDE_SONNET_5, self::CLAUDE_SONNET_4_5, self::CLAUDE_SONNET_4_6, self::CLAUDE_OPUS_4_8, self::CLAUDE_OPUS_4_7, self::CLAUDE_OPUS_4_6, self::CLAUDE_OPUS_4_1, self::CLAUDE_OPUS_4_5, self::CLAUDE_OPUS_4, self::CLAUDE_SONNET_4, self::CLAUDE_3_7_SONNET, self::CLAUDE_3_5_SONNET_V2, self::CLAUDE_3_5_SONNET, self::CLAUDE_3_SONNET => 0.000015,
            self::CLAUDE_3_5_HAIKU => 0.000003,
            self::CLAUDE_3_OPUS    => 0.000015,
            self::CLAUDE_3_HAIKU   => 0.000003,
            self::CLAUDE_2_1, self::CLAUDE_2_0 => 0.000015,

            // Voyage
            self::VOYAGE_2, self::VOYAGE_CODE_2 => 0.00001,
            self::VOYAGE_LARGE_2 => 0.000015,

            // Deepseek
            self::DEEPSEEK_CHAT     => 0.00000147,
            self::DEEPSEEK_REASONER => 0.00000292,

            // Others
            self::CLIPDROP        => 0.5,
            self::NOVITA          => 0.017,
            self::FREEPIK         => 0.20,
            self::PLAGIARISMCHECK => 0.000276,
            self::SYNTHESIA       => 2.97,
            self::HEYGEN          => 0.5,
            self::PEBBLELY        => 0.019,

            // Gemini
            self::GEMINI_3_PRO_PREVIEW             => 0.0001197,
            self::GEMINI_3_1_PRO_PREVIEW           => 0.0001197,
            self::GEMINI_2_5_FLASH_PREVIEW_05_20   => 0.00000333,
            self::GEMINI_2_5_PRO                   => 0.00001333,
            self::GEMINI_DEEP_RESEARCH             => 0.00001333,
            self::GEMINI_2_0_FLASH                 => 0.00000053,
            self::GEMINI_2_0_FLASH_LITE            => 0.00000040,
            self::GEMINI_1_5_PRO                   => 0.00001333,
            self::GEMINI_EMBEDDING_EXP             => 0.00001,
            self::GEMINI_1_5_FLASH                 => 0.00000051,
            self::GEMINI_3_FLASH                   => 0.00000266,
            self::GEMINI_3_1_FLASH_LIVE_PREVIEW    => 0.00000266,
            self::GEMINI_TEXT_EMBEDDING_004        => 0.00001,

            // Lyria (Google Gemini) - per song pricing
            self::LYRIA_3_CLIP => 0.04,
            self::LYRIA_3_PRO  => 0.08,

            // Unsplash
            self::UNSPLASH, self::PEXELS, self::PIXABAY => 0.01,

            // Elevenlabs
            self::ELEVENLABS,
            self::ELEVENLABS_V3,
            self::ELEVENLABS_VOICE_CHATBOT,
            self::ELEVENLABS_AI_MUSIC,
            self::ISOLATOR => 0.3,

            // Google
            self::GOOGLE => 0.016,

            // Azure
            self::AZURE        => 0.015,
            self::AZURE_OPENAI => 0.00,

            // Speechify
            self::Speechify => 0.015,

            // Serper
            self::SERPER => 0.001,

            // Perplexity
            self::PERPLEXITY => 0.00002,

            // PiAPI
            self::MIDJOURNEY => 0.03,

            // X AI
            self::GROK_2_1212, self::GROK_2_VISION_1212 => 0.00001333,
            self::GROK_3                      => 0.00002,
            self::GROK_3_MINI                 => 0.00000067,
            self::GROK_3_FAST                 => 0.00003333,
            self::GROK_3_MINI_FAST            => 0.000001,
            self::GROK_4                      => 0.000011000,
            self::GROK_4_FAST                 => 0.000000650,
            self::GROK_4_1_FAST_REASONING     => 0.00001995,
            self::GROK_4_1_FAST_NON_REASONING => 0.00001995,

            // FAL AI
            self::NANO_BANANA            => 0.039,
            self::NANO_BANANA_PRO        => 0.039,
            self::NANO_BANANA_2          => 0.039,
            self::GROK_IMAGINE_IMAGE     => 0.039,
            self::GROK_IMAGINE_VIDEO_TTV, self::GROK_IMAGINE_VIDEO_ITV => 0.5,
            self::SEEDREAM_4             => 0.03,
            self::FLUX_PRO_1_1, self::FLUX_REALISM, self::IMAGEN_4 => 0.04,
            self::SEEDANCE_2_TTV, self::SEEDANCE_2_ITV, self::SEEDANCE_2_RTV => 0.5,
            self::SEEDANCE_2_FAST_TTV, self::SEEDANCE_2_FAST_ITV, self::SEEDANCE_2_FAST_RTV => 0.35,
            self::GEMINI_OMNI_FLASH_TTV, self::GEMINI_OMNI_FLASH_ITV, self::GEMINI_OMNI_FLASH_RTV => 0.5,
            self::NANO_BANANA_EDIT, self::SEEDREAM_4_EDIT, self::GROK_IMAGINE_IMAGE_EDIT => 0.4,
            self::NANO_BANANA_PRO_EDIT                   => 0.4,
            self::NANO_BANANA_2_EDIT                     => 0.4,
            self::GAMMA_AI                               => 0.01,
            self::VEO_2                                  => 2.5,
            self::VEED                                   => 0.35,
            self::VEED_FABRIC                            => 0.50,
            self::VEO_3_1_TEXT_TO_VIDEO                  => 3.2,
            self::VEO_3_1_TEXT_TO_VIDEO_FAST             => 1.2,
            self::VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO      => 3.2,
            self::VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO_FAST => 1.2,
            self::VEO_3_1_IMAGE_TO_VIDEO                 => 3.2,
            self::VEO_3_1_IMAGE_TO_VIDEO_FAST            => 1.2,
            self::VEO_3_1_REFERENCE_TO_VIDEO             => 3.2,
            self::VEO_3_1_LITE_TEXT_TO_VIDEO             => 0.8,
            self::VEO_3_1_LITE_IMAGE_TO_VIDEO            => 0.8,
            self::VEO_3_1_LITE_FIRST_LAST_FRAME_TO_VIDEO => 0.8,
            self::VEO_3_FAST                             => 1.2,
            self::VEO_3                                  => 3.2,
            self::KLING_VIDEO                            => 1.4,
            self::FLUX_PRO_KONTEXT_TEXT_TO_IMAGE, self::FLUX_PRO_KONTEXT, self::FLUX_PRO, self::FLUX_2_FLEX, self::FLUX_2_FLEX_EDIT => 0.4,
            self::FLUX_SCHNELL               => 0.003,
            self::FLUX_PRO_KONTEXT_MAX_MULTI => 0.8,
            self::KLING_2_5_TURBO_PRO_TTV, self::KLING_2_5_TURBO_PRO_ITV, self::KLING_2_5_TURBO_STANDARD_ITV => 0.6,
            self::KLING, self::KLING_2_1, self::KLING_IMAGE, self::LUMA_DREAM_MACHINE, self::HAIPER, self::MINIMAX => 0.5,
            self::KLING_2_6_PRO_TTV, self::KLING_2_6_PRO_ITV => 1.4,
            self::KLING_2_6_PRO_MOTION_CONTROL      => 1.12,
            self::KLING_2_6_STANDARD_MOTION_CONTROL => 0.70,
            self::KLING_3_PRO_TTV, self::KLING_3_PRO_ITV => 1.4,
            self::KLING_3_STANDARD_TTV, self::KLING_3_STANDARD_ITV => 0.70,
            self::IDEOGRAM                          => 0.6,
            self::VIDEO_UPSCALER, self::COGVIDEOX_5B, self::ANIMATEDIFF_V2V, self::FAST_ANIMATEDIFF_TURBO, self::VIDEO_BACKGROUND_REMOVAL => 0.20,

            // Creatify
            self::AD_MARKETING_VIDEO => 1.5,

            // Topview
            self::AD_MARKETING_VIDEO_TOPVIEW => 1.5,

            // Ai clips
            self::AI_CLIP_KLAP   => 0.48,
            self::AI_CLIP_VIZARD => 0.24,

            // Music
            self::MUSIC_01 => 0.05,

            // OpenRouter
            self::ANTHROPIC_CLAUDE_3_5_HAIKU_20241022                => 0.00000533,
            self::ANTHROPIC_CLAUDE_3_5_HAIKU_20241022_SELF_MODERATED => 0.00000533,
            self::ANTHROPIC_CLAUDE_3_5_HAIKU                         => 0.00000533,
            self::ANTHROPIC_CLAUDE_3_5_HAIKU_SELF_MODERATED          => 0.00000533,
            self::LUMIMAID_V02_70B                                   => 0.005,
            self::MAGNUM_V4_72B                                      => 0.005,
            self::XAI_GROK_BETA                                      => 0.005,
            self::MINISTRAL_8B                                       => 0.005,
            self::MINISTRAL_3B                                       => 0.005,
            self::QWEN25_7B_INSTRUCT                                 => 0.005,
            self::NVIDIA_LLAMA_31_NEMOTRON_70B_INSTRUCT              => 0.005,
            self::INFLECTION_3_PI                                    => 0.005,
            self::INFLECTION_3_PRODUCTIVITY                          => 0.005,
            self::LIQUID_LFM_40B_MOE_FREE                            => 0.005,
            self::LIQUID_LFM_40B_MOB                                 => 0.005,
            self::ROCINANTE_12B                                      => 0.005,
            self::EVA_QWEN25_14B                                     => 0.005,
            self::MAGNUM_V2_72B                                      => 0.005,
            self::META_LLAMA32_3B_INSTRUCT_FREE                      => 0.005,
            self::META_LLAMA32_1B_INSTRUCT_FREE                      => 0.005,
            self::META_LLAMA32_3B_INSTRUCT                           => 0.005,
            self::META_LLAMA32_1B_INSTRUCT                           => 0.005,
            self::PERPLEXITY_LLAMA_31_SONAR_405B_ONLINE              => 0.005,
            self::PERPLEXITY_LLAMA_31_SONAR_70B_ONLINE               => 0.005,
            self::PERPLEXITY_LLAMA_31_SONAR_70B                      => 0.005,
            self::PERPLEXITY_LLAMA_31_SONAR_8B_ONLINE                => 0.005,
            self::PERPLEXITY_LLAMA_31_SONAR_8B                       => 0.000000270,
            // Default fallback
            default => 0.0,
        };
    }

    public static function getPlanLimits(EngineEnum $engine): Collection
    {
        return collect(self::listableCases())->filter(
            fn (EntityEnum $model) => $model->engine() === $engine
        )->mapWithKeys(
            fn (EntityEnum $model) => [
                $model->slug() => [
                    'credit'      => 0,
                    'isUnlimited' => false,
                ],
            ]
        );
    }

    public function subLabel(): string
    {
        $driverClass = $this->driverClass();

        return match (true) {
            class_interface_exists($driverClass, WithVideoToVideoInterface::class)  => __('Video'),
            class_interface_exists($driverClass, WithImagesInterface::class)        => __('Images'),
            class_interface_exists($driverClass, WithWordsInterface::class)         => __('Words'),
            class_interface_exists($driverClass, WithCharsInterface::class)         => __('Characters'),
            class_interface_exists($driverClass, WithMinuteInterface::class)        => __('Minutes'),
            class_interface_exists($driverClass, WithSecondInterface::class)        => __('Seconds'),
            class_interface_exists($driverClass, WithImageToVideoInterface::class)  => __('Image to Video'),
            class_interface_exists($driverClass, WithTextToSpeechInterface::class)  => __('Text to Speech'),
            class_interface_exists($driverClass, WithSpeechToTextInterface::class)  => __('Speech to Text'),
            class_interface_exists($driverClass, WithTextToVideoInterface::class)   => __('Text to Video'),
            class_interface_exists($driverClass, WithVisionPreviewInterface::class) => __('Vision'),
            class_interface_exists($driverClass, WithPresentationInterface::class)  => __('Presentation'),
            class_interface_exists($driverClass, WithPlagiarismInterface::class)    => __('Plagiarism'),
        };
    }

    public function tokenType(): AITokenType
    {
        $driverClass = $this->driverClass();

        return match (true) {
            class_interface_exists($driverClass, WithImagesInterface::class)        => AITokenType::IMAGE,
            class_interface_exists($driverClass, WithWordsInterface::class)         => AITokenType::WORD,
            class_interface_exists($driverClass, WithCharsInterface::class)         => AITokenType::CHARACTER,
            class_interface_exists($driverClass, WithMinuteInterface::class)        => AITokenType::MINUTE,
            class_interface_exists($driverClass, WithSecondInterface::class)        => AITokenType::SECOND,
            class_interface_exists($driverClass, WithPresentationInterface::class)  => AITokenType::PRESENTATION,
            class_interface_exists($driverClass, WithImageToVideoInterface::class)  => AITokenType::IMAGE_TO_VIDEO,
            class_interface_exists($driverClass, WithTextToSpeechInterface::class)  => AITokenType::TEXT_TO_SPEECH,
            class_interface_exists($driverClass, WithSpeechToTextInterface::class)  => AITokenType::SPEECH_TO_TEXT,
            class_interface_exists($driverClass, WithTextToVideoInterface::class)   => AITokenType::TEXT_TO_VIDEO,
            class_interface_exists($driverClass, WithVideoToVideoInterface::class)  => AITokenType::VIDEO_TO_VIDEO,
            class_interface_exists($driverClass, WithVisionPreviewInterface::class) => AITokenType::VISION,
            class_interface_exists($driverClass, WithPlagiarismInterface::class)    => AITokenType::PLAGIARISM
        };
    }

    public function tooltipHowToCalc(): string
    {
        $driverClass = $this->driverClass();

        return match (true) {
            class_interface_exists($driverClass, WithImagesInterface::class) => __('1 Credit = 1 Image'),
            class_interface_exists($driverClass, WithWordsInterface::class),
            class_interface_exists($driverClass, WithSpeechToTextInterface::class),
            class_interface_exists($driverClass, WithVisionPreviewInterface::class),
            class_interface_exists($driverClass, WithPlagiarismInterface::class)   => __('1 Credit = 1 Word'),
            class_interface_exists($driverClass, WithCharsInterface::class)        => __('1 Credit = 1 Character'),
            class_interface_exists($driverClass, WithMinuteInterface::class)       => __('1 Credit = 1 Minute'),
            class_interface_exists($driverClass, WithSecondInterface::class)       => __('1 Credit = 1 Second'),
            class_interface_exists($driverClass, WithPresentationInterface::class) => __('1 Credit = 1 Presentation Credit'),
            class_interface_exists($driverClass, WithImageToVideoInterface::class),
            class_interface_exists($driverClass, WithVideoToVideoInterface::class),
            class_interface_exists($driverClass, WithTextToVideoInterface::class)  => __('1 Credit = 1 Video'),
            class_interface_exists($driverClass, WithTextToSpeechInterface::class) => __('1 Credit = 1 Voice'),
        };
    }

    public static function entityDrivers(): array
    {
        return [
            WithImagesInterface::class,
            WithWordsInterface::class,
            WithCharsInterface::class,
            WithMinuteInterface::class,
            WithSecondInterface::class,
            WithPresentationInterface::class,
            WithImageToVideoInterface::class,
            WithVideoToVideoInterface::class,
            WithTextToSpeechInterface::class,
            WithSpeechToTextInterface::class,
            WithTextToVideoInterface::class,
            WithVisionPreviewInterface::class,
            WithPlagiarismInterface::class,
        ];
    }

    public function defaultCreditForDemo(): float
    {
        $driverClass = $this->driverClass();

        return match (true) {
            class_interface_exists($driverClass, WithImagesInterface::class) => 200,
            class_interface_exists($driverClass, WithWordsInterface::class),
            class_interface_exists($driverClass, WithCharsInterface::class)  => 5000,
            class_interface_exists($driverClass, WithMinuteInterface::class) => 20,
            class_interface_exists($driverClass, WithSecondInterface::class) => 8,
            class_interface_exists($driverClass, WithPresentationInterface::class),
            class_interface_exists($driverClass, WithVideoToVideoInterface::class),
            class_interface_exists($driverClass, WithImageToVideoInterface::class),
            class_interface_exists($driverClass, WithTextToSpeechInterface::class),
            class_interface_exists($driverClass, WithSpeechToTextInterface::class),
            class_interface_exists($driverClass, WithTextToVideoInterface::class),
            class_interface_exists($driverClass, WithVisionPreviewInterface::class),
            class_interface_exists($driverClass, WithPlagiarismInterface::class) => 100,
        };
    }

    public function isBetaEntity(): bool
    {
        return false;
    }

    public static function embedingModels(EngineEnum $engineEnum): array
    {
        return match ($engineEnum) {
            EngineEnum::ANTHROPIC => [
                self::VOYAGE_2,
                self::VOYAGE_CODE_2,
                self::VOYAGE_LARGE_2,
            ],
            EngineEnum::GEMINI => [
                self::GEMINI_TEXT_EMBEDDING_004,
                self::GEMINI_EMBEDDING_EXP,
            ],
            default => [
                self::TEXT_EMBEDDING_ADA_002,
                self::TEXT_EMBEDDING_3_SMALL,
                self::TEXT_EMBEDDING_3_LARGE,
            ]
        };
    }

    public function isEmbedding(): bool
    {
        return in_array($this, [
            self::TEXT_EMBEDDING_ADA_002,
            self::TEXT_EMBEDDING_3_SMALL,
            self::TEXT_EMBEDDING_3_LARGE,
            self::GEMINI_TEXT_EMBEDDING_004,
            self::GEMINI_EMBEDDING_EXP,
            self::VOYAGE_2,
            self::VOYAGE_CODE_2,
            self::VOYAGE_LARGE_2,
        ]);
    }

    public function isDeprecated(): bool
    {
        return in_array($this, [
            // OpenAI deprecated models
            self::DAVINCI,
            self::TEXT_DAVINCI_003,
            self::GPT_3_5_TURBO,
            self::GPT_3_5_TURBO_0125,
            self::GPT_3_5_TURBO_1106,
            self::GPT_4_1106_PREVIEW,
            self::GPT_4_0125_PREVIEW,
            self::GPT_4_O1_PREVIEW,
            self::GPT_4_O1_MINI,
            self::GPT_4_O_REALTIME_PREVIEW,
            self::DALL_E_2,
            self::DALL_E_3,
            self::SORA_2,
            self::SORA_2_PRO,
        ]);
    }

    public function isChatModel(): bool
    {
        if ($this->isEmbedding()) {
            return false;
        }

        if (in_array($this, [self::DAVINCI, self::TEXT_DAVINCI_003])) {
            return false;
        }

        return $this->tokenType() === AITokenType::WORD;
    }

    public static function reWriterModels(EngineEnum $engineEnum): array
    {
        return match ($engineEnum) {
            default => [
                self::DAVINCI,
                self::TEXT_DAVINCI_003,
                self::GPT_3_5_TURBO,
                self::GPT_3_5_TURBO_0125,
                self::GPT_4,
                self::GPT_4_1106_PREVIEW,
                self::GPT_4_0125_PREVIEW,
                self::GPT_4_TURBO,
                self::GPT_4_O,
                self::GPT_4_O_MINI,
                self::GPT_4_O_SEARCH_PREVIEW,
                self::GPT_4_O_MINI_SEARCH_PREVIEW,
                self::GPT_4_O1_PREVIEW,
                self::GPT_4_O1_MINI,
                self::GPT_O_1,
                self::GPT_O_03_mini,
                self::GPT_4_1,
                self::GPT_O_4_MINI,
                self::GPT_4_1_NANO,
                self::GPT_4_1_MINI,
                self::GPT_O_3,
                self::GPT_5,
                self::GPT_5_MINI,
                self::GPT_5_NANO,
                self::GPT_5_CHAT,
                self::GPT_5_PRO,
                self::GPT_5_1,
                self::GPT_5_1_CHAT,
                self::GPT_5_2,
                self::GPT_5_2_PRO,
                self::GPT_5_3_CHAT,
                self::GPT_5_4,
                self::GPT_5_4_MINI,
                self::GPT_5_4_NANO,
                self::GPT_5_5,
                self::GPT_5_6_SOL,
                self::GPT_5_6_TERRA,
                self::GPT_5_6_LUNA,
                self::DEEPSEEK_CHAT,
                self::DEEPSEEK_REASONER,
            ]
        };
    }

    public function keyAsString(): string
    {
        return (string) $this->name;
    }

    public function valueAsString(): string
    {
        return (string) $this->value;
    }
}
