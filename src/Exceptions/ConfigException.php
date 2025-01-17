<?php

namespace App\Exceptions;

class ConfigException extends \Exception
{
    public function __construct($message = "", $code = 0, \Throwable $previous = null)
    {
        parent::__construct("Configuration Error: " . $message, $code, $previous);
    }
}
