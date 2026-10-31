<?php

namespace App\Domains\Memory;

use Illuminate\Database\Eloquent\Model;

class MemoryChunk extends Model
{
    protected $fillable = ['source_type', 'source_id', 'title', 'position', 'content', 'vector', 'model', 'hash'];

    protected $hidden = ['vector'];

    public function vectorArray(): ?array
    {
        return $this->vector ? json_decode($this->vector, true) : null;
    }
}
