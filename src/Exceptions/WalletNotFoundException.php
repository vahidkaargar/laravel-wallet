<?php

namespace vahidkaargar\LaravelWallet\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

class WalletNotFoundException extends ModelNotFoundException
{
    /**
     * @param $message
     * @param $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "Wallet not found.", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}