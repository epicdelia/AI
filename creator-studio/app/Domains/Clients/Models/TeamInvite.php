<?php

namespace App\Domains\Clients\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A client asked a colleague to share their requests (accepted when that email is confirmed). */
class TeamInvite extends Model
{
    protected $fillable = ['owner_id', 'email', 'token', 'expires_at', 'accepted_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
