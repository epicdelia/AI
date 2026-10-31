<?php

namespace App\Domains\Engine\Data;

/** One completed chat call: the text plus whatever token counts the provider reported. */
final class ChatResult
{
    public function __construct(
        public readonly string $text,
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
        public readonly ?string $model = null,
    ) {}
}
