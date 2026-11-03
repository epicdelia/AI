<?php

namespace App\Services\Ai\Images\Contracts;

use App\Domains\Entity\Enums\EntityEnum;
use Exception;

interface ImageGeneratorInterface
{
    /**
     * Generate images using the AI service
     *
     * @param  array  $options  Generation options (prompt, size, style, etc.)
     *
     * @return array Array of image binary data or URLs
     *
     * @throws Exception
     */
    public function generate(array $options): array;

    /**
     * Check if the service supports async generation
     */
    public function supportsAsync(): bool;

    /**
     * Check generation status (for async services)
     *
     * @param  string  $requestId  The request/job ID
     *
     * @return array Status information
     */
    public function checkStatus(string $requestId, EntityEnum $entityEnum): ?array;
}
