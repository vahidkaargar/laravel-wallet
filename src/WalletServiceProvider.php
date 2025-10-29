<?php

namespace vahidkaargar\LaravelWallet;

use vahidkaargar\LaravelWallet\Services\{ValidationService,
    WalletLedgerService,
    TransactionRollbackService,
    BatchReversalService,
    CreditAgingService,
    CreditManagerService,
    CurrencyConverterService,
    ConfigExchangeRateProvider,
    LoggingService,
    TransactionApprovalService
};
use vahidkaargar\LaravelWallet\Contracts\ExchangeRateProvider;
use Illuminate\Support\ServiceProvider;

class WalletServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        // Register exchange rate provider
        $this->app->singleton(ExchangeRateProvider::class, function ($app) {
            $rates = config('wallet.exchange_rates', [
                'USD' => ['EUR' => 0.95, 'GBP' => 0.82],
                'EUR' => ['USD' => 1.05, 'GBP' => 0.86],
                'GBP' => ['USD' => 1.22, 'EUR' => 1.16],
            ]);
            return new ConfigExchangeRateProvider($rates);
        });

        $this->app->singleton(CurrencyConverterService::class, function ($app) {
            return new CurrencyConverterService($app->make(ExchangeRateProvider::class));
        });

        $this->app->singleton(ValidationService::class, function ($app) {
            return new ValidationService();
        });

        $this->app->singleton(LoggingService::class, function ($app) {
            return new LoggingService();
        });

        $this->app->singleton(CreditManagerService::class, function ($app) {
            return new CreditManagerService();
        });

        $this->app->singleton(TransactionApprovalService::class, function ($app) {
            return new TransactionApprovalService(
                $app->make(CreditManagerService::class),
                $app->make(ValidationService::class)
            );
        });

        $this->app->singleton(WalletLedgerService::class, function ($app) {
            return new WalletLedgerService(
                $app->make(CreditManagerService::class),
                $app->make(TransactionApprovalService::class),
                $app->make(ValidationService::class),
                $app->make(LoggingService::class)
            );
        });

        $this->app->singleton(TransactionRollbackService::class, function ($app) {
            return new TransactionRollbackService(
                $app->make(CreditManagerService::class)
            );
        });

        $this->app->singleton(CreditAgingService::class, function ($app) {
            return new CreditAgingService(
                $app->make(WalletLedgerService::class),
                $app->make(CreditManagerService::class)
            );
        });

        $this->app->singleton(BatchReversalService::class, function ($app) {
            return new BatchReversalService(
                $app->make(TransactionRollbackService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

            $this->publishes([
                __DIR__ . '/database/migrations' => database_path('migrations'),
            ], 'migrations');

            $this->publishes([
                __DIR__ . '/../config/wallet.php' => config_path('wallet.php'),
            ], 'config');
        }
    }
}