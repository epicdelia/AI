<?php

namespace App\Domains\Community\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One member's upvote on one item (unique per member and item).
 */
class CommunityVote extends Model
{
    protected $guarded = ['id'];
}
