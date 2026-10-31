<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    protected $fillable = ['handle', 'title', 'active', 'last_scanned_at', 'last_error'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'last_scanned_at' => 'datetime'];
    }

    public function outliers(): HasMany
    {
        return $this->hasMany(Outlier::class);
    }

    /** Accepts "@handle", "handle", a UC... ID or a youtube.com URL and returns the bare handle or ID. */
    public static function normalize(string $input): string
    {
        $input = trim($input);
        if (preg_match('~youtube\.com/(?:channel/(UC[\w-]{22})|@([\w.\-]+))~i', $input, $m)) {
            return $m[1] ?: $m[2];
        }

        return ltrim($input, '@');
    }
}

