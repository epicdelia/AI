<?php

namespace App\Domains\Team\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An emailed link that lets a new team member set their own password. Only the token's
 * SHA-256 hash is stored.
 */
class StaffInvite extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public static function findByToken(string $token): ?self
    {
        return self::where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')->where('expires_at', '>', now())->first();
    }
}
