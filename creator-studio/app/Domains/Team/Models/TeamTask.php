<?php

namespace App\Domains\Team\Models;

use App\Models\Script;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A production assignment: one stage of one video (or a standalone job) for one team member,
 * with a due date and an optional payout she approves and marks paid.
 */
class TeamTask extends Model
{
    public const STAGES = ['idea', 'script', 'filming', 'editing', 'review', 'scheduled', 'published'];

    public const PAYOUT_STATUSES = ['none', 'pending', 'approved', 'paid'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'payout_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function script(): BelongsTo
    {
        return $this->belongsTo(Script::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_on !== null && !$this->completed_at && $this->due_on->copy()->endOfDay()->isPast();
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'stage' => $this->stage,
            'role_label' => $this->role_label,
            'assignee_id' => $this->assignee_id,
            'assignee_name' => $this->assignee?->name ?? 'Unassigned',
            'script_id' => $this->script_id,
            'script_title' => $this->script?->title,
            'due_on' => $this->due_on?->toDateString(),
            'overdue' => $this->isOverdue(),
            'notes' => $this->notes,
            'completed' => $this->completed_at !== null,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'payout_amount' => (float)$this->payout_amount,
            'payout_currency' => $this->payout_currency,
            'payout_status' => $this->payout_status,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'paid_ref' => $this->paid_ref,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
