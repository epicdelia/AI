<?php

declare(strict_types=1);

namespace App\Services\Ai\OpenAI\Image\Support;

/**
 * Normalizes request payloads for OpenAI GPT Image models
 * (gpt-image-1, gpt-image-1.5, gpt-image-2).
 *
 * The OpenAI API rejects requests that contain unknown parameters or disallowed
 * parameter values for a given model. This helper coerces our internal request
 * payload into a shape that each GPT Image model will accept, so downstream
 * callers can pass any combination of UI-selected params without risk.
 *
 * Reference: https://developers.openai.com/api/docs/guides/image-generation
 */
final class GptImageParamNormalizer
{
    public const MODEL_GPT_IMAGE_1 = 'gpt-image-1';

    public const MODEL_GPT_IMAGE_1_5 = 'gpt-image-1.5';

    public const MODEL_GPT_IMAGE_2 = 'gpt-image-2';

    public const ALLOWED_QUALITY = ['auto', 'low', 'medium', 'high'];

    public const ALLOWED_OUTPUT_FORMAT = ['png', 'jpeg', 'webp'];

    public static function isGptImageModel(string $model): bool
    {
        return in_array($model, [
            self::MODEL_GPT_IMAGE_1,
            self::MODEL_GPT_IMAGE_1_5,
            self::MODEL_GPT_IMAGE_2,
        ], true);
    }

    /**
     * Return the backgrounds each model accepts.
     *
     * @return array<int, string>
     */
    public static function allowedBackgrounds(string $model): array
    {
        // gpt-image-2 dropped transparent-background support.
        if ($model === self::MODEL_GPT_IMAGE_2) {
            return ['auto', 'opaque'];
        }

        return ['auto', 'transparent', 'opaque'];
    }

    /**
     * Normalize a request payload for a GPT Image model.
     *
     * Non-GPT-Image models pass through untouched so this is safe to call for
     * any OpenAI image request.
     *
     * @param  array<string, mixed>  $data
     *
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        $model = (string) ($data['model'] ?? '');

        if (! self::isGptImageModel($model)) {
            return $data;
        }

        if (isset($data['quality']) && ! in_array($data['quality'], self::ALLOWED_QUALITY, true)) {
            $data['quality'] = 'auto';
        }

        if (isset($data['background']) && ! in_array($data['background'], self::allowedBackgrounds($model), true)) {
            $data['background'] = 'auto';
        }

        if (isset($data['size']) && $data['size'] !== 'auto' && ! preg_match('/^\d+x\d+$/', (string) $data['size'])) {
            $data['size'] = 'auto';
        }

        if (isset($data['output_format']) && ! in_array($data['output_format'], self::ALLOWED_OUTPUT_FORMAT, true)) {
            $data['output_format'] = 'png';
        }

        // These params are not accepted by gpt-image-* models regardless of version.
        unset($data['response_format']);

        // input_fidelity was removed in gpt-image-2 (always high fidelity).
        if ($model === self::MODEL_GPT_IMAGE_2) {
            unset($data['input_fidelity']);
        }

        return $data;
    }
}
