<?php

namespace App\Packages\Elevenlabs\Contracts;

use Illuminate\Http\JsonResponse;

interface AiMusicInterface
{
    public function submit(array $params): JsonResponse;
}
