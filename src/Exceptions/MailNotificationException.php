<?php

namespace App\Exceptions;

class MailNotificationException extends \Exception
{
    public function __construct($message = "", $code = 0, \Throwable $previous = null)
    {
        parent::__construct("Mail Notification Error: " . $message, $code, $previous);
    }
}
