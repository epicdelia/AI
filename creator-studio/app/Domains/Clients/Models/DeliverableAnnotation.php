<?php

namespace App\Domains\Clients\Models;

use App\Models\Deal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliverableAnnotation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'timecode_seconds' => 'float',
            'deliverable_index' => 'integer',
            'page_or_slide' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'deal_id' => $this->deal_id,
            'deliverable_index' => $this->deliverable_index,
            'kind' => $this->kind,
            'timecode_seconds' => $this->timecode_seconds,
            'position_xy' => $this->position_xy,
            'page_or_slide' => $this->page_or_slide,
            'body' => $this->body,
            'status' => $this->status,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolved_by' => $this->resolver?->name,
            'author' => $this->user?->name ?: ($this->user?->isAdmin() ? 'Studio' : 'Client'),
            'from_admin' => (bool) ($this->user?->isAdmin()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
