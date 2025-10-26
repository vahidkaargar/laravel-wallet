# Laravel Wallet Enterprise

Finance-grade wallet & credit system for Laravel 10+, 11, and 12.

## Features

- **Multi-currency support** with real-time exchange rates
- **Automatic credit repayment** with debt management
- **Credit limits & aging** with configurable interest rates
- **Interest on outstanding credit** with automated aging
- **Transaction workflow**: pending/approved/rejected/reversed
- **Scheduled batch reversals** for expired transactions
- **Complete ledger audit trail** for financial compliance
- **Event-driven notifications** for all wallet operations
- **Lock/Unlock funds** for escrow and hold operations
- **Role-based credit enforcement** with validation layers
- **Precise decimal arithmetic** using bcmath for financial accuracy
- **Thread-safe operations** with pessimistic locking
- **Comprehensive test coverage** with 80+ tests
- **Cross-wallet transfers** with automatic currency conversion

## Requirements

- **PHP**: 8.2 or higher
- **Laravel**: 10.x, 11.x, or 12.x
- **Database**: MySQL, PostgreSQL, SQLite, or SQL Server
- **Extensions**: bcmath (required for precise monetary calculations)

## Installation

```bash
composer require vahidkaargar/laravel-wallet
php artisan vendor:publish --provider="vahidkaargar\LaravelWallet\WalletServiceProvider"
php artisan migrate
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=config --provider="vahidkaargar\LaravelWallet\WalletServiceProvider"
```

Configure your wallet settings in `config/wallet.php`:

```php
return [
    'max_credit_limit' => 100000.00,
    'interest_rate' => 0.05, // 5% annual interest
    'exchange_rates' => [
        'USD' => ['EUR' => 0.95, 'GBP' => 0.82],
        'EUR' => ['USD' => 1.05, 'GBP' => 0.86],
        'GBP' => ['USD' => 1.22, 'EUR' => 1.16],
    ],
    'supported_currencies' => ['USD', 'EUR', 'GBP', 'JPY'],
    'audit_logging' => true,
];
```

## Quick Start

### 1. Add the HasWallets trait to your User model

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use vahidkaargar\LaravelWallet\Traits\HasWallets;

class User extends Authenticatable
{
    use HasWallets;
}
```

### 2. Create wallets

```php
$user = User::find(1);

// Create USD wallet
$usdWallet = $user->createWallet([
    'name' => 'USD Wallet',
    'slug' => 'usd',
    'currency' => 'USD',
    'description' => 'Primary USD wallet',
    'is_active' => true,
]);

// Create EUR wallet
$eurWallet = $user->createWallet([
    'name' => 'EUR Wallet',
    'slug' => 'eur',
    'currency' => 'EUR',
    'description' => 'Primary EUR wallet',
    'is_active' => true,
]);
```

### 3. Basic wallet operations

```php
use vahidkaargar\LaravelWallet\ValueObjects\Money;

// Deposit funds
$user->deposit('usd', Money::fromDecimal(1000.00));

// Withdraw funds
$user->withdraw('usd', Money::fromDecimal(250.00));

// Grant credit
$user->grantCredit('usd', Money::fromDecimal(5000.00));

// Lock funds (for escrow)
$user->lock('usd', Money::fromDecimal(100.00));

// Unlock funds
$user->unlock('usd', Money::fromDecimal(100.00));
```

### 4. Cross-wallet transfers with currency conversion

```php
// Transfer from USD wallet to EUR wallet (automatic conversion)
$result = $user->transfer('usd', 'eur', Money::fromDecimal(100.00));

// Result contains both transactions and conversion details
echo "Original amount: " . $result['original_amount']->toDecimal() . " USD\n";
echo "Converted amount: " . $result['converted_amount']->toDecimal() . " EUR\n";
echo "Exchange rate: " . $result['conversion_rate'] . "\n";
```

### 5. Check wallet status

```php
// Get wallet summary
$summary = $user->getWalletSummary('usd');
/*
[
    'balance' => Money object,
    'available_balance' => Money object,
    'available_funds' => Money object,
    'locked' => Money object,
    'credit' => Money object,
    'debt' => Money object,
]
*/

// Check if sufficient funds are available
$hasFunds = $user->hasSufficientFunds('usd', Money::fromDecimal(500.00));
```

## Advanced Usage

### Transaction Workflow

```php
use vahidkaargar\LaravelWallet\Services\WalletLedgerService;
use vahidkaargar\LaravelWallet\Models\WalletTransaction;

$ledger = app(WalletLedgerService::class);

// Create a pending transaction
$transaction = $ledger->deposit($wallet, Money::fromDecimal(1000.00), false);

// Approve the transaction
$approvalService = app(\vahidkaargar\LaravelWallet\Services\TransactionApprovalService::class);
$approvalService->approve($transaction);

// Or reject it
$approvalService->reject($transaction, 'Insufficient documentation');
```

### Credit Management

```php
use vahidkaargar\LaravelWallet\Services\CreditManagerService;

$creditManager = app(CreditManagerService::class);

// Check available credit
$availableCredit = $creditManager->getAvailableCredit($wallet);

// Calculate debt
$debt = $creditManager->getDebt($wallet);

// Charge interest on outstanding debt
$agingService = app(\vahidkaargar\LaravelWallet\Services\CreditAgingService::class);
$agingService->processWalletAging($wallet);
```

### Custom Exchange Rate Provider

You can implement your own exchange rate provider to fetch live rates from external APIs. Here's a complete example:

```php
use vahidkaargar\LaravelWallet\Contracts\ExchangeRateProvider;
use vahidkaargar\LaravelWallet\Services\CurrencyConverterService;
use vahidkaargar\LaravelWallet\ValueObjects\Money;

// Example: Live exchange rate provider using an external API
class LiveExchangeRateProvider implements ExchangeRateProvider
{
    private string $apiKey;
    private string $baseUrl;
    
    public function __construct(string $apiKey, string $baseUrl = 'https://api.exchangerate-api.com/v4/latest/')
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
    }

    public function getExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        // Fetch live rates from your preferred API
        $rates = $this->fetchRatesFromAPI($fromCurrency);
        
        if (!isset($rates[$toCurrency])) {
            throw new \Exception("Exchange rate not found for {$fromCurrency} to {$toCurrency}");
        }

        return $rates[$toCurrency];
    }

    public function convert(Money $money, string $toCurrency): Money
    {
        // Note: This assumes the money is in USD - you might want to track currency in Money object
        $rate = $this->getExchangeRate('USD', $toCurrency);
        return $money->multiply($rate);
    }

    public function supports(string $fromCurrency, string $toCurrency): bool
    {
        $supportedCurrencies = ['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD'];
        return in_array($fromCurrency, $supportedCurrencies) && 
               in_array($toCurrency, $supportedCurrencies);
    }

    private function fetchRatesFromAPI(string $baseCurrency): array
    {
        $url = $this->baseUrl . $baseCurrency . '?access_key=' . $this->apiKey;
        
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['rates'])) {
            throw new \Exception("Failed to fetch exchange rates from API");
        }
        
        return $data['rates'];
    }
}

// Example: Cached exchange rate provider (recommended for production)
class CachedExchangeRateProvider implements ExchangeRateProvider
{
    private ExchangeRateProvider $provider;
    private int $cacheMinutes;
    
    public function __construct(ExchangeRateProvider $provider, int $cacheMinutes = 60)
    {
        $this->provider = $provider;
        $this->cacheMinutes = $cacheMinutes;
    }

    public function getExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        $cacheKey = "exchange_rate_{$fromCurrency}_{$toCurrency}";
        
        return \Cache::remember($cacheKey, $this->cacheMinutes, function () use ($fromCurrency, $toCurrency) {
            return $this->provider->getExchangeRate($fromCurrency, $toCurrency);
        });
    }

    public function convert(Money $money, string $toCurrency): Money
    {
        return $this->provider->convert($money, $toCurrency);
    }

    public function supports(string $fromCurrency, string $toCurrency): bool
    {
        return $this->provider->supports($fromCurrency, $toCurrency);
    }
}
```

#### Registering Your Custom Provider

**Option 1: Service Provider (Recommended)**

```php
// In your AppServiceProvider or a custom service provider
use vahidkaargar\LaravelWallet\Contracts\ExchangeRateProvider;
use vahidkaargar\LaravelWallet\Services\CurrencyConverterService;

public function register()
{
    // Register your custom exchange rate provider
    $this->app->singleton(ExchangeRateProvider::class, function ($app) {
        $liveProvider = new LiveExchangeRateProvider(
            config('services.exchange_rate_api_key'),
            config('services.exchange_rate_base_url')
        );
        
        // Wrap with caching for better performance
        return new CachedExchangeRateProvider($liveProvider, 60);
    });
}
```

**Option 2: Runtime Registration**

```php
// Register your provider at runtime
$converter = app(CurrencyConverterService::class);
$converter->setExchangeRateProvider(new LiveExchangeRateProvider('your-api-key'));
```

#### Configuration Example

Add to your `config/services.php`:

```php
return [
    // ... other services
    
    'exchange_rate_api_key' => env('EXCHANGE_RATE_API_KEY'),
    'exchange_rate_base_url' => env('EXCHANGE_RATE_BASE_URL', 'https://api.exchangerate-api.com/v4/latest/'),
];
```

#### Usage Example

```php
// Now all transfers will use your live exchange rates
$result = $user->transfer('usd', 'eur', Money::fromDecimal(100.00));

// The conversion will use live rates from your API
echo "Live rate: " . $result['conversion_rate'] . "\n";
echo "Converted amount: " . $result['converted_amount']->toDecimal() . " EUR\n";
```

#### Error Handling

```php
try {
    $result = $user->transfer('usd', 'eur', Money::fromDecimal(100.00));
} catch (\Exception $e) {
    // Handle exchange rate API failures
    if (str_contains($e->getMessage(), 'Exchange rate not found')) {
        // Fallback to config rates or show error to user
        Log::error('Exchange rate API failed', ['error' => $e->getMessage()]);
    }
}
```

### Batch Operations

```php
use vahidkaargar\LaravelWallet\Services\BatchReversalService;
use Carbon\Carbon;

$batchService = app(BatchReversalService::class);

// Reject expired pending transactions
$count = $batchService->rejectPendingOlderThan(
    Carbon::now()->subDays(7),
    'Transaction expired'
);

// Rollback approved transactions older than 30 days
$count = $batchService->rollbackApprovedByTypeOlderThan(
    WalletTransaction::TYPE_DEPOSIT,
    Carbon::now()->subDays(30),
    'Regulatory compliance'
);
```

### Multi-Currency Operations

```php
// Create wallets in different currencies
$usdWallet = $user->createWallet(['slug' => 'usd', 'currency' => 'USD']);
$eurWallet = $user->createWallet(['slug' => 'eur', 'currency' => 'EUR']);
$gbpWallet = $user->createWallet(['slug' => 'gbp', 'currency' => 'GBP']);

// Deposit in different currencies
$user->deposit('usd', Money::fromDecimal(1000.00));
$user->deposit('eur', Money::fromDecimal(800.00));
$user->deposit('gbp', Money::fromDecimal(600.00));

// Transfer between different currencies
$result = $user->transfer('usd', 'eur', Money::fromDecimal(100.00));
// 100 USD becomes 95 EUR (rate: 0.95)

$result = $user->transfer('eur', 'gbp', Money::fromDecimal(50.00));
// 50 EUR becomes 43 GBP (rate: 0.86)
```

### Credit and Debt Management

```php
// Grant credit to a wallet
$user->grantCredit('usd', Money::fromDecimal(5000.00));

// Withdraw using credit (creates debt)
$user->withdraw('usd', Money::fromDecimal(2000.00));
// Balance: -2000, Credit: 5000, Available funds: 3000

// Deposit automatically repays debt
$user->deposit('usd', Money::fromDecimal(1500.00));
// Balance: -500, Credit: 5000, Debt: 500

// Revoke credit (cannot exceed current debt)
$user->revokeCredit('usd', Money::fromDecimal(4500.00));
// New credit limit: 500 (matches current debt)
```

### Fund Locking and Escrow

```php
// Lock funds for escrow
$user->lock('usd', Money::fromDecimal(500.00));
// Available balance decreases, locked amount increases

// Process escrow (example: approve a purchase)
// ... business logic ...

// Unlock funds after successful transaction
$user->unlock('usd', Money::fromDecimal(500.00));
// Locked amount decreases, available balance increases
```

### Interest and Aging

```php
use vahidkaargar\LaravelWallet\Services\CreditAgingService;

$agingService = app(CreditAgingService::class);

// Process aging for all wallets (typically run as scheduled job)
$agingService->processWalletAging($wallet);

// This will:
// 1. Calculate outstanding debt
// 2. Apply interest charges
// 3. Create interest_charge transactions
// 4. Update wallet balance
```

### Event Handling

```php
use vahidkaargar\LaravelWallet\Events\WalletTransactionCreated;
use vahidkaargar\LaravelWallet\Events\CreditGranted;
use vahidkaargar\LaravelWallet\Events\CreditRepaid;

// Listen to wallet events
Event::listen(WalletTransactionCreated::class, function ($event) {
    Log::info('Transaction created', [
        'wallet_id' => $event->wallet->id,
        'amount' => $event->amount->toDecimal(),
        'type' => $event->transaction->type,
    ]);
});

Event::listen(CreditGranted::class, function ($event) {
    // Send notification to user about credit increase
    $event->wallet->user->notify(new CreditGrantedNotification($event->amount));
});
```

## Testing

The package includes comprehensive test coverage with 80+ tests covering:

- Financial calculations and precision
- Transaction workflows and state management
- Credit management and debt calculations
- Batch operations and reversals
- Laravel compatibility across versions 10, 11, and 12
- Edge cases and error handling
- Multi-currency transfers and conversions

Run the tests:

```bash
composer test
```

Run tests with code coverage (requires Xdebug or PCOV):

```bash
composer test-coverage
```

If you get a "No code coverage driver available" warning, you can:

1. **Install a coverage driver:**
   ```bash
   # For Xdebug (development)
   brew install php-xdebug  # macOS
   sudo apt-get install php-xdebug  # Ubuntu
   
   # For PCOV (faster, CI-friendly)
   brew install php-pcov  # macOS
   sudo apt-get install php-pcov  # Ubuntu
   ```

2. **Or use the setup script:**
   ```bash
   ./setup-coverage.sh
   ```

3. **Or just run tests without coverage:**
   ```bash
   composer test
   ```

## Security Features

- **Precise decimal arithmetic** using bcmath to prevent floating-point errors
- **Pessimistic locking** to prevent race conditions
- **Atomic transactions** for all financial operations
- **Comprehensive validation** before any wallet state changes
- **Audit trail** for all financial operations
- **Role-based access control** through Laravel's authorization system
- **Currency validation** to prevent invalid currency operations

## Laravel Compatibility

This package is fully compatible with:

- **Laravel 10.x** 
- **Laravel 11.x** 
- **Laravel 12.x**
- **PHP 8.2+**

The package automatically adapts to different Laravel versions and includes compatibility tests to ensure smooth operation across all supported versions.

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Support

For support and questions:

- Create an issue on GitHub
- Email: vahidkaargar@gmail.com

## Acknowledgments

- Built with Laravel's excellent service container and Eloquent ORM
- Inspired by financial industry best practices
- Designed for enterprise-grade reliability and security
