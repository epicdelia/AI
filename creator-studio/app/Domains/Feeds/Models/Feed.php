<?php

namespace App\Domains\Feeds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feed extends Model
{
    protected $fillable = ['url', 'title', 'active', 'last_fetched_at', 'last_error'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'last_fetched_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeedItem::class);
    }
}
