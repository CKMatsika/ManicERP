<?php

namespace Tests\Unit;

use App\Support\Decimal;
use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_decimal_amounts_convert_to_exact_minor_units(): void
    {
        $this->assertSame(123456, Money::fromDecimal('1234.56')->minor);
        $this->assertSame(1, Money::fromDecimal('0.01')->minor);
        $this->assertSame(100, Money::fromDecimal('1.00')->minor);
        $this->assertSame(-250, Money::fromDecimal('-2.50')->minor);
    }

    public function test_invalid_amounts_and_floats_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::fromDecimal('12.3456.7');
    }

    public function test_money_formats_with_the_currency(): void
    {
        $this->assertSame('USD 1,234.56', Money::fromMinor(123456)->format());
        $this->assertSame('1234.56', Money::fromMinor(123456)->toDecimal());
        $this->assertSame('-0.05', Money::fromMinor(-5)->toDecimal());
    }

    public function test_arithmetic_is_integer_based(): void
    {
        $total = Money::fromMinor(10000)->add(Money::fromMinor(2500))->subtract(Money::fromMinor(500));

        $this->assertSame(12000, $total->minor);
        $this->assertSame(1200, $total->multiply('0.1')->minor);
    }

    public function test_allocation_never_loses_or_creates_a_cent(): void
    {
        $allocations = Money::fromMinor(100)->allocate([1, 1, 1]);

        $this->assertSame(100, array_sum(array_map(fn (Money $money) => $money->minor, $allocations)));
        $this->assertSame([34, 33, 33], array_map(fn (Money $money) => $money->minor, $allocations));
    }

    public function test_currencies_cannot_be_mixed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::fromMinor(100, 'USD')->add(Money::fromMinor(100, 'ZWG'));
    }

    public function test_decimal_rounding_is_half_away_from_zero(): void
    {
        $this->assertSame(3, Decimal::roundDiv(5, 2));
        $this->assertSame(-3, Decimal::roundDiv(-5, 2));
        $this->assertSame(2, Decimal::roundDiv(4, 2));
        $this->assertSame(1250, Decimal::toScaled('1.25', 3));
        $this->assertSame('1.250', Decimal::fromScaled(1250, 3));
    }
}
