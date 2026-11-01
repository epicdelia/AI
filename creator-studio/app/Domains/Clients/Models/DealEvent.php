<?php

namespace App\Domains\Clients\Models;

use Illuminate\Database\Eloquent\Model;

/** A step in a request's timeline, worded for the client (ClientNotifier writes these). */
class DealEvent extends Model
{
    protected $fillable = ['deal_id', 'kind', 'label'];

    public function toRow(): array
    {
        return ['kind' => $this->kind, 'label' => $this->label, 'at' => $this->created_at?->toIso8601String()];
    }
}
