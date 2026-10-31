<?php

namespace App\Domains\Engine\Contracts;

use App\Domains\Engine\Data\ChatResult;
use App\Domains\Engine\Data\ToolTurn;
use App\Domains\Engine\Enums\EngineEnum;

interface EngineDriver
{
    public function enum(): EngineEnum;

    /**
     * @param  array<int, array{role: string, content: string}>  $messages  user/assistant turns
     * @param  array{temperature?: float, max_tokens?: int, timeout?: int}  $options
     */
    public function chat(string $system, array $messages, array $options = []): ChatResult;

    /**
     * Same as chat(), but calls $onDelta with each piece of text as the model writes it.
     *
     * @param  callable(string $delta): void  $onDelta
     */
    public function stream(string $system, array $messages, callable $onDelta, array $options = []): ChatResult;

    /**
     * One step of a tool-using conversation (native function calling).
     *
     * $messages use a provider-neutral shape:
     *   ['role' => 'user', 'content' => string]
     *   ['role' => 'assistant', 'content' => ?string, 'tool_calls' => [['id', 'name', 'args']]]
     *   ['role' => 'tool', 'tool_call_id' => string, 'name' => string, 'content' => string]
     * $tools: [['name', 'description', 'parameters' => JSON schema]]
     */
    public function toolChat(string $system, array $messages, array $tools, array $options = []): ToolTurn;
}
