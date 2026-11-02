<?php

namespace App\Domains\Social\Connect;

use RuntimeException;

/**
 * A network said no. $reconnect = the sign-in is no longer valid and she has to connect again.
 */
class ConnectException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $reconnect = false
    ) {
        parent::__construct($message);
    }
}
