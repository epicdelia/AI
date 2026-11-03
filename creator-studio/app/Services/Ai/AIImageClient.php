<?php

namespace App\Services\Ai;

use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Entity\Enums\EntityEnum;
use App\Services\Ai\Images\Contracts\ImageGeneratorInterface;
use App\Services\Ai\Images\FalAIImageService;
use App\Services\Ai\Images\OpenAIImageService;
use App\Services\Ai\Images\StableDiffusionImageService;
use BadMethodCallException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class AIImageClient
{
    /**
     * Generate images via the correct AI backend.
     *
     * @param  array  $options  Generation options
     *
     * @return array Raw image binary data or async response
     *
     * @throws Throwable
     */
    public static function generate(array $options): array
    {
        try {
            $options = self::appendStyleToPrompt($options);

            return static::resolveService($options['model'] ?? '')->generate($options);
        } catch (Throwable $e) {
            Log::error('AI image generation failed in AIImageClient', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'options' => $options,
            ]);

            throw $e;
        }
    }

    /**
     * Check generation status for async services
     */
    public static function checkStatus(string $requestId, string $entity): ?array
    {
        $entityEnum = EntityEnum::fromSlug($entity) ?? throw new InvalidArgumentException("Unsupported entity: {$entity}");
        $service = static::resolveServiceByEngine($entityEnum->engine());

        if (! $service->supportsAsync()) {
            throw new BadMethodCallException("Entity {$entity} does not support async generation");
        }

        return $service->checkStatus($requestId, $entityEnum);
    }

    protected static function resolveService(string $modelSlug): ImageGeneratorInterface
    {
        $entity = EntityEnum::fromSlug($modelSlug) ?? EntityEnum::DALL_E_2;

        return static::resolveServiceByEngine($entity->engine());
    }

    protected static function resolveServiceByEngine(EngineEnum $engine): ImageGeneratorInterface
    {
        return match ($engine) {
            EngineEnum::OPEN_AI          => app(OpenAIImageService::class),
            EngineEnum::STABLE_DIFFUSION => app(StableDiffusionImageService::class),
            EngineEnum::FAL_AI           => app(FalAIImageService::class),
            default                      => throw new InvalidArgumentException("Unsupported engine: {$engine->value}"),
        };
    }

    protected static function appendStyleToPrompt(array $options): array
    {
        if (! empty($options['style'])) {
            $options['prompt'] .= '. In ' . $options['style'] . ' style';
        }

        return $options;
    }
}
