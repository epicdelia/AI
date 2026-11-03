<?php

namespace App\Domains\Community\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A points ledger entry: asking, getting upvoted, being answered, a featured project.
 */
class CommunityPoint extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['points' => 'integer'];
    }
}
