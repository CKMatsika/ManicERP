<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Integer minor-unit money value object. Floating point values are rejected
 * so that financial arithmetic never drifts.
 */
final class Money implements \Stringable
{
    private function __construct(
        public readonly int $minor,
        public readonly string $currency,
    ) {}

    public static function zero(string $currency = 'USD'): self
    {
        return new self(0, strtoupper($currency));
    }

    public static function fromMinor(int $minor, string $currency = 'USD'): self
    {
        return new self($minor, strtoupper($currency));
    }

    /**
     * Parse a decimal string such as "1234.56" (never a float).
     */
    public static function fromDecimal(string $amount, string $currency = 'USD'): self
    {
        $currency = strtoupper($currency);
        $amount = trim($amount);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException("Invalid money amount [{$amount}].");
        }

        $decimals = self::decimalsFor($currency);
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $negative = str_starts_with($whole, '-');
        $whole = ltrim($whole, '-');
        $fraction = str_pad(substr($fraction, 0, $decimals), $decimals, '0');

        $minor = (int) $whole * (10 ** $decimals) + (int) ($fraction === '' ? '0' : $fraction);

        return new self($negative ? -$minor : $minor, $currency);
    }

    public static function decimalsFor(string $currency): int
    {
        $currency = strtoupper($currency);

        try {
            $configured = config("manicerp.currencies.{$currency}.decimals");

            if (is_numeric($configured)) {
                return (int) $configured;
            }
        } catch (\Throwable) {
            // Unit tests may use this value object without a booted framework.
        }

        return in_array($currency, ['JPY', 'KRW'], true) ? 0 : 2;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function multiply(string $factor): self
    {
        if (! is_numeric($factor)) {
            throw new InvalidArgumentException('Multiplier must be numeric.');
        }

        $result = bcmul((string) $this->minor, $factor, 0);

        return new self((int) $result, $this->currency);
    }

    /**
     * Allocate by integer weights with remainder distributed to the first
     * buckets, so the allocations always sum exactly to this amount.
     *
     * @param  array<int, int>  $weights
     * @return array<int, self>
     */
    public function allocate(array $weights): array
    {
        $total = array_sum($weights);

        if ($total <= 0) {
            throw new InvalidArgumentException('Allocation weights must be positive.');
        }

        $remaining = $this->minor;
        $allocations = [];

        foreach ($weights as $index => $weight) {
            $share = intdiv($this->minor * $weight, $total);
            $allocations[$index] = $share;
            $remaining -= $share;
        }

        $keys = array_keys($weights);
        $i = 0;
        while ($remaining !== 0) {
            $key = $keys[$i % count($keys)];
            $step = $remaining > 0 ? 1 : -1;
            $allocations[$key] += $step;
            $remaining -= $step;
            $i++;
        }

        return array_map(fn (int $minor) => new self($minor, $this->currency), $allocations);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->minor === $other->minor;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor > $other->minor;
    }

    public function absolute(): self
    {
        return new self(abs($this->minor), $this->currency);
    }

    public function toDecimal(): string
    {
        $decimals = self::decimalsFor($this->currency);
        $divisor = 10 ** $decimals;
        $sign = $this->minor < 0 ? '-' : '';
        $abs = abs($this->minor);

        return $sign.intdiv($abs, $divisor).'.'.str_pad((string) ($abs % $divisor), $decimals, '0', STR_PAD_LEFT);
    }

    public function format(): string
    {
        $decimals = self::decimalsFor($this->currency);
        $sign = $this->minor < 0 ? '-' : '';
        $abs = abs($this->minor);
        $divisor = 10 ** $decimals;
        $whole = number_format(intdiv($abs, $divisor));
        $fraction = str_pad((string) ($abs % $divisor), $decimals, '0', STR_PAD_LEFT);

        return "{$sign}{$this->currency} {$whole}.{$fraction}";
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Cannot combine amounts in different currencies.');
        }
    }
}
