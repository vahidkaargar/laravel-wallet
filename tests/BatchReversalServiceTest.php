<?php

namespace vahidkaargar\LaravelWallet\Tests;

use vahidkaargar\LaravelWallet\Models\WalletTransaction;
use vahidkaargar\LaravelWallet\Services\BatchReversalService;
use vahidkaargar\LaravelWallet\Services\TransactionRollbackService;
use vahidkaargar\LaravelWallet\Services\WalletLedgerService;
use vahidkaargar\LaravelWallet\ValueObjects\Money;

class BatchReversalServiceTest extends TestCase
{
    protected BatchReversalService $batchService;
    protected WalletLedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->batchService = $this->app->make(BatchReversalService::class);
        $this->ledger = $this->app->make(WalletLedgerService::class);
    }

    public function test_pending_transactions_are_rejected()
    {
        // Create a pending transaction
        $tx = $this->ledger->deposit($this->wallet, Money::fromDecimal(100.00), false);

        // Manually set its date to be old
        $tx->created_at = now()->subDays(5);
        $tx->save();

        $count = $this->batchService->rejectPendingOlderThan(now()->subDay(), 'Test expired');

        $this->assertEquals(1, $count);
        $this->assertDatabaseHas('wallet_transactions', [
            'id' => $tx->id,
            'status' => 'rejected',
        ]);

        $tx->refresh();
        $this->assertEquals('Test expired', $tx->meta['rejection_reason']);
    }

    public function test_approved_transactions_can_be_rolled_back_in_batch()
    {
        // Create an approved transaction
        $tx = $this->ledger->deposit($this->wallet, Money::fromDecimal(100.00), true);
        $tx->created_at = now()->subDays(10);
        $tx->save();

        // Mock the rollback service BEFORE creating the batch service
        $mock = $this->mock(TransactionRollbackService::class);

        // Expect the rollback method to be called once with our transaction
        $mock->shouldReceive('rollback')
            ->once()
            ->withArgs(function (WalletTransaction $transaction) use ($tx) {
                return $transaction->id === $tx->id;
            });

        // Create a new batch service instance with the mocked rollback service
        $batchService = new BatchReversalService($mock);

        $count = $batchService->rollbackApprovedByTypeOlderThan(
            WalletTransaction::TYPE_DEPOSIT,
            now()->subDay(),
            'Test rollback'
        );

        $this->assertEquals(1, $count);
    }
}