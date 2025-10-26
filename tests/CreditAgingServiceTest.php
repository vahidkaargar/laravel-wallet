<?php

namespace vahidkaargar\LaravelWallet\Tests;

use vahidkaargar\LaravelWallet\Services\CreditAgingService;
use vahidkaargar\LaravelWallet\Services\CreditManagerService;
use vahidkaargar\LaravelWallet\Services\WalletLedgerService;
use vahidkaargar\LaravelWallet\ValueObjects\Money;
use Mockery\MockInterface;

class CreditAgingServiceTest extends TestCase
{
    protected CreditAgingService $agingService;
    protected WalletLedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        // We need to mock the CreditManagerService to control interest calculation
        $this->mock(CreditManagerService::class, function (MockInterface $mock) {
            // Mock getDebt to return Money object when called
            $mock->shouldReceive('getDebt')->andReturn(Money::fromDecimal(100.00));

            // Mock calculateInterest to return Money object when called
            $mock->shouldReceive('calculateInterest')->andReturn(Money::fromDecimal(5.00));

            // Allow other methods to be called normally
            $mock->shouldAllowMockingProtectedMethods()->makePartial();
        });

        $this->agingService = $this->app->make(CreditAgingService::class);
        $this->ledger = $this->app->make(WalletLedgerService::class);
    }

    public function test_interest_is_charged_on_debt()
    {
        // Manually set a negative balance to simulate debt
        $this->wallet->balance = -100.00;
        $this->wallet->save();

        $this->agingService->processWalletAging($this->wallet);

        // Balance should be -100 (original) - 5 (interest) = -105
        $this->assertDatabaseHas('wallets', [
            'id' => $this->wallet->id,
            'balance' => -105.00,
        ]);

        // A new "interest_charge" transaction should exist
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $this->wallet->id,
            'type' => 'interest_charge',
            'amount' => 5.00,
            'status' => 'approved',
        ]);
    }
}