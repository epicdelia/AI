<?php

namespace App\Domains\Love;

use Illuminate\Database\Eloquent\Model;

class LoveLetter extends Model
{
    protected $fillable = ['title', 'body', 'unlock', 'unlock_value', 'unlocked_at', 'opened_at'];

    protected $hidden = ['body']; // only sent once it's unlocked (see Love::letters)

    protected function casts(): array
    {
        return ['body' => 'encrypted', 'unlocked_at' => 'datetime', 'opened_at' => 'datetime'];
    }
}
