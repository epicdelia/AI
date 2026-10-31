<?php

namespace App\Domains\Copilot\Models;

use Illuminate\Database\Eloquent\Model;

class CopilotAction extends Model
{
    protected $fillable = ['copilot_thread_id', 'tool', 'args', 'summary', 'status', 'result'];

    protected function casts(): array
    {
        return ['args' => 'array', 'result' => 'array'];
    }
}
