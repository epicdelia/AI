<?php

namespace App\Domains\Social\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One connected account per network. Tokens are encrypted at rest and hidden from JSON.
 */
class SocialAccount extends Model
{
    protected $fillable = [
        'platform', 'external_id', 'handle', 'name', 'avatar_url', 'access_token', 'refresh_token',
        'expires_at', 'refresh_expires_at', 'scopes', 'status', 'last_error', 'last_synced_at', 'meta',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    public function expiresSoon(int $seconds = 300): bool
    {
        return $this->expires_at !== null && $this->expires_at->lte(now()->addSeconds($seconds));
    }

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'refresh_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'scopes' => 'array',
            'meta' => 'array',
        ];
    }
}
