<?php

namespace App\Packages\FalAI\Contracts;

use Illuminate\Http\JsonResponse;

interface TextToVideoModelInterface
{
    // submit task to generate the video
    public function submit(array $params): JsonResponse;

    // check status of submitted task
    public function checkStatus(string $requestId): JsonResponse;

    // get the final result
    public function getResult(string $requestId): JsonResponse;
}
