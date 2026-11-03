<?php

namespace App\Domains\YouTube;

use Illuminate\Database\Eloquent\Model;

class VideoUpload extends Model
{
    protected $fillable = ['script_id', 'title', 'description', 'tags', 'privacy', 'publish_at', 'path', 'mime', 'bytes', 'received', 'sent',
        'upload_url', 'thumbnail_media_id', 'status', 'video_id', 'error'];

    protected $hidden = ['path', 'upload_url'];

    protected $appends = ['progress'];

    /**
     * 0–100: the first half is her browser → studio, the second studio → YouTube.
     */
    public function getProgressAttribute(): int
    {
        if ($this->status === 'done') {
            return 100;
        }
        $b = max(1, (int)$this->bytes);

        return (int)floor(($this->received / $b) * 50 + ($this->sent / $b) * 50);
    }

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'publish_at' => 'datetime',
        ];
    }
}
