<?php

namespace App\Domains\Copilot\Models;

use Illuminate\Database\Eloquent\Model;

class CopilotMission extends Model
{
    protected $fillable = ['copilot_thread_id', 'kind', 'title', 'status', 'step', 'steps', 'data'];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'data' => 'array',
        ];
    }
}
