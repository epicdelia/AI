<?php

namespace App\Models;

use App\Enums\AiInfluencer\VideoStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ExportedVideo extends Model
{
    use HasFactory;

    protected $fillable = ['video_url', 'used_ai_tool', 'cover_url', 'video_duration', 'title', 'task_id', 'status', 'user_id'];

    protected $casts = [
        'status' => VideoStatusEnum::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $exportedVideo): void {
            if (! $exportedVideo->user_id && Auth::check()) {
                $exportedVideo->user_id = Auth::id();
            }
        });
    }
}
