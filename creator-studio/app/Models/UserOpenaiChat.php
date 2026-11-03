<?php

namespace App\Models;

use App\Helpers\Classes\MarketplaceHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserOpenaiChat extends Model
{
    protected $table = 'user_openai_chat';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(static function ($chat) {
            if (MarketplaceHelper::isRegistered('ai-chat-pro') && ! auth()->check()) {
                $chat->is_guest = true;
            }
        });
    }

    public function messages(): HasMany
    {
        return $this->hasMany(UserOpenaiChatMessage::class);
    }

    public function messagesWithoutInitial(): HasMany
    {
        return $this->hasMany(UserOpenaiChatMessage::class)->where('response', '!==', 'First Initiation');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(OpenaiGeneratorChatCategory::class, 'openai_chat_category_id', 'id');
    }
}
