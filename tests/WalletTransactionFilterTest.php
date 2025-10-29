<?php

namespace vahidkaargar\LaravelWallet\Tests;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use vahidkaargar\LaravelWallet\Enums\TransactionStatus;
use vahidkaargar\LaravelWallet\Enums\TransactionType;
use vahidkaargar\LaravelWallet\Models\Wallet;
use vahidkaargar\LaravelWallet\Models\WalletTransaction;
use vahidkaargar\LaravelWallet\ValueObjects\Money;

class WalletTransactionFilterTest extends TestCase
{
    use RefreshDatabase;

    protected $wallet;
    protected $deposit1;
    protected $deposit2;
    protected $withdraw1;
    protected $pendingTransaction;
    protected $rejectedTransaction;

    protected function setUp(): void
    {
        parent::setUp();

        // Create transactions with different types, statuses, and dates
        $this->deposit1 = WalletTransaction::create([
            'wallet_id' => $this->wallet->id,
            'type' => TransactionType::DEPOSIT,
            'status' => TransactionStatus::APPROVED,
            'amount' => 100.00,
            'description' => 'Test deposit 1',
            'created_at' => Carbon::now()->subDays(5),
        ]);

        $this->deposit2 = WalletTransaction::create([
            'wallet_id' => $this->wallet->id,
            'type' => TransactionType::DEPOSIT,
            'status' => TransactionStatus::APPROVED,
            'amount' => 200.00,
            'description' => 'Test deposit 2',
            'created_at' => Carbon::now()->subDays(3),
        ]);

        $this->withdraw1 = WalletTransaction::create([
            'wallet_id' => $this->wallet->id,
            'type' => TransactionType::WITHDRAW,
            'status' => TransactionStatus::APPROVED,
            'amount' => 50.00,
            'description' => 'Test withdraw 1',
            'created_at' => Carbon::now()->subDays(1),
        ]);

        $this->pendingTransaction = WalletTransaction::create([
            'wallet_id' => $this->wallet->id,
            'type' => TransactionType::DEPOSIT,
            'status' => TransactionStatus::PENDING,
            'amount' => 75.00,
            'description' => 'Test pending',
            'created_at' => Carbon::now()->subHours(2),
        ]);

        $this->rejectedTransaction = WalletTransaction::create([
            'wallet_id' => $this->wallet->id,
            'type' => TransactionType::WITHDRAW,
            'status' => TransactionStatus::REJECTED,
            'amount' => 100.00,
            'description' => 'Test rejected',
            'created_at' => Carbon::now()->subHours(1),
        ]);
    }

    public function test_get_all_transactions()
    {
        $transactions = $this->wallet->getTransactions();

        $this->assertCount(5, $transactions);
        $this->assertEquals($this->rejectedTransaction->id, $transactions->first()->id); // Most recent first
    }

    public function test_filter_by_transaction_type()
    {
        $deposits = $this->wallet->getTransactions(type: TransactionType::DEPOSIT);
        $withdraws = $this->wallet->getTransactions(type: TransactionType::WITHDRAW);

        $this->assertCount(3, $deposits);
        $this->assertCount(2, $withdraws);

        foreach ($deposits as $transaction) {
            $this->assertEquals(TransactionType::DEPOSIT, $transaction->type);
        }

        foreach ($withdraws as $transaction) {
            $this->assertEquals(TransactionType::WITHDRAW, $transaction->type);
        }
    }

    public function test_filter_by_transaction_status()
    {
        $approved = $this->wallet->getTransactions(status: TransactionStatus::APPROVED);
        $pending = $this->wallet->getTransactions(status: TransactionStatus::PENDING);
        $rejected = $this->wallet->getTransactions(status: TransactionStatus::REJECTED);

        $this->assertCount(3, $approved);
        $this->assertCount(1, $pending);
        $this->assertCount(1, $rejected);

        foreach ($approved as $transaction) {
            $this->assertEquals(TransactionStatus::APPROVED, $transaction->status);
        }
    }

    public function test_filter_by_date_range()
    {
        $fromDate = Carbon::now()->subDays(4);
        $toDate = Carbon::now()->subDays(2);

        $transactions = $this->wallet->getTransactions(
            fromDate: $fromDate,
            toDate: $toDate
        );

        $this->assertCount(1, $transactions);
        $this->assertEquals($this->deposit2->id, $transactions->first()->id);
    }

    public function test_filter_by_from_date_only()
    {
        $fromDate = Carbon::now()->subDays(2);

        $transactions = $this->wallet->getTransactions(fromDate: $fromDate);

        $this->assertCount(3, $transactions);
        $this->assertTrue($transactions->contains($this->withdraw1));
        $this->assertTrue($transactions->contains($this->pendingTransaction));
        $this->assertTrue($transactions->contains($this->rejectedTransaction));
    }

    public function test_filter_by_to_date_only()
    {
        $toDate = Carbon::now()->subDays(2);

        $transactions = $this->wallet->getTransactions(toDate: $toDate);

        $this->assertCount(2, $transactions);
        $this->assertTrue($transactions->contains($this->deposit1));
        $this->assertTrue($transactions->contains($this->deposit2));
    }

    public function test_combined_filters()
    {
        $fromDate = Carbon::now()->subDays(4);
        $toDate = Carbon::now()->subDays(1);

        $transactions = $this->wallet->getTransactions(
            type: TransactionType::DEPOSIT,
            status: TransactionStatus::APPROVED,
            fromDate: $fromDate,
            toDate: $toDate
        );

        $this->assertCount(1, $transactions);
        $this->assertEquals($this->deposit2->id, $transactions->first()->id);
    }

    public function test_limit_and_offset()
    {
        $transactions = $this->wallet->getTransactions(limit: 2, offset: 1);

        $this->assertCount(2, $transactions);
        $this->assertEquals($this->pendingTransaction->id, $transactions->first()->id);
        $this->assertEquals($this->withdraw1->id, $transactions->last()->id);
    }

    public function test_paginated_transactions()
    {
        $paginated = $this->wallet->getTransactionsPaginated(perPage: 2);

        $this->assertEquals(2, $paginated->perPage());
        $this->assertEquals(5, $paginated->total());
        $this->assertEquals(3, $paginated->lastPage());
        $this->assertCount(2, $paginated->items());
    }

    public function test_paginated_with_filters()
    {
        $paginated = $this->wallet->getTransactionsPaginated(
            type: TransactionType::DEPOSIT,
            perPage: 2
        );

        $this->assertEquals(3, $paginated->total());
        $this->assertEquals(2, $paginated->lastPage());

        foreach ($paginated->items() as $transaction) {
            $this->assertEquals(TransactionType::DEPOSIT, $transaction->type);
        }
    }

    public function test_has_wallets_trait_proxy_methods()
    {
        // Test getWalletTransactions
        $deposits = $this->user->getWalletTransactions('test-wallet', type: TransactionType::DEPOSIT);
        $this->assertCount(3, $deposits);

        // Test getWalletTransactionsPaginated
        $paginated = $this->user->getWalletTransactionsPaginated('test-wallet', perPage: 2);
        $this->assertEquals(5, $paginated->total());
        $this->assertCount(2, $paginated->items());

        // Test with wallet object instead of slug
        $depositsFromObject = $this->user->getWalletTransactions($this->wallet, type: TransactionType::DEPOSIT);
        $this->assertCount(3, $depositsFromObject);
    }

    public function test_transactions_ordered_by_created_at_desc()
    {
        $transactions = $this->wallet->getTransactions();

        $this->assertEquals($this->rejectedTransaction->id, $transactions[0]->id);
        $this->assertEquals($this->pendingTransaction->id, $transactions[1]->id);
        $this->assertEquals($this->withdraw1->id, $transactions[2]->id);
        $this->assertEquals($this->deposit2->id, $transactions[3]->id);
        $this->assertEquals($this->deposit1->id, $transactions[4]->id);
    }

    public function test_empty_result_when_no_matching_filters()
    {
        $transactions = $this->wallet->getTransactions(
            type: TransactionType::CREDIT_GRANT,
            status: TransactionStatus::REVERSED
        );

        $this->assertCount(0, $transactions);
    }
}
