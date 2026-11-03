<?php

declare(strict_types=1);

namespace App\Domains\Engine\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Helpers\Classes\ApiHelper;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FalAIService
{
    public const GENERATE_ENDPOINT = 'https://queue.fal.run/fal-ai/%s';

    public const CHECK_ENDPOINT = 'https://queue.fal.run/fal-ai/%s/requests/%s';

    public const SYNC_ENDPOINT = 'https://fal.run/%s';

    public const HAIPER_URL = 'https://queue.fal.run/fal-ai/haiper-video-v2/image-to-video';

    public const IDEOGRAM_URL = 'https://queue.fal.run/fal-ai/ideogram/v2';

    public const KLING_URL = 'https://queue.fal.run/fal-ai/kling-video/v1/standard/text-to-video';

    public const KLING_V21_URL = 'https://queue.fal.run/fal-ai/kling-video/v2.1/master/image-to-video';

    public const KLING_IMAGE_URL = 'https://queue.fal.run/fal-ai/kling-video/v1.6/pro/image-to-video';

    public const LUMA_URL = 'https://queue.fal.run/fal-ai/luma-dream-machine';

    public const MINIMAX_URL = 'https://queue.fal.run/fal-ai/minimax-video';

    public const VEO_2_URL = 'https://queue.fal.run/fal-ai/veo2';

    public static function ratio(): null|array|string
    {
        $ratio = request('image_ratio');

        if (! is_string($ratio)) {
            return null;
        }

        $explode = explode('x', $ratio);

        if (! is_array($explode)) {
            return null;
        }

        if ((isset($explode[0]) && is_numeric($explode[0])) && (isset($explode[1]) && is_numeric($explode[1]))) {
            return [
                'width'  => (int) $explode[0],
                'height' => (int) $explode[1],
            ];
        }

        return $ratio;
    }

    /**
     * Get the correct ratio parameter name based on the entity.
     *
     * Different FAL AI models use different parameter names:
     * - FLUX Pro, SeeDream: use 'image_size' with named values (portrait_16_9, landscape_4_3, etc.)
     * - Nano Banana, Ideogram: use 'aspect_ratio' with colon format (16:9, 9:16, etc.)
     */
    public static function getRatioParameterName(?EntityEnum $entity): string
    {
        $aspectRatioModels = [
            EntityEnum::NANO_BANANA,
            EntityEnum::NANO_BANANA_EDIT,
            EntityEnum::NANO_BANANA_PRO,
            EntityEnum::NANO_BANANA_PRO_EDIT,
            EntityEnum::NANO_BANANA_2,
            EntityEnum::NANO_BANANA_2_EDIT,
            EntityEnum::GROK_IMAGINE_IMAGE,
            EntityEnum::GROK_IMAGINE_IMAGE_EDIT,
            EntityEnum::IMAGEN_4,
        ];

        if ($entity && in_array($entity, $aspectRatioModels, true)) {
            return 'aspect_ratio';
        }

        return 'image_size';
    }

    public static function generateKontext($prompt, EntityEnum $entity = EntityEnum::FLUX_PRO, array $images = [])
    {
        $url = sprintf(self::GENERATE_ENDPOINT, $entity->value);

        $images = self::createImageUrl($images);

        $entityValue = $entity->value;

        if ($entityValue === EntityEnum::IMAGEN_4->value) {
            $url .= '/preview';
        }

        $request = [
            'prompt' => $prompt,
        ];

        if ($entity === EntityEnum::FLUX_PRO_KONTEXT && count($images) === 1) {
            $request['image_url'] = Arr::first($images);
        } else {
            $request['image_urls'] = $images;
        }

        $http = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])->post($url, $request);

        if (($http->status() === 200) && $requestId = $http->json('request_id')) {
            return $requestId;
        }

        $detail = $http->json('detail');

        throw new RuntimeException(__($detail ?: 'Check your FAL API key.'));
    }

    public static function createImageUrl(array $images = []): ?array
    {
        $urls = [];

        foreach ($images as $image) {
            $urls[] = url('uploads/' . $image->store('falai', 'public'));
        }

        return $urls;
    }

    public static function generate($prompt, ?EntityEnum $entity = EntityEnum::FLUX_PRO, ?array $options = [])
    {
        $ratio = self::ratio() ?? ($options['aspect_ratio'] ?? null);
        $request = [
            'prompt' => $prompt,
        ];

        if ($ratio) {
            $ratioParamName = self::getRatioParameterName($entity);
            $request = Arr::add($request, $ratioParamName, $ratio);
        }

        // Handle image_reference which can be a string or array
        $imageReference = $options['image_reference'] ?? null;
        $styleReference = $options['style_reference'] ?? null;

        // Normalize image_reference to array if it's a string
        $imageReferenceUrls = [];
        if (is_array($imageReference)) {
            $imageReferenceUrls = array_filter($imageReference);
        } elseif (is_string($imageReference) && ! empty($imageReference)) {
            $imageReferenceUrls = [$imageReference];
        }

        // Collect all image URLs
        $imageUrls = collect($imageReferenceUrls);
        if (! empty($styleReference)) {
            $imageUrls->push($styleReference);
        }
        $imageUrls = $imageUrls->filter()->values();

        if ($entity === EntityEnum::SEEDREAM_4) {
            $entityValue = 'bytedance/' . $entity?->value;
            if ($imageUrls->isNotEmpty()) {
                $entityValue = EntityEnum::SEEDREAM_4_EDIT->value;
                $request['image_urls'] = $imageUrls->all();
            }
        } elseif ($entity === EntityEnum::FLUX_PRO_KONTEXT_TEXT_TO_IMAGE) {
            $entityValue = $entity?->value;
        } elseif ($entity === EntityEnum::NANO_BANANA) {
            $entityValue = $entity?->value;
        } elseif ($entity === EntityEnum::NANO_BANANA_PRO) {
            $entityValue = $entity?->value;
        } elseif ($entity === EntityEnum::NANO_BANANA_2) {
            $entityValue = $entity?->value;
        } elseif ($entity === EntityEnum::FLUX_2_FLEX || $entity === EntityEnum::FLUX_2_FLEX_EDIT) {
            $entityValue = $entity?->value;
        } elseif ($entity === EntityEnum::SEEDREAM_4) {
            $entityValue = 'bytedance/' . $entity?->value;
        } else {
            $entityValue = $entity?->value ?? setting('fal_ai_default_model');
            $entityValue = EntityEnum::fromSlug($entityValue)->value;
        }

        if (in_array($entityValue, [EntityEnum::NANO_BANANA->value, EntityEnum::NANO_BANANA_EDIT->value], true) && $imageUrls->isNotEmpty()) {
            $entityValue = EntityEnum::NANO_BANANA_EDIT->value;
            $request['image_urls'] = $imageUrls->all();
        }

        if (in_array($entityValue, [EntityEnum::NANO_BANANA_PRO->value, EntityEnum::NANO_BANANA_PRO_EDIT->value], true) && $imageUrls->isNotEmpty()) {
            $entityValue = EntityEnum::NANO_BANANA_PRO_EDIT->value;
            $request['image_urls'] = $imageUrls->all();
        }

        if (in_array($entityValue, [EntityEnum::NANO_BANANA_2->value, EntityEnum::NANO_BANANA_2_EDIT->value], true) && $imageUrls->isNotEmpty()) {
            $entityValue = EntityEnum::NANO_BANANA_2_EDIT->value;
            $request['image_urls'] = $imageUrls->all();
        }

        $url = sprintf(self::GENERATE_ENDPOINT, $entityValue);

        if ($entityValue === EntityEnum::IMAGEN_4->value) {
            $url .= '/preview';
        }

        if ($ratio) {
            $request = Arr::add($request, 'image_size', $ratio);
        }

        $http = Http::timeout(3000)->withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])->post($url, $request);

        if (($http->status() === 200) && $requestId = $http->json('request_id')) {
            return $requestId;
        }

        $detail = $http->json('detail');

        throw new RuntimeException(__($detail ?: 'Check your FAL API key.'));
    }

    /**
     * Generate a Grok image synchronously (no queue).
     *
     * @return array{images: array, revised_prompt: string|null}
     */
    public static function generateGrokSync(string $prompt, EntityEnum $entity, array $options = []): array
    {
        $request = ['prompt' => $prompt];

        $ratio = self::ratio() ?? ($options['aspect_ratio'] ?? null);
        if ($ratio) {
            $request['aspect_ratio'] = $ratio;
        }

        $request['output_format'] = $options['output_format'] ?? 'png';

        $entityValue = $entity->value;

        // Handle image references for edit mode
        $imageReference = $options['image_reference'] ?? null;
        $imageReferenceUrls = [];
        if (is_array($imageReference)) {
            $imageReferenceUrls = array_filter($imageReference);
        } elseif (is_string($imageReference) && ! empty($imageReference)) {
            $imageReferenceUrls = [$imageReference];
        }

        $imageUrls = collect($imageReferenceUrls);
        $styleReference = $options['style_reference'] ?? null;
        if (! empty($styleReference)) {
            $imageUrls->push($styleReference);
        }
        $imageUrls = $imageUrls->filter()->values();

        if ($imageUrls->isNotEmpty()) {
            $entityValue = EntityEnum::GROK_IMAGINE_IMAGE_EDIT->value;
            $request['image_url'] = (string) Arr::first($imageUrls->all());
        }

        $url = sprintf(self::SYNC_ENDPOINT, $entityValue);

        $http = Http::timeout(120)->withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])->post($url, $request);

        if ($http->successful() && ($images = $http->json('images')) && is_array($images)) {
            return [
                'images'         => $images,
                'revised_prompt' => $http->json('revised_prompt'),
            ];
        }

        $detail = $http->json('detail');

        throw new RuntimeException(__($detail ?: 'Grok image generation failed.'));
    }

    public static function isGrokModel(?EntityEnum $entity): bool
    {
        return $entity !== null && in_array($entity, [EntityEnum::GROK_IMAGINE_IMAGE, EntityEnum::GROK_IMAGINE_IMAGE_EDIT], true);
    }

    public static function check($uuid, EntityEnum $entity = EntityEnum::FLUX_PRO): ?array
    {
        $entityValue = $entity->value ?? setting('fal_ai_default_model');

        $enum = EntityEnum::fromSlug($entityValue);

        if ($enum === EntityEnum::FLUX_SCHNELL) {
            $entityValue = 'flux-pro';
        }

        if ($enum === EntityEnum::SEEDREAM_4 || $enum === EntityEnum::SEEDREAM_4_EDIT) {
            $entityValue = 'bytedance';
        }

        if (in_array($enum, [
            EntityEnum::FLUX_PRO_1_1,
            EntityEnum::FLUX_PRO,
            EntityEnum::FLUX_PRO_KONTEXT,
            EntityEnum::FLUX_PRO_KONTEXT_MAX_MULTI,
            EntityEnum::FLUX_PRO_KONTEXT_TEXT_TO_IMAGE,
        ], true)) {
            $entityValue = 'flux';
        }

        if ($enum === EntityEnum::FLUX_2_FLEX || $enum === EntityEnum::FLUX_2_FLEX_EDIT) {
            $entityValue = 'flux-2-flex';
        }

        if (in_array($enum, [
            EntityEnum::NANO_BANANA,
            EntityEnum::NANO_BANANA_EDIT,
            EntityEnum::NANO_BANANA_PRO,
            EntityEnum::NANO_BANANA_PRO_EDIT,
            EntityEnum::NANO_BANANA_2,
            EntityEnum::NANO_BANANA_2_EDIT,
        ], true)) {
            $entityValue = 'flux';
        }

        if (in_array($enum, [
            EntityEnum::GROK_IMAGINE_IMAGE,
            EntityEnum::GROK_IMAGINE_IMAGE_EDIT,
        ], true)) {
            $entityValue = 'xai';
        }

        $url = sprintf(self::CHECK_ENDPOINT, $entityValue, $uuid);

        $http = self::falRequest($url);

        if ($result = self::parseImageResult($http->json() ?? [])) {
            return $result;
        }

        $requestStatus = strtoupper((string) $http->json('status'));
        if ($requestStatus === 'FAILED') {
            return [
                'status' => 'FAILED',
                'error'  => self::extractFalError($http->json() ?? []),
            ];
        }

        if (in_array($requestStatus, ['CREATED', 'IN_QUEUE', 'IN_PROGRESS'], true)) {
            return null;
        }

        $http = self::falRequest($url . '/status');

        if ($result = self::parseImageResult($http->json() ?? [])) {
            return $result;
        }

        $responseStatus = strtoupper((string) $http->json('status'));

        if ($responseStatus === 'FAILED') {
            return [
                'status' => 'FAILED',
                'error'  => self::extractFalError($http->json() ?? []),
            ];
        }

        // Avoid false negatives caused by inconsistent status endpoints.
        if (in_array($responseStatus, ['CREATED', 'IN_QUEUE', 'IN_PROGRESS', 'COMPLETED'], true)) {
            return null;
        }

        return null;
    }

    private static function falRequest(string $url): Response
    {
        return Http::timeout(3000)->withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])->get($url);
    }

    private static function parseImageResult(array $payload): ?array
    {
        $images = data_get($payload, 'images')
            ?? data_get($payload, 'output.images')
            ?? data_get($payload, 'result.images');

        if (is_array($images) && ! empty($images)) {
            $image = Arr::first($images);

            if (is_string($image)) {
                $image = ['url' => $image];
            }

            if (is_array($image) && data_get($image, 'url')) {
                return [
                    'image' => $image,
                    'size'  => self::buildImageSize($image),
                ];
            }
        }

        $image = data_get($payload, 'image')
            ?? data_get($payload, 'output.image')
            ?? data_get($payload, 'result.image');

        if (is_string($image)) {
            $image = ['url' => $image];
        }

        if (is_array($image) && data_get($image, 'url')) {
            return [
                'image' => $image,
                'size'  => self::buildImageSize($image),
            ];
        }

        return null;
    }

    private static function buildImageSize(array $image): string
    {
        $width = data_get($image, 'width');
        $height = data_get($image, 'height');

        if ($width && $height) {
            return $width . 'x' . $height;
        }

        return 'unknown';
    }

    private static function extractFalError(array $payload): string
    {
        $detail = data_get($payload, 'detail');

        if (is_string($detail) && $detail !== '') {
            return $detail;
        }

        if (is_array($detail)) {
            $encoded = json_encode($detail);

            if (is_string($encoded) && $encoded !== '') {
                return $encoded;
            }
        }

        $error = data_get($payload, 'error');

        if (is_string($error) && $error !== '') {
            return $error;
        }

        if (is_array($error)) {
            $encoded = json_encode($error);

            if (is_string($encoded) && $encoded !== '') {
                return $encoded;
            }
        }

        return __('Image generation failed.');
    }

    public static function ideogramGenerate(string $prompt, ?EntityEnum $entity = EntityEnum::IDEOGRAM, ?array $options = [])
    {
        $ratio = self::ratio() ?? ($options['aspect_ratio'] ?? null);

        $request = [
            'prompt'    => $prompt,
        ];

        if ($ratio) {
            $ratioParam = match ($ratio) {
                'landscape_16_9' => '16:9',
                'square'         => '1:1',
                'portrait_16_9'  => '9:16',
                default          => $ratio,
            };

            $request = Arr::add($request, 'aspect_ratio', $ratioParam);
        }

        $http = Http::timeout(3000)->withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])->post(self::IDEOGRAM_URL, $request);

        if (($http->status() === 200) && $requestId = $http->json('request_id')) {
            return $requestId;
        }

        $detail = $http->json('detail');

        throw new RuntimeException(__($detail ?: 'Check your FAL API key.'));
    }

    public static function haiperGenerate(string $prompt, string $imageUrl)
    {
        $response = Http::timeout(3000)->withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])
            ->post(self::HAIPER_URL,
                [
                    'prompt'    => $prompt,
                    'image_url' => $imageUrl,
                ]);

        return $response->json();
    }

    public static function klingImageGenerate(string $prompt, string $imageUrl)
    {
        set_time_limit(0);
        ini_set('max_execution_time', 540);

        $response = Http::timeout(3000)->withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])
            ->post(self::KLING_IMAGE_URL,
                [
                    'prompt'    => $prompt,
                    'image_url' => $imageUrl,
                ]);

        return $response->json();
    }

    public static function klingV21Generate(string $prompt, string $imageUrl)
    {
        set_time_limit(0);
        ini_set('max_execution_time', 540);

        $response = Http::timeout(3000)->withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])
            ->post(self::KLING_V21_URL,
                [
                    'prompt'    => $prompt,
                    'image_url' => $imageUrl,
                ]);

        return $response->json();
    }

    public static function minimaxGenerate(string $prompt)
    {
        $response = Http::timeout(3000)->withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])
            ->post(self::MINIMAX_URL,
                [
                    'prompt' => $prompt,
                ]);

        return $response->json();
    }

    public static function klingGenerate(string $prompt)
    {
        $response = Http::timeout(3000)->withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])
            ->post(self::KLING_URL,
                [
                    'prompt' => $prompt,
                ]);

        return $response->json();
    }

    public static function lumaGenerate(string $prompt)
    {
        $response = Http::timeout(3000)->withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])
            ->post(self::LUMA_URL,
                [
                    'prompt' => $prompt,
                ]);

        return $response->json();
    }

    public static function veo2Generate(string $prompt): PromiseInterface|Response
    {
        return Http::timeout(3000)->withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])
            ->post(self::VEO_2_URL,
                [
                    'prompt' => $prompt,
                ]);
    }

    public static function getStatus($url)
    {
        ini_set('max_execution_time', 440);
        set_time_limit(0);

        $response = Http::timeout(3000)->withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])
            ->get($url);

        return $response->json();
    }
}
