<?php

namespace App\Ai;

use RuntimeException;

class AiException extends RuntimeException
{
    /** not_configured | limit | unavailable | format | error */
    public function __construct(string $message, public readonly string $reason = 'error')
    {
        parent::__construct($message);
    }
}
