<?php

namespace vahidkaargar\LaravelWallet\Tests;

use vahidkaargar\LaravelWallet\Services\WalletLedgerService;
use vahidkaargar\LaravelWallet\ValueObjects\Money;

class TransferTest extends TestCase
{
    protected WalletLedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = $this->app->make(WalletLedgerService::class);
    }

    public function test_can_transfer_between_same_currency_wallets()
    {
        // Create two USD wallets
        $fromWallet = $this->user->createWallet([
            'name' => 'Source Wallet',
            'slug' => 'source',
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $toWallet = $this->user->createWallet([
            'name' => 'Destination Wallet',
            'slug' => 'destination',
            'currency' => 'USD',
            'is_active' => true,
        ]);

        // Deposit to source wallet
        $this->ledger->deposit($fromWallet, Money::fromDecimal(1000.00));

        // Transfer 500 USD
        $result = $this->ledger->transfer($fromWallet, $toWallet, Money::fromDecimal(500.00));

        // Verify transactions were created
        $this->assertInstanceOf(\vahidkaargar\LaravelWallet\Models\WalletTransaction::class, $result['withdrawal_transaction']);
        $this->assertInstanceOf(\vahidkaargar\LaravelWallet\Models\WalletTransaction::class, $result['deposit_transaction']);

        // Verify amounts
        $this->assertEquals(500.00, $result['original_amount']->toDecimal());
        $this->assertEquals(500.00, $result['converted_amount']->toDecimal());
        $this->assertEquals(1.0, $result['conversion_rate']);

        // Verify wallet balances
        $fromWallet->refresh();
        $toWallet->refresh();
        $this->assertEquals(500.00, $fromWallet->balance);
        $this->assertEquals(500.00, $toWallet->balance);
    }

    public function test_can_transfer_between_different_currency_wallets()
    {
        // Create USD and EUR wallets
        $fromWallet = $this->user->createWallet([
            'name' => 'USD Wallet',
            'slug' => 'usd',
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $toWallet = $this->user->createWallet([
            'name' => 'EUR Wallet',
            'slug' => 'eur',
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        // Deposit to USD wallet
        $this->ledger->deposit($fromWallet, Money::fromDecimal(1000.00));

        // Transfer 100 USD (should convert to EUR)
        $result = $this->ledger->transfer($fromWallet, $toWallet, Money::fromDecimal(100.00));

        // Verify conversion rate was applied (USD to EUR = 0.95)
        $this->assertEquals(100.00, $result['original_amount']->toDecimal());
        $this->assertEquals(95.00, $result['converted_amount']->toDecimal());
        $this->assertEquals(0.95, $result['conversion_rate']);

        // Verify wallet balances
        $fromWallet->refresh();
        $toWallet->refresh();
        $this->assertEquals(900.00, $fromWallet->balance);
        $this->assertEquals(95.00, $toWallet->balance);
    }

    public function test_transfer_fails_with_insufficient_funds()
    {
        // Create two USD wallets
        $fromWallet = $this->user->createWallet([
            'name' => 'Source Wallet',
            'slug' => 'source',
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $toWallet = $this->user->createWallet([
            'name' => 'Destination Wallet',
            'slug' => 'destination',
            'currency' => 'USD',
            'is_active' => true,
        ]);

        // Deposit only 100 USD
        $this->ledger->deposit($fromWallet, Money::fromDecimal(100.00));

        // Try to transfer 200 USD (should fail)
        $this->expectException(\Exception::class);
        $this->ledger->transfer($fromWallet, $toWallet, Money::fromDecimal(200.00));
    }

    public function test_transfer_creates_proper_metadata()
    {
        // Create two wallets
        $fromWallet = $this->user->createWallet([
            'name' => 'Source Wallet',
            'slug' => 'source',
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $toWallet = $this->user->createWallet([
            'name' => 'Destination Wallet',
            'slug' => 'destination',
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        // Deposit to source wallet
        $this->ledger->deposit($fromWallet, Money::fromDecimal(1000.00));

        // Transfer with custom reference
        $result = $this->ledger->transfer(
            $fromWallet, 
            $toWallet, 
            Money::fromDecimal(100.00),
            true,
            'Test transfer',
            ['custom_field' => 'test_value']
        );

        // Verify withdrawal transaction metadata
        $withdrawalMeta = $result['withdrawal_transaction']->meta;
        $this->assertEquals($toWallet->id, $withdrawalMeta['transfer_to_wallet_id']);
        $this->assertEquals('destination', $withdrawalMeta['transfer_to_wallet_slug']);
        $this->assertEquals('USD', $withdrawalMeta['original_currency']);
        $this->assertEquals('EUR', $withdrawalMeta['converted_currency']);
        $this->assertEquals('test_value', $withdrawalMeta['custom_field']);

        // Verify deposit transaction metadata
        $depositMeta = $result['deposit_transaction']->meta;
        $this->assertEquals($fromWallet->id, $depositMeta['transfer_from_wallet_id']);
        $this->assertEquals('source', $depositMeta['transfer_from_wallet_slug']);
        $this->assertEquals('USD', $depositMeta['original_currency']);
        $this->assertEquals('EUR', $depositMeta['converted_currency']);
        $this->assertEquals('test_value', $depositMeta['custom_field']);
    }
}
