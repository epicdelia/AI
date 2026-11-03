<?php

namespace App\Packages\Klap\Constracts;

use Illuminate\Http\JsonResponse;

interface TaskOperationInterface
{
    // submit the task
    public function submitTask(array $params): JsonResponse;

    // check task status
    public function checkTask(string $taskId, ?array $extraParam = []): JsonResponse;
}
