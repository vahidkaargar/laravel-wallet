<?php

namespace vahidkaargar\LaravelWallet\Enums;

/**
 * Transaction status enumeration for wallet operations.
 *
 * @method static TransactionStatus PENDING()
 * @method static TransactionStatus APPROVED()
 * @method static TransactionStatus REJECTED()
 * @method static TransactionStatus REVERSED()
 */
enum TransactionStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case REVERSED = 'reversed';

    /**
     * Get all final statuses (non-pending).
     *
     * @return array<TransactionStatus>
     */
    public static function finalStatuses(): array
    {
        return [
            self::APPROVED,
            self::REJECTED,
            self::REVERSED,
        ];
    }

    /**
     * Check if this is a final status (non-pending).
     *
     * @return bool
     */
    public function isFinal(): bool
    {
        return in_array($this, self::finalStatuses());
    }

    /**
     * Check if this status allows reversal.
     *
     * @return bool
     */
    public function canBeReversed(): bool
    {
        return in_array($this, [
            self::APPROVED,
            self::PENDING,
        ]);
    }

    /**
     * Get human-readable label for the transaction status.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::REVERSED => 'Reversed',
        };
    }

    /**
     * Get Bootstrap class for status display.
     *
     * @return string
     */
    public function bootstrapClass(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::REVERSED => 'secondary',
        };
    }

    /**
     * Get Tailwind class for status display.
     *
     * @return string
     */
    public function tailwindClass(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::APPROVED => 'green',
            self::REJECTED => 'red',
            self::REVERSED => 'zinc',
        };
    }
}
