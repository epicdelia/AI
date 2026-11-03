<?php

declare(strict_types=1);

namespace App\Domains\Engine\Enums;

use App\Domains\Engine\Drivers\AiMlMinimaxAIEngineDriver;
use App\Domains\Engine\Drivers\AnthropicEngineDriver;
use App\Domains\Engine\Drivers\AzureEngineDriver;
use App\Domains\Engine\Drivers\CaptionsEngineDriver;
use App\Domains\Engine\Drivers\ClipDropEngineDriver;
use App\Domains\Engine\Drivers\CreatifyEngineDriver;
use App\Domains\Engine\Drivers\DeepSeekAIEngineDriver;
use App\Domains\Engine\Drivers\ElevenlabsEngineDriver;
use App\Domains\Engine\Drivers\FallAIEngineDriver;
use App\Domains\Engine\Drivers\FreepikEngineDriver;
use App\Domains\Engine\Drivers\GammaAIEngineDriver;
use App\Domains\Engine\Drivers\GeminiEngineDriver;
use App\Domains\Engine\Drivers\GoogleEngineDriver;
use App\Domains\Engine\Drivers\HeygenEngineDriver;
use App\Domains\Engine\Drivers\KlapEngineDriver;
use App\Domains\Engine\Drivers\NovitaEngineDriver;
use App\Domains\Engine\Drivers\OpenAIEngineDriver;
use App\Domains\Engine\Drivers\OpenRouterEngineDriver;
use App\Domains\Engine\Drivers\PebblelyEngineDriver;
use App\Domains\Engine\Drivers\PerplexityEngineDriver;
use App\Domains\Engine\Drivers\PexelsEngineDriver;
use App\Domains\Engine\Drivers\PiAPIEngineDriver;
use App\Domains\Engine\Drivers\PixabayEngineDriver;
use App\Domains\Engine\Drivers\PlagiarismCheckEngineDriver;
use App\Domains\Engine\Drivers\SerperEngineDriver;
use App\Domains\Engine\Drivers\SpeechifyEngineDriver;
use App\Domains\Engine\Drivers\StableDiffusionEngineDriver;
use App\Domains\Engine\Drivers\SynthesiaEngineDriver;
use App\Domains\Engine\Drivers\TogetherEngineDriver;
use App\Domains\Engine\Drivers\TopviewEngineDriver;
use App\Domains\Engine\Drivers\UnsplashEngineDriver;
use App\Domains\Engine\Drivers\VizardEngineDriver;
use App\Domains\Engine\Drivers\XAIEngineDriver;
use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Models\Entity;
use App\Enums\Contracts;
use App\Enums\Traits\EnumTo;
use App\Enums\Traits\SluggableEnumTrait;
use App\Enums\Traits\StringBackedEnumTrait;
use App\Models\Setting;
use App\Models\SettingTwo;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

enum EngineEnum: string implements Contracts\WithStringBackedEnum
{
    use EnumTo;
    use SluggableEnumTrait;
    use StringBackedEnumTrait;

    case OPEN_AI = 'openai';

    case PI_API = 'piapi';

    case DEEP_SEEK = 'deep_seek';

    case STABLE_DIFFUSION = 'stable_diffusion';

    case ANTHROPIC = 'anthropic';

    case GEMINI = 'gemini';

    case UNSPLASH = 'unsplash';

    case PEXELS = 'pexels';

    case PIXABAY = 'pixabay';

    case ELEVENLABS = 'elevenlabs';

    case GOOGLE = 'google';

    case AZURE = 'azure';

    case Speechify = 'speechify';

    case SERPER = 'serper';
    case PERPLEXITY = 'perplexity';

    case CLIPDROP = 'clipdrop';

    case NOVITA = 'novita';

    case FREEPIK = 'freepik';

    case PLAGIARISM_CHECK = 'plagiarism_check';

    case SYNTHESIA = 'synthesia';
    case HEYGEN = 'heygen';

    case PEBBLELY = 'pebblely';

    case FAL_AI = 'fal_ai';

    case GAMMA_AI = 'gamma_ai';
    case X_AI = 'x_ai';

    case AI_ML_MINIMAX = 'minimax';

    case OPEN_ROUTER = 'open_router';

    case TOGETHER = 'together';

    case CREATIFY = 'creatify';

    case TOPVIEW = 'topview';

    case VIZARD = 'vizard';
    case KLAP = 'klap';

    case CAPTIONS = 'captions';
    case DEEPSEEK = 'deepseek';
    case XAI = 'xai';
    case OPENROUTER = 'openrouter';
    case ASSEMBLYAI = 'assemblyai';
    case CUSTOM = 'custom';

    public const OPENAI = self::OPEN_AI;

    public function label(): string
    {
        return match ($this) {
            self::TOGETHER               => __('Together'),
            self::OPEN_AI                => __('OpenAI'),
            self::DEEP_SEEK              => __('Deepseek'),
            self::STABLE_DIFFUSION       => __('Stable Diffusion'),
            self::ANTHROPIC              => __('Anthropic'),
            self::GEMINI                 => __('Gemini'),
            self::UNSPLASH               => __('Unsplash'),
            self::PEXELS                 => __('Pexels'),
            self::PIXABAY                => __('Pixabay'),
            self::ELEVENLABS             => __('Elevenlabs'),
            self::GOOGLE                 => __('Google TTS'),
            self::AZURE                  => __('Azure'),
            self::Speechify              => __('Speechify TTS'),
            self::SERPER                 => __('Serper'),
            self::PERPLEXITY             => __('Perplexity'),
            self::CLIPDROP               => __('Clipdrop'),
            self::NOVITA                 => __('Novita'),
            self::FREEPIK                => __('Freepik'),
            self::PLAGIARISM_CHECK       => __('Plagiarism Check'),
            self::SYNTHESIA              => __('Synthesia'),
            self::HEYGEN                 => __('Heygen'),
            self::PEBBLELY               => __('Pebblely'),
            self::FAL_AI                 => __('Fal AI'),
            self::GAMMA_AI               => __('Gamma AI'),
            self::X_AI                   => __('X AI'),
            self::PI_API                 => __('PiAPI'),
            self::AI_ML_MINIMAX          => __('AI/ML Minimax'),
            self::OPEN_ROUTER            => __('Open Router'),
            self::CREATIFY               => __('Creatify'),
            self::TOPVIEW                => __('Topview'),
            self::VIZARD                 => __('Vizard'),
            self::KLAP                   => __('Klap'),
            self::CAPTIONS               => __('Captions'),
            self::DEEPSEEK               => __('Deepseek'),
            self::XAI                    => __('X AI'),
            self::OPENROUTER             => __('Open Router'),
            self::ASSEMBLYAI             => __('AssemblyAI LLM Gateway'),
            self::CUSTOM                 => __('Custom (OpenAI-compatible)'),
        };
    }

    public function driverClass(): string
    {
        return match ($this) {
            self::OPEN_AI          => OpenAIEngineDriver::class,
            self::PI_API           => PiAPIEngineDriver::class,
            self::DEEP_SEEK, self::DEEPSEEK => DeepSeekAIEngineDriver::class,
            self::STABLE_DIFFUSION => StableDiffusionEngineDriver::class,
            self::ANTHROPIC        => AnthropicEngineDriver::class,
            self::GEMINI           => GeminiEngineDriver::class,
            self::UNSPLASH         => UnsplashEngineDriver::class,
            self::PEXELS           => PexelsEngineDriver::class,
            self::PIXABAY          => PixabayEngineDriver::class,
            self::ELEVENLABS       => ElevenlabsEngineDriver::class,
            self::GOOGLE           => GoogleEngineDriver::class,
            self::AZURE            => AzureEngineDriver::class,
            self::Speechify        => SpeechifyEngineDriver::class,
            self::SERPER           => SerperEngineDriver::class,
            self::PERPLEXITY       => PerplexityEngineDriver::class,
            self::CLIPDROP         => ClipDropEngineDriver::class,
            self::NOVITA           => NovitaEngineDriver::class,
            self::FREEPIK          => FreepikEngineDriver::class,
            self::PLAGIARISM_CHECK => PlagiarismCheckEngineDriver::class,
            self::SYNTHESIA        => SynthesiaEngineDriver::class,
            self::HEYGEN           => HeygenEngineDriver::class,
            self::PEBBLELY         => PebblelyEngineDriver::class,
            self::FAL_AI           => FallAIEngineDriver::class,
            self::GAMMA_AI         => GammaAIEngineDriver::class,
            self::CREATIFY         => CreatifyEngineDriver::class,
            self::TOPVIEW          => TopviewEngineDriver::class,
            self::VIZARD           => VizardEngineDriver::class,
            self::KLAP             => KlapEngineDriver::class,
            self::X_AI, self::XAI  => XAIEngineDriver::class,
            self::AI_ML_MINIMAX    => AiMlMinimaxAIEngineDriver::class,
            self::OPEN_ROUTER, self::OPENROUTER => OpenRouterEngineDriver::class,
            self::TOGETHER         => TogetherEngineDriver::class,
            self::CAPTIONS         => CaptionsEngineDriver::class,
            self::ASSEMBLYAI, self::CUSTOM => OpenAIEngineDriver::class,
        };
    }

    public function models(): array
    {
        return collect(EntityEnum::cases())->filter(fn ($model) => $model->engine() === $this)->toArray();
    }

    /**
     * @return Collection<Entity>
     */
    public function getModels(): Collection
    {
        return Entity::byEngine($this)->get();
    }

    /**
     * @return Collection<Entity>
     */
    public function getEnabledModels(): Collection
    {
        return Cache::remember('engine_models_' . $this->value, now()->addMinutes(5), function () {
            return Entity::query()->isEnabled()->byEngine($this)->get();
        });
    }

    private function getDefaultOpenAiImageModel($settings_two): string
    {
        return match ($settings_two?->dalle) {
            'dalle3' => EntityEnum::DALL_E_3->slug(),
            'dalle2' => EntityEnum::DALL_E_2->slug(),
            default  => $settings_two?->dalle ?? 'dall-e-2',
        };
    }

    /**
     * @throws Exception
     */
    public function getDefaultModels(?Setting $setting, ?SettingTwo $settingTwo): array
    {
        return match ($this) {
            self::OPEN_AI          => [
                EntityEnum::fromSlug($setting?->openai_default_model ?? EntityEnum::GPT_5_MINI->slug()),
                EntityEnum::fromSlug($this->getDefaultOpenAiImageModel($settingTwo)),
                EntityEnum::TTS_1_HD,
                EntityEnum::TTS_1,
                EntityEnum::WHISPER_1,
                EntityEnum::TEXT_EMBEDDING_3_SMALL,
                ...(EntityEnum::fromSlug($setting?->openai_default_model ?? EntityEnum::GPT_5_MINI->slug()) !== EntityEnum::GPT_5_MINI
                    ? [EntityEnum::GPT_5_MINI]
                    : []),
                EntityEnum::GPT_REALTIME,
                EntityEnum::GPT_4_O_REALTIME_PREVIEW,
                EntityEnum::GPT_4_O_SEARCH_PREVIEW,
                EntityEnum::GPT_4_O_MINI_SEARCH_PREVIEW,
                EntityEnum::GPT_IMAGE_1,
                EntityEnum::GPT_IMAGE_1_5,
                EntityEnum::GPT_IMAGE_2,
                EntityEnum::SORA_2,
                EntityEnum::SORA_2_PRO,
            ],
            self::STABLE_DIFFUSION => [
                EntityEnum::fromSlug($settingTwo?->stable_diffusion_default_model ?? $settingTwo?->stablediffusion_default_model ?? EntityEnum::STABLE_DIFFUSION_XL_1024_V_1_0->slug()),
                EntityEnum::IMAGE_TO_VIDEO,
            ],
            self::ANTHROPIC        => [EntityEnum::fromSlug(setting('anthropic_default_model', EntityEnum::CLAUDE_3_OPUS->slug()))],
            self::OPEN_ROUTER      => [EntityEnum::fromSlug(setting('default_open_router_model', EntityEnum::PERPLEXITY_LLAMA_31_SONAR_8B->slug()))],
            self::GEMINI           => [
                EntityEnum::fromSlug(setting('gemini_default_model', EntityEnum::GEMINI_3_FLASH->slug())),
                EntityEnum::LYRIA_3_CLIP,
                EntityEnum::LYRIA_3_PRO,
                EntityEnum::GEMINI_EMBEDDING_EXP,
                EntityEnum::GEMINI_TEXT_EMBEDDING_004,
                EntityEnum::GEMINI_3_1_FLASH_LIVE_PREVIEW,
            ],
            self::DEEP_SEEK        => [EntityEnum::fromSlug(setting('deepseek_default_model', EntityEnum::DEEPSEEK_CHAT->slug()))],
            self::ELEVENLABS       => [
                EntityEnum::ELEVENLABS_AI_MUSIC,
                EntityEnum::ELEVENLABS,
                EntityEnum::ELEVENLABS_V3,
                EntityEnum::ELEVENLABS_VOICE_CHATBOT,
                EntityEnum::ISOLATOR,
            ],
            self::PI_API 		        => [EntityEnum::MIDJOURNEY],
            self::GAMMA_AI         => [EntityEnum::GAMMA_AI],
            self::FAL_AI           => [
                EntityEnum::fromSlug(setting('fal_ai_default_model', EntityEnum::FLUX_PRO->slug())),
                EntityEnum::VEO_2,
                EntityEnum::VEO_3,
                EntityEnum::VEO_3_FAST,
                EntityEnum::VEO_3_1_TEXT_TO_VIDEO,
                EntityEnum::VEO_3_1_IMAGE_TO_VIDEO,
                EntityEnum::VEO_3_1_TEXT_TO_VIDEO_FAST,
                EntityEnum::VEO_3_1_IMAGE_TO_VIDEO_FAST,
                EntityEnum::VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO,
                EntityEnum::VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO_FAST,
                EntityEnum::VEO_3_1_REFERENCE_TO_VIDEO,
                EntityEnum::VEO_3_1_LITE_TEXT_TO_VIDEO,
                EntityEnum::VEO_3_1_LITE_IMAGE_TO_VIDEO,
                EntityEnum::VEO_3_1_LITE_FIRST_LAST_FRAME_TO_VIDEO,
                EntityEnum::VEED,
                EntityEnum::VIDEO_BACKGROUND_REMOVAL,
                EntityEnum::KLING,
                EntityEnum::KLING_2_1,
                EntityEnum::KLING_2_5_TURBO_PRO_TTV,
                EntityEnum::KLING_2_5_TURBO_PRO_ITV,
                EntityEnum::KLING_2_5_TURBO_STANDARD_ITV,
                EntityEnum::KLING_2_6_PRO_ITV,
                EntityEnum::KLING_2_6_PRO_TTV,
                EntityEnum::KLING_2_6_STANDARD_MOTION_CONTROL,
                EntityEnum::KLING_2_6_PRO_MOTION_CONTROL,
                EntityEnum::KLING_3_STANDARD_ITV,
                EntityEnum::KLING_3_STANDARD_TTV,
                EntityEnum::KLING_3_PRO_TTV,
                EntityEnum::KLING_3_PRO_ITV,
                EntityEnum::KLING_IMAGE,
                EntityEnum::KLING_VIDEO,
                EntityEnum::LUMA_DREAM_MACHINE,
                EntityEnum::MINIMAX,
                EntityEnum::IDEOGRAM,
                EntityEnum::VIDEO_UPSCALER,
                EntityEnum::COGVIDEOX_5B,
                EntityEnum::ANIMATEDIFF_V2V,
                EntityEnum::FAST_ANIMATEDIFF_TURBO,
                EntityEnum::FLUX_PRO_KONTEXT_MAX_MULTI,
                EntityEnum::FLUX_PRO_KONTEXT,
                EntityEnum::FLUX_PRO_KONTEXT_TEXT_TO_IMAGE,
                EntityEnum::FLUX_2_FLEX,
                EntityEnum::FLUX_2_FLEX_EDIT,
                EntityEnum::NANO_BANANA,
                EntityEnum::NANO_BANANA_EDIT,
                EntityEnum::NANO_BANANA_PRO,
                EntityEnum::NANO_BANANA_PRO_EDIT,
                EntityEnum::NANO_BANANA_2,
                EntityEnum::NANO_BANANA_2_EDIT,
                EntityEnum::GROK_IMAGINE_IMAGE,
                EntityEnum::GROK_IMAGINE_IMAGE_EDIT,
                EntityEnum::GROK_IMAGINE_VIDEO_TTV,
                EntityEnum::GROK_IMAGINE_VIDEO_ITV,
                EntityEnum::SEEDREAM_4,
                EntityEnum::SEEDREAM_4_EDIT,
                EntityEnum::SEEDANCE_2_TTV,
                EntityEnum::SEEDANCE_2_ITV,
                EntityEnum::SEEDANCE_2_RTV,
                EntityEnum::SEEDANCE_2_FAST_TTV,
                EntityEnum::SEEDANCE_2_FAST_ITV,
                EntityEnum::SEEDANCE_2_FAST_RTV,
                EntityEnum::GEMINI_OMNI_FLASH_TTV,
                EntityEnum::GEMINI_OMNI_FLASH_ITV,
                EntityEnum::GEMINI_OMNI_FLASH_RTV,
            ],
            self::CREATIFY => [EntityEnum::AD_MARKETING_VIDEO],
            self::TOPVIEW  => [EntityEnum::AD_MARKETING_VIDEO_TOPVIEW],
            self::VIZARD   => [EntityEnum::AI_CLIP_VIZARD],
            self::KLAP     => [EntityEnum::AI_CLIP_KLAP],
            self::X_AI     => [EntityEnum::fromSlug(setting('xai_default_model', EntityEnum::GROK_2_1212->slug()))],

            self::AI_ML_MINIMAX     => [EntityEnum::MUSIC_01],
            self::UNSPLASH          => [EntityEnum::UNSPLASH],
            self::PEXELS            => [EntityEnum::PEXELS],
            self::PIXABAY           => [EntityEnum::PIXABAY],
            self::GOOGLE            => [EntityEnum::GOOGLE],
            self::AZURE             => [
                EntityEnum::AZURE,
                EntityEnum::AZURE_OPENAI,
            ],
            self::Speechify         => [EntityEnum::Speechify],
            self::SERPER            => [EntityEnum::SERPER],
            self::PERPLEXITY        => [EntityEnum::PERPLEXITY],
            self::CLIPDROP          => [EntityEnum::CLIPDROP],
            self::NOVITA            => [EntityEnum::NOVITA],
            self::FREEPIK           => [EntityEnum::FREEPIK],
            self::PLAGIARISM_CHECK  => [EntityEnum::PLAGIARISMCHECK],
            self::SYNTHESIA         => [EntityEnum::SYNTHESIA],
            self::HEYGEN            => [EntityEnum::HEYGEN],
            self::PEBBLELY          => [EntityEnum::PEBBLELY],
            self::TOGETHER          => [EntityEnum::BLACK_FOREST_LABS_FLUX_1_SCHNELL],
            self::CAPTIONS          => [EntityEnum::AI_CAPTIONS],
            default                 => throw new Exception('No default model found for engine ' . $this->value),
        };
    }

    public function getDefaultWordModel(mixed $setting = null): EntityEnum
    {
        return match ($this) {
            self::OPEN_AI          => EntityEnum::fromSlug($setting?->openai_default_model ?? EntityEnum::GPT_5_MINI->slug()),
            self::ANTHROPIC        => EntityEnum::fromSlug(setting('anthropic_default_model', EntityEnum::CLAUDE_3_OPUS->slug())),
            self::GEMINI           => EntityEnum::fromSlug(setting('gemini_default_model', EntityEnum::GEMINI_3_FLASH->slug())),
            self::DEEP_SEEK        => EntityEnum::fromSlug(setting('deepseek_default_model', EntityEnum::DEEPSEEK_CHAT->slug())),
            self::X_AI             => EntityEnum::fromSlug(setting('xai_default_model', EntityEnum::GROK_2_1212->slug())),
            default                => throw new Exception('No default model found for engine ' . $this->value),
        };
    }

    public function getDefaultImageModel(): ?EntityEnum
    {
        $settingTwo = SettingTwo::getCache();

        return match ($this) {
            self::OPEN_AI          => EntityEnum::fromSlug($this->getDefaultOpenAiImageModel($settingTwo)),
            self::STABLE_DIFFUSION => EntityEnum::fromSlug($settingTwo?->stable_diffusion_default_model ?? $settingTwo?->stablediffusion_default_model ?? EntityEnum::STABLE_DIFFUSION_XL_1024_V_1_0->slug()),
            self::FAL_AI           => EntityEnum::fromSlug(setting('fal_ai_default_model', EntityEnum::FLUX_PRO->slug())),
            self::PI_API           => EntityEnum::MIDJOURNEY,
            default                => null,
        };
    }

    public function getDefaultAWImageModel(?SettingTwo $settingTwo): EntityEnum
    {
        return match ($this) {
            self::OPEN_AI          => EntityEnum::fromSlug($this->getDefaultOpenAiImageModel($settingTwo)),
            self::STABLE_DIFFUSION => EntityEnum::fromSlug($settingTwo?->stable_diffusion_default_model ?? $settingTwo?->stablediffusion_default_model ?? EntityEnum::STABLE_DIFFUSION_XL_1024_V_1_0->slug()),
            self::UNSPLASH         => EntityEnum::UNSPLASH,
            self::PEXELS           => EntityEnum::PEXELS,
            self::PIXABAY          => EntityEnum::PIXABAY,
            default                => throw new Exception('No default model found for engine ' . $this->value),
        };
    }

    /**
     * @throws Exception
     */
    public function defaultEntitiesCount(): int
    {
        return count($this->getDefaultModels(Setting::getCache(), SettingTwo::getCache()));
    }

    /**
     * @throws Exception
     */
    public function getListableActiveModels(Setting $setting, SettingTwo $settingTwo): Collection
    {
        $defaultModelKeys = collect($this->getDefaultModels($setting, $settingTwo))->map(fn ($model) => $model->slug());

        // return all engine models without default models
        return $this->getEnabledModels()
            ->filter(fn (Entity $model) => EntityEnum::listableCases()->contains($model->key))
            ->filter(fn (Entity $model) => ! $defaultModelKeys->contains($model->key->slug()));
    }

    public static function whereHasEnabledModels(): array
    {
        return collect(self::cases())->filter(fn (EngineEnum $engine) => $engine->getEnabledModels()->isNotEmpty())->toArray();
    }

    public static function getNestedPlanLimits(): array
    {
        return collect(self::cases())->mapWithKeys(function (EngineEnum $engine) {
            return [$engine->slug() => EntityEnum::getPlanLimits($engine)->toArray()];
        })->toArray();
    }

    public static function rules(string $prefix = '', array $rules = []): array
    {
        return collect(self::cases())
            ->mapWithKeys(function (EngineEnum $engine) use ($prefix, $rules) {
                return [$prefix . $engine->slug() => collect($engine->models())->mapWithKeys(function (EntityEnum $model) use ($rules) {
                    return [$model->slug() => [
                        'credit'      => $rules[0],
                        'isUnlimited' => $rules[1],
                    ]];
                })->toArray()];
            })->dot()->toArray();
    }

    public function baseUrl(): string
    {
        return match ($this) {
            self::OPEN_AI => 'https://api.openai.com/v1',
            self::ANTHROPIC => 'https://api.anthropic.com/v1',
            self::GEMINI => 'https://generativelanguage.googleapis.com/v1beta/openai',
            self::OPEN_ROUTER, self::OPENROUTER => 'https://openrouter.ai/api/v1',
            self::DEEP_SEEK, self::DEEPSEEK => 'https://api.deepseek.com/v1',
            self::X_AI, self::XAI => 'https://api.x.ai/v1',
            self::PERPLEXITY => 'https://api.perplexity.ai',
            self::TOGETHER => 'https://api.together.xyz/v1',
            self::ASSEMBLYAI => 'https://llm-gateway.assemblyai.com/v1',
            self::FAL_AI => 'https://queue.fal.run',
            self::ELEVENLABS => 'https://api.elevenlabs.io/v1',
            self::VIZARD => 'https://elb-api.vizard.ai/hvizard-server-front/open-api/v1',
            default => '',
        };
    }

    public function defaultModel(): string
    {
        return match ($this) {
            self::OPEN_AI => 'gpt-6-astra',
            self::ANTHROPIC => 'claude-sonnet-5-5',
            self::GEMINI => 'gemini-4-argon',
            self::OPEN_ROUTER, self::OPENROUTER => 'anthropic/claude-sonnet-5-5',
            self::DEEP_SEEK, self::DEEPSEEK => 'deepseek-v4-pro',
            self::X_AI, self::XAI => 'grok-4-6',
            self::PERPLEXITY => 'sonar',
            self::TOGETHER => 'meta-llama/Llama-3.3-70B-Instruct-Turbo',
            self::ASSEMBLYAI => 'gpt-6-astra',
            self::FAL_AI => 'flux-pro',
            self::ELEVENLABS => 'eleven_v4_turbo',
            default => '',
        };
    }

    public function keyUrl(): ?string
    {
        return match ($this) {
            self::OPEN_AI => 'https://platform.openai.com/api-keys',
            self::ANTHROPIC => 'https://console.anthropic.com/settings/keys',
            self::GEMINI => 'https://aistudio.google.com/apikey',
            self::OPEN_ROUTER, self::OPENROUTER => 'https://openrouter.ai/keys',
            self::DEEP_SEEK, self::DEEPSEEK => 'https://platform.deepseek.com/api_keys',
            self::X_AI, self::XAI => 'https://console.x.ai',
            self::PERPLEXITY => 'https://www.perplexity.ai/settings/api',
            self::TOGETHER => 'https://api.together.ai/settings/api-keys',
            self::ASSEMBLYAI => 'https://www.assemblyai.com/dashboard/api-keys',
            self::FAL_AI => 'https://fal.ai/dashboard/keys',
            self::ELEVENLABS => 'https://elevenlabs.io/app/settings/api-keys',
            self::VIZARD => 'https://vizard.ai/api',
            default => null,
        };
    }

    public function canUseAssemblyKey(): bool
    {
        return $this === self::ASSEMBLYAI;
    }

    public static function fromBaseUrl(?string $url): self
    {
        $url = (string)$url;
        foreach (self::cases() as $case) {
            // MagicAI's duplicate cases (OPEN_ROUTER, DEEP_SEEK, X_AI) share a base URL with the studio's
            // own engines; an install saved by URL always maps to the studio engine the Settings page lists.
            if (in_array($case, [self::CUSTOM, self::OPEN_ROUTER, self::DEEP_SEEK, self::X_AI], true)) {
                continue;
            }
            if ($case->baseUrl() !== '' && str_starts_with(rtrim($url, '/'), $case->baseUrl())) {
                return $case;
            }
        }
        if (str_contains($url, 'assemblyai.com')) {
            return self::ASSEMBLYAI;
        }

        return $url === '' ? self::GEMINI : self::CUSTOM;
    }

    public static function catalog(): array
    {
        return array_map(fn (self $e) => [
            'value' => $e->value,
            'label' => $e->label(),
            'base_url' => $e->baseUrl(),
            'default_model' => $e->defaultModel(),
            'key_url' => $e->keyUrl(),
        ], [
            self::OPEN_AI,
            self::ANTHROPIC,
            self::GEMINI,
            self::OPEN_ROUTER,
            self::DEEP_SEEK,
            self::X_AI,
            self::PERPLEXITY,
            self::TOGETHER,
            self::FAL_AI,
            self::ELEVENLABS,
            self::VIZARD,
            self::ASSEMBLYAI,
            self::CUSTOM,
        ]);
    }
}
