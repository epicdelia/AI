<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** A background AI job the dashboard waits for (RunAiTask). */
class AiTask extends Model
{
    use HasUuids;

    protected $fillable = ['kind', 'payload', 'status', 'result', 'http_status'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'result' => 'array'];
    }
}
