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
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for error and audit logging in wallet operations.
    |
    */
    'logging' => [
        /*
        |--------------------------------------------------------------------------
        | Enable Error Logging
        |--------------------------------------------------------------------------
        |
        | Whether to enable error logging for wallet operations. Set to false
        | to disable all error logging for performance optimization.
        |
        */
        'enabled' => env('WALLET_ERROR_LOGGING', true),

        /*
        |--------------------------------------------------------------------------
        | Enable Audit Logging
        |--------------------------------------------------------------------------
        |
        | Whether to enable detailed audit logging for all financial operations.
        | This logs successful operations for compliance and auditing purposes.
        |
        */
        'audit_enabled' => env('WALLET_AUDIT_LOGGING', true),

        /*
        |--------------------------------------------------------------------------
        | Log Channel
        |--------------------------------------------------------------------------
        |
        | The log channel to use for wallet operations. This can be any channel
        | defined in your logging configuration (e.g., 'single', 'daily', 'slack').
        | Set to null to use the default channel.
        |
        */
        'channel' => env('WALLET_LOG_CHANNEL', 'null'),

        /*
        |--------------------------------------------------------------------------
        | Log Level
        |--------------------------------------------------------------------------
        |
        | The minimum log level for wallet operations. Options: debug, info,
        | notice, warning, error, critical, alert, emergency.
        |
        */
        'level' => env('WALLET_LOG_LEVEL', 'error'),

        /*
        |--------------------------------------------------------------------------
        | Include Stack Traces
        |--------------------------------------------------------------------------
        |
        | Whether to include full stack traces in error logs. Disable for
        | performance optimization in production.
        |
        */
        'include_stack_trace' => env('WALLET_LOG_STACK_TRACE', true),

        /*
        |--------------------------------------------------------------------------
        | Sensitive Data Masking
        |--------------------------------------------------------------------------
        |
        | Whether to mask sensitive data in logs (e.g., partial credit card numbers).
        | Enable for compliance with data protection regulations.
        |
        */
        'mask_sensitive_data' => env('WALLET_MASK_SENSITIVE_DATA', false),
    ],

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
