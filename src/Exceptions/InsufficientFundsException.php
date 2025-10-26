<?php

namespace vahidkaargar\LaravelWallet\Exceptions;

class InsufficientFundsException extends \RuntimeException
{
    public function __construct($message = "Insufficient funds.", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}