<?php

namespace App\Domains\Workflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowRun extends Model
{
    protected $fillable = ['workflow_id', 'status', 'trigger_ref', 'context', 'cursor', 'resume_at', 'log', 'dry_run', 'error'];

    protected function casts(): array
    {
        return ['context' => 'array', 'log' => 'array', 'dry_run' => 'boolean', 'resume_at' => 'datetime'];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
