<?php

namespace App\Models;

use App\Domains\Social\Models\SocialPost;
use App\Domains\Workflows\Engine;
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
        'lost',
    ];

    /**
     * Not open money: a brand she might pitch, money already in, or a deal that didn't happen.
     */
    public const NOT_PIPELINE = ['prospect', 'paid', 'lost'];

    /**
     * Why a deal was lost, for the win-rate insights.
     */
    public const LOST_REASONS = [
        'price' => 'Price / budget',
        'timing' => 'Timing',
        'no_reply' => 'Went quiet',
        'competitor' => 'Chose someone else',
        'not_fit' => 'Not a fit',
        'scope' => 'Scope changed',
        'other' => 'Other',
    ];

    /**
     * How likely a deal at each stage is to be paid, for the weighted pipeline (rule of thumb).
     */
    public const WIN_ODDS = [
        'prospect' => 0.0,
        'lost' => 0.0,
        'inquiry' => 0.1,
        'pitching' => 0.25,
        'negotiating' => 0.5,
        'contracted' => 0.9,
        'production' => 0.9,
        'in_review' => 0.95,
        'scheduled' => 0.95,
        'invoiced' => 0.98,
        'paid' => 1.0,
    ];

    protected $fillable = [
        'user_id',
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
        'stripe_subscription_id',
        'subscription_status',
        'room_token',
        'room_views',
        'room_viewed_at',
        'accepted_at',
        'accepted_by',
        'prep',
        'prep_at',
        'lost_reason',
        'signature_hash',
        'signer_ip',
        'signer_user_agent',
        'signer_email',
        'add_ons',
        'selected_tier',
        'referral_partner_id',
        'referral_credit_earned',
        'vat_rate',
        'vat_amount',
        'gross_amount',
        'oss_country_code',
        'is_ai_assisted',
        'ai_model_name',
        'human_reviewed_at',
        'dpa_signed_at',
        'dpa_signer_name',
        'dpa_signer_email',
        'dpa_signature_hash',
        'zdr_certified',
        'zdr_certificate_id',
        'retention_purged_at',
    ];

    protected $hidden = ['kit_token', 'room_token'];

    /**
     * Website leads carry a rule-of-thumb fit score (LeadScore) wherever deals are listed.
     */
    protected $appends = ['lead_score'];

    public function getLeadScoreAttribute(): ?array
    {
        return \App\Domains\Deals\LeadScore::for($this);
    }

    /**
     * The last time anything happened on this deal: created, moved, or she got in touch.
     */
    public function lastTouchAt(): Carbon
    {
        return collect([$this->created_at, $this->stage_changed_at, $this->last_contacted_at])
            ->filter()->sortByDesc(fn ($d) => $d->getTimestamp())->first() ?? now();
    }

    /**
     * The client account this deal belongs to (null: not linked to a client).
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function files(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domains\Clients\Models\DealFile::class);
    }

    public function annotations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domains\Clients\Models\DeliverableAnnotation::class);
    }

    public function messages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domains\Clients\Models\DealMessage::class);
    }

    public function script(): BelongsTo
    {
        return $this->belongsTo(Script::class);
    }

    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }

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
            'room_viewed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'prep' => 'array',
            'prep_at' => 'datetime',
            'approved_at' => 'datetime',
            'room_expires_at' => 'datetime',
            'kit_expires_at' => 'datetime',
            'add_ons' => 'array',
            'is_ai_assisted' => 'boolean',
            'human_reviewed_at' => 'datetime',
            'vat_rate' => 'float',
            'vat_amount' => 'float',
            'gross_amount' => 'float',
            'dpa_signed_at' => 'datetime',
            'retention_purged_at' => 'datetime',
            'zdr_certified' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Follow-ups count quiet days from the last stage move, so remember when that was.
        static::saving(function (Deal $deal) {
            if ($deal->isDirty('stage') && !$deal->isDirty('stage_changed_at')) {
                $deal->stage_changed_at = now();
            }
        });

        // A deal from a verified client's email (website booking, checkout, added by her) is theirs.
        static::creating(function (Deal $deal) {
            if (!$deal->user_id && $deal->stage !== 'prospect') {
                $deal->user_id = rescue(fn () => \App\Domains\Clients\ClientLink::ownerFor($deal->contact_email), null, false);
            }
        });

        // The client's timeline and emails (proposal ready, new status, invoice, delivery).
        static::created(fn (Deal $deal) => rescue(fn () => \App\Domains\Clients\ClientNotifier::created($deal), null, true));
        static::updated(fn (Deal $deal) => rescue(fn () => \App\Domains\Clients\ClientNotifier::updated($deal), null, true));

        // Business events for Workflows (each fires once per deal and stage; never blocks the save).
        static::created(function (Deal $deal) {
            if ($deal->source) {
                Engine::fire('new_lead', Engine::dealContext($deal), "deal:{$deal->id}:lead");
            }
        });
        static::updated(function (Deal $deal) {
            if ($deal->wasChanged('stage')) {
                Engine::fire('deal_stage', Engine::dealContext($deal), "deal:{$deal->id}:stage:{$deal->stage}");
            }
            if ($deal->wasChanged('payment_status') && $deal->payment_status === 'paid') {
                Engine::fire('payment', Engine::dealContext($deal), "deal:{$deal->id}:paid");
                rescue(fn () => \App\Domains\Clients\PartnerReferrals::attributePayment($deal), null, true);
            }
        });
    }
}
