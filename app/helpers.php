<?php

use App\Support\Money;
use App\Support\Settings;

if (! function_exists('money')) {
    /** Format an amount for display, e.g. ₹1,23,456.00. Uses the configured currency by default. */
    function money(mixed $amount, ?string $currency = null): string
    {
        return Money::format($amount ?? '0', $currency ?? Settings::currency());
    }
}
