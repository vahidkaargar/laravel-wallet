<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Wallet Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the Laravel Wallet package.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Maximum Transaction Amount
    |--------------------------------------------------------------------------
    |
    | The maximum amount allowed for a single transaction to prevent overflow attacks.
    |
    */
    'max_transaction_amount' => env('WALLET_MAX_TRANSACTION_AMOUNT', 999999.99),

    /*
    |--------------------------------------------------------------------------
    | Maximum Credit Limit
    |--------------------------------------------------------------------------
    |
    | The maximum credit limit that can be granted to any wallet.
    |
    */
    'max_credit_limit' => env('WALLET_MAX_CREDIT_LIMIT', 100000.00),

    /*
    |--------------------------------------------------------------------------
    | Interest Rate
    |--------------------------------------------------------------------------
    |
    | Default interest rate for credit aging (e.g., 0.05 = 5%).
    |
    */
    'interest_rate' => env('WALLET_INTEREST_RATE', 0.05),

    /*
    |--------------------------------------------------------------------------
    | Exchange Rates
    |--------------------------------------------------------------------------
    |
    | Exchange rates for currency conversion. In production, these should be
    | loaded from an external API or service.
    |
    */
    'exchange_rates' => [
        'USD' => [
            'EUR' => 0.95,
            'GBP' => 0.82,
            'JPY' => 150.00,
        ],
        'EUR' => [
            'USD' => 1.05,
            'GBP' => 0.86,
            'JPY' => 157.89,
        ],
        'GBP' => [
            'USD' => 1.22,
            'EUR' => 1.16,
            'JPY' => 182.93,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-approve Transactions
    |--------------------------------------------------------------------------
    |
    | Whether to auto-approve transactions by default. Set to false for
    | manual approval workflow.
    |
    */
    'auto_approve_transactions' => env('WALLET_AUTO_APPROVE', true),

    /*
    |--------------------------------------------------------------------------
    | Transaction Timeout
    |--------------------------------------------------------------------------
    |
    | How long (in minutes) pending transactions should remain valid before
    | being automatically rejected.
    |
    */
    'transaction_timeout_minutes' => env('WALLET_TRANSACTION_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Whether to enable detailed audit logging for all financial operations.
    |
    */
    'audit_logging' => env('WALLET_AUDIT_LOGGING', true),

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies
    |--------------------------------------------------------------------------
    |
    | List of supported currencies for wallet operations.
    |
    */
    'supported_currencies' => [
        'USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD', 'CHF', 'CNY'
    ],
];
