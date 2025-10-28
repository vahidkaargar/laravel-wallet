<?php

namespace vahidkaargar\LaravelWallet\ValueObjects;

use InvalidArgumentException;

/**
 * Immutable Money value object for precise financial calculations.
 * Uses integer cents internally to avoid floating point precision issues.
 */
final class Money
{
    private const PRECISION = 2;
    private const SCALE = 100; // 10^PRECISION

    private readonly int $cents;

    public function __construct(int $cents)
    {
        $this->cents = $cents;
    }

    /**
     * Create Money from a decimal amount (e.g., 12.34).
     *
     * @param float|string $amount
     * @return self
     */
    public static function fromDecimal(float|string $amount): self
    {
        $amount = (string)$amount;

        if (!is_numeric($amount)) {
            throw new InvalidArgumentException("Invalid amount: $amount");
        }

        // Convert to cents with proper rounding
        $cents = (int)round((float)$amount * self::SCALE);

        return new self($cents);
    }

    /**
     * Create Money from cents (e.g., 1234 cents = $12.34).
     *
     * @param int $cents
     * @return Money
     */
    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    /**
     * Get the amount as decimal (e.g., 12.34).
     *
     * @return float
     */
    public function toDecimal(): float
    {
        return round($this->cents / self::SCALE, self::PRECISION);
    }

    /**
     * Get the amount in cents.
     *
     * @return int
     */
    public function toCents(): int
    {
        return $this->cents;
    }

    /**
     * Add another Money amount.
     *
     * @param Money $other
     * @return self
     */
    public function add(Money $other): self
    {
        return new self($this->cents + $other->cents);
    }

    /**
     * Subtract another Money amount.
     *
     * @param Money $other
     * @return self
     */
    public function subtract(Money $other): self
    {
        return new self($this->cents - $other->cents);
    }

    /**
     * Multiply by a factor.
     *
     * @param float $factor
     * @return Money
     */
    public function multiply(float $factor): self
    {
        $result = (int)round($this->cents * $factor);
        return new self($result);
    }

    /**
     * Divide by a factor.
     *
     * @param float $divisor
     * @return Money
     */
    public function divide(float $divisor): self
    {
        if ($divisor == 0) {
            throw new InvalidArgumentException('Cannot divide by zero');
        }

        $result = (int)round($this->cents / $divisor);
        return new self($result);
    }

    /**
     * Check if this amount is greater than another.
     *
     * @param Money $other
     * @return bool
     */
    public function greaterThan(Money $other): bool
    {
        return $this->cents > $other->cents;
    }

    /**
     * Check if this amount is greater than or equal to another.
     *
     * @param Money $other
     * @return bool
     */
    public function greaterThanOrEqual(Money $other): bool
    {
        return $this->cents >= $other->cents;
    }

    /**
     * Check if this amount is less than another.
     *
     * @param Money $other
     * @return bool
     */
    public function lessThan(Money $other): bool
    {
        return $this->cents < $other->cents;
    }

    /**
     * Check if this amount is less than or equal to another.
     *
     * @param Money $other
     * @return bool
     */
    public function lessThanOrEqual(Money $other): bool
    {
        return $this->cents <= $other->cents;
    }

    /**
     * Check if this amount equals another.
     *
     * @param Money $other
     * @return bool
     */
    public function equals(Money $other): bool
    {
        return $this->cents === $other->cents;
    }

    /**
     * Check if this amount is zero.
     *
     * @return bool
     */
    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    /**
     * Check if this amount is positive.
     *
     * @return bool
     */
    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    /**
     * Check if this amount is negative.
     *
     * @return bool
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
        return new self(abs($this->cents));
    }

    /**
     * Get the negative value.
     */
    public function negate(): self
    {
        return new self(-$this->cents);
    }

    /**
     * String representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return number_format($this->toDecimal(), self::PRECISION, '.', '');
    }

    /**
     * JSON serialization.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->toDecimal(),
            'cents' => $this->cents,
        ];
    }
}
