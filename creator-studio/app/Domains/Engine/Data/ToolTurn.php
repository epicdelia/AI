<?php

namespace App\Domains\Engine\Data;

/**
 * One assistant turn in a tool-using conversation: either final text, or tool calls
 * (each ['id' => string, 'name' => string, 'args' => array]) to run before the next turn.
 */
final class ToolTurn
{
    /**
     * @param array<int, array{id: string, name: string, args: array}> $toolCalls
     */
    public function __construct(
        public readonly string $text,
        public readonly array $toolCalls = [],
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
        public readonly ?string $model = null,
    ) {
    }

    public function wantsTools(): bool
    {
        return $this->toolCalls !== [];
    }
}
