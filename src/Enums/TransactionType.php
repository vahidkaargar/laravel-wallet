<?php

namespace vahidkaargar\LaravelWallet\Enums;

/**
 * Transaction type enumeration for wallet operations.
 *
 * @method static TransactionType DEPOSIT()
 * @method static TransactionType WITHDRAW()
 * @method static TransactionType LOCK()
 * @method static TransactionType UNLOCK()
 * @method static TransactionType CREDIT_GRANT()
 * @method static TransactionType CREDIT_REVOKE()
 * @method static TransactionType CREDIT_REPAY()
 * @method static TransactionType INTEREST_CHARGE()
 */
enum TransactionType: string
{
    case DEPOSIT = 'deposit';
    case WITHDRAW = 'withdraw';
    case LOCK = 'lock';
    case UNLOCK = 'unlock';
    case CREDIT_GRANT = 'credit_grant';
    case CREDIT_REVOKE = 'credit_revoke';
    case CREDIT_REPAY = 'credit_repay';
    case INTEREST_CHARGE = 'interest_charge';

    /**
     * Get all transaction types that increase wallet balance.
     *
     * @return array<TransactionType>
     */
    public static function balanceIncreasing(): array
    {
        return [
            self::DEPOSIT,
            self::UNLOCK,
            self::CREDIT_REPAY,
        ];
    }

    /**
     * Get all transaction types that decrease wallet balance.
     *
     * @return array<TransactionType>
     */
    public static function balanceDecreasing(): array
    {
        return [
            self::WITHDRAW,
            self::LOCK,
            self::INTEREST_CHARGE,
        ];
    }

    /**
     * Check if this transaction type increases balance.
     *
     * @return bool
     */
    public function increasesBalance(): bool
    {
        return in_array($this, self::balanceIncreasing());
    }

    /**
     * Check if this transaction type decreases balance.
     *
     * @return bool
     */
    public function decreasesBalance(): bool
    {
        return in_array($this, self::balanceDecreasing());
    }

    /**
     * Check if this is a credit-related transaction.
     *
     * @return bool
     */
    public function isCreditRelated(): bool
    {
        return in_array($this, [
            self::CREDIT_GRANT,
            self::CREDIT_REVOKE,
            self::CREDIT_REPAY,
        ]);
    }

    /**
     * Get human-readable label for the transaction type.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::DEPOSIT => 'Deposit',
            self::WITHDRAW => 'Withdrawal',
            self::LOCK => 'Lock Funds',
            self::UNLOCK => 'Unlock Funds',
            self::CREDIT_GRANT => 'Credit Grant',
            self::CREDIT_REVOKE => 'Credit Revoke',
            self::CREDIT_REPAY => 'Credit Repayment',
            self::INTEREST_CHARGE => 'Interest Charge',
        };
    }
}
