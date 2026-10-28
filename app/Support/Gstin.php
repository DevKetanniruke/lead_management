<?php

namespace App\Support;

class Gstin
{
    /** Indian GSTIN: 2-digit state code, PAN, entity number, 'Z', checksum character. */
    public const PATTERN = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';
}
