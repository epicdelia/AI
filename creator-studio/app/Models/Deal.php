<?php

namespace App\Models;

use App\Domains\Social\Models\SocialPost;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deal extends Model
{
    public const STAGES = [
        'inquiry',
        'pitching',
        'negotiating',
        'contracted',
        'production',
        'in_review',
        'scheduled',
        'invoiced',
        'paid',
    ];

    protected $fillable = [
        'brand_name',
        'contact_name',
        'contact_email',
        'stage',
        'deliverables',
        'rate',
        'currency',
        'payment_terms',
        'payment_status',
        'coupon_code',
        'tracking_url',
        'key_talking_points',
        'talking_points_checklist',
        'do_nots',
        'script_id',
        'social_post_id',
        'notes',
        'published_at',
        'invoiced_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'deliverables' => 'array',
            'talking_points_checklist' => 'array',
            'rate' => 'float',
            'published_at' => 'datetime',
            'invoiced_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function script(): BelongsTo
    {
        return $this->belongsTo(Script::class);
    }

    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }
}
