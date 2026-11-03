<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserUsageCredit extends Model
{
    use HasFactory;

    protected $table = 'user_usage_credits';

    protected $fillable = [
        'user_id',
        'model_key',
        'feature',
        'unit',
        'quantity',
        'unit_price',
        'total_cost',
        'byok',
    ];

    protected $casts = [
        'byok' => 'boolean',
        'quantity' => 'integer',
        'unit_price' => 'decimal:6',
        'total_cost' => 'decimal:4',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
