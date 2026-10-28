<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'position', 'description', 'hsn_sac', 'quantity', 'unit_price', 'discount', 'tax_rate',
        'line_subtotal', 'taxable_amount', 'cgst_amount', 'sgst_amount', 'igst_amount', 'tax_amount', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'cgst_amount' => 'decimal:2',
            'sgst_amount' => 'decimal:2',
            'igst_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** Quantity without trailing zeros, e.g. 2 or 1.5. */
    public function displayQuantity(): string
    {
        return rtrim(rtrim((string) $this->quantity, '0'), '.');
    }
}
