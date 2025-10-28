<?php

namespace vahidkaargar\LaravelWallet\Services;

use Exception;
use InvalidArgumentException;
use Throwable;
use vahidkaargar\LaravelWallet\Events\TransactionReversed;
use vahidkaargar\LaravelWallet\Models\Wallet;
use vahidkaargar\LaravelWallet\Models\WalletTransaction;
use vahidkaargar\LaravelWallet\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for "rolling back" (reversing) an *approved* transaction.
 * This creates a new, opposing transaction to ensure a clean audit trail.
 */
class TransactionRollbackService
{
    /**
     * @param CreditManagerService $creditManager
     */
    public function __construct(protected CreditManagerService $creditManager)
    {
    }

    /**
     * Rolls back an approved transaction.
     *
     * @param WalletTransaction $originalTransaction
     * @param string|null $reason
     * @return WalletTransaction
     * @throws Throwable
     */
    public function rollback(WalletTransaction $originalTransaction, ?string $reason = null): WalletTransaction
    {
        if (!$originalTransaction->isApproved()) {
            throw new Exception('Only approved transactions can be reversed.');
        }

        return DB::transaction(function () use ($originalTransaction, $reason) {
            // Lock the wallet for update to prevent race conditions
            /** @var Wallet $wallet */
            $wallet = $originalTransaction->wallet()->lockForUpdate()->first();

            if (!$wallet) {
                throw new Exception('Wallet not found during rollback operation.');
            }

            $amount = Money::fromDecimal($originalTransaction->amount);
            $reversalType = $this->getReversalType($originalTransaction->type);

            // Apply the *opposite* logic of the original transaction
            $this->applyReversal($wallet, $originalTransaction, $amount);

            // Save wallet changes
            $wallet->save();

            // Mark original transaction as reversed
            $originalTransaction->status = WalletTransaction::STATUS_REVERSED;
            $originalTransaction->meta = array_merge($originalTransaction->meta ?? [], [
                'reversed_at' => now()->toISOString(),
                'reversal_reason' => $reason,
            ]);
            $originalTransaction->save();

            // Create the new reversal transaction
            /** @var WalletTransaction $reversalTransaction */
            $reversalTransaction = $wallet->transactions()->create([
                'type' => $reversalType,
                'amount' => $amount->toDecimal(),
                'reference' => $originalTransaction->reference,
                'meta' => [
                    'reversal_reason' => $reason,
                    'reversed_transaction_id' => $originalTransaction->id,
                    'reversed_at' => now()->toISOString(),
                ],
                'status' => WalletTransaction::STATUS_APPROVED, // Reversals are auto-approved
            ]);

            event(new TransactionReversed($originalTransaction, $reversalTransaction));

            Log::info("Transaction $originalTransaction->id reversed", [
                'wallet_id' => $wallet->id,
                'original_type' => $originalTransaction->type,
                'reversal_type' => $reversalType,
                'amount' => $amount->toDecimal(),
                'reason' => $reason,
            ]);

            return $reversalTransaction;
        });
    }

    /**
     * Apply the reversal logic to the wallet.
     *
     * @param Wallet $wallet
     * @param WalletTransaction $originalTransaction
     * @param Money $amount
     */
    protected function applyReversal(Wallet $wallet, WalletTransaction $originalTransaction, Money $amount): void
    {
        switch ($originalTransaction->type) {
            case WalletTransaction::TYPE_DEPOSIT:
            case WalletTransaction::TYPE_CREDIT_REPAY:
                $newBalance = Money::fromDecimal($wallet->balance)->subtract($amount);
                $wallet->balance = $newBalance->toDecimal();
                break;

            case WalletTransaction::TYPE_WITHDRAW:
            case WalletTransaction::TYPE_INTEREST_CHARGE:
                $newBalance = Money::fromDecimal($wallet->balance)->add($amount);
                $wallet->balance = $newBalance->toDecimal();
                break;

            case WalletTransaction::TYPE_LOCK:
                $newLocked = Money::fromDecimal($wallet->locked)->subtract($amount);
                $wallet->locked = $newLocked->toDecimal();
                break;

            case WalletTransaction::TYPE_UNLOCK:
                $newLocked = Money::fromDecimal($wallet->locked)->add($amount);
                $wallet->locked = $newLocked->toDecimal();
                break;

            case WalletTransaction::TYPE_CREDIT_GRANT:
                $newCredit = Money::fromDecimal($wallet->credit)->subtract($amount);
                $wallet->credit = $newCredit->toDecimal();
                break;

            case WalletTransaction::TYPE_CREDIT_REVOKE:
                $newCredit = Money::fromDecimal($wallet->credit)->add($amount);
                $wallet->credit = $newCredit->toDecimal();
                break;

            default:
                throw new InvalidArgumentException("Unknown transaction type for reversal: $originalTransaction->type");
        }
    }

    /**
     * Get the opposing transaction type for a reversal.
     *
     * @param string $originalType
     * @return string
     */
    protected function getReversalType(string $originalType): string
    {
        return match ($originalType) {
            WalletTransaction::TYPE_DEPOSIT, WalletTransaction::TYPE_CREDIT_REPAY => WalletTransaction::TYPE_WITHDRAW,
            WalletTransaction::TYPE_WITHDRAW, WalletTransaction::TYPE_INTEREST_CHARGE => WalletTransaction::TYPE_DEPOSIT,
            WalletTransaction::TYPE_LOCK => WalletTransaction::TYPE_UNLOCK,
            WalletTransaction::TYPE_UNLOCK => WalletTransaction::TYPE_LOCK,
            WalletTransaction::TYPE_CREDIT_GRANT => WalletTransaction::TYPE_CREDIT_REVOKE,
            WalletTransaction::TYPE_CREDIT_REVOKE => WalletTransaction::TYPE_CREDIT_GRANT,
            default => throw new InvalidArgumentException("Unknown transaction type: $originalType"),
        };
    }
}