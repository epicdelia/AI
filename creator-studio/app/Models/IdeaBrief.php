<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IdeaBrief extends Model
{
    protected $fillable = ['summary', 'signals'];

    public function ideas(): HasMany
    {
        return $this->hasMany(Idea::class);
    }
}

