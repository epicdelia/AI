<?php

namespace App\Domains\Engine\Stream;

use Psr\Http\Message\StreamInterface;

/**
 * Reads an upstream provider's SSE body incrementally (MagicAI split the fully-buffered
 * body on "\n"; this does the same line handling but chunk by chunk, so text reaches the
 * browser while the model is still writing).
 */
class SseParser
{
    /**
     * @param  callable(?string $event, string $data): bool|null  $onEvent  return false to stop
     */
    public static function read(StreamInterface $body, callable $onEvent): void
    {
        $buffer = '';
        $event = null;
        $data = [];

        while (!$body->eof()) {
            $buffer .= $body->read(8192);
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = rtrim(substr($buffer, 0, $pos), "\r");
                $buffer = substr($buffer, $pos + 1);

                if ($line === '') {
                    if ($data && $onEvent($event, implode("\n", $data)) === false) {
                        return;
                    }
                    $event = null;
                    $data = [];
                } elseif (str_starts_with($line, 'event:')) {
                    $event = trim(substr($line, 6));
                } elseif (str_starts_with($line, 'data:')) {
                    $data[] = ltrim(substr($line, 5));
                }
            }
            if (connection_aborted()) {
                return;
            }
        }
        if ($data) {
            $onEvent($event, implode("\n", $data));
        }
    }
}
