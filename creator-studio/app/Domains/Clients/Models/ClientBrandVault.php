<?php

namespace App\Domains\Clients\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientBrandVault extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'brand_colors' => 'array',
            'talking_points' => 'array',
            'dos_and_donts' => 'array',
            'utm_params' => 'array',
            'vies_verified' => 'boolean',
            'vies_verified_at' => 'datetime',
            'retention_days' => 'integer',
            'zdr_enabled' => 'boolean',
            'auto_redact_secrets' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toResponse(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'brand_name' => $this->brand_name,
            'website' => $this->website,
            'logo_url' => $this->logo_path ? url('/storage/'.$this->logo_path) : null,
            'brand_colors' => $this->brand_colors ?: [
                'primary' => '#6366f1',
                'secondary' => '#ec4899',
                'accent' => '#10b981',
                'bg' => '#0f172a',
            ],
            'guidelines_markdown' => $this->guidelines_markdown ?: '',
            'talking_points' => $this->talking_points ?: [],
            'dos_and_donts' => $this->dos_and_donts ?: ['dos' => [], 'donts' => []],
            'utm_params' => $this->utm_params ?: ['utm_source' => 'tech-unicorn', 'utm_medium' => 'creator-partnership'],
            'slack_webhook_url' => $this->slack_webhook_url,
            'tax_id' => $this->tax_id,
            'billing_address' => $this->billing_address,
            'country_code' => $this->country_code ?: 'US',
            'vies_verified' => (bool) $this->vies_verified,
            'vies_request_identifier' => $this->vies_request_identifier,
            'vies_verified_at' => $this->vies_verified_at?->toIso8601String(),
            'vies_company_name' => $this->vies_company_name,
            'vies_company_address' => $this->vies_company_address,
            'retention_days' => $this->retention_days ?? 60,
            'zdr_enabled' => (bool) $this->zdr_enabled,
            'auto_redact_secrets' => $this->auto_redact_secrets ?? true,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

