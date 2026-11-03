<?php

namespace App\Domains\Media;

use App\Models\Script;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClipProject extends Model
{
    protected $fillable = ['script_id', 'source_url', 'project_id', 'status', 'options', 'clips', 'error', 'checked_at'];

    public function script(): BelongsTo
    {
        return $this->belongsTo(Script::class);
    }

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'clips' => 'array',
            'checked_at' => 'datetime',
        ];
    }
}
