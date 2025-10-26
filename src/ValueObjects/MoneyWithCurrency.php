<?php

namespace vahidkaargar\LaravelWallet\ValueObjects;

use InvalidArgumentException;

/**
 * Money value object with currency information for multi-currency support.
 */
final class MoneyWithCurrency
{
    private const PRECISION = 2;
    private const SCALE = 100; // 10^PRECISION

    private readonly int $cents;
    private readonly string $currency;

    public function __construct(int $cents, string $currency)
    {
        $this->cents = $cents;
        $this->currency = strtoupper($currency);
    }

    /**
     * Create MoneyWithCurrency from a decimal amount and currency.
     */
    public static function fromDecimal(float|string $amount, string $currency): self
    {
        $amount = (string) $amount;
        
        if (!is_numeric($amount)) {
            throw new InvalidArgumentException("Invalid amount: {$amount}");
        }

        // Convert to cents with proper rounding
        $cents = (int) round((float) $amount * self::SCALE);
        
        return new self($cents, $currency);
    }

    /**
     * Create MoneyWithCurrency from cents and currency.
     */
    public static function fromCents(int $cents, string $currency): self
    {
        return new self($cents, $currency);
    }

    /**
     * Get the amount as decimal.
     */
    public function toDecimal(): float
    {
        return round($this->cents / self::SCALE, self::PRECISION);
    }

    /**
     * Get the amount in cents.
     */
    public function toCents(): int
    {
        return $this->cents;
    }

    /**
     * Get the currency code.
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Convert to Money object (loses currency information).
     */
    public function toMoney(): Money
    {
        return Money::fromCents($this->cents);
    }

    /**
     * Add another MoneyWithCurrency amount (must be same currency).
     */
    public function add(MoneyWithCurrency $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Cannot add different currencies: {$this->currency} and {$other->currency}");
        }

        return new self($this->cents + $other->cents, $this->currency);
    }

    /**
     * Subtract another MoneyWithCurrency amount (must be same currency).
     */
    public function subtract(MoneyWithCurrency $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Cannot subtract different currencies: {$this->currency} and {$other->currency}");
        }

        return new self($this->cents - $other->cents, $this->currency);
    }

    /**
     * Multiply by a factor.
     */
    public function multiply(float $factor): self
    {
        $result = (int) round($this->cents * $factor);
        return new self($result, $this->currency);
    }

    /**
     * Divide by a factor.
     */
    public function divide(float $divisor): self
    {
        if ($divisor == 0) {
            throw new InvalidArgumentException('Cannot divide by zero');
        }
        
        $result = (int) round($this->cents / $divisor);
        return new self($result, $this->currency);
    }

    /**
     * Check if this amount is greater than another (must be same currency).
     */
    public function greaterThan(MoneyWithCurrency $other): bool
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Cannot compare different currencies: {$this->currency} and {$other->currency}");
        }

        return $this->cents > $other->cents;
    }

    /**
     * Check if this amount is greater than or equal to another (must be same currency).
     */
    public function greaterThanOrEqual(MoneyWithCurrency $other): bool
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Cannot compare different currencies: {$this->currency} and {$other->currency}");
        }

        return $this->cents >= $other->cents;
    }

    /**
     * Check if this amount is less than another (must be same currency).
     */
    public function lessThan(MoneyWithCurrency $other): bool
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Cannot compare different currencies: {$this->currency} and {$other->currency}");
        }

        return $this->cents < $other->cents;
    }

    /**
     * Check if this amount is less than or equal to another (must be same currency).
     */
    public function lessThanOrEqual(MoneyWithCurrency $other): bool
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Cannot compare different currencies: {$this->currency} and {$other->currency}");
        }

        return $this->cents <= $other->cents;
    }

    /**
     * Check if this amount equals another (must be same currency).
     */
    public function equals(MoneyWithCurrency $other): bool
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Cannot compare different currencies: {$this->currency} and {$other->currency}");
        }

        return $this->cents === $other->cents;
    }

    /**
     * Check if this amount is zero.
     */
    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    /**
     * Check if this amount is positive.
     */
    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    /**
     * Check if this amount is negative.
     */
    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    /**
     * Get the absolute value.
     */
    public function abs(): self
    {
        return new self(abs($this->cents), $this->currency);
    }

    /**
     * Get the negative value.
     */
    public function negate(): self
    {
        return new self(-$this->cents, $this->currency);
    }

    /**
     * String representation.
     */
    public function __toString(): string
    {
        return number_format($this->toDecimal(), self::PRECISION, '.', '') . ' ' . $this->currency;
    }

    /**
     * JSON serialization.
     */
    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->toDecimal(),
            'cents' => $this->cents,
            'currency' => $this->currency,
        ];
    }
}
