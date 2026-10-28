<?php

namespace App\Support;

/** "Rupees One Lakh Twenty Thousand and Fifty Paise Only" — Indian grouping for INR, international otherwise. */
class AmountInWords
{
    private const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    private const NAMES = [
        'INR' => ['Rupees', 'Paise'], 'USD' => ['Dollars', 'Cents'], 'EUR' => ['Euros', 'Cents'],
        'GBP' => ['Pounds', 'Pence'], 'AUD' => ['Australian Dollars', 'Cents'], 'CAD' => ['Canadian Dollars', 'Cents'],
        'SGD' => ['Singapore Dollars', 'Cents'], 'AED' => ['Dirhams', 'Fils'],
    ];

    public static function format(mixed $amount, string $currency = 'INR'): string
    {
        $value = ltrim(Money::round($amount), '-');
        [$whole, $fraction] = explode('.', $value);
        [$major, $minor] = self::NAMES[strtoupper($currency)] ?? [strtoupper($currency), 'Cents'];

        $indian = strtoupper($currency) === 'INR';
        $words = (int) $whole === 0 ? 'Zero' : ($indian ? self::indian((int) $whole) : self::international((int) $whole));
        $result = ($indian ? "$major " : '').$words.($indian ? '' : " $major");

        if ((int) $fraction > 0) {
            $result .= ' and '.self::belowHundred((int) $fraction)." $minor";
        }

        return $result.' Only';
    }

    private static function indian(int $n): string
    {
        $parts = [];
        foreach ([10000000 => 'Crore', 100000 => 'Lakh', 1000 => 'Thousand', 100 => 'Hundred'] as $size => $label) {
            if ($n >= $size) {
                $count = intdiv($n, $size);
                $parts[] = ($size === 10000000 ? self::indian($count) : self::belowHundred($count)).' '.$label;
                $n %= $size;
            }
        }
        if ($n > 0) {
            $parts[] = self::belowHundred($n);
        }

        return implode(' ', $parts);
    }

    private static function international(int $n): string
    {
        $parts = [];
        foreach ([1000000000 => 'Billion', 1000000 => 'Million', 1000 => 'Thousand'] as $size => $label) {
            if ($n >= $size) {
                $parts[] = self::belowThousand(intdiv($n, $size)).' '.$label;
                $n %= $size;
            }
        }
        if ($n > 0) {
            $parts[] = self::belowThousand($n);
        }

        return implode(' ', $parts);
    }

    private static function belowThousand(int $n): string
    {
        $hundreds = intdiv($n, 100);
        $rest = $n % 100;

        return trim(($hundreds ? self::ONES[$hundreds].' Hundred ' : '').($rest ? self::belowHundred($rest) : ''));
    }

    private static function belowHundred(int $n): string
    {
        return $n < 20 ? self::ONES[$n] : trim(self::TENS[intdiv($n, 10)].' '.self::ONES[$n % 10]);
    }
}
