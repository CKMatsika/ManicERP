<?php

namespace App\Support;

/**
 * Fixed-point decimal helpers. All financial and unit arithmetic stays in
 * integers; floats are never used.
 */
final class Decimal
{
    /**
     * Parse a decimal string into an integer scaled by 10^$scale.
     */
    public static function toScaled(string $value, int $scale = 3): int
    {
        $value = trim($value);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new \InvalidArgumentException("Invalid decimal value [{$value}].");
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = substr(str_pad($fraction, $scale, '0'), 0, $scale);

        $scaled = (int) $whole * (10 ** $scale) + (int) ($fraction === '' ? '0' : $fraction);

        return $negative ? -$scaled : $scaled;
    }

    /**
     * Divide two integers, rounding half away from zero.
     */
    public static function roundDiv(int $numerator, int $denominator): int
    {
        if ($denominator === 0) {
            throw new \DivisionByZeroError('Cannot divide by zero.');
        }

        $negative = ($numerator < 0) xor ($denominator < 0);
        $n = abs($numerator);
        $d = abs($denominator);

        $quotient = intdiv($n, $d);
        $remainder = $n % $d;

        if ($remainder * 2 >= $d) {
            $quotient++;
        }

        return $negative ? -$quotient : $quotient;
    }

    public static function scale(int $value, int $fromScale, int $toScale): int
    {
        if ($toScale > $fromScale) {
            return $value * (10 ** ($toScale - $fromScale));
        }

        return self::roundDiv($value, 10 ** ($fromScale - $toScale));
    }

    /**
     * Render an integer scaled by 10^$scale as a fixed decimal string.
     */
    public static function fromScaled(int $value, int $scale): string
    {
        $divisor = 10 ** $scale;
        $sign = $value < 0 ? '-' : '';
        $abs = abs($value);

        return $sign.intdiv($abs, $divisor).'.'.str_pad((string) ($abs % $divisor), $scale, '0', STR_PAD_LEFT);
    }
}
