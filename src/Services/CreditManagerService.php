<?php

namespace vahidkaargar\LaravelWallet\Services;

use vahidkaargar\LaravelWallet\Events\CreditRepaid;
use vahidkaargar\LaravelWallet\Models\Wallet;
use vahidkaargar\LaravelWallet\Models\WalletTransaction;
use vahidkaargar\LaravelWallet\ValueObjects\Money;

/**
 * Service containing business logic for credit and debt management.
 * This service performs calculations and checks, but does not modify state itself.
 * State modifications are handled by TransactionApprovalService.
 */
class CreditManagerService
{
    /**
     * Get the current outstanding debt (negative balance).
     *
     * @param Wallet $wallet
     * @return Money
     */
    public function getDebt(Wallet $wallet): Money
    {
        $balance = Money::fromDecimal($wallet->balance);
        return $balance->isNegative() ? $balance->abs() : Money::fromCents(0);
    }

    /**
     * Get the total available credit line (credit limit - debt).
     *
     * @param Wallet $wallet
     * @return Money
     */
    public function getAvailableCredit(Wallet $wallet): Money
    {
        $creditLimit = Money::fromDecimal($wallet->credit);
        $debt = $this->getDebt($wallet);

        $available = $creditLimit->subtract($debt);
        return $available->isPositive() ? $available : Money::fromCents(0);
    }

    /**
     * Get the total available funds (balance + available credit - locked).
     *
     * @param Wallet $wallet
     * @return Money
     */
    public function getAvailableFunds(Wallet $wallet): Money
    {
        $balance = Money::fromDecimal($wallet->balance);
        $locked = Money::fromDecimal($wallet->locked);
        $creditLimit = Money::fromDecimal($wallet->credit);

        // Available balance (can be negative)
        $availableBalance = $balance->subtract($locked);

        // If balance is negative, we're already using credit
        if ($availableBalance->isNegative()) {
            $debt = $availableBalance->abs();
            $remainingCredit = $creditLimit->subtract($debt);
            return $remainingCredit->isPositive() ? $remainingCredit : Money::fromCents(0);
        }

        // If balance is positive, add full credit
        return $availableBalance->add($creditLimit);
    }

    /**
     * Check if the wallet has sufficient funds for a given amount.
     *
     * @param Wallet $wallet
     * @param Money $amount
     * @param bool $checkOnlyUnlocked If true, only check (balance - locked) and ignore credit.
     * @return bool
     */
    public function hasSufficientFunds(Wallet $wallet, Money $amount, bool $checkOnlyUnlocked = false): bool
    {
        if ($checkOnlyUnlocked) {
            $availableBalance = Money::fromDecimal($wallet->balance)->subtract(Money::fromDecimal($wallet->locked));
            return $availableBalance->greaterThanOrEqual($amount);
        }

        $availableFunds = $this->getAvailableFunds($wallet);
        return $availableFunds->greaterThanOrEqual($amount);
    }

    /**
     * Logic to apply a deposit to a wallet.
     * This method is called by TransactionApprovalService.
     * It updates the balance and fires a CreditRepaid event if debt was reduced.
     *
     * IMPORTANT: This method *mutates* the $wallet object but *does not save it*.
     * The calling service is responsible for saving within its transaction.
     *
     * @param Wallet $wallet
     * @param WalletTransaction $transaction
     * @return void
     */
    public function applyDeposit(Wallet $wallet, WalletTransaction $transaction): void
    {
        $amount = Money::fromDecimal($transaction->amount);
        $debtBefore = $this->getDebt($wallet);

        $newBalance = Money::fromDecimal($wallet->balance)->add($amount);
        $wallet->balance = $newBalance->toDecimal();

        $debtAfter = $this->getDebt($wallet);

        $repaidAmount = $debtBefore->subtract($debtAfter);

        if ($repaidAmount->isPositive()) {
            event(new CreditRepaid($wallet, $repaidAmount->toDecimal(), $transaction));
        }
    }

    /**
     * Calculate interest on the wallet's debt.
     * In a real app, this logic would be much more complex (e.g., APR, days overdue).
     *
     * @param Wallet $wallet
     * @param float $rate
     * @return Money
     */
    public function calculateInterest(Wallet $wallet, float $rate = 0.05): Money
    {
        $debt = $this->getDebt($wallet);

        if ($debt->isZero()) {
            return Money::fromCents(0);
        }

        // Simple interest calculation (e.g., 5% of outstanding debt)
        return $debt->multiply($rate);
    }

    /**
     * Calculate the amount that can be withdrawn from credit.
     *
     * @param Wallet $wallet
     * @param Money $requestedAmount
     * @return Money
     */
    public function calculateCreditWithdrawal(Wallet $wallet, Money $requestedAmount): Money
    {
        $availableBalance = Money::fromDecimal($wallet->balance)->subtract(Money::fromDecimal($wallet->locked));

        // If we have enough cash balance, no credit needed
        if ($availableBalance->greaterThanOrEqual($requestedAmount)) {
            return Money::fromCents(0);
        }

        // Calculate how much we need from credit
        $neededFromCredit = $requestedAmount->subtract($availableBalance);
        $availableCredit = $this->getAvailableCredit($wallet);

        // Return the minimum of what we need and what's available
        return $neededFromCredit->lessThanOrEqual($availableCredit) ? $neededFromCredit : $availableCredit;
    }

    /**
     * Check if a credit grant would exceed maximum limits.
     * @param Wallet $wallet
     * @param Money $amount
     * @return bool
     */
    public function validateCreditGrant(Wallet $wallet, Money $amount): bool
    {
        $currentCredit = Money::fromDecimal($wallet->credit);
        $newCredit = $currentCredit->add($amount);

        $maxCredit = Money::fromDecimal(config('wallet.max_credit_limit', 100000.00));

        return $newCredit->lessThanOrEqual($maxCredit);
    }

    /**
     * Check if a credit revoke would leave debt exceeding new credit limit.
     *
     * @param Wallet $wallet
     * @param Money $amount
     * @return bool
     */
    public function validateCreditRevoke(Wallet $wallet, Money $amount): bool
    {
        $currentCredit = Money::fromDecimal($wallet->credit);
        $newCredit = $currentCredit->subtract($amount);
        $currentDebt = $this->getDebt($wallet);

        // Can't revoke more credit than exists
        if ($amount->greaterThan($currentCredit)) {
            return false;
        }

        // Can't revoke credit if it would leave debt exceeding new limit
        return $currentDebt->lessThanOrEqual($newCredit);
    }
}