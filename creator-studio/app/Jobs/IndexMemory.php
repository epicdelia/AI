<?php

namespace App\Jobs;

use App\Domains\Memory\Memory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Re-index one record in Memory. Dispatched with dispatchAfterResponse() from the model
 * observers, so it runs right after the request without needing a queue worker
 * (MagicAI's job pattern: Dispatchable + InteractsWithQueue + Queueable + SerializesModels).
 */
class IndexMemory implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    /**
     * @param class-string<\Illuminate\Database\Eloquent\Model> $class
     */
    public function __construct(
        public string $class,
        public int $id,
        public bool $delete = false
    ) {
    }

    public function handle(Memory $memory): void
    {
        try {
            if ($this->delete) {
                $memory->forgetModel((new $this->class())->forceFill([
                    'id' => $this->id,
                ]));

                return;
            }
            $model = $this->class::find($this->id);
            if ($model) {
                $memory->index($model);
            }
        } catch (Throwable $e) {
            report($e); // indexing must never break saving
        }
    }
}
