<?php

namespace App\Models;

use App\Domains\Social\Models\SocialPost;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Deal extends Model
{
    public const STAGES = [
        'prospect',
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

    /** Not money yet: a brand she might pitch. Left out of pipeline totals. */
    public const NOT_PIPELINE = ['prospect', 'paid'];

    /** How likely a deal at each stage is to be paid, for the weighted pipeline (rule of thumb). */
    public const WIN_ODDS = [
        'prospect' => 0.0, 'inquiry' => 0.1, 'pitching' => 0.25, 'negotiating' => 0.5, 'contracted' => 0.9,
        'production' => 0.9, 'in_review' => 0.95, 'scheduled' => 0.95, 'invoiced' => 0.98, 'paid' => 1.0,
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
        'source',
        'attribution',
        'stage_changed_at',
        'last_contacted_at',
        'follow_up_snoozed_until',
        'proposal',
        'proposal_at',
        'meeting_at',
        'kit_token',
        'kit_views',
        'kit_viewed_at',
    ];

    protected $hidden = ['kit_token'];

    protected function casts(): array
    {
        return [
            'deliverables' => 'array',
            'talking_points_checklist' => 'array',
            'attribution' => 'array',
            'proposal' => 'array',
            'rate' => 'float',
            'published_at' => 'datetime',
            'invoiced_at' => 'datetime',
            'paid_at' => 'datetime',
            'stage_changed_at' => 'datetime',
            'last_contacted_at' => 'datetime',
            'follow_up_snoozed_until' => 'datetime',
            'proposal_at' => 'datetime',
            'meeting_at' => 'datetime',
            'kit_viewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Follow-ups count quiet days from the last stage move, so remember when that was.
        static::saving(function (Deal $deal) {
            if ($deal->isDirty('stage') && ! $deal->isDirty('stage_changed_at')) {
                $deal->stage_changed_at = now();
            }
        });
    }

    /** The last time anything happened on this deal: created, moved, or she got in touch. */
    public function lastTouchAt(): Carbon
    {
        return collect([$this->created_at, $this->stage_changed_at, $this->last_contacted_at])
            ->filter()->sortByDesc(fn ($d) => $d->getTimestamp())->first() ?? now();
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
