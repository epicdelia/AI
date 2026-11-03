<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserUsageCredit extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'model_key', 'credit', 'unit_price', 'total'];

    protected $table = 'user_usage_credits';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
