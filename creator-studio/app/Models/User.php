<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * `role` is never mass assignable: sign-up always makes a client (Role::forSignUp), the first
 * install makes her the admin, and nothing a request sends can change that.
 */
#[Fillable(['name', 'email', 'password', 'company', 'phone'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_last_step'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'disabled_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function role(): Role
    {
        return $this->role ?? Role::Client;
    }

    public function isAdmin(): bool
    {
        return $this->role() === Role::Admin;
    }

    public function hasAbility(string $ability): bool
    {
        return $this->role()->allows($ability);
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret;
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    /** Admins are hers from the first install, so only a client's email needs proving. */
    public function needsEmailVerification(): bool
    {
        return $this->role()->requiresVerifiedEmail() && ! $this->hasVerifiedEmail();
    }

    /** What the dashboard needs to know about who is signed in. */
    public function toSession(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'company' => $this->company,
            'role' => $this->role()->value,
            'abilities' => $this->role()->abilities(),
            'email_verified' => ! $this->needsEmailVerification(),
            'two_factor' => [
                'enabled' => (bool) $this->hasTwoFactor(),
                'required' => $this->role()->requiresTwoFactor(),
            ],
        ];
    }
}
