<?php

namespace vahidkaargar\LaravelWallet\Services;

use Exception;
use InvalidArgumentException;
use Throwable;
use vahidkaargar\LaravelWallet\Enums\{TransactionStatus, TransactionType};
use vahidkaargar\LaravelWallet\Events\{CreditGranted, WalletDeposited, WalletWithdrawn};
use vahidkaargar\LaravelWallet\Models\{Wallet, WalletTransaction};
use vahidkaargar\LaravelWallet\ValueObjects\Money;
use Illuminate\Support\Facades\{DB, Log};

/**
 * Service dedicated to approving or rejecting pending transactions.
 * This is the *only* service that should mutate wallet balances.
 * All operations are atomic and use pessimistic locking for safety.
 */
class TransactionApprovalService
{
    /**
     * @param CreditManagerService $creditManager
     * @param ValidationService $validator
     */
    public function __construct(
        protected CreditManagerService $creditManager,
        protected ValidationService    $validator
    )
    {
    }

    /**
     * Approves a pending transaction and applies it to the wallet balance.
     * This operation is atomic and uses pessimistic locking.
     *
     * @param WalletTransaction $transaction
     * @return bool
     * @throws Throwable
     */
    public function approve(WalletTransaction $transaction): bool
    {
        if (!$transaction->isPending()) {
            Log::warning("Attempted to approve an already-processed transaction: $transaction->id");
            return false;
        }

        return DB::transaction(function () use ($transaction) {
            // Lock the wallet for update to prevent race conditions
            /** @var Wallet $wallet */
            $wallet = $transaction->wallet()->lockForUpdate()->first();

            if (!$wallet || !$wallet->is_active) {
                $this->reject($transaction, 'Wallet is not active or not found.');
                return false;
            }

            try {
                // Validate the transaction before applying
                $this->validator->validateTransactionForApproval($transaction);

                $amount = Money::fromDecimal($transaction->amount);

                // Apply the transaction based on its type
                $this->applyTransaction($wallet, $transaction, $amount);

                // Save wallet changes
                $wallet->save();

                // Update transaction status
                $transaction->status = TransactionStatus::APPROVED;
                $transaction->save();

                // Dispatch appropriate event
                $this->dispatchApprovalEvent($wallet, $transaction);

                Log::info("Transaction $transaction->id approved successfully", [
                    'wallet_id' => $wallet->id,
                    'type' => $transaction->type,
                    'amount' => $amount->toDecimal(),
                ]);

                return true;

            } catch (Exception $e) {
                Log::error("Failed to approve transaction $transaction->id: {$e->getMessage()}", [
                    'wallet_id' => $wallet->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                ]);

                $this->reject($transaction, $e->getMessage());
                return false;
            }
        });
    }

    /**
     * Apply the transaction to the wallet based on its type.
     *
     * @param Wallet $wallet
     * @param WalletTransaction $transaction
     * @param Money $amount
     */
    protected function applyTransaction(Wallet $wallet, WalletTransaction $transaction, Money $amount): void
    {
        switch ($transaction->type) {
            case TransactionType::DEPOSIT:
            case TransactionType::CREDIT_REPAY:
                $this->creditManager->applyDeposit($wallet, $transaction);
                break;

            case TransactionType::WITHDRAW:
                $newBalance = Money::fromDecimal($wallet->balance)->subtract($amount);
                $wallet->balance = $newBalance->toDecimal();
                break;

            case TransactionType::LOCK:
                $newLocked = Money::fromDecimal($wallet->locked)->add($amount);
                $wallet->locked = $newLocked->toDecimal();
                break;

            case TransactionType::UNLOCK:
                $newLocked = Money::fromDecimal($wallet->locked)->subtract($amount);
                $wallet->locked = $newLocked->toDecimal();
                break;

            case TransactionType::CREDIT_GRANT:
                $newCredit = Money::fromDecimal($wallet->credit)->add($amount);
                $wallet->credit = $newCredit->toDecimal();
                break;

            case TransactionType::CREDIT_REVOKE:
                $newCredit = Money::fromDecimal($wallet->credit)->subtract($amount);
                $wallet->credit = $newCredit->toDecimal();
                break;

            case TransactionType::INTEREST_CHARGE:
                // Interest can drive balance further negative, no funds check needed.
                $newBalance = Money::fromDecimal($wallet->balance)->subtract($amount);
                $wallet->balance = $newBalance->toDecimal();
                break;

            default:
                throw new InvalidArgumentException("Unknown transaction type: $transaction->type");
        }
    }

    /**
     * Rejects a pending transaction.
     *
     * @param WalletTransaction $transaction
     * @param string $reason
     * @return bool
     * @throws Throwable
     */
    public function reject(WalletTransaction $transaction, string $reason = 'Transaction rejected'): bool
    {
        if (!$transaction->isPending()) {
            return false;
        }

        return DB::transaction(function () use ($transaction, $reason) {
            $transaction->status = TransactionStatus::REJECTED;
            $transaction->meta = array_merge($transaction->meta ?? [], [
                'rejection_reason' => $reason,
                'rejected_at' => now()->toISOString(),
            ]);
            $transaction->save();

            Log::info("Transaction $transaction->id rejected", [
                'wallet_id' => $transaction->wallet_id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'reason' => $reason,
            ]);

            return true;
        });
    }

    /**
     * Dispatches the appropriate event based on transaction type.
     *
     * @param Wallet $wallet
     * @param WalletTransaction $transaction
     */
    protected function dispatchApprovalEvent(Wallet $wallet, WalletTransaction $transaction): void
    {
        match ($transaction->type) {
            TransactionType::DEPOSIT => event(new WalletDeposited($wallet, $transaction)),
            TransactionType::WITHDRAW => event(new WalletWithdrawn($wallet, $transaction)),
            TransactionType::CREDIT_GRANT => event(new CreditGranted($wallet, $transaction->amount, $transaction)),
            // Other events (CreditRepaid, etc.) are fired from CreditManagerService
            default => null,
        };
    }
}