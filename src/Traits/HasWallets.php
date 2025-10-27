<?php

namespace vahidkaargar\LaravelWallet\Traits;

use vahidkaargar\LaravelWallet\Exceptions\WalletNotFoundException;
use vahidkaargar\LaravelWallet\Models\Wallet;
use vahidkaargar\LaravelWallet\Models\WalletTransaction;
use vahidkaargar\LaravelWallet\Services\WalletLedgerService;
use vahidkaargar\LaravelWallet\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection|Wallet[] $wallets
 * @mixin Model
 */
trait HasWallets
{
    /**
     * Get all wallets associated with this model.
     */
    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class, 'user_id');
    }

    /**
     * Create a new wallet for the user.
     */
    public function createWallet(array $attributes): Wallet
    {
        return $this->wallets()->create($attributes);
    }

    /**
     * Get a specific wallet by its slug.
     *
     * @throws WalletNotFoundException
     */
    public function getWalletBySlug(string $slug): Wallet
    {
        $wallet = $this->wallets()->where('slug', $slug)->first();

        if (!$wallet) {
            throw new WalletNotFoundException("Wallet with slug '{$slug}' not found for this user.");
        }

        return $wallet;
    }

    /**
     * Get the wallet ledger service instance.
     */
    protected function ledger(): WalletLedgerService
    {
        return app(WalletLedgerService::class);
    }

    /**
     * Proxy for WalletLedgerService::deposit.
     */
    public function deposit(
        string $walletSlug,
        Money|float|string $amount,
        ?string $reference = null,
        bool $autoApprove = true,
        ?array $meta = null
    ): WalletTransaction {
        $wallet = $this->getWalletBySlug($walletSlug);
        $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);
        return $this->ledger()->deposit($wallet, $moneyAmount, $autoApprove, $reference, $meta);
    }

    /**
     * Proxy for WalletLedgerService::withdraw.
     */
    public function withdraw(
        string $walletSlug,
        Money|float|string $amount,
        ?string $reference = null,
        bool $autoApprove = true,
        ?array $meta = null
    ): WalletTransaction {
        $wallet = $this->getWalletBySlug($walletSlug);
        $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);
        return $this->ledger()->withdraw($wallet, $moneyAmount, $autoApprove, $reference, $meta);
    }

    /**
     * Proxy for WalletLedgerService::lock.
     */
    public function lock(
        string $walletSlug,
        Money|float|string $amount,
        ?string $reference = null,
        bool $autoApprove = true,
        ?array $meta = null
    ): WalletTransaction {
        $wallet = $this->getWalletBySlug($walletSlug);
        $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);
        return $this->ledger()->lock($wallet, $moneyAmount, $autoApprove, $reference, $meta);
    }

    /**
     * Proxy for WalletLedgerService::unlock.
     */
    public function unlock(
        string $walletSlug,
        Money|float|string $amount,
        ?string $reference = null,
        bool $autoApprove = true,
        ?array $meta = null
    ): WalletTransaction {
        $wallet = $this->getWalletBySlug($walletSlug);
        $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);
        return $this->ledger()->unlock($wallet, $moneyAmount, $autoApprove, $reference, $meta);
    }

    /**
     * Proxy for WalletLedgerService::grantCredit.
     */
    public function grantCredit(
        string $walletSlug,
        Money|float|string $amount,
        ?string $reference = null,
        bool $autoApprove = true,
        ?array $meta = null
    ): WalletTransaction {
        $wallet = $this->getWalletBySlug($walletSlug);
        $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);
        return $this->ledger()->grantCredit($wallet, $moneyAmount, $autoApprove, $reference, $meta);
    }

    /**
     * Proxy for WalletLedgerService::revokeCredit.
     */
    public function revokeCredit(
        string $walletSlug,
        Money|float|string $amount,
        ?string $reference = null,
        bool $autoApprove = true,
        ?array $meta = null
    ): WalletTransaction {
        $wallet = $this->getWalletBySlug($walletSlug);
        $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);
        return $this->ledger()->revokeCredit($wallet, $moneyAmount, $autoApprove, $reference, $meta);
    }

    /**
     * Get wallet summary for a specific wallet.
     */
    public function getWalletSummary(string $walletSlug): array
    {
        $wallet = $this->getWalletBySlug($walletSlug);
        return $this->ledger()->getWalletSummary($wallet);
    }

    /**
     * Get wallet summary for a specific wallet.
     */
    public function getWallet(string $walletSlug): object
    {
        $wallet = $this->getWalletBySlug($walletSlug);
        return $this->ledger()->getWallet($wallet);
    }

    /**
     * Check if wallet has sufficient funds.
     */
    public function hasSufficientFunds(string $walletSlug, Money|float|string $amount): bool
    {
        $wallet = $this->getWalletBySlug($walletSlug);
        $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);
        return $wallet->hasSufficientFunds($moneyAmount);
    }

    /**
     * Transfer funds between wallets with automatic currency conversion.
     */
    public function transfer(
        string $fromWalletSlug,
        string $toWalletSlug,
        Money|float|string $amount,
        bool $autoApprove = true,
        ?string $reference = null,
        ?array $meta = null
    ): array {
        $fromWallet = $this->getWalletBySlug($fromWalletSlug);
        $toWallet = $this->getWalletBySlug($toWalletSlug);
        $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);
        
        return $this->ledger()->transfer($fromWallet, $toWallet, $moneyAmount, $autoApprove, $reference, $meta);
    }
}