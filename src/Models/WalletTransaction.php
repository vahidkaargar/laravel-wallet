<?php

namespace vahidkaargar\LaravelWallet\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use vahidkaargar\LaravelWallet\ValueObjects\Money;

/**
 * @property int $id
 * @property int $wallet_id
 * @property string $type
 * @property float $amount
 * @property string|null $reference
 * @property array|null $meta
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Wallet $wallet
 * @property-read Money $money_amount
 */
class WalletTransaction extends Model
{
    use HasFactory;

    protected $table = 'wallet_transactions';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'json',
    ];

    // Transaction Types
    public const TYPE_DEPOSIT = 'deposit';
    public const TYPE_WITHDRAW = 'withdraw';
    public const TYPE_LOCK = 'lock';
    public const TYPE_UNLOCK = 'unlock';
    public const TYPE_CREDIT_GRANT = 'credit_grant';
    public const TYPE_CREDIT_REVOKE = 'credit_revoke';
    public const TYPE_CREDIT_REPAY = 'credit_repay';
    public const TYPE_INTEREST_CHARGE = 'interest_charge';

    // Transaction Statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REVERSED = 'reversed';

    /**
     * The wallet this transaction belongs to.
     *
     * @return BelongsTo
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Accessor for amount as Money object.
     *
     * @return Attribute
     */
    protected function moneyAmount(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => Money::fromDecimal($attributes['amount'])
        );
    }

    /**
     * Check if transaction is pending.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if transaction is approved.
     *
     * @return bool
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if transaction is rejected.
     *
     * @return bool
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if transaction is reversed.
     *
     * @return bool
     */
    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }

    /**
     * Check if transaction is a deposit type.
     *
     * @return bool
     */
    public function isDeposit(): bool
    {
        return $this->type === self::TYPE_DEPOSIT;
    }

    /**
     * Check if transaction is a withdrawal type.
     *
     * @return bool
     */
    public function isWithdrawal(): bool
    {
        return $this->type === self::TYPE_WITHDRAW;
    }

    /**
     * Check if transaction affects balance positively.
     *
     * @return bool
     */
    public function increasesBalance(): bool
    {
        return in_array($this->type, [
            self::TYPE_DEPOSIT,
            self::TYPE_UNLOCK,
            self::TYPE_CREDIT_REPAY,
        ]);
    }

    /**
     * Check if transaction affects balance negatively.
     *
     * @return bool
     */
    public function decreasesBalance(): bool
    {
        return in_array($this->type, [
            self::TYPE_WITHDRAW,
            self::TYPE_LOCK,
            self::TYPE_INTEREST_CHARGE,
        ]);
    }
}