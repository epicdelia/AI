<?php

namespace App\Domains\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoJob extends Model
{
    protected $fillable = ['script_id', 'model', 'prompt', 'params', 'request_id', 'status_url', 'response_url', 'status', 'media_file_id', 'error', 'checked_at'];

    protected $hidden = ['status_url', 'response_url'];

    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'media_file_id');
    }

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'checked_at' => 'datetime',
        ];
    }
}
