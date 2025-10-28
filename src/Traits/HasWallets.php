<?php

namespace vahidkaargar\LaravelWallet\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Throwable;
use vahidkaargar\LaravelWallet\Enums\TransactionStatus;
use vahidkaargar\LaravelWallet\Enums\TransactionType;
use vahidkaargar\LaravelWallet\Exceptions\WalletNotFoundException;
use vahidkaargar\LaravelWallet\Models\Wallet;
use vahidkaargar\LaravelWallet\Models\WalletTransaction;
use vahidkaargar\LaravelWallet\Services\LoggingService;
use vahidkaargar\LaravelWallet\Services\WalletLedgerService;
use vahidkaargar\LaravelWallet\ValueObjects\Money;

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
     * Get a wallet by slug or Wallet instance.
     * The returned wallet has access to formatted monetary fields:
     * - $wallet->formatted_balance
     * - $wallet->formatted_locked
     * - $wallet->formatted_credit_limit
     * - $wallet->available_balance (Money object)
     * - $wallet->available_funds (Money object)
     * - $wallet->debt (Money object)
     * - $wallet->remaining_credit (Money object)
     *
     * @param string|Wallet $walletOrSlug
     * @return Wallet
     * @throws WalletNotFoundException
     */
    public function getWallet(string|Wallet $walletOrSlug): Wallet
    {
        try {
            // If it's already a Wallet model, use it directly
            if ($walletOrSlug instanceof Wallet) {
                return $walletOrSlug;
            }

            // Otherwise, find by slug
            $wallet = $this->wallets()->where('slug', $walletOrSlug)->first();
            
            if (!$wallet) {
                throw new WalletNotFoundException("Wallet with slug '$walletOrSlug' not found for this user.");
            }

            return $wallet;
        } catch (\Exception $e) {
            $this->logger()->logError('Failed to get wallet', [
                'user_id' => $this->id ?? 'unknown',
                'wallet_slug' => is_string($walletOrSlug) ? $walletOrSlug : $walletOrSlug->slug ?? 'unknown',
            ], $e);
            
            // Re-throw the original exception to preserve type information
            throw $e;
        }
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
     * Get the logging service instance.
     *
     * @return LoggingService
     */
    protected function logger(): LoggingService
    {
        return app(LoggingService::class);
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
        try {
            $wallet = $this->getWallet($walletSlug);
            $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);
            return $this->ledger()->deposit($wallet, $moneyAmount, $autoApprove, $reference, $meta);
        } catch (\Exception $e) {
            $this->logger()->logError('Failed to deposit funds', [
                'user_id' => $this->id ?? 'unknown',
                'wallet_slug' => $walletSlug,
                'amount' => is_numeric($amount) ? $amount : 'non-numeric',
                'reference' => $reference,
                'auto_approve' => $autoApprove,
                'meta' => $meta,
            ], $e);
            
            // Re-throw the original exception to preserve type information
            throw $e;
        }
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
        try {
            $wallet = $this->getWallet($walletSlug);
            $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);
            return $this->ledger()->withdraw($wallet, $moneyAmount, $autoApprove, $reference, $meta);
        } catch (\Exception $e) {
            $this->logger()->logError('Failed to withdraw funds', [
                'user_id' => $this->id ?? 'unknown',
                'wallet_slug' => $walletSlug,
                'amount' => is_numeric($amount) ? $amount : 'non-numeric',
                'reference' => $reference,
                'auto_approve' => $autoApprove,
                'meta' => $meta,
            ], $e);
            
            // Re-throw the original exception to preserve type information
            throw $e;
        }
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
        try {
            $wallet = $this->getWallet($walletSlug);
            $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);
            return $this->ledger()->lock($wallet, $moneyAmount, $autoApprove, $reference, $meta);
        } catch (\Exception $e) {
            $this->logger()->logError('Failed to lock funds', [
                'user_id' => $this->id ?? 'unknown',
                'wallet_slug' => $walletSlug,
                'amount' => is_numeric($amount) ? $amount : 'non-numeric',
                'reference' => $reference,
                'auto_approve' => $autoApprove,
                'meta' => $meta,
            ], $e);
            
            // Re-throw the original exception to preserve type information
            throw $e;
        }
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
        try {
            $wallet = $this->getWallet($walletSlug);
            $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);
            return $this->ledger()->unlock($wallet, $moneyAmount, $autoApprove, $reference, $meta);
        } catch (\Exception $e) {
            $this->logger()->logError('Failed to unlock funds', [
                'user_id' => $this->id ?? 'unknown',
                'wallet_slug' => $walletSlug,
                'amount' => is_numeric($amount) ? $amount : 'non-numeric',
                'reference' => $reference,
                'auto_approve' => $autoApprove,
                'meta' => $meta,
            ], $e);
            
            // Re-throw the original exception to preserve type information
            throw $e;
        }
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
        try {
            $wallet = $this->getWallet($walletSlug);
            $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);
            return $this->ledger()->grantCredit($wallet, $moneyAmount, $autoApprove, $reference, $meta);
        } catch (\Exception $e) {
            $this->logger()->logError('Failed to grant credit', [
                'user_id' => $this->id ?? 'unknown',
                'wallet_slug' => $walletSlug,
                'amount' => is_numeric($amount) ? $amount : 'non-numeric',
                'reference' => $reference,
                'auto_approve' => $autoApprove,
                'meta' => $meta,
            ], $e);
            
            // Re-throw the original exception to preserve type information
            throw $e;
        }
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
        try {
            $wallet = $this->getWallet($walletSlug);
            $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);
            return $this->ledger()->revokeCredit($wallet, $moneyAmount, $autoApprove, $reference, $meta);
        } catch (\Exception $e) {
            $this->logger()->logError('Failed to revoke credit', [
                'user_id' => $this->id ?? 'unknown',
                'wallet_slug' => $walletSlug,
                'amount' => is_numeric($amount) ? $amount : 'non-numeric',
                'reference' => $reference,
                'auto_approve' => $autoApprove,
                'meta' => $meta,
            ], $e);
            
            // Re-throw the original exception to preserve type information
            throw $e;
        }
    }


    /**
     * Proxy for WalletLedgerService::chargeInterest.
     *
     * @param string $walletSlug
     * @param Money|float|string $amount
     * @param bool $autoApprove
     * @param string|null $reference
     * @param array|null $meta
     * @return WalletTransaction
     * @throws Throwable
     */
    public function chargeInterest(
        string             $walletSlug,
        Money|float|string $amount,
        bool               $autoApprove = true,
        ?string            $reference = null,
        ?array             $meta = null
    ): WalletTransaction
    {
        try {
            $wallet = $this->getWallet($walletSlug);
            $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);
            return $this->ledger()->chargeInterest($wallet, $moneyAmount, $autoApprove, $reference, $meta);
        } catch (\Exception $e) {
            $this->logger()->logError('Failed to charge interest', [
                'user_id' => $this->id ?? 'unknown',
                'wallet_slug' => $walletSlug,
                'amount' => is_numeric($amount) ? $amount : 'non-numeric',
                'reference' => $reference,
                'auto_approve' => $autoApprove,
                'meta' => $meta,
            ], $e);
            
            // Re-throw the original exception to preserve type information
            throw $e;
        }
    }

    /**
     * Get wallet transactions with optional filters.
     *
     * @param string|Wallet $walletOrSlug
     * @param TransactionType|null $type
     * @param TransactionStatus|null $status
     * @param Carbon|null $fromDate
     * @param Carbon|null $toDate
     * @param int|null $limit
     * @param int $offset
     * @return \Illuminate\Database\Eloquent\Collection|WalletTransaction[]
     */
    public function getWalletTransactions(
        string|Wallet $walletOrSlug,
        ?TransactionType $type = null,
        ?TransactionStatus $status = null,
        ?Carbon $fromDate = null,
        ?Carbon $toDate = null,
        ?int $limit = null,
        int $offset = 0
    ): \Illuminate\Database\Eloquent\Collection {
        try {
            $wallet = $this->getWallet($walletOrSlug);
            return $wallet->getTransactions($type, $status, $fromDate, $toDate, $limit, $offset);
        } catch (\Exception $e) {
            $this->logger()->logError('Failed to get wallet transactions via trait', [
                'user_id' => $this->id ?? 'unknown',
                'wallet_slug' => is_string($walletOrSlug) ? $walletOrSlug : $walletOrSlug->slug ?? 'unknown',
                'type' => $type?->value,
                'status' => $status?->value,
                'from_date' => $fromDate?->toDateTimeString(),
                'to_date' => $toDate?->toDateTimeString(),
                'limit' => $limit,
                'offset' => $offset,
            ], $e);
            
            throw new \RuntimeException('Failed to retrieve wallet transactions: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get wallet transactions paginated with optional filters.
     *
     * @param string|Wallet $walletOrSlug
     * @param TransactionType|null $type
     * @param TransactionStatus|null $status
     * @param Carbon|null $fromDate
     * @param Carbon|null $toDate
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getWalletTransactionsPaginated(
        string|Wallet $walletOrSlug,
        ?TransactionType $type = null,
        ?TransactionStatus $status = null,
        ?Carbon $fromDate = null,
        ?Carbon $toDate = null,
        int $perPage = 15
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        try {
            $wallet = $this->getWallet($walletOrSlug);
            return $wallet->getTransactionsPaginated($type, $status, $fromDate, $toDate, $perPage);
        } catch (\Exception $e) {
            $this->logger()->logError('Failed to get paginated wallet transactions via trait', [
                'user_id' => $this->id ?? 'unknown',
                'wallet_slug' => is_string($walletOrSlug) ? $walletOrSlug : $walletOrSlug->slug ?? 'unknown',
                'type' => $type?->value,
                'status' => $status?->value,
                'from_date' => $fromDate?->toDateTimeString(),
                'to_date' => $toDate?->toDateTimeString(),
                'per_page' => $perPage,
            ], $e);
            
            throw new \RuntimeException('Failed to retrieve paginated wallet transactions: ' . $e->getMessage(), 0, $e);
        }
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
        try {
            $fromWallet = $this->getWallet($fromWalletSlug);
            $toWallet = $this->getWallet($toWalletSlug);
            $moneyAmount = $amount instanceof Money ? $amount : Money::fromDecimal($amount);

            return $this->ledger()->transfer($fromWallet, $toWallet, $moneyAmount, $autoApprove, $reference, $meta);
        } catch (\Exception $e) {
            $this->logger()->logError('Failed to transfer funds', [
                'user_id' => $this->id ?? 'unknown',
                'from_wallet_slug' => $fromWalletSlug,
                'to_wallet_slug' => $toWalletSlug,
                'amount' => is_numeric($amount) ? $amount : 'non-numeric',
                'reference' => $reference,
                'auto_approve' => $autoApprove,
                'meta' => $meta,
            ], $e);
            
            // Re-throw the original exception to preserve type information
            throw $e;
        }
    }
}