<?php

namespace vahidkaargar\LaravelWallet\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use vahidkaargar\LaravelWallet\Enums\TransactionStatus;
use vahidkaargar\LaravelWallet\Enums\TransactionType;
use vahidkaargar\LaravelWallet\ValueObjects\Money;

/**
 * @property string $id
 * @property int $wallet_id
 * @property TransactionType $type
 * @property float $amount
 * @property string $description
 * @property string|null $reference
 * @property array|null $meta
 * @property TransactionStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Wallet $wallet
 * @property-read Money $money_amount
 */
class WalletTransaction extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'wallet_transactions';

    protected $guarded = ['id'];
    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'array',
        'type' => TransactionType::class,
        'status' => TransactionStatus::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->description)) {
                $amount = number_format((float)$model->amount, 2, '.', '');
                $suffix = $model->reference ? ' (ref: ' . $model->reference . ')' : '';
                $model->description = sprintf('%s of %s%s', $model->type->label(), $amount, $suffix);
            }
        });
    }


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
        return $this->status === TransactionStatus::PENDING;
    }

    /**
     * Check if transaction is approved.
     *
     * @return bool
     */
    public function isApproved(): bool
    {
        return $this->status === TransactionStatus::APPROVED;
    }

    /**
     * Check if transaction is rejected.
     *
     * @return bool
     */
    public function isRejected(): bool
    {
        return $this->status === TransactionStatus::REJECTED;
    }

    /**
     * Check if transaction is reversed.
     *
     * @return bool
     */
    public function isReversed(): bool
    {
        return $this->status === TransactionStatus::REVERSED;
    }

    /**
     * Check if transaction is a deposit type.
     *
     * @return bool
     */
    public function isDeposit(): bool
    {
        return $this->type === TransactionType::DEPOSIT;
    }

    /**
     * Check if transaction is a withdrawal type.
     *
     * @return bool
     */
    public function isWithdrawal(): bool
    {
        return $this->type === TransactionType::WITHDRAW;
    }

    /**
     * Check if transaction affects balance positively.
     *
     * @return bool
     */
    public function increasesBalance(): bool
    {
        return $this->type->increasesBalance();
    }

    /**
     * Check if transaction affects balance negatively.
     *
     * @return bool
     */
    public function decreasesBalance(): bool
    {
        return $this->type->decreasesBalance();
    }
}