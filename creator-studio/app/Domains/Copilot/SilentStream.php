<?php

namespace App\Domains\Copilot;

use App\Domains\Engine\Stream\StreamService;

/**
 * A StreamService that goes nowhere: for agent runs with nobody watching (the scheduler).
 */
class SilentStream extends StreamService
{
    public function emit(string $event, string $data): void
    {
    }
}
