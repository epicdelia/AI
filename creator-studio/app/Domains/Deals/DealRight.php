<?php

namespace App\Domains\Deals;

use App\Models\Deal;
use Illuminate\Database\Eloquent\Model;

/**
 * One usage-rights / whitelisting / exclusivity line on a deal (see Rights).
 */
class DealRight extends Model
{
    protected $table = 'deal_rights';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'reminded_at' => 'datetime',
            'price' => 'decimal:2',
        ];
    }

    public function deal(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }
}
