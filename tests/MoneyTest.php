<?php

namespace vahidkaargar\LaravelWallet\Tests;

use vahidkaargar\LaravelWallet\ValueObjects\Money;
use InvalidArgumentException;

class MoneyTest extends TestCase
{
    public function test_can_create_money_from_decimal()
    {
        $money = Money::fromDecimal(12.34);
        
        $this->assertEquals(1234, $money->toCents());
        $this->assertEquals(12.34, $money->toDecimal());
    }

    public function test_can_create_money_from_cents()
    {
        $money = Money::fromCents(1234);
        
        $this->assertEquals(1234, $money->toCents());
        $this->assertEquals(12.34, $money->toDecimal());
    }

    public function test_handles_negative_amounts()
    {
        $money = Money::fromDecimal(-12.34);
        
        $this->assertEquals(-1234, $money->toCents());
        $this->assertEquals(-12.34, $money->toDecimal());
        $this->assertTrue($money->isNegative());
    }

    public function test_handles_zero_amount()
    {
        $money = Money::fromDecimal(0);
        
        $this->assertEquals(0, $money->toCents());
        $this->assertEquals(0.0, $money->toDecimal());
        $this->assertTrue($money->isZero());
    }

    public function test_rounds_decimal_input_correctly()
    {
        $money = Money::fromDecimal(12.345);
        
        $this->assertEquals(1235, $money->toCents()); // Rounds to 12.35
        $this->assertEquals(12.35, $money->toDecimal());
    }

    public function test_handles_large_amounts()
    {
        $money = Money::fromDecimal(999999.99);
        
        $this->assertEquals(99999999, $money->toCents());
        $this->assertEquals(999999.99, $money->toDecimal());
    }

    public function test_addition()
    {
        $money1 = Money::fromDecimal(10.50);
        $money2 = Money::fromDecimal(5.25);
        
        $result = $money1->add($money2);
        
        $this->assertEquals(15.75, $result->toDecimal());
    }

    public function test_subtraction()
    {
        $money1 = Money::fromDecimal(10.50);
        $money2 = Money::fromDecimal(5.25);
        
        $result = $money1->subtract($money2);
        
        $this->assertEquals(5.25, $result->toDecimal());
    }

    public function test_multiplication()
    {
        $money = Money::fromDecimal(10.50);
        
        $result = $money->multiply(2);
        
        $this->assertEquals(21.00, $result->toDecimal());
    }

    public function test_division()
    {
        $money = Money::fromDecimal(10.50);
        
        $result = $money->divide(2);
        
        $this->assertEquals(5.25, $result->toDecimal());
    }

    public function test_division_by_zero_throws_exception()
    {
        $money = Money::fromDecimal(10.50);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot divide by zero');
        
        $money->divide(0);
    }

    public function test_comparison_methods()
    {
        $money1 = Money::fromDecimal(10.50);
        $money2 = Money::fromDecimal(5.25);
        $money3 = Money::fromDecimal(10.50);
        
        $this->assertTrue($money1->greaterThan($money2));
        $this->assertFalse($money2->greaterThan($money1));
        $this->assertTrue($money1->greaterThanOrEqual($money3));
        $this->assertTrue($money1->equals($money3));
        $this->assertFalse($money1->equals($money2));
    }

    public function test_absolute_value()
    {
        $money = Money::fromDecimal(-12.34);
        
        $abs = $money->abs();
        
        $this->assertEquals(12.34, $abs->toDecimal());
        $this->assertTrue($abs->isPositive());
    }

    public function test_negation()
    {
        $money = Money::fromDecimal(12.34);
        
        $negated = $money->negate();
        
        $this->assertEquals(-12.34, $negated->toDecimal());
        $this->assertTrue($negated->isNegative());
    }

    public function test_string_representation()
    {
        $money = Money::fromDecimal(12.34);
        
        $this->assertEquals('12.34', (string) $money);
    }

    public function test_json_serialization()
    {
        $money = Money::fromDecimal(12.34);
        
        $json = $money->jsonSerialize();
        
        $this->assertEquals([
            'amount' => 12.34,
            'cents' => 1234,
        ], $json);
    }

    public function test_invalid_decimal_input_throws_exception()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid amount: abc');
        
        Money::fromDecimal('abc');
    }

    public function test_precision_handling()
    {
        // Test that we maintain precision for financial calculations
        $money1 = Money::fromDecimal(0.01);
        $money2 = Money::fromDecimal(0.02);
        
        $result = $money1->add($money2);
        
        $this->assertEquals(0.03, $result->toDecimal());
    }

    public function test_edge_case_rounding()
    {
        // Test edge cases for rounding
        $money1 = Money::fromDecimal(0.005); // Should round to 0.01
        $money2 = Money::fromDecimal(0.004); // Should round to 0.00
        
        $this->assertEquals(0.01, $money1->toDecimal());
        $this->assertEquals(0.00, $money2->toDecimal());
    }
}
