<?php

namespace vahidkaargar\LaravelWallet\Exceptions;

class InvalidAmountException extends \InvalidArgumentException
{
    public function __construct($message = "Invalid amount provided. Amount must be positive.", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}