<?php

namespace App\Domains\Workflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
    protected $fillable = ['name', 'active', 'nodes', 'runs_count', 'last_run_at'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'nodes' => 'array', 'last_run_at' => 'datetime'];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(WorkflowRun::class);
    }

    public function trigger(): ?array
    {
        return $this->nodes[0] ?? null;
    }
}
