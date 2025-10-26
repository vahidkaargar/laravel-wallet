<?php

namespace vahidkaargar\LaravelWallet\Services;

use vahidkaargar\LaravelWallet\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for batch operations, such as rejecting old pending transactions.
 */
class BatchReversalService
{
    public function __construct(
        protected TransactionRollbackService $rollbackService
    ) {
    }

    /**
     * Rejects all pending transactions older than a given date.
     *
     * @return int The number of transactions rejected.
     */
    public function rejectPendingOlderThan(Carbon $date, string $reason = 'Transaction expired'): int
    {
        $count = 0;

        WalletTransaction::where('status', WalletTransaction::STATUS_PENDING)
            ->where('created_at', '<', $date)
            ->chunkById(100, function ($transactions) use (&$count, $reason) {
                foreach ($transactions as $transaction) {
                    try {
                        DB::transaction(function () use ($transaction, $reason) {
                            $transaction->status = WalletTransaction::STATUS_REJECTED;
                            $transaction->meta = array_merge($transaction->meta ?? [], [
                                'rejection_reason' => $reason
                            ]);
                            $transaction->save();
                        });
                        $count++;
                    } catch (\Exception $e) {
                        Log::error("Failed to reject pending transaction ID {$transaction->id}: {$e->getMessage()}");
                    }
                }
            });

        return $count;
    }

    /**
     * Rolls back *approved* transactions of a specific type older than a given date.
     * This is a "true" reversal. Use with caution.
     *
     * @return int The number of transactions reversed.
     */
    public function rollbackApprovedByTypeOlderThan(string $type, Carbon $date, string $reason = 'Expired transaction reversal'): int
    {
        $count = 0;

        WalletTransaction::where('status', WalletTransaction::STATUS_APPROVED)
            ->where('type', $type)
            ->where('created_at', '<', $date)
            ->chunkById(100, function ($transactions) use (&$count, $reason) {
                foreach ($transactions as $transaction) {
                    try {
                        $this->rollbackService->rollback($transaction, $reason);
                        $count++;
                    } catch (\Exception $e) {
                        Log::error("Failed to rollback approved transaction ID {$transaction->id}: {$e->getMessage()}");
                    }
                }
            });

        return $count;
    }
}