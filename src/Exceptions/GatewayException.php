<?php

declare(strict_types=1);

namespace App\Exceptions;

class GatewayException extends \RuntimeException
{
    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
