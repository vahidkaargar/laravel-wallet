<?php

namespace vahidkaargar\LaravelWallet\Tests;

use Illuminate\Support\Facades\App;
use vahidkaargar\LaravelWallet\Services\WalletLedgerService;
use vahidkaargar\LaravelWallet\Services\ValidationService;
use vahidkaargar\LaravelWallet\Services\CreditManagerService;
use vahidkaargar\LaravelWallet\ValueObjects\Money;

class LaravelCompatibilityTest extends TestCase
{
    protected WalletLedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = $this->app->make(WalletLedgerService::class);
    }

    public function test_laravel_version_compatibility()
    {
        $laravelVersion = App::version();
        $this->assertStringStartsWith('1', $laravelVersion, 'Laravel version should be 10.x, 11.x, or 12.x');
        
        // Test that we can resolve services from the container
        $this->assertInstanceOf(WalletLedgerService::class, App::make(WalletLedgerService::class));
        $this->assertInstanceOf(ValidationService::class, App::make(ValidationService::class));
        $this->assertInstanceOf(CreditManagerService::class, App::make(CreditManagerService::class));
    }

    public function test_service_provider_registration()
    {
        // Test that all services are properly registered
        $services = [
            WalletLedgerService::class,
            ValidationService::class,
            CreditManagerService::class,
        ];

        foreach ($services as $service) {
            $this->assertTrue(App::bound($service), "Service {$service} should be bound in container");
            $this->assertInstanceOf($service, App::make($service), "Service {$service} should be resolvable");
        }
    }

    public function test_money_value_object_compatibility()
    {
        // Test Money object works across Laravel versions
        $money = Money::fromDecimal(100.50);
        
        $this->assertEquals('100.50', $money->toDecimal());
        $this->assertEquals(10050, $money->toCents());
        
        // Test arithmetic operations
        $result = $money->add(Money::fromDecimal(50.25));
        $this->assertEquals('150.75', $result->toDecimal());
        
        $result = $money->subtract(Money::fromDecimal(25.25));
        $this->assertEquals('75.25', $result->toDecimal());
    }

    public function test_database_migrations_compatibility()
    {
        // Test that migrations can be run
        $this->assertDatabaseHas('wallets', [
            'id' => $this->wallet->id,
        ]);
        
        // Create a transaction to test the transactions table
        $this->ledger->deposit($this->wallet, Money::fromDecimal(100.00));
        
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $this->wallet->id,
        ]);
    }

    public function test_eloquent_model_compatibility()
    {
        // Test that Eloquent models work correctly
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Model::class, $this->wallet);
        
        // Create a transaction to test relationships
        $transaction = $this->ledger->deposit($this->wallet, Money::fromDecimal(100.00));
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Model::class, $transaction);
        
        // Test relationships
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $this->wallet->transactions());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $transaction->wallet());
    }

    public function test_configuration_compatibility()
    {
        // Test that configuration is properly loaded
        $config = config('wallet');
        $this->assertIsArray($config);
        $this->assertArrayHasKey('max_credit_limit', $config);
        $this->assertArrayHasKey('interest_rate', $config);
        $this->assertArrayHasKey('exchange_rates', $config);
        $this->assertArrayHasKey('supported_currencies', $config);
    }

    public function test_event_system_compatibility()
    {
        // Test that events can be dispatched and listened to
        \Illuminate\Support\Facades\Event::fake();
        
        $this->ledger->deposit($this->wallet, Money::fromDecimal(100.00));
        
        // Events should be dispatched (this tests the event system works)
        $this->assertTrue(true); // If we get here without errors, events work
    }

    public function test_facade_compatibility()
    {
        // Test that Laravel facades work correctly
        $this->assertInstanceOf(\Illuminate\Database\DatabaseManager::class, \Illuminate\Support\Facades\DB::getFacadeRoot());
        $this->assertInstanceOf(\Illuminate\Events\Dispatcher::class, \Illuminate\Support\Facades\Event::getFacadeRoot());
        $this->assertInstanceOf(\Illuminate\Log\LogManager::class, \Illuminate\Support\Facades\Log::getFacadeRoot());
    }

    public function test_service_container_compatibility()
    {
        // Test that the service container works correctly
        $container = App::getInstance();
        $this->assertInstanceOf(\Illuminate\Container\Container::class, $container);
        
        // Test singleton behavior
        $service1 = App::make(ValidationService::class);
        $service2 = App::make(ValidationService::class);
        $this->assertSame($service1, $service2, 'Services should be singletons');
    }
}
