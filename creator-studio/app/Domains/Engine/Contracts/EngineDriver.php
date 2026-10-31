<?php

namespace App\Domains\Engine\Contracts;

use App\Domains\Engine\Data\ChatResult;
use App\Domains\Engine\Enums\EngineEnum;

interface EngineDriver
{
    public function enum(): EngineEnum;

    /**
     * @param  array<int, array{role: string, content: string}>  $messages  user/assistant turns
     * @param  array{temperature?: float, max_tokens?: int, timeout?: int}  $options
     */
    public function chat(string $system, array $messages, array $options = []): ChatResult;
}
