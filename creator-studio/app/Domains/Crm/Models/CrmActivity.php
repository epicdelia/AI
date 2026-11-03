<?php

namespace App\Domains\Crm\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One entry on a contact's timeline: a note, call, email, meeting or a task with a due date.
 */
class CrmActivity extends Model
{
    public const KINDS = ['note', 'call', 'email', 'meeting', 'task'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'done_at' => 'datetime',
        ];
    }
}
