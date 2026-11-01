<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A Stripe payment: the first checkout of a deal, or a retainer renewal. */
class Payment extends Model
{
    protected $fillable = ['deal_id', 'amount', 'currency', 'kind', 'stripe_id', 'paid_at'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'paid_at' => 'datetime'];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }
}
