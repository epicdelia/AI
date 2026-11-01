<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\AiTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Slow AI work off the web request: the same controller action the button used to wait for,
 * run by the queue worker, its JSON answer stored on the task for the dashboard to pick up.
 * A request no longer hangs (or times out behind nginx) while an idea batch or a scan runs.
 */
class RunAiTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    /** kind => [controller, action]: what may run in the background. */
    public const KINDS = [
        'ideas.generate' => [ApiController::class, 'generateIdeas'],
        'breakouts.scan' => [ApiController::class, 'scanBreakouts'],
    ];

    public function __construct(public string $taskId) {}

    public function handle(): void
    {
        $task = AiTask::find($this->taskId);
        if (! $task || $task->status !== 'queued') {
            return;
        }
        $task->update(['status' => 'running']);
        try {
            [$class, $method] = self::KINDS[$task->kind];
            $request = Request::create('/', 'POST', $task->payload ?? []);
            app()->instance('request', $request);
            /** @var JsonResponse $response */
            $response = app()->call([app($class), $method], ['request' => $request]);
            $ok = $response->getStatusCode() < 400;
            $task->update(['status' => $ok ? 'done' : 'failed', 'result' => $response->getData(true), 'http_status' => $response->getStatusCode()]);
        } catch (Throwable $e) {
            $task->update(['status' => 'failed', 'result' => ['error' => $e->getMessage()], 'http_status' => 500]);
        }
    }
}
