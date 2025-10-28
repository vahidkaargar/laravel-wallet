<?php

namespace vahidkaargar\LaravelWallet\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use vahidkaargar\LaravelWallet\Enums\TransactionStatus;
use vahidkaargar\LaravelWallet\Enums\TransactionType;
use vahidkaargar\LaravelWallet\Services\LoggingService;
use vahidkaargar\LaravelWallet\ValueObjects\Money;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $slug
 * @property string $description
 * @property string $currency
 * @property float $credit
 * @property float $locked
 * @property float $balance
 * @property bool $is_active
 * @property-read float $formatted_balance
 * @property-read float $formatted_locked
 * @property-read float $formatted_credit_limit
 * @property-read Money $available_balance
 * @property-read Money $available_funds
 * @property-read Money $debt
 * @property-read Money $remaining_credit
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Collection|WalletTransaction[] $transactions
 */
class Wallet extends Model
{
    use HasFactory;

    protected $table = 'wallets';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'balance' => 'decimal:2',
        'credit' => 'decimal:2',
        'locked' => 'decimal:2',
    ];

    /**
     * The user who owns this wallet.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
     * All transactions for this wallet.
     *
     * @return HasMany
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Get transactions with optional filters.
     *
     * @param TransactionType|null $type
     * @param TransactionStatus|null $status
     * @param Carbon|null $fromDate
     * @param Carbon|null $toDate
     * @param int|null $limit
     * @param int $offset
     * @return \Illuminate\Database\Eloquent\Collection|WalletTransaction[]
     */
    public function getTransactions(
        ?TransactionType $type = null,
        ?TransactionStatus $status = null,
        ?Carbon $fromDate = null,
        ?Carbon $toDate = null,
        ?int $limit = null,
        int $offset = 0
    ): \Illuminate\Database\Eloquent\Collection {
        try {
            $query = $this->transactions();

            if ($type) {
                $query->where('type', $type->value);
            }

            if ($status) {
                $query->where('status', $status->value);
            }

            if ($fromDate) {
                $query->where('created_at', '>=', $fromDate);
            }

            if ($toDate) {
                $query->where('created_at', '<=', $toDate);
            }

            $query->orderBy('created_at', 'desc');

            if ($limit) {
                $query->limit($limit)->offset($offset);
            }

            return $query->get();
        } catch (\Exception $e) {
            $this->logger()->logError('Failed to get wallet transactions', [
                'wallet_id' => $this->id,
                'type' => $type?->value,
                'status' => $status?->value,
                'from_date' => $fromDate?->toDateTimeString(),
                'to_date' => $toDate?->toDateTimeString(),
                'limit' => $limit,
                'offset' => $offset,
            ], $e);
            
            // Re-throw the original exception to preserve type information
            throw $e;
        }
    }

    /**
     * Get transactions paginated with optional filters.
     *
     * @param TransactionType|null $type
     * @param TransactionStatus|null $status
     * @param Carbon|null $fromDate
     * @param Carbon|null $toDate
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getTransactionsPaginated(
        ?TransactionType $type = null,
        ?TransactionStatus $status = null,
        ?Carbon $fromDate = null,
        ?Carbon $toDate = null,
        int $perPage = 15
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        try {
            $query = $this->transactions();

            if ($type) {
                $query->where('type', $type->value);
            }

            if ($status) {
                $query->where('status', $status->value);
            }

            if ($fromDate) {
                $query->where('created_at', '>=', $fromDate);
            }

            if ($toDate) {
                $query->where('created_at', '<=', $toDate);
            }

            return $query->orderBy('created_at', 'desc')->paginate($perPage);
        } catch (\Exception $e) {
            $this->logger()->logError('Failed to get paginated wallet transactions', [
                'wallet_id' => $this->id,
                'type' => $type?->value,
                'status' => $status?->value,
                'from_date' => $fromDate?->toDateTimeString(),
                'to_date' => $toDate?->toDateTimeString(),
                'per_page' => $perPage,
            ], $e);
            
            // Re-throw the original exception to preserve type information
            throw $e;
        }
    }

    /**
     * Accessor for the "available" balance (balance - locked).
     * This does *not* include credit.
     *
     * @return Attribute
     */
    protected function availableBalance(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                $balance = Money::fromDecimal($attributes['balance']);
                $locked = Money::fromDecimal($attributes['locked']);
                $available = $balance->subtract($locked);
                return $available->isNegative() ? Money::fromCents(0) : $available;
            }
        );
    }

    /**
     * Accessor for total available funds (balance + credit - locked).
     *
     * @return Attribute
     */
    protected function availableFunds(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                $balance = Money::fromDecimal($attributes['balance']);
                $locked = Money::fromDecimal($attributes['locked']);
                $credit = Money::fromDecimal($attributes['credit']);

                // Available balance (can be negative)
                $availableBalance = $balance->subtract($locked);

                // If balance is negative, we're already using credit
                if ($availableBalance->isNegative()) {
                    $debt = $availableBalance->abs();
                    $remainingCredit = $credit->subtract($debt);
                    return $remainingCredit->isPositive() ? $remainingCredit : Money::fromCents(0);
                }

                // If balance is positive, add full credit
                return $availableBalance->add($credit);
            }
        );
    }

    /**
     * Accessor for current debt (negative balance).
     *
     * @return Attribute
     */
    protected function debt(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                $balance = Money::fromDecimal($attributes['balance']);
                return $balance->isNegative() ? $balance->abs() : Money::fromCents(0);
            }
        );
    }

    /**
     * Check if wallet has sufficient funds for a given amount.
     *
     * @param Money $amount
     * @return bool
     */
    public function hasSufficientFunds(Money $amount): bool
    {
        return $this->available_funds->greaterThanOrEqual($amount);
    }

    /**
     * Check if wallet has sufficient unlocked balance for a given amount.
     *
     * @param Money $amount
     * @return bool
     */
    public function hasSufficientUnlockedBalance(Money $amount): bool
    {
        return $this->available_balance->greaterThanOrEqual($amount);
    }

    /**
     * Get the remaining credit available.
     *
     * @return Money
     */
    public function getRemainingCredit(): Money
    {
        $credit = Money::fromDecimal($this->credit);
        $debt = $this->debt;

        $remaining = $credit->subtract($debt);
        return $remaining->isPositive() ? $remaining : Money::fromCents(0);
    }

    /**
     * Accessor for formatted balance.
     *
     * @return Attribute
     */
    protected function formattedBalance(): Attribute
    {
        return Attribute::make(
            get: fn() => Money::fromDecimal($this->balance)->toDecimal()
        );
    }

    /**
     * Accessor for formatted locked amount.
     *
     * @return Attribute
     */
    protected function formattedLocked(): Attribute
    {
        return Attribute::make(
            get: fn() => Money::fromDecimal($this->locked)->toDecimal()
        );
    }

    /**
     * Accessor for formatted credit limit.
     *
     * @return Attribute
     */
    protected function formattedCreditLimit(): Attribute
    {
        return Attribute::make(
            get: fn() => Money::fromDecimal($this->credit)->toDecimal()
        );
    }

    /**
     * Accessor for remaining credit as Money.
     *
     * @return Attribute
     */
    protected function remainingCredit(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->getRemainingCredit()
        );
    }
}