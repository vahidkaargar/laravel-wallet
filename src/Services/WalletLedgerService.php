<?php

namespace vahidkaargar\LaravelWallet\Services;

use Exception;
use Throwable;
use vahidkaargar\LaravelWallet\Exceptions\InvalidAmountException;
use vahidkaargar\LaravelWallet\Exceptions\InsufficientFundsException;
use vahidkaargar\LaravelWallet\Models\Wallet;
use vahidkaargar\LaravelWallet\Models\WalletTransaction;
use vahidkaargar\LaravelWallet\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for creating and managing wallet transactions (the "ledger").
 * This is the primary entry point for all financial operations.
 * All operations are validated and use proper Money objects for precision.
 */
class WalletLedgerService
{
    /**
     * @param CreditManagerService $creditManager
     * @param TransactionApprovalService $approvalService
     * @param ValidationService $validator
     */
    public function __construct(
        protected CreditManagerService       $creditManager,
        protected TransactionApprovalService $approvalService,
        protected ValidationService          $validator
    )
    {
    }

    /**
     * Deposit funds into a wallet.
     *
     * @param Wallet $wallet
     * @param Money $amount
     * @param bool $autoApprove
     * @param string|null $reference
     * @param array|null $meta
     * @return WalletTransaction
     * @throws Throwable
     */
    public function deposit(
        Wallet  $wallet,
        Money   $amount,
        bool    $autoApprove = true,
        ?string $reference = null,
        ?array  $meta = null
    ): WalletTransaction
    {
        if ($autoApprove) {
            $this->validator->validateWallet($wallet);
        }
        $this->validator->validateAmount($amount);

        return $this->createWalletTransaction(
            $wallet,
            WalletTransaction::TYPE_DEPOSIT,
            $amount,
            $autoApprove,
            $reference,
            $meta
        );
    }

    /**
     * Withdraw funds from a wallet.
     *
     * @param Wallet $wallet
     * @param Money $amount
     * @param bool $autoApprove
     * @param string|null $reference
     * @param array|null $meta
     * @return WalletTransaction
     * @throws Throwable
     */
    public function withdraw(
        Wallet  $wallet,
        Money   $amount,
        bool    $autoApprove = true,
        ?string $reference = null,
        ?array  $meta = null
    ): WalletTransaction
    {
        $this->validator->validateWithdrawal($wallet, $amount);

        return DB::transaction(function () use ($wallet, $amount, $autoApprove, $reference, $meta) {
            // Lock wallet for update to prevent race conditions
            $lockedWallet = Wallet::query()->lockForUpdate()->find($wallet->id);

            if (!$lockedWallet) {
                throw new Exception('Wallet not found during withdrawal.');
            }

            // Re-validate with locked wallet to ensure funds are still available
            $this->validator->validateWithdrawal($lockedWallet, $amount);

            return $this->createWalletTransaction(
                $lockedWallet,
                WalletTransaction::TYPE_WITHDRAW,
                $amount,
                $autoApprove,
                $reference,
                $meta,
                false // Wallet is already locked, don't re-lock in createWalletTransaction
            );
        });
    }

    /**
     * Lock funds in a wallet.
     *
     * @param Wallet $wallet
     * @param Money $amount
     * @param bool $autoApprove
     * @param string|null $reference
     * @param array|null $meta
     * @return WalletTransaction
     * @throws Throwable
     */
    public function lock(
        Wallet  $wallet,
        Money   $amount,
        bool    $autoApprove = true,
        ?string $reference = null,
        ?array  $meta = null
    ): WalletTransaction
    {
        $this->validator->validateLock($wallet, $amount);

        return DB::transaction(function () use ($wallet, $amount, $autoApprove, $reference, $meta) {
            $lockedWallet = Wallet::query()->lockForUpdate()->find($wallet->id);

            if (!$lockedWallet) {
                throw new Exception('Wallet not found during lock operation.');
            }

            // Re-validate with locked wallet
            $this->validator->validateLock($lockedWallet, $amount);

            return $this->createWalletTransaction(
                $lockedWallet,
                WalletTransaction::TYPE_LOCK,
                $amount,
                $autoApprove,
                $reference,
                $meta,
                false
            );
        });
    }

    /**
     * Unlock previously locked funds.
     *
     * @param Wallet $wallet
     * @param Money $amount
     * @param bool $autoApprove
     * @param string|null $reference
     * @param array|null $meta
     * @return WalletTransaction
     * @throws Throwable
     */
    public function unlock(
        Wallet  $wallet,
        Money   $amount,
        bool    $autoApprove = true,
        ?string $reference = null,
        ?array  $meta = null
    ): WalletTransaction
    {
        $this->validator->validateUnlock($wallet, $amount);

        return $this->createWalletTransaction(
            $wallet,
            WalletTransaction::TYPE_UNLOCK,
            $amount,
            $autoApprove,
            $reference,
            $meta
        );
    }

    /**
     * Grant credit to a wallet.
     *
     * @param Wallet $wallet
     * @param Money $amount
     * @param bool $autoApprove
     * @param string|null $reference
     * @param array|null $meta
     * @return WalletTransaction
     * @throws Throwable
     */
    public function grantCredit(
        Wallet  $wallet,
        Money   $amount,
        bool    $autoApprove = true,
        ?string $reference = null,
        ?array  $meta = null
    ): WalletTransaction
    {
        $this->validator->validateCreditGrant($wallet, $amount);

        return $this->createWalletTransaction(
            $wallet,
            WalletTransaction::TYPE_CREDIT_GRANT,
            $amount,
            $autoApprove,
            $reference,
            $meta
        );
    }

    /**
     * Revoke credit from a wallet.
     *
     * @param Wallet $wallet
     * @param Money $amount
     * @param bool $autoApprove
     * @param string|null $reference
     * @param array|null $meta
     * @return WalletTransaction
     * @throws Throwable
     */
    public function revokeCredit(
        Wallet  $wallet,
        Money   $amount,
        bool    $autoApprove = true,
        ?string $reference = null,
        ?array  $meta = null
    ): WalletTransaction
    {
        $this->validator->validateCreditRevoke($wallet, $amount);

        return $this->createWalletTransaction(
            $wallet,
            WalletTransaction::TYPE_CREDIT_REVOKE,
            $amount,
            $autoApprove,
            $reference,
            $meta
        );
    }

    /**
     * Charge interest on a wallet (typically for debt).
     *
     * @param Wallet $wallet
     * @param Money $amount
     * @param bool $autoApprove
     * @param string|null $reference
     * @param array|null $meta
     * @return WalletTransaction
     * @throws Throwable
     */
    public function chargeInterest(
        Wallet  $wallet,
        Money   $amount,
        bool    $autoApprove = true,
        ?string $reference = null,
        ?array  $meta = null
    ): WalletTransaction
    {
        $this->validator->validateWallet($wallet);
        $this->validator->validateAmount($amount);

        return $this->createWalletTransaction(
            $wallet,
            WalletTransaction::TYPE_INTEREST_CHARGE,
            $amount,
            $autoApprove,
            $reference,
            $meta
        );
    }

    /**
     * Transfer funds between wallets with automatic currency conversion.
     *
     * @param Wallet $fromWallet
     * @param Wallet $toWallet
     * @param Money $amount
     * @param bool $autoApprove
     * @param string|null $reference
     * @param array|null $meta
     * @return array
     * @throws Throwable
     */
    public function transfer(
        Wallet  $fromWallet,
        Wallet  $toWallet,
        Money   $amount,
        bool    $autoApprove = true,
        ?string $reference = null,
        ?array  $meta = null
    ): array
    {
        $this->validator->validateWallet($fromWallet);
        $this->validator->validateWallet($toWallet);
        $this->validator->validateAmount($amount);

        return DB::transaction(function () use ($fromWallet, $toWallet, $amount, $autoApprove, $reference, $meta) {
            // Lock both wallets for update to prevent race conditions
            $lockedFromWallet = Wallet::query()->lockForUpdate()->find($fromWallet->id);
            $lockedToWallet = Wallet::query()->lockForUpdate()->find($toWallet->id);

            if (!$lockedFromWallet || !$lockedToWallet) {
                throw new Exception('One or both wallets not found during transfer.');
            }

            // Check if currency conversion is needed
            $convertedAmount = $amount;
            if ($lockedFromWallet->currency !== $lockedToWallet->currency) {
                $converter = app(CurrencyConverterService::class);
                $convertedAmount = $converter->convertMoney($amount, $lockedFromWallet->currency, $lockedToWallet->currency);
            }

            // Validate withdrawal from source wallet
            $this->validator->validateWithdrawal($lockedFromWallet, $amount);

            // Create withdrawal transaction
            $withdrawalTransaction = $this->createWalletTransaction(
                $lockedFromWallet,
                WalletTransaction::TYPE_WITHDRAW,
                $amount,
                $autoApprove,
                $reference ?: 'Transfer to ' . $lockedToWallet->slug,
                array_merge($meta ?? [], [
                    'transfer_to_wallet_id' => $lockedToWallet->id,
                    'transfer_to_wallet_slug' => $lockedToWallet->slug,
                    'original_currency' => $lockedFromWallet->currency,
                    'converted_currency' => $lockedToWallet->currency,
                    'conversion_rate' => $amount->equals($convertedAmount) ? 1.0 : $convertedAmount->toDecimal() / $amount->toDecimal(),
                ]),
                false // Don't use transaction since we're already in one
            );

            // Create deposit transaction
            $depositTransaction = $this->createWalletTransaction(
                $lockedToWallet,
                WalletTransaction::TYPE_DEPOSIT,
                $convertedAmount,
                $autoApprove,
                $reference ?: 'Transfer from ' . $lockedFromWallet->slug,
                array_merge($meta ?? [], [
                    'transfer_from_wallet_id' => $lockedFromWallet->id,
                    'transfer_from_wallet_slug' => $lockedFromWallet->slug,
                    'original_currency' => $lockedFromWallet->currency,
                    'converted_currency' => $lockedToWallet->currency,
                    'conversion_rate' => $amount->equals($convertedAmount) ? 1.0 : $convertedAmount->toDecimal() / $amount->toDecimal(),
                ]),
                false // Don't use transaction since we're already in one
            );

            return [
                'withdrawal_transaction' => $withdrawalTransaction,
                'deposit_transaction' => $depositTransaction,
                'original_amount' => $amount,
                'converted_amount' => $convertedAmount,
                'conversion_rate' => $amount->equals($convertedAmount) ? 1.0 : $convertedAmount->toDecimal() / $amount->toDecimal(),
            ];
        });
    }

    /**
     * Core function to create and optionally approve a transaction.
     *
     * @param Wallet $wallet
     * @param string $type
     * @param Money $amount
     * @param bool $autoApprove
     * @param string|null $reference
     * @param array|null $meta
     * @param bool $useTransaction
     * @return WalletTransaction
     * @throws Throwable
     */
    protected function createWalletTransaction(
        Wallet  $wallet,
        string  $type,
        Money   $amount,
        bool    $autoApprove,
        ?string $reference,
        ?array  $meta,
        bool    $useTransaction = true
    ): WalletTransaction
    {
        $createAndApprove = function ($walletInstance) use ($type, $amount, $autoApprove, $reference, $meta) {
            if (!$walletInstance->is_active && $autoApprove) {
                throw new Exception('Cannot auto-approve transactions for an inactive wallet.');
            }

            /** @var WalletTransaction $transaction */
            $transaction = $walletInstance->transactions()->create([
                'type' => $type,
                'amount' => $amount->toDecimal(),
                'reference' => $reference,
                'meta' => $meta,
                'status' => WalletTransaction::STATUS_PENDING,
            ]);

            if ($autoApprove) {
                $this->approvalService->approve($transaction);
            }

            return $transaction;
        };

        if ($useTransaction) {
            return DB::transaction(fn() => $createAndApprove($wallet));
        }

        return $createAndApprove($wallet);
    }

    /**
     * Manually approve a pending transaction.
     *
     * @param WalletTransaction $transaction
     * @return bool
     * @throws Throwable
     */
    public function approveTransaction(WalletTransaction $transaction): bool
    {
        return $this->approvalService->approve($transaction);
    }

    /**
     * Manually reject a pending transaction.
     *
     * @param WalletTransaction $transaction
     * @param string $reason
     * @return bool
     * @throws Throwable
     */
    public function rejectTransaction(WalletTransaction $transaction, string $reason = 'Rejected by admin'): bool
    {
        return $this->approvalService->reject($transaction, $reason);
    }

    /**
     * Get wallet balance summary.
     *
     * @param Wallet $wallet
     * @return array
     */
    public function getWalletSummary(Wallet $wallet): array
    {
        return [
            'name' => $wallet->name,
            'slug' => $wallet->slug,
            'currency' => $wallet->currency,
            'balance' => Money::fromDecimal($wallet->balance)->toDecimal(),
            'locked' => Money::fromDecimal($wallet->locked)->toDecimal(),
            'credit_limit' => Money::fromDecimal($wallet->credit)->toDecimal(),
            'available_balance' => $wallet->available_balance->toDecimal(),
            'available_funds' => $wallet->available_funds->toDecimal(),
            'debt' => $wallet->debt->toDecimal(),
            'remaining_credit' => $wallet->getRemainingCredit()->toDecimal(),
        ];
    }

    /**
     * Get wallet
     *
     * @param Wallet $wallet
     * @return object
     */
    public function getWallet(Wallet $wallet): object
    {
        return literal(
            model: $wallet,
            name: $wallet->name,
            slug: $wallet->slug,
            currency: $wallet->currency,
            balance: Money::fromDecimal($wallet->balance)->toDecimal(),
            locked: Money::fromDecimal($wallet->locked)->toDecimal(),
            credit_limit: Money::fromDecimal($wallet->credit)->toDecimal(),
            available_balance: $wallet->available_balance->toDecimal(),
            available_funds: $wallet->available_funds->toDecimal(),
            debt: $wallet->debt->toDecimal(),
            remaining_credit: $wallet->getRemainingCredit()->toDecimal(),
        );
    }
}