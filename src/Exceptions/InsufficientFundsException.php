<?php

namespace vahidkaargar\LaravelWallet\Exceptions;

use RuntimeException;
use Throwable;

class InsufficientFundsException extends RuntimeException
{
    /**
     * @param $message
     * @param $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "Insufficient funds.", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}