<?php

namespace App\Domains\Copilot\Models;

use Illuminate\Database\Eloquent\Model;

class CopilotSignal extends Model
{
    protected $fillable = ['kind', 'detail', 'learned'];

    protected function casts(): array
    {
        return ['learned' => 'boolean'];
    }
}
