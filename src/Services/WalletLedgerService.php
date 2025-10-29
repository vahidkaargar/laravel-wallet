<?php

namespace vahidkaargar\LaravelWallet\Services;

use Exception;
use Throwable;
use vahidkaargar\LaravelWallet\Enums\TransactionStatus;
use vahidkaargar\LaravelWallet\Enums\TransactionType;
use vahidkaargar\LaravelWallet\Exceptions\InvalidAmountException;
use vahidkaargar\LaravelWallet\Exceptions\InsufficientFundsException;
use vahidkaargar\LaravelWallet\Models\Wallet;
use vahidkaargar\LaravelWallet\Models\WalletTransaction;
use vahidkaargar\LaravelWallet\Services\LoggingService;
use vahidkaargar\LaravelWallet\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
     * @param LoggingService $logger
     */
    public function __construct(
        protected CreditManagerService       $creditManager,
        protected TransactionApprovalService $approvalService,
        protected ValidationService          $validator,
        protected LoggingService            $logger
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
        try {
            if ($autoApprove) {
                $this->validator->validateWallet($wallet);
            }
            $this->validator->validateAmount($amount);

            return $this->createWalletTransaction(
                $wallet,
                TransactionType::DEPOSIT,
                $amount,
                $autoApprove,
                $reference,
                $meta
            );
        } catch (\Exception $e) {
            $this->logger->logError('Failed to deposit funds via ledger service', [
                'wallet_id' => $wallet->id ?? 'unknown',
                'wallet_slug' => $wallet->slug ?? 'unknown',
                'amount' => $amount->toDecimal(),
                'reference' => $reference,
                'auto_approve' => $autoApprove,
                'meta' => $meta,
            ], $e);
            
            // Re-throw the original exception to preserve type information
            throw $e;
        }
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
        try {
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
                    TransactionType::WITHDRAW,
                    $amount,
                    $autoApprove,
                    $reference,
                    $meta,
                    false // Wallet is already locked, don't re-lock in createWalletTransaction
                );
            });
        } catch (\Exception $e) {
            $this->logger->logError('Failed to withdraw funds via ledger service', [
                'wallet_id' => $wallet->id ?? 'unknown',
                'wallet_slug' => $wallet->slug ?? 'unknown',
                'amount' => $amount->toDecimal(),
                'reference' => $reference,
                'auto_approve' => $autoApprove,
                'meta' => $meta,
            ], $e);
            
            // Re-throw the original exception to preserve type information
            throw $e;
        }
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
    public function lockFunds(
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
                TransactionType::LOCK,
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
    public function unlockFunds(
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
            TransactionType::UNLOCK,
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
            TransactionType::CREDIT_GRANT,
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
            TransactionType::CREDIT_REVOKE,
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
            TransactionType::INTEREST_CHARGE,
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
                TransactionType::WITHDRAW,
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
                TransactionType::DEPOSIT,
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
     * @param TransactionType $type
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
        TransactionType  $type,
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

            $description = $this->generateDescription($type, $amount, $reference);
            $normalizedMeta = $this->normalizeMeta($walletInstance, $type, $amount, $reference, $meta);

            /** @var WalletTransaction $transaction */
            $transaction = $walletInstance->transactions()->create([
                'type' => $type->value,
                'amount' => $amount->toDecimal(),
                'description' => $description,
                'reference' => $reference,
                'meta' => $normalizedMeta,
                'status' => TransactionStatus::PENDING,
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
     * Generate a human-readable description for the transaction.
     */
    protected function generateDescription(TransactionType $type, Money $amount, ?string $reference): string
    {
        $desc = sprintf('%s of %0.2f', $type->label(), $amount->toDecimal());
        if ($reference) {
            $desc .= sprintf(' (ref: %s)', $reference);
        }
        return $desc;
    }

    /**
     * Normalize meta into a consistent, retrievable structure.
     */
    protected function normalizeMeta(Wallet $wallet, TransactionType $type, Money $amount, ?string $reference, ?array $meta): array
    {
        $meta = $meta ?? [];

        $standard = [
            'correlation_id' => $meta['correlation_id'] ?? (string) Str::ulid(),
            'initiated_by' => $meta['initiated_by'] ?? 'system', // user|system|schedule
            'actor_user_id' => $meta['actor_user_id'] ?? $wallet->user_id,
            'reference' => $reference,
            'ip' => $meta['ip'] ?? null,
            'user_agent' => $meta['user_agent'] ?? null,
            'tags' => array_values(array_unique(array_map('strval', $meta['tags'] ?? []))),
            'context' => is_array($meta['context'] ?? null) ? $meta['context'] : [],
            'notes' => isset($meta['notes']) ? (string) $meta['notes'] : null,
            'audit' => [
                'type' => $type->value,
                'currency' => $wallet->currency,
                'amount_decimal' => $amount->toDecimal(),
            ],
        ];

        // Preserve any provided custom keys at the root level for retrievability
        return array_merge($meta, $standard);
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

}