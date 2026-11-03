<?php

namespace App\Models;

use App\Domains\Engine\Enums\EngineEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class UserApiKey extends Model
{
    use HasFactory;

    protected $table = 'user_api_keys';

    protected $fillable = [
        'user_id',
        'provider',
        'api_key',
        'base_url',
        'default_model',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Store raw key encrypted.
     */
    public function setApiKeyAttribute(string $value): void
    {
        $this->attributes['api_key'] = Crypt::encryptString(trim($value));
    }

    /**
     * Retrieve decrypted key safely.
     */
    public function getDecryptedApiKey(): ?string
    {
        if (empty($this->api_key)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->api_key);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Masked key for UI display (e.g. sk-proj...••••••••ab12).
     */
    public function maskedKey(): string
    {
        $plain = $this->getDecryptedApiKey();
        if (!$plain) {
            return '••••••••';
        }

        $len = strlen($plain);
        if ($len <= 8) {
            return '••••••••';
        }

        return substr($plain, 0, 4) . '••••••••' . substr($plain, -4);
    }
}

