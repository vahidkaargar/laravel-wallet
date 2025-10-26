<?php

namespace vahidkaargar\LaravelWallet\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;

class WalletNotFoundException extends ModelNotFoundException
{
    public function __construct($message = "Wallet not found.", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}