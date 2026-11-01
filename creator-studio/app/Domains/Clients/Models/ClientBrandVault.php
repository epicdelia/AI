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
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
