<?php

namespace vahidkaargar\LaravelWallet\Services;

use vahidkaargar\LaravelWallet\Events\CreditGranted;
use vahidkaargar\LaravelWallet\Events\WalletDeposited;
use vahidkaargar\LaravelWallet\Events\WalletWithdrawn;
use vahidkaargar\LaravelWallet\Exceptions\InsufficientFundsException;
use vahidkaargar\LaravelWallet\Models\Wallet;
use vahidkaargar\LaravelWallet\Models\WalletTransaction;
use vahidkaargar\LaravelWallet\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service dedicated to approving or rejecting pending transactions.
 * This is the *only* service that should mutate wallet balances.
 * All operations are atomic and use pessimistic locking for safety.
 */
class TransactionApprovalService
{
    public function __construct(
        protected CreditManagerService $creditManager,
        protected ValidationService $validator
    ) {
    }

    /**
     * Approves a pending transaction and applies it to the wallet balance.
     * This operation is atomic and uses pessimistic locking.
     */
    public function approve(WalletTransaction $transaction): bool
    {
        if (!$transaction->isPending()) {
            Log::warning("Attempted to approve an already-processed transaction: {$transaction->id}");
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
                $transaction->status = WalletTransaction::STATUS_APPROVED;
                $transaction->save();

                // Dispatch appropriate event
                $this->dispatchApprovalEvent($wallet, $transaction);

                Log::info("Transaction {$transaction->id} approved successfully", [
                    'wallet_id' => $wallet->id,
                    'type' => $transaction->type,
                    'amount' => $amount->toDecimal(),
                ]);

                return true;

            } catch (\Exception $e) {
                Log::error("Failed to approve transaction {$transaction->id}: {$e->getMessage()}", [
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
     */
    protected function applyTransaction(Wallet $wallet, WalletTransaction $transaction, Money $amount): void
    {
        switch ($transaction->type) {
            case WalletTransaction::TYPE_DEPOSIT:
            case WalletTransaction::TYPE_CREDIT_REPAY:
                $this->creditManager->applyDeposit($wallet, $transaction);
                break;

            case WalletTransaction::TYPE_WITHDRAW:
                $newBalance = Money::fromDecimal($wallet->balance)->subtract($amount);
                $wallet->balance = $newBalance->toDecimal();
                break;

            case WalletTransaction::TYPE_LOCK:
                $newLocked = Money::fromDecimal($wallet->locked)->add($amount);
                $wallet->locked = $newLocked->toDecimal();
                break;

            case WalletTransaction::TYPE_UNLOCK:
                $newLocked = Money::fromDecimal($wallet->locked)->subtract($amount);
                $wallet->locked = $newLocked->toDecimal();
                break;

            case WalletTransaction::TYPE_CREDIT_GRANT:
                $newCredit = Money::fromDecimal($wallet->credit)->add($amount);
                $wallet->credit = $newCredit->toDecimal();
                break;

            case WalletTransaction::TYPE_CREDIT_REVOKE:
                $newCredit = Money::fromDecimal($wallet->credit)->subtract($amount);
                $wallet->credit = $newCredit->toDecimal();
                break;

            case WalletTransaction::TYPE_INTEREST_CHARGE:
                // Interest can drive balance further negative, no funds check needed.
                $newBalance = Money::fromDecimal($wallet->balance)->subtract($amount);
                $wallet->balance = $newBalance->toDecimal();
                break;

            default:
                throw new \InvalidArgumentException("Unknown transaction type: {$transaction->type}");
        }
    }

    /**
     * Rejects a pending transaction.
     */
    public function reject(WalletTransaction $transaction, string $reason = 'Transaction rejected'): bool
    {
        if (!$transaction->isPending()) {
            return false;
        }

        return DB::transaction(function () use ($transaction, $reason) {
            $transaction->status = WalletTransaction::STATUS_REJECTED;
            $transaction->meta = array_merge($transaction->meta ?? [], [
                'rejection_reason' => $reason,
                'rejected_at' => now()->toISOString(),
            ]);
            $transaction->save();

            Log::info("Transaction {$transaction->id} rejected", [
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
     */
    protected function dispatchApprovalEvent(Wallet $wallet, WalletTransaction $transaction): void
    {
        match ($transaction->type) {
            WalletTransaction::TYPE_DEPOSIT => event(new WalletDeposited($wallet, $transaction)),
            WalletTransaction::TYPE_WITHDRAW => event(new WalletWithdrawn($wallet, $transaction)),
            WalletTransaction::TYPE_CREDIT_GRANT => event(new CreditGranted($wallet, $transaction->amount, $transaction)),
            // Other events (CreditRepaid, etc.) are fired from CreditManagerService
            default => null,
        };
    }
}