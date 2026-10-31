<?php

namespace App\Domains\Copilot\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CopilotThread extends Model
{
    protected $fillable = ['title', 'project', 'mode', 'model'];

    public function messages(): HasMany
    {
        return $this->hasMany(CopilotMessage::class)->orderBy('id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(CopilotAction::class)->orderBy('id');
    }
}
