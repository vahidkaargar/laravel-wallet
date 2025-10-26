<?php

namespace vahidkaargar\LaravelWallet\Tests;

use vahidkaargar\LaravelWallet\Events\CreditGranted;
use vahidkaargar\LaravelWallet\Events\CreditRepaid;
use vahidkaargar\LaravelWallet\Exceptions\InsufficientFundsException;
use vahidkaargar\LaravelWallet\Services\CreditManagerService;
use vahidkaargar\LaravelWallet\Services\WalletLedgerService;
use vahidkaargar\LaravelWallet\ValueObjects\Money;
use Illuminate\Support\Facades\Event;

class CreditManagerServiceTest extends TestCase
{
    protected WalletLedgerService $ledger;
    protected CreditManagerService $creditManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = $this->app->make(WalletLedgerService::class);
        $this->creditManager = $this->app->make(CreditManagerService::class);
    }

    public function test_can_grant_and_revoke_credit()
    {
        Event::fake();

        $this->ledger->grantCredit($this->wallet, Money::fromDecimal(500.00));
        $this->wallet->refresh(); // Refresh to get updated credit
        $this->assertDatabaseHas('wallets', [
            'id' => $this->wallet->id,
            'credit' => 500.00,
        ]);
        Event::assertDispatched(CreditGranted::class);

        $this->ledger->revokeCredit($this->wallet, Money::fromDecimal(100.00));
        $this->assertDatabaseHas('wallets', [
            'id' => $this->wallet->id,
            'credit' => 400.00,
        ]);
    }

    public function test_can_withdraw_using_credit_balance()
    {
        $this->ledger->grantCredit($this->wallet, Money::fromDecimal(500.00));
        $this->wallet->refresh(); // Refresh to get updated credit

        // Wallet balance is 0, withdraw 100
        $this->ledger->withdraw($this->wallet, Money::fromDecimal(100.00));

        $this->assertDatabaseHas('wallets', [
            'id' => $this->wallet->id,
            'balance' => -100.00,
            'credit' => 500.00,
        ]);

        $this->assertEquals(100.00, $this->creditManager->getDebt($this->wallet->refresh())->toDecimal());
    }

    public function test_withdraw_respects_credit_limit()
    {
        $this->ledger->deposit($this->wallet, Money::fromDecimal(50.00)); // Balance: 50
        $this->ledger->grantCredit($this->wallet, Money::fromDecimal(100.00)); // Credit: 100
        $this->wallet->refresh(); // Refresh to get updated balance and credit

        // Total available: 50 (balance) + 100 (credit) = 150

        // This should fail (150.01 > 150)
        $this->expectException(InsufficientFundsException::class);
        $this->ledger->withdraw($this->wallet, Money::fromDecimal(150.01));

        // This should succeed
        $this->ledger->withdraw($this->wallet, Money::fromDecimal(150.00));
        $this->assertDatabaseHas('wallets', [
            'id' => $this->wallet->id,
            'balance' => -100.00,
        ]);
    }

    public function test_deposit_automatically_repays_debt()
    {
        Event::fake();
        $this->ledger->grantCredit($this->wallet, Money::fromDecimal(500.00));
        $this->wallet->refresh(); // Refresh to get updated credit
        
        $this->ledger->withdraw($this->wallet, Money::fromDecimal(100.00)); // Balance: -100
        $this->wallet->refresh(); // Refresh to get updated balance

        $this->assertEquals(100.00, $this->creditManager->getDebt($this->wallet->refresh())->toDecimal());

        // Deposit 150. 100 repays debt, 50 goes to balance.
        $this->ledger->deposit($this->wallet, Money::fromDecimal(150.00));

        $this->assertDatabaseHas('wallets', [
            'id' => $this->wallet->id,
            'balance' => 50.00,
        ]);

        $this->assertEquals(0, $this->creditManager->getDebt($this->wallet->refresh())->toDecimal());

        // Check that the CreditRepaid event fired for the correct amount
        Event::assertDispatched(CreditRepaid::class, function (CreditRepaid $event) {
            return $event->amount == 100.00;
        });
    }

    public function test_cannot_revoke_credit_beyond_debt()
    {
        $this->ledger->grantCredit($this->wallet, Money::fromDecimal(500.00));
        $this->wallet->refresh(); // Refresh to get updated credit
        
        $this->ledger->withdraw($this->wallet, Money::fromDecimal(300.00)); // Balance: -300 (Debt: 300)
        $this->wallet->refresh(); // Refresh to get updated balance

        // Try to revoke 300 credit (new limit 200). Debt (300) > new limit (200) = fail
        $this->expectException(\Exception::class);
        $this->ledger->revokeCredit($this->wallet, Money::fromDecimal(300.00));

        // This should be fine (new limit 300. Debt 300 <= new limit 300)
        $this->ledger->revokeCredit($this->wallet, Money::fromDecimal(200.00));
        $this->assertDatabaseHas('wallets', [
            'id' => $this->wallet->id,
            'credit' => 300.00,
        ]);
    }
}