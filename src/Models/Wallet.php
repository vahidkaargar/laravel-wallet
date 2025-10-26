<?php

namespace vahidkaargar\LaravelWallet\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property-read Money $available_balance
 * @property-read Money $available_funds
 * @property-read Money $debt
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection|WalletTransaction[] $transactions
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
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * All transactions for this wallet.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Accessor for the "available" balance (balance - locked).
     * This does *not* include credit.
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
     */
    public function hasSufficientFunds(Money $amount): bool
    {
        return $this->available_funds->greaterThanOrEqual($amount);
    }

    /**
     * Check if wallet has sufficient unlocked balance for a given amount.
     */
    public function hasSufficientUnlockedBalance(Money $amount): bool
    {
        return $this->available_balance->greaterThanOrEqual($amount);
    }

    /**
     * Get the remaining credit available.
     */
    public function getRemainingCredit(): Money
    {
        $credit = Money::fromDecimal($this->credit);
        $debt = $this->debt;
        
        $remaining = $credit->subtract($debt);
        return $remaining->isPositive() ? $remaining : Money::fromCents(0);
    }
}