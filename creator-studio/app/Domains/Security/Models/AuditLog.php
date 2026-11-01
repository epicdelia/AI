<?php

namespace App\Domains\Security\Models;

use App\Domains\Security\Audit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One security-relevant action: who, what, on what, from where (see Audit). */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'meta', 'ip', 'user_agent', 'device'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'label' => Audit::LABELS[$this->action] ?? $this->action,
            'who' => $this->user?->name,
            'subject' => $this->subject_type ? "{$this->subject_type} #{$this->subject_id}" : null,
            'meta' => $this->meta,
            'ip' => $this->ip,
            'browser' => Audit::browser((string) $this->user_agent),
            'at' => $this->created_at?->toIso8601String(),
        ];
    }
}
