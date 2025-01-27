<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

class MediaException extends Exception
{
    private array $context;

    public function __construct(
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function toArray(): array
    {
        return [
            'status' => 'error',
            'code' => $this->code ?: 500,
            'message' => $this->message,
            'context' => $this->context,
            'trace' => $this->getTraceAsString(),
        ];
    }
}
