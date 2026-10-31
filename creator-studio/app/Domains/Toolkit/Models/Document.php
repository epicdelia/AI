<?php

namespace App\Domains\Toolkit\Models;

use App\Models\Script;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A saved output in the Library. */
class Document extends Model
{
    protected $fillable = ['title', 'tool', 'content', 'inputs', 'favorite', 'script_id'];

    protected function casts(): array
    {
        return ['inputs' => 'array', 'favorite' => 'boolean'];
    }

    public function script(): BelongsTo
    {
        return $this->belongsTo(Script::class);
    }
}
