<?php

namespace App\Exceptions;

class LogRotateException extends \Exception
{
    public function __construct($message = "", $code = 0, \Throwable $previous = null)
    {
        parent::__construct("Log Rotation Error: " . $message, $code, $previous);
    }
}
