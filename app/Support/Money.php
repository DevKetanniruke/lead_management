<?php

namespace App\Support;

/**
 * Decimal-safe money arithmetic on numeric strings (bcmath), never floats.
 * Results are rounded half away from zero to the requested scale (default 2).
 */
final class Money
{
    public const SCALE = 2;

    /** Internal precision for intermediate steps before final rounding. */
    private const WORK = 10;

    private const SYMBOLS = ['INR' => '₹', 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'AUD' => 'A$', 'CAD' => 'C$', 'SGD' => 'S$', 'AED' => 'AED ', 'JPY' => '¥'];

    public const CURRENCIES = ['INR', 'USD', 'EUR', 'GBP', 'AUD', 'CAD', 'SGD', 'AED'];

    public static function of(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }
        if (is_float($value)) {
            // Floats only arrive from loose input; normalise via a fixed representation.
            $value = sprintf('%.'.self::WORK.'F', $value);
        }
        $value = trim((string) $value);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new \InvalidArgumentException("Not a decimal amount: [$value]");
        }

        return $value;
    }

    public static function add(mixed ...$values): string
    {
        $total = '0';
        foreach ($values as $v) {
            $total = bcadd($total, self::of($v), self::WORK);
        }

        return self::round($total);
    }

    public static function sub(mixed $a, mixed $b): string
    {
        return self::round(bcsub(self::of($a), self::of($b), self::WORK));
    }

    public static function mul(mixed $a, mixed $b, int $scale = self::SCALE): string
    {
        return self::round(bcmul(self::of($a), self::of($b), self::WORK), $scale);
    }

    /** $amount × $percent / 100 */
    public static function percent(mixed $amount, mixed $percent): string
    {
        return self::round(bcdiv(bcmul(self::of($amount), self::of($percent), self::WORK), '100', self::WORK));
    }

    public static function div(mixed $a, mixed $b, int $scale = self::SCALE): string
    {
        if (self::cmp($b, '0') === 0) {
            throw new \DivisionByZeroError('Division by zero');
        }

        return self::round(bcdiv(self::of($a), self::of($b), self::WORK), $scale);
    }

    /** Sum without intermediate rounding errors. */
    public static function sum(iterable $values): string
    {
        $total = '0';
        foreach ($values as $v) {
            $total = bcadd($total, self::of($v), self::WORK);
        }

        return self::round($total);
    }

    public static function round(mixed $value, int $scale = self::SCALE): string
    {
        $value = self::of($value);
        $offset = '0.'.str_repeat('0', $scale).'5';

        $rounded = str_starts_with($value, '-')
            ? bcsub($value, $offset, $scale)
            : bcadd($value, $offset, $scale);

        // Avoid "-0.00"
        return bccomp($rounded, '0', $scale) === 0 ? bcadd('0', '0', $scale) : $rounded;
    }

    public static function cmp(mixed $a, mixed $b): int
    {
        return bccomp(self::of($a), self::of($b), self::WORK);
    }

    public static function isPositive(mixed $value): bool
    {
        return self::cmp($value, '0') > 0;
    }

    public static function max(mixed $a, mixed $b): string
    {
        return self::cmp($a, $b) >= 0 ? self::round($a) : self::round($b);
    }

    public static function symbol(string $currency): string
    {
        return self::SYMBOLS[strtoupper($currency)] ?? strtoupper($currency).' ';
    }

    /** Human format, e.g. ₹1,23,456.00 (Indian grouping for INR) or $123,456.00. */
    public static function format(mixed $amount, string $currency = 'INR', bool $symbol = true): string
    {
        $value = self::round($amount);
        $negative = str_starts_with($value, '-');
        [$int, $dec] = explode('.', ltrim($value, '-'));

        $grouped = strtoupper($currency) === 'INR' ? self::indianGrouping($int) : number_format((int) $int);
        if (strlen($int) > 15) { // beyond int precision: plain grouping on the string
            $grouped = strrev(implode(',', str_split(strrev($int), 3)));
        }

        return ($negative ? '-' : '').($symbol ? self::symbol($currency) : '').$grouped.'.'.$dec;
    }

    private static function indianGrouping(string $int): string
    {
        if (strlen($int) <= 3) {
            return $int;
        }
        // 1234567 → 12,34,567: last three digits, then pairs.
        $rest = substr($int, 0, -3);

        return strrev(implode(',', str_split(strrev($rest), 2))).','.substr($int, -3);
    }
}
