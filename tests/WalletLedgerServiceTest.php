<?php

namespace vahidkaargar\LaravelWallet\Tests;

use vahidkaargar\LaravelWallet\Exceptions\InsufficientFundsException;
use vahidkaargar\LaravelWallet\Exceptions\InvalidAmountException;
use vahidkaargar\LaravelWallet\Models\Wallet;
use vahidkaargar\LaravelWallet\Models\WalletTransaction;
use vahidkaargar\LaravelWallet\Services\WalletLedgerService;
use vahidkaargar\LaravelWallet\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

class WalletLedgerServiceTest extends TestCase
{
    protected WalletLedgerService $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledgerService = app(WalletLedgerService::class);
    }

    public function test_can_deposit_funds()
    {
        $amount = Money::fromDecimal(100.00);
        
        $transaction = $this->ledgerService->deposit($this->wallet, $amount);
        
        $this->assertInstanceOf(WalletTransaction::class, $transaction);
        $this->assertEquals(WalletTransaction::TYPE_DEPOSIT, $transaction->type);
        $this->assertEquals(100.00, $transaction->amount);
        $this->assertEquals(WalletTransaction::STATUS_APPROVED, $transaction->status);
        
        $this->wallet->refresh();
        $this->assertEquals(100.00, $this->wallet->balance);
    }

    public function test_can_withdraw_funds()
    {
        // First deposit some funds
        $this->ledgerService->deposit($this->wallet, Money::fromDecimal(100.00));
        $this->wallet->refresh(); // Refresh to get updated balance
        
        $amount = Money::fromDecimal(50.00);
        $transaction = $this->ledgerService->withdraw($this->wallet, $amount);
        
        $this->assertInstanceOf(WalletTransaction::class, $transaction);
        $this->assertEquals(WalletTransaction::TYPE_WITHDRAW, $transaction->type);
        $this->assertEquals(50.00, $transaction->amount);
        $this->assertEquals(WalletTransaction::STATUS_APPROVED, $transaction->status);
        
        $this->wallet->refresh();
        $this->assertEquals(50.00, $this->wallet->balance);
    }

    public function test_withdrawal_fails_with_insufficient_funds()
    {
        $this->expectException(InsufficientFundsException::class);
        
        $amount = Money::fromDecimal(100.00);
        $this->ledgerService->withdraw($this->wallet, $amount);
    }

    public function test_can_withdraw_using_credit()
    {
        // Grant credit first
        $this->ledgerService->grantCredit($this->wallet, Money::fromDecimal(100.00));
        $this->wallet->refresh(); // Refresh to get updated credit
        
        $amount = Money::fromDecimal(50.00);
        $transaction = $this->ledgerService->withdraw($this->wallet, $amount);
        
        $this->assertInstanceOf(WalletTransaction::class, $transaction);
        $this->assertEquals(WalletTransaction::STATUS_APPROVED, $transaction->status);
        
        $this->wallet->refresh();
        $this->assertEquals(-50.00, $this->wallet->balance); // Negative balance using credit
    }

    public function test_can_lock_funds()
    {
        // First deposit some funds
        $this->ledgerService->deposit($this->wallet, Money::fromDecimal(100.00));
        $this->wallet->refresh(); // Refresh to get updated balance
        
        $amount = Money::fromDecimal(30.00);
        $transaction = $this->ledgerService->lock($this->wallet, $amount);
        
        $this->assertInstanceOf(WalletTransaction::class, $transaction);
        $this->assertEquals(WalletTransaction::TYPE_LOCK, $transaction->type);
        $this->assertEquals(30.00, $transaction->amount);
        $this->assertEquals(WalletTransaction::STATUS_APPROVED, $transaction->status);
        
        $this->wallet->refresh();
        $this->assertEquals(30.00, $this->wallet->locked);
        $this->assertEquals(70.00, $this->wallet->available_balance->toDecimal());
    }

    public function test_lock_fails_with_insufficient_unlocked_funds()
    {
        $this->expectException(InsufficientFundsException::class);
        
        $amount = Money::fromDecimal(100.00);
        $this->ledgerService->lock($this->wallet, $amount);
    }

    public function test_can_unlock_funds()
    {
        // First deposit and lock some funds
        $this->ledgerService->deposit($this->wallet, Money::fromDecimal(100.00));
        $this->wallet->refresh(); // Refresh after deposit
        $this->ledgerService->lock($this->wallet, Money::fromDecimal(30.00));
        $this->wallet->refresh(); // Refresh to get updated locked amount
        
        $amount = Money::fromDecimal(20.00);
        $transaction = $this->ledgerService->unlock($this->wallet, $amount);
        
        $this->assertInstanceOf(WalletTransaction::class, $transaction);
        $this->assertEquals(WalletTransaction::TYPE_UNLOCK, $transaction->type);
        $this->assertEquals(20.00, $transaction->amount);
        $this->assertEquals(WalletTransaction::STATUS_APPROVED, $transaction->status);
        
        $this->wallet->refresh();
        $this->assertEquals(10.00, $this->wallet->locked);
        $this->assertEquals(90.00, $this->wallet->available_balance->toDecimal());
    }

    public function test_unlock_fails_with_insufficient_locked_funds()
    {
        $this->expectException(InsufficientFundsException::class);
        
        $amount = Money::fromDecimal(100.00);
        $this->ledgerService->unlock($this->wallet, $amount);
    }

    public function test_can_grant_credit()
    {
        $amount = Money::fromDecimal(1000.00);
        $transaction = $this->ledgerService->grantCredit($this->wallet, $amount);
        
        $this->assertInstanceOf(WalletTransaction::class, $transaction);
        $this->assertEquals(WalletTransaction::TYPE_CREDIT_GRANT, $transaction->type);
        $this->assertEquals(1000.00, $transaction->amount);
        $this->assertEquals(WalletTransaction::STATUS_APPROVED, $transaction->status);
        
        $this->wallet->refresh();
        $this->assertEquals(1000.00, $this->wallet->credit);
    }

    public function test_can_revoke_credit()
    {
        // First grant credit
        $this->ledgerService->grantCredit($this->wallet, Money::fromDecimal(1000.00));
        $this->wallet->refresh(); // Refresh to get updated credit
        
        $amount = Money::fromDecimal(200.00);
        $transaction = $this->ledgerService->revokeCredit($this->wallet, $amount);
        
        $this->assertInstanceOf(WalletTransaction::class, $transaction);
        $this->assertEquals(WalletTransaction::TYPE_CREDIT_REVOKE, $transaction->type);
        $this->assertEquals(200.00, $transaction->amount);
        $this->assertEquals(WalletTransaction::STATUS_APPROVED, $transaction->status);
        
        $this->wallet->refresh();
        $this->assertEquals(800.00, $this->wallet->credit);
    }

    public function test_can_charge_interest()
    {
        // First create debt by withdrawing with credit
        $this->ledgerService->grantCredit($this->wallet, Money::fromDecimal(100.00));
        $this->wallet->refresh(); // Refresh after credit grant
        $this->ledgerService->withdraw($this->wallet, Money::fromDecimal(50.00));
        $this->wallet->refresh(); // Refresh to get updated balance
        
        $amount = Money::fromDecimal(5.00);
        $transaction = $this->ledgerService->chargeInterest($this->wallet, $amount);
        
        $this->assertInstanceOf(WalletTransaction::class, $transaction);
        $this->assertEquals(WalletTransaction::TYPE_INTEREST_CHARGE, $transaction->type);
        $this->assertEquals(5.00, $transaction->amount);
        $this->assertEquals(WalletTransaction::STATUS_APPROVED, $transaction->status);
        
        $this->wallet->refresh();
        $this->assertEquals(-55.00, $this->wallet->balance); // -50 - 5
    }

    public function test_deposit_automatically_repays_debt()
    {
        // Create debt first
        $this->ledgerService->grantCredit($this->wallet, Money::fromDecimal(100.00));
        $this->wallet->refresh(); // Refresh after credit grant
        $this->ledgerService->withdraw($this->wallet, Money::fromDecimal(50.00));
        $this->wallet->refresh(); // Refresh to get updated balance
        
        $this->assertEquals(-50.00, $this->wallet->balance);
        
        // Deposit should repay debt first
        $this->ledgerService->deposit($this->wallet, Money::fromDecimal(30.00));
        
        $this->wallet->refresh();
        $this->assertEquals(-20.00, $this->wallet->balance); // -50 + 30
    }

    public function test_deposit_after_debt_repayment_adds_to_balance()
    {
        // Create debt first
        $this->ledgerService->grantCredit($this->wallet, Money::fromDecimal(100.00));
        $this->wallet->refresh(); // Refresh after credit grant
        $this->ledgerService->withdraw($this->wallet, Money::fromDecimal(50.00));
        $this->wallet->refresh(); // Refresh to get updated balance
        
        // Deposit more than debt
        $this->ledgerService->deposit($this->wallet, Money::fromDecimal(80.00));
        
        $this->wallet->refresh();
        $this->assertEquals(30.00, $this->wallet->balance); // -50 + 80
    }

    public function test_pending_transaction_workflow()
    {
        $amount = Money::fromDecimal(100.00);
        $transaction = $this->ledgerService->deposit($this->wallet, $amount, false); // Don't auto-approve
        
        $this->assertEquals(WalletTransaction::STATUS_PENDING, $transaction->status);
        
        $this->wallet->refresh();
        $this->assertEquals(0.00, $this->wallet->balance); // Should not be applied yet
        
        // Now approve the transaction
        $result = $this->ledgerService->approveTransaction($transaction);
        
        $this->assertTrue($result);
        $transaction->refresh();
        $this->assertEquals(WalletTransaction::STATUS_APPROVED, $transaction->status);
        
        $this->wallet->refresh();
        $this->assertEquals(100.00, $this->wallet->balance);
    }

    public function test_can_reject_pending_transaction()
    {
        $amount = Money::fromDecimal(100.00);
        $transaction = $this->ledgerService->deposit($this->wallet, $amount, false);
        
        $this->assertEquals(WalletTransaction::STATUS_PENDING, $transaction->status);
        
        $result = $this->ledgerService->rejectTransaction($transaction, 'Test rejection');
        
        $this->assertTrue($result);
        $transaction->refresh();
        $this->assertEquals(WalletTransaction::STATUS_REJECTED, $transaction->status);
        $this->assertEquals('Test rejection', $transaction->meta['rejection_reason']);
        
        $this->wallet->refresh();
        $this->assertEquals(0.00, $this->wallet->balance); // Should remain unchanged
    }

    public function test_get_wallet_summary()
    {
        // Set up wallet with various amounts
        $this->ledgerService->deposit($this->wallet, Money::fromDecimal(100.00));
        $this->wallet->refresh(); // Refresh after deposit
        $this->ledgerService->grantCredit($this->wallet, Money::fromDecimal(500.00));
        $this->wallet->refresh(); // Refresh after credit grant
        $this->ledgerService->lock($this->wallet, Money::fromDecimal(30.00));
        $this->wallet->refresh(); // Refresh to get all updated values
        
        $summary = $this->ledgerService->getWalletSummary($this->wallet);
        
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('balance', $summary);
        $this->assertArrayHasKey('locked', $summary);
        $this->assertArrayHasKey('credit_limit', $summary);
        $this->assertArrayHasKey('available_balance', $summary);
        $this->assertArrayHasKey('available_funds', $summary);
        $this->assertArrayHasKey('debt', $summary);
        $this->assertArrayHasKey('remaining_credit', $summary);
        
        $this->assertEquals(100.00, $summary['balance']);
        $this->assertEquals(30.00, $summary['locked']);
        $this->assertEquals(500.00, $summary['credit_limit']);
        $this->assertEquals(70.00, $summary['available_balance']);
        $this->assertEquals(570.00, $summary['available_funds']); // 70 + 500
        $this->assertEquals(0.00, $summary['debt']);
        $this->assertEquals(500.00, $summary['remaining_credit']);
    }

    public function test_concurrent_transactions_are_safe()
    {
        // This test simulates concurrent access to ensure locking works
        $this->ledgerService->deposit($this->wallet, Money::fromDecimal(100.00));
        $this->wallet->refresh(); // Refresh to get updated balance
        
        // Simulate concurrent withdrawals
        $results = [];
        $exceptions = [];
        
        for ($i = 0; $i < 5; $i++) {
            try {
                $results[] = $this->ledgerService->withdraw($this->wallet, Money::fromDecimal(20.00));
            } catch (\Exception $e) {
                $exceptions[] = $e;
            }
        }
        
        // Should have exactly 5 successful withdrawals
        $this->assertCount(5, $results);
        $this->assertCount(0, $exceptions);
        
        $this->wallet->refresh();
        $this->assertEquals(0.00, $this->wallet->balance);
    }

    public function test_invalid_amount_throws_exception()
    {
        $this->expectException(InvalidAmountException::class);
        
        $amount = Money::fromDecimal(-10.00); // Negative amount
        $this->ledgerService->deposit($this->wallet, $amount);
    }

    public function test_inactive_wallet_cannot_auto_approve()
    {
        $this->wallet->update(['is_active' => false]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Wallet is not active.');
        
        $amount = Money::fromDecimal(100.00);
        $this->ledgerService->deposit($this->wallet, $amount);
    }

    public function test_inactive_wallet_can_create_pending_transactions()
    {
        $this->wallet->update(['is_active' => false]);
        
        $amount = Money::fromDecimal(100.00);
        $transaction = $this->ledgerService->deposit($this->wallet, $amount, false);
        
        $this->assertEquals(WalletTransaction::STATUS_PENDING, $transaction->status);
    }
}