<?php

namespace App\Services\Ai\Images;

use App\Domains\Entity\Enums\EntityEnum;
use App\Helpers\Classes\ApiHelper;
use App\Services\Ai\Images\Contracts\ImageGeneratorInterface;
use BadMethodCallException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

class StableDiffusionImageService implements ImageGeneratorInterface
{
    private const BASE_URL = 'https://api.stability.ai/v2beta/stable-image/generate/';

    public function generate(array $options): array
    {
        $model = EntityEnum::fromSlug($options['model'] ?? EntityEnum::SD_3_5_LARGE->slug()) ?? EntityEnum::SD_3_5_LARGE;

        // Handle Stability API models
        return $this->generateWithStabilityApi($options, $model);
    }

    public function supportsAsync(): bool
    {
        return false;
    }

    public function checkStatus(string $requestId, EntityEnum $entityEnum): array
    {
        throw new BadMethodCallException('Stable Diffusion does not support async generation');
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    protected function generateWithStabilityApi(array $options, EntityEnum $model): array
    {
        $payload = $this->buildPayload($options, $model);
        $client = $this->setStableClient($model);
        $endpoint = match ($model) {
            EntityEnum::ULTRA => 'ultra',
            EntityEnum::CORE  => 'core',
            default           => 'sd3', // Default to SD3 for other models
        };

        $response = $client->post($endpoint, [
            'headers'   => ['accept' => 'application/json'],
            'multipart' => $payload,
        ]);

        $body = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException($body['message'] ?? __('Image generation failed'));
        }

        // Extract image data
        return [base64_decode($body['image'])];
    }

    protected function buildPayload(array $options, EntityEnum $model): array
    {
        $prompt = $options['prompt']
            ?? throw new InvalidArgumentException('Prompt is required');

        // Base JSON payload
        $payload = [
            'prompt' => $prompt,
        ];

        // Normalize image reference
        $ref = $options['image_reference'] ?? null;
        $ref = is_array($ref) ? ($ref[0] ?? null) : $ref;

        $hasRef = ! empty($ref);

        if ($hasRef) {
            // Handle file path or URL
            if (filter_var($ref, FILTER_VALIDATE_URL)) {
                $binary = @file_get_contents($ref);
            } elseif (file_exists($ref)) {
                $binary = file_get_contents($ref);
            } elseif (file_exists(public_path($ref))) {
                $binary = file_get_contents(public_path($ref));
            } else {
                $binary = $ref;
            }

            if ($binary === false) {
                throw new RuntimeException("Failed to read image file: {$ref}");
            }

            $payload['image'] = $binary;
            $payload['strength'] = 0.97;
        }

        if (! in_array($model, [EntityEnum::ULTRA, EntityEnum::CORE], true)) {
            $payload['mode'] = $hasRef ? 'image-to-image' : 'text-to-image';
        }

        return $this->buildMultipart($payload);
    }

    protected function buildMultipart(array $payload): array
    {
        $parts = [];

        foreach ($payload as $key => $value) {
            $part = ['name' => $key];

            // Handle image binary data specially
            if ($key === 'image' && is_string($value)) {
                $part['contents'] = Utils::streamFor($value);
                $part['filename'] = 'image.png';
            } else {
                $part['contents'] = $value;
            }

            $parts[] = $part;
        }

        return $parts;
    }

    protected function setStableClient(EntityEnum $model): Client
    {
        $apiKey = ApiHelper::setStableDiffusionKey();

        return new Client([
            'base_uri' => self::BASE_URL,
            'headers'  => [
                'Authorization' => "Bearer {$apiKey}",
                'Accept'        => 'application/json',
            ],
        ]);
    }
}
