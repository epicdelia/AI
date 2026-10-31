<?php

namespace App\Domains\Chat\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $fillable = ['conversation_id', 'role', 'content'];
}
