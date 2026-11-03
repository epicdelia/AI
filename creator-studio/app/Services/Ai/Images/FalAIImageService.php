<?php

namespace App\Services\Ai\Images;

use App\Domains\Engine\Services\FalAIService as FalAIApiService;
use App\Domains\Entity\Enums\EntityEnum;
use App\Services\Ai\Images\Contracts\ImageGeneratorInterface;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class FalAIImageService implements ImageGeneratorInterface
{
    protected FalAIApiService $falService;

    public function __construct(FalAIApiService $falService)
    {
        $this->falService = $falService;
    }

    public function generate(array $options): array
    {
        $model = EntityEnum::fromSlug($options['model'] ?? 'flux-pro') ?? EntityEnum::FLUX_PRO;
        $prompt = $options['prompt'] ?? throw new InvalidArgumentException('Prompt is required');

        if (FalAIApiService::isGrokModel($model)) {
            return $this->generateGrokSync($prompt, $model, $options);
        }

        // Generate request ID
        $requestId = match ($model) {
            EntityEnum::IDEOGRAM => $this->falService->ideogramGenerate($prompt, $model, $options),
            EntityEnum::FLUX_PRO_KONTEXT,
            EntityEnum::FLUX_PRO_KONTEXT_MAX_MULTI => $this->falService->generateKontext(
                $prompt,
                $model,
                $options['image_src'] ?? null
            ),
            default => $this->falService->generate($prompt, $model, $options),
        };

        // Return placeholder for async processing
        return [
            'request_id' => $requestId,
            'status'     => 'IN_QUEUE',
            'model'      => $model->value,
        ];
    }

    public function supportsAsync(): bool
    {
        return true;
    }

    public function checkStatus(string $requestId, EntityEnum $entityEnum): ?array
    {
        return $this->falService->check($requestId, $entityEnum);
    }

    private function generateGrokSync(string $prompt, EntityEnum $model, array $options): array
    {
        $result = FalAIApiService::generateGrokSync($prompt, $model, $options);
        $imageUrl = (string) data_get($result, 'images.0.url', '');

        if ($imageUrl === '') {
            throw new RuntimeException(__('Image generation failed.'));
        }

        $response = Http::timeout(120)->get($imageUrl);
        if (! $response->successful()) {
            throw new RuntimeException(__('Failed to download generated image.'));
        }

        return [$response->body()];
    }
}
