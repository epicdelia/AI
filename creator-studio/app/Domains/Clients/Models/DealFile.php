<?php

namespace App\Domains\Clients\Models;

use App\Models\Deal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A file on a request: a client's brief or assets, or her deliverable. Private storage only.
 */
class DealFile extends Model
{
    protected $fillable = ['deal_id', 'user_id', 'from_admin', 'name', 'path', 'mime', 'size'];

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'size' => $this->size,
            'from_admin' => $this->from_admin,
            'by' => $this->user?->name,
            'at' => $this->created_at?->toIso8601String(),
        ];
    }

    protected function casts(): array
    {
        return [
            'from_admin' => 'boolean',
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(fn (DealFile $f) => Storage::disk('local')->delete($f->path));
    }
}
