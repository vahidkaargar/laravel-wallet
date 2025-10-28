<?php

namespace vahidkaargar\LaravelWallet\Exceptions;

use Throwable;

class InvalidAmountException extends \InvalidArgumentException
{
    /**
     * @param $message
     * @param $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "Invalid amount provided. Amount must be positive.", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}