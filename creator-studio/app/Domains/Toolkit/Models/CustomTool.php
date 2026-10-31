<?php

namespace App\Domains\Toolkit\Models;

use Illuminate\Database\Eloquent\Model;

/** One of her own Toolkit templates. */
class CustomTool extends Model
{
    protected $fillable = ['name', 'description', 'icon', 'fields', 'prompt'];

    protected function casts(): array
    {
        return ['fields' => 'array'];
    }
}
