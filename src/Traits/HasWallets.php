<?php

namespace vahidkaargar\LaravelWallet\Traits;

use Illuminate\Database\Eloquent\Collection;
use Throwable;
use vahidkaargar\LaravelWallet\Exceptions\WalletNotFoundException;
use vahidkaargar\LaravelWallet\Models\Wallet;
use vahidkaargar\LaravelWallet\Models\WalletTransaction;
use vahidkaargar\LaravelWallet\Services\WalletLedgerService;
use vahidkaargar\LaravelWallet\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Collection|Wallet[] $wallets
 * @mixin Model
 */
trait HasWallets
{
    /**
     * Get all wallets associated with this model.
     *
     * @return HasMany
     */
    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class, 'user_id');
    }

    /**
     * Create a new wallet for the user.
     *
     * @param array $attributes
     * @return Wallet
     */
    public function createWallet(array $attributes): Wallet
    {
        return $this->wallets()->create($attributes);
    }

    /**
     * Get a specific wallet by its slug.
     *
     * @param string $slug
     * @return Wallet
     */
    public function getWalletBySlug(string $slug): Wallet
    {
        $wallet = $this->wallets()->where('slug', $slug)->first();

        if (!$wallet) {
            throw new WalletNotFoundException("Wallet with slug '$slug' not found for this user.");
        }

        return $wallet;
    }

    /**
     * Get the wallet ledger service instance.
     *
     * @return WalletLedgerService
     */
    protected function ledger(): WalletLedgerService
    {
        return app(WalletLedgerService::class);
    }

    /**
     * Proxy for WalletLedgerService::deposit.
     *
     * @param string $walletSlug
     * @param Money|float|string $amount
     * @param string|null $reference
     * @param bool $autoApprove
     * @param array|null $meta
     * @return WalletTransaction
     * @throws Throwable
     */
    public function deposit(
        string             $walletSlug,
        Money|float|string $amount,
        ?string            $reference = null,
        bool               $autoApprove = true,
        ?array             $meta = null
    ): WalletTransaction
    {
        $wallet = $this->getWalletBySlug($walletSlug);
        $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);
        return $this->ledger()->deposit($wallet, $moneyAmount, $autoApprove, $reference, $meta);
    }

    /**
     * Proxy for WalletLedgerService::withdraw.
     *
     * @param string $walletSlug
     * @param Money|float|string $amount
     * @param string|null $reference
     * @param bool $autoApprove
     * @param array|null $meta
     * @return WalletTransaction
     * @throws Throwable
     */
    public function withdraw(
        string             $walletSlug,
        Money|float|string $amount,
        ?string            $reference = null,
        bool               $autoApprove = true,
        ?array             $meta = null
    ): WalletTransaction
    {
        $wallet = $this->getWalletBySlug($walletSlug);
        $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);
        return $this->ledger()->withdraw($wallet, $moneyAmount, $autoApprove, $reference, $meta);
    }

    /**
     * Proxy for WalletLedgerService::lock.
     *
     * @param string $walletSlug
     * @param Money|float|string $amount
     * @param string|null $reference
     * @param bool $autoApprove
     * @param array|null $meta
     * @return WalletTransaction
     * @throws Throwable
     */
    public function lock(
        string             $walletSlug,
        Money|float|string $amount,
        ?string            $reference = null,
        bool               $autoApprove = true,
        ?array             $meta = null
    ): WalletTransaction
    {
        $wallet = $this->getWalletBySlug($walletSlug);
        $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);
        return $this->ledger()->lock($wallet, $moneyAmount, $autoApprove, $reference, $meta);
    }

    /**
     * Proxy for WalletLedgerService::unlock.
     *
     * @param string $walletSlug
     * @param Money|float|string $amount
     * @param string|null $reference
     * @param bool $autoApprove
     * @param array|null $meta
     * @return WalletTransaction
     * @throws Throwable
     */
    public function unlock(
        string             $walletSlug,
        Money|float|string $amount,
        ?string            $reference = null,
        bool               $autoApprove = true,
        ?array             $meta = null
    ): WalletTransaction
    {
        $wallet = $this->getWalletBySlug($walletSlug);
        $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);
        return $this->ledger()->unlock($wallet, $moneyAmount, $autoApprove, $reference, $meta);
    }

    /**
     * Proxy for WalletLedgerService::grantCredit.
     *
     * @param string $walletSlug
     * @param Money|float|string $amount
     * @param string|null $reference
     * @param bool $autoApprove
     * @param array|null $meta
     * @return WalletTransaction
     * @throws Throwable
     */
    public function grantCredit(
        string             $walletSlug,
        Money|float|string $amount,
        ?string            $reference = null,
        bool               $autoApprove = true,
        ?array             $meta = null
    ): WalletTransaction
    {
        $wallet = $this->getWalletBySlug($walletSlug);
        $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);
        return $this->ledger()->grantCredit($wallet, $moneyAmount, $autoApprove, $reference, $meta);
    }

    /**
     * Proxy for WalletLedgerService::revokeCredit.
     *
     * @param string $walletSlug
     * @param Money|float|string $amount
     * @param string|null $reference
     * @param bool $autoApprove
     * @param array|null $meta
     * @return WalletTransaction
     * @throws Throwable
     */
    public function revokeCredit(
        string             $walletSlug,
        Money|float|string $amount,
        ?string            $reference = null,
        bool               $autoApprove = true,
        ?array             $meta = null
    ): WalletTransaction
    {
        $wallet = $this->getWalletBySlug($walletSlug);
        $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);
        return $this->ledger()->revokeCredit($wallet, $moneyAmount, $autoApprove, $reference, $meta);
    }

    /**
     * Get wallet summary for a specific wallet.
     *
     * @param string $walletSlug
     * @return array
     */
    public function getWalletSummary(string $walletSlug): array
    {
        $wallet = $this->getWalletBySlug($walletSlug);
        return $this->ledger()->getWalletSummary($wallet);
    }

    /**
     * Get wallet summary for a specific wallet.
     * @param string $walletSlug
     * @return object
     */
    public function getWallet(string $walletSlug): object
    {
        $wallet = $this->getWalletBySlug($walletSlug);
        return $this->ledger()->getWallet($wallet);
    }

    /**
     * Check if wallet has sufficient funds.
     *
     * @param string $walletSlug
     * @param Money|float|string $amount
     * @return bool
     */
    public function hasSufficientFunds(string $walletSlug, Money|float|string $amount): bool
    {
        $wallet = $this->getWalletBySlug($walletSlug);
        $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);
        return $wallet->hasSufficientFunds($moneyAmount);
    }

    /**
     * Transfer funds between wallets with automatic currency conversion.
     *
     * @param string $fromWalletSlug
     * @param string $toWalletSlug
     * @param Money|float|string $amount
     * @param bool $autoApprove
     * @param string|null $reference
     * @param array|null $meta
     * @return array
     * @throws Throwable
     */
    public function transfer(
        string             $fromWalletSlug,
        string             $toWalletSlug,
        Money|float|string $amount,
        bool               $autoApprove = true,
        ?string            $reference = null,
        ?array             $meta = null
    ): array
    {
        $fromWallet = $this->getWalletBySlug($fromWalletSlug);
        $toWallet = $this->getWalletBySlug($toWalletSlug);
        $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);

        return $this->ledger()->transfer($fromWallet, $toWallet, $moneyAmount, $autoApprove, $reference, $meta);
    }
}