<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    protected $fillable = ['email', 'name', 'source', 'attribution', 'magnet', 'unsubscribed_at'];

    protected function casts(): array
    {
        return ['attribution' => 'array', 'unsubscribed_at' => 'datetime'];
    }
}
