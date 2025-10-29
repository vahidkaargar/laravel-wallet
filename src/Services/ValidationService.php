<?php

namespace vahidkaargar\LaravelWallet\Services;

use Exception;
use vahidkaargar\LaravelWallet\Enums\{TransactionStatus, TransactionType};
use vahidkaargar\LaravelWallet\Exceptions\{InvalidAmountException, InsufficientFundsException, WalletNotFoundException};
use vahidkaargar\LaravelWallet\Models\{Wallet, WalletTransaction};
use vahidkaargar\LaravelWallet\ValueObjects\Money;

/**
 * Service for validating financial operations before execution.
 * This ensures all business rules are enforced consistently.
 */
class ValidationService
{
    /**
     * Validate amount for any transaction.
     *
     * @param Money $amount
     */
    public function validateAmount(Money $amount): void
    {
        if (!$amount->isPositive()) {
            throw new InvalidAmountException('Transaction amount must be positive.');
        }

        // Check for reasonable limits (prevent overflow attacks)
        $maxAmount = Money::fromDecimal(config('wallet.max_transaction_amount', 999999.99));
        if ($amount->greaterThan($maxAmount)) {
            throw new InvalidAmountException('Transaction amount exceeds maximum allowed limit.');
        }
    }

    /**
     * Validate wallet is active and accessible.
     *
     * @param Wallet $wallet
     * @return void
     * @throws Exception
     */
    public function validateWallet(Wallet $wallet): void
    {
        if (!$wallet->exists) {
            throw new WalletNotFoundException('Wallet does not exist.');
        }

        if (!$wallet->is_active) {
            throw new Exception('Wallet is not active.');
        }
    }

    /**
     * Validate withdrawal operation.
     *
     * @param Wallet $wallet
     * @param Money $amount
     * @return void
     * @throws Exception
     */
    public function validateWithdrawal(Wallet $wallet, Money $amount): void
    {
        $this->validateWallet($wallet);
        $this->validateAmount($amount);

        $availableFunds = $this->calculateAvailableFunds($wallet);

        if ($amount->greaterThan($availableFunds)) {
            throw new InsufficientFundsException(
                sprintf(
                    'Insufficient funds. Available: %s, Requested: %s',
                    $availableFunds->toDecimal(),
                    $amount->toDecimal()
                )
            );
        }
    }

    /**
     * Validate lock operation.
     *
     * @param Wallet $wallet
     * @param Money $amount
     * @return void
     * @throws Exception
     */
    public function validateLock(Wallet $wallet, Money $amount): void
    {
        $this->validateWallet($wallet);
        $this->validateAmount($amount);

        $availableBalance = $this->calculateAvailableBalance($wallet);

        if ($amount->greaterThan($availableBalance)) {
            throw new InsufficientFundsException(
                sprintf(
                    'Insufficient unlocked balance to lock. Available: %s, Requested: %s',
                    $availableBalance->toDecimal(),
                    $amount->toDecimal()
                )
            );
        }
    }

    /**
     * Validate unlock operation.
     *
     * @param Wallet $wallet
     * @param Money $amount
     * @return void
     * @throws Exception
     */
    public function validateUnlock(Wallet $wallet, Money $amount): void
    {
        $this->validateWallet($wallet);
        $this->validateAmount($amount);

        $lockedAmount = Money::fromDecimal($wallet->locked ?? 0);

        if ($amount->greaterThan($lockedAmount)) {
            throw new InsufficientFundsException(
                sprintf(
                    'Insufficient locked funds to unlock. Locked: %s, Requested: %s',
                    $lockedAmount->toDecimal(),
                    $amount->toDecimal()
                )
            );
        }
    }

    /**
     * Validate credit grant operation.
     *
     * @param Wallet $wallet
     * @param Money $amount
     * @return void
     * @throws Exception
     */
    public function validateCreditGrant(Wallet $wallet, Money $amount): void
    {
        $this->validateWallet($wallet);
        $this->validateAmount($amount);

        // Check maximum credit limit
        $maxCredit = Money::fromDecimal(config('wallet.max_credit_limit', 100000.00));
        $newCredit = Money::fromDecimal($wallet->credit ?? 0)->add($amount);

        if ($newCredit->greaterThan($maxCredit)) {
            throw new Exception('Credit grant would exceed maximum credit limit.');
        }
    }

    /**
     * Validate credit revoke operation.
     *
     * @param Wallet $wallet
     * @param Money $amount
     * @return void
     * @throws Exception
     */
    public function validateCreditRevoke(Wallet $wallet, Money $amount): void
    {
        $this->validateWallet($wallet);
        $this->validateAmount($amount);

        $currentCredit = Money::fromDecimal($wallet->credit ?? 0);

        if ($amount->greaterThan($currentCredit)) {
            throw new Exception('Cannot revoke more credit than currently available.');
        }

        // Check if revoking would leave debt exceeding new credit limit
        $newCredit = $currentCredit->subtract($amount);
        $currentDebt = $this->calculateDebt($wallet);

        if ($currentDebt->greaterThan($newCredit)) {
            throw new Exception('Cannot revoke credit, would result in debt exceeding credit limit.');
        }
    }

    /**
     * Validate transaction for approval.
     *
     * @param WalletTransaction $transaction
     * @return void
     * @throws Exception
     */
    public function validateTransactionForApproval(WalletTransaction $transaction): void
    {
        if ($transaction->status !== TransactionStatus::PENDING) {
            throw new Exception('Only pending transactions can be approved.');
        }

        $wallet = $transaction->wallet;
        $amount = Money::fromDecimal($transaction->amount);

        switch ($transaction->type) {
            case TransactionType::WITHDRAW:
                $this->validateWithdrawal($wallet, $amount);
                break;
            case TransactionType::LOCK:
                $this->validateLock($wallet, $amount);
                break;
            case TransactionType::UNLOCK:
                $this->validateUnlock($wallet, $amount);
                break;
            case TransactionType::CREDIT_GRANT:
                $this->validateCreditGrant($wallet, $amount);
                break;
            case TransactionType::CREDIT_REVOKE:
                $this->validateCreditRevoke($wallet, $amount);
                break;
            case TransactionType::DEPOSIT:
            case TransactionType::CREDIT_REPAY:
            case TransactionType::INTEREST_CHARGE:
                // These operations don't require additional validation beyond amount
                $this->validateAmount($amount);
                break;
        }
    }

    /**
     * Calculate available funds (balance + credit - locked).
     *
     * @param Wallet $wallet
     * @return Money
     */
    private function calculateAvailableFunds(Wallet $wallet): Money
    {
        $balance = Money::fromDecimal($wallet->balance ?? 0);
        $locked = Money::fromDecimal($wallet->locked ?? 0);
        $credit = Money::fromDecimal($wallet->credit ?? 0);

        // Available balance (can be negative)
        $availableBalance = $balance->subtract($locked);

        // If balance is negative, we're already using credit
        if ($availableBalance->isNegative()) {
            $debt = $availableBalance->abs();
            $remainingCredit = $credit->subtract($debt);
            return $remainingCredit->isPositive() ? $remainingCredit : Money::fromCents(0);
        }

        // If balance is positive, add full credit
        return $availableBalance->add($credit);
    }

    /**
     * Calculate available balance (balance - locked, no credit).
     *
     * @param Wallet $wallet
     * @return Money
     */
    private function calculateAvailableBalance(Wallet $wallet): Money
    {
        $balance = Money::fromDecimal($wallet->balance ?? 0);
        $locked = Money::fromDecimal($wallet->locked ?? 0);

        $available = $balance->subtract($locked);
        return $available->isNegative() ? Money::fromCents(0) : $available;
    }

    /**
     * Calculate current debt (negative balance).
     *
     * @param Wallet $wallet
     * @return Money
     */
    private function calculateDebt(Wallet $wallet): Money
    {
        $balance = Money::fromDecimal($wallet->balance ?? 0);
        return $balance->isNegative() ? $balance->abs() : Money::fromCents(0);
    }
}
