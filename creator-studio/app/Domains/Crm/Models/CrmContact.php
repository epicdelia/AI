<?php

namespace App\Domains\Crm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person she's in touch with: a lead, a client, a sponsor contact, an engaged follower.
 */
class CrmContact extends Model
{
    public const STAGES = ['lead', 'contacted', 'qualified', 'customer', 'lost'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'value' => 'decimal:2',
            'last_contacted_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
        ];
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'contact_id')->latest();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
