<?php

namespace App\Domains\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = ['title', 'pinned'];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'pinned' => 'boolean',
        ];
    }
}
