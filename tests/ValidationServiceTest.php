<?php

namespace vahidkaargar\LaravelWallet\Tests;

use vahidkaargar\LaravelWallet\Exceptions\InsufficientFundsException;
use vahidkaargar\LaravelWallet\Exceptions\InvalidAmountException;
use vahidkaargar\LaravelWallet\Models\WalletTransaction;
use vahidkaargar\LaravelWallet\Services\ValidationService;
use vahidkaargar\LaravelWallet\Services\WalletLedgerService;
use vahidkaargar\LaravelWallet\ValueObjects\Money;

class ValidationServiceTest extends TestCase
{
    protected ValidationService $validator;
    protected WalletLedgerService $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = app(ValidationService::class);
        $this->ledgerService = app(WalletLedgerService::class);
    }

    public function test_validates_positive_amount()
    {
        $amount = Money::fromDecimal(100.00);
        
        // Should not throw exception
        $this->validator->validateAmount($amount);
        $this->assertTrue(true);
    }

    public function test_rejects_negative_amount()
    {
        $this->expectException(InvalidAmountException::class);
        $this->expectExceptionMessage('Transaction amount must be positive.');
        
        $amount = Money::fromDecimal(-10.00);
        $this->validator->validateAmount($amount);
    }

    public function test_rejects_zero_amount()
    {
        $this->expectException(InvalidAmountException::class);
        $this->expectExceptionMessage('Transaction amount must be positive.');
        
        $amount = Money::fromDecimal(0.00);
        $this->validator->validateAmount($amount);
    }

    public function test_rejects_amount_exceeding_maximum()
    {
        config(['wallet.max_transaction_amount' => 1000.00]);
        
        $this->expectException(InvalidAmountException::class);
        $this->expectExceptionMessage('Transaction amount exceeds maximum allowed limit.');
        
        $amount = Money::fromDecimal(1500.00);
        $this->validator->validateAmount($amount);
    }

    public function test_validates_active_wallet()
    {
        // Should not throw exception
        $this->validator->validateWallet($this->wallet);
        $this->assertTrue(true);
    }

    public function test_rejects_inactive_wallet()
    {
        $this->wallet->update(['is_active' => false]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Wallet is not active.');
        
        $this->validator->validateWallet($this->wallet);
    }

    public function test_validates_sufficient_funds_for_withdrawal()
    {
        $this->ledgerService->deposit($this->wallet, Money::fromDecimal(100.00));
        $this->wallet->refresh(); // Refresh to get updated balance
        
        $amount = Money::fromDecimal(50.00);
        
        // Should not throw exception
        $this->validator->validateWithdrawal($this->wallet, $amount);
        $this->assertTrue(true);
    }

    public function test_rejects_withdrawal_with_insufficient_funds()
    {
        $this->expectException(InsufficientFundsException::class);
        
        $amount = Money::fromDecimal(100.00);
        $this->validator->validateWithdrawal($this->wallet, $amount);
    }

    public function test_allows_withdrawal_with_credit()
    {
        $this->ledgerService->grantCredit($this->wallet, Money::fromDecimal(100.00));
        $this->wallet->refresh(); // Refresh to get updated credit
        
        $amount = Money::fromDecimal(50.00);
        
        // Should not throw exception
        $this->validator->validateWithdrawal($this->wallet, $amount);
        $this->assertTrue(true);
    }

    public function test_validates_sufficient_unlocked_funds_for_lock()
    {
        $this->ledgerService->deposit($this->wallet, Money::fromDecimal(100.00));
        $this->wallet->refresh(); // Refresh to get updated balance
        
        $amount = Money::fromDecimal(50.00);
        
        // Should not throw exception
        $this->validator->validateLock($this->wallet, $amount);
        $this->assertTrue(true);
    }

    public function test_rejects_lock_with_insufficient_unlocked_funds()
    {
        $this->expectException(InsufficientFundsException::class);
        
        $amount = Money::fromDecimal(100.00);
        $this->validator->validateLock($this->wallet, $amount);
    }

    public function test_rejects_lock_when_funds_are_locked()
    {
        $this->ledgerService->deposit($this->wallet, Money::fromDecimal(100.00));
        $this->wallet->refresh(); // Refresh after deposit
        $this->ledgerService->lock($this->wallet, Money::fromDecimal(80.00));
        $this->wallet->refresh(); // Refresh to get updated locked amount
        
        $this->expectException(InsufficientFundsException::class);
        
        $amount = Money::fromDecimal(50.00);
        $this->validator->validateLock($this->wallet, $amount);
    }

    public function test_validates_sufficient_locked_funds_for_unlock()
    {
        $this->ledgerService->deposit($this->wallet, Money::fromDecimal(100.00));
        $this->wallet->refresh(); // Refresh after deposit
        $this->ledgerService->lock($this->wallet, Money::fromDecimal(50.00));
        $this->wallet->refresh(); // Refresh to get updated locked amount
        
        $amount = Money::fromDecimal(30.00);
        
        // Should not throw exception
        $this->validator->validateUnlock($this->wallet, $amount);
        $this->assertTrue(true);
    }

    public function test_rejects_unlock_with_insufficient_locked_funds()
    {
        $this->expectException(InsufficientFundsException::class);
        
        $amount = Money::fromDecimal(100.00);
        $this->validator->validateUnlock($this->wallet, $amount);
    }

    public function test_validates_credit_grant_within_limits()
    {
        $amount = Money::fromDecimal(1000.00);
        
        // Should not throw exception
        $this->validator->validateCreditGrant($this->wallet, $amount);
        $this->assertTrue(true);
    }

    public function test_rejects_credit_grant_exceeding_maximum()
    {
        config(['wallet.max_credit_limit' => 500.00]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Credit grant would exceed maximum credit limit.');
        
        $amount = Money::fromDecimal(1000.00);
        $this->validator->validateCreditGrant($this->wallet, $amount);
    }

    public function test_validates_credit_revoke_within_available_credit()
    {
        $this->ledgerService->grantCredit($this->wallet, Money::fromDecimal(1000.00));
        $this->wallet->refresh(); // Refresh to get updated credit
        
        $amount = Money::fromDecimal(200.00);
        
        // Should not throw exception
        $this->validator->validateCreditRevoke($this->wallet, $amount);
        $this->assertTrue(true);
    }

    public function test_rejects_credit_revoke_exceeding_available_credit()
    {
        $this->ledgerService->grantCredit($this->wallet, Money::fromDecimal(100.00));
        $this->wallet->refresh(); // Refresh to get updated credit
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot revoke more credit than currently available.');
        
        $amount = Money::fromDecimal(200.00);
        $this->validator->validateCreditRevoke($this->wallet, $amount);
    }

    public function test_rejects_credit_revoke_that_would_exceed_debt()
    {
        $this->ledgerService->grantCredit($this->wallet, Money::fromDecimal(1000.00));
        $this->wallet->refresh(); // Refresh to get updated credit
        
        // Create debt by withdrawing more than available balance
        $this->ledgerService->withdraw($this->wallet, Money::fromDecimal(800.00)); // Create debt
        $this->wallet->refresh(); // Refresh to get updated balance and credit
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot revoke credit, would result in debt exceeding credit limit.');
        
        $amount = Money::fromDecimal(500.00); // Would leave only 500 credit but 800 debt
        $this->validator->validateCreditRevoke($this->wallet, $amount);
    }

    public function test_validates_transaction_for_approval()
    {
        $this->ledgerService->deposit($this->wallet, Money::fromDecimal(100.00));
        $this->wallet->refresh(); // Refresh to get updated balance
        
        $transaction = $this->wallet->transactions()->create([
            'type' => WalletTransaction::TYPE_WITHDRAW,
            'amount' => 50.00,
            'status' => WalletTransaction::STATUS_PENDING,
        ]);
        
        // Should not throw exception
        $this->validator->validateTransactionForApproval($transaction);
        $this->assertTrue(true);
    }

    public function test_rejects_approval_of_non_pending_transaction()
    {
        $transaction = $this->wallet->transactions()->create([
            'type' => WalletTransaction::TYPE_DEPOSIT,
            'amount' => 100.00,
            'status' => WalletTransaction::STATUS_APPROVED,
        ]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only pending transactions can be approved.');
        
        $this->validator->validateTransactionForApproval($transaction);
    }

    public function test_rejects_approval_of_invalid_withdrawal()
    {
        $transaction = $this->wallet->transactions()->create([
            'type' => WalletTransaction::TYPE_WITHDRAW,
            'amount' => 100.00,
            'status' => WalletTransaction::STATUS_PENDING,
        ]);
        
        $this->expectException(InsufficientFundsException::class);
        
        $this->validator->validateTransactionForApproval($transaction);
    }

    public function test_edge_case_precision_handling()
    {
        // Test that validation handles precision correctly
        $this->ledgerService->deposit($this->wallet, Money::fromDecimal(0.01));
        $this->wallet->refresh(); // Refresh to get updated balance
        
        $amount = Money::fromDecimal(0.01);
        
        // Should not throw exception
        $this->validator->validateWithdrawal($this->wallet, $amount);
        $this->assertTrue(true);
    }

    public function test_edge_case_rounding_validation()
    {
        // Test edge cases with rounding
        $this->ledgerService->deposit($this->wallet, Money::fromDecimal(0.005)); // Rounds to 0.01
        $this->wallet->refresh(); // Refresh to get updated balance
        
        $amount = Money::fromDecimal(0.01);
        
        // Should not throw exception
        $this->validator->validateWithdrawal($this->wallet, $amount);
        $this->assertTrue(true);
    }
}
