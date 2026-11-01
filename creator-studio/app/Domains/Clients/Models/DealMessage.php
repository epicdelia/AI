<?php

namespace App\Domains\Clients\Models;

use App\Models\Deal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One message in a deal's thread between her (`from_admin`) and the client. */
class DealMessage extends Model
{
    protected $fillable = ['deal_id', 'user_id', 'from_admin', 'body', 'read_at'];

    protected function casts(): array
    {
        return ['from_admin' => 'boolean', 'read_at' => 'datetime'];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toThread(): array
    {
        return [
            'id' => $this->id,
            'from_admin' => $this->from_admin,
            'author' => $this->user?->name,
            'body' => $this->body,
            'read' => $this->read_at !== null,
            'at' => $this->created_at?->toIso8601String(),
        ];
    }
}
