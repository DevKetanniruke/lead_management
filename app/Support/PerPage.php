<?php

namespace App\Support;

use Illuminate\Http\Request;

class PerPage
{
    public const OPTIONS = [10, 25, 50, 100, 500];

    public const DEFAULT = 10;

    /** Page size from ?per_page=, limited to the allowed options. */
    public static function from(Request $request): int
    {
        $value = (int) $request->query('per_page');

        return in_array($value, self::OPTIONS, true) ? $value : self::DEFAULT;
    }
}
