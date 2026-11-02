<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunAiTask;
use App\Models\AiTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Start a slow AI job in the background, then ask how it's doing (core.js runTask polls).
 */
class TasksController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(RunAiTask::KINDS))],
            'payload' => 'nullable|array',
        ]);
        $task = AiTask::create([
            'kind' => $data['kind'],
            'payload' => $data['payload'] ?? [],
            'status' => 'queued',
        ]);
        RunAiTask::dispatch($task->id);
        AiTask::where('created_at', '<', now()->subDays(2))->delete(); // keep the table small

        return response()->json($this->state($task->fresh()), 202);
    }

    public function show(string $task): JsonResponse
    {
        return response()->json($this->state(AiTask::findOrFail($task)));
    }

    private function state(AiTask $t): array
    {
        return [
            'id' => $t->id,
            'status' => $t->status,
            'result' => in_array($t->status, ['done', 'failed'], true) ? $t->result : null,
            'http_status' => $t->http_status,
        ];
    }
}
