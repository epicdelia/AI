<?php

namespace App\Domains\Copilot\Models;

use Illuminate\Database\Eloquent\Model;

class CopilotFeedback extends Model
{
    protected $table = 'copilot_feedback';

    protected $fillable = ['copilot_thread_id', 'copilot_message_id', 'rating', 'text'];
}
