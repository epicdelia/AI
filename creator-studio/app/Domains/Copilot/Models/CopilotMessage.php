<?php

namespace App\Domains\Copilot\Models;

use Illuminate\Database\Eloquent\Model;

class CopilotMessage extends Model
{
    protected $fillable = ['copilot_thread_id', 'role', 'content', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
