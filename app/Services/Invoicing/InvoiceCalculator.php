<?php

namespace App\Services\Invoicing;

use App\Support\Money;

/**
 * Pure invoice maths on decimal strings (bcmath via Money); no floats, no database.
 *
 * Per line: subtotal = qty × price, taxable = subtotal − discount, tax by the tax type:
 *   cgst_sgst → CGST and SGST each at half the rate (rounded per line, so the halves are always equal)
 *   igst      → IGST at the full rate
 *   simple    → a single tax at the full rate
 *   none      → no tax
 * Every line amount is rounded to 2 dp; invoice totals are the sums of the rounded line amounts.
 */
class InvoiceCalculator
{
    public const TAX_TYPES = ['cgst_sgst', 'igst', 'simple', 'none'];

    /**
     * @param  list<array{quantity: mixed, unit_price: mixed, discount?: mixed, tax_rate?: mixed}>  $items
     * @return array{items: list<array<string, string>>, totals: array<string, string>}
     */
    public function calculate(array $items, string $taxType): array
    {
        if (! in_array($taxType, self::TAX_TYPES, true)) {
            throw new \InvalidArgumentException("Unknown tax type [$taxType]");
        }

        $lines = [];
        foreach (array_values($items) as $item) {
            $lines[] = $this->line($item, $taxType);
        }

        $sum = fn (string $key) => Money::sum(array_column($lines, $key));

        $taxable = $sum('taxable_amount');
        $tax = $sum('tax_amount');

        return [
            'items' => $lines,
            'totals' => [
                'subtotal' => $sum('line_subtotal'),
                'discount_total' => $sum('discount'),
                'taxable_total' => $taxable,
                'cgst_total' => $sum('cgst_amount'),
                'sgst_total' => $sum('sgst_amount'),
                'igst_total' => $sum('igst_amount'),
                'tax_total' => $tax,
                'grand_total' => Money::add($taxable, $tax),
            ],
        ];
    }

    /** @return array<string, string> */
    public function line(array $item, string $taxType): array
    {
        $quantity = Money::of($item['quantity']);
        $price = Money::of($item['unit_price']);
        $rate = $taxType === 'none' ? '0' : Money::of($item['tax_rate'] ?? '0');

        if (Money::cmp($quantity, '0') <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than zero.');
        }
        if (Money::cmp($price, '0') < 0 || Money::cmp($rate, '0') < 0) {
            throw new \InvalidArgumentException('Price and tax rate cannot be negative.');
        }

        $subtotal = Money::mul($quantity, $price);
        $discount = Money::round($item['discount'] ?? '0');

        if (Money::cmp($discount, '0') < 0 || Money::cmp($discount, $subtotal) > 0) {
            throw new \InvalidArgumentException('Discount must be between zero and the line amount.');
        }

        $taxable = Money::sub($subtotal, $discount);
        $cgst = $sgst = $igst = '0.00';

        switch ($taxType) {
            case 'cgst_sgst':
                $half = Money::div($rate, '2', 4);
                $cgst = Money::percent($taxable, $half);
                $sgst = $cgst;
                $tax = Money::add($cgst, $sgst);
                break;
            case 'igst':
                $igst = Money::percent($taxable, $rate);
                $tax = $igst;
                break;
            case 'simple':
                $tax = Money::percent($taxable, $rate);
                break;
            default:
                $tax = '0.00';
        }

        return [
            'quantity' => Money::round($quantity, 3),
            'unit_price' => Money::round($price),
            'discount' => $discount,
            'tax_rate' => Money::round($rate),
            'line_subtotal' => $subtotal,
            'taxable_amount' => $taxable,
            'cgst_amount' => $cgst,
            'sgst_amount' => $sgst,
            'igst_amount' => $igst,
            'tax_amount' => $tax,
            'line_total' => Money::add($taxable, $tax),
        ];
    }

    /**
     * Tax type from the configured system and the transaction location:
     * GST is intra-state (CGST + SGST) when the place of supply equals the seller's state, otherwise IGST.
     */
    public static function taxTypeFor(string $system, ?string $sellerStateCode, ?string $placeOfSupply): string
    {
        return match ($system) {
            'gst' => $sellerStateCode !== null && $sellerStateCode !== '' && $sellerStateCode === $placeOfSupply ? 'cgst_sgst' : 'igst',
            'simple' => 'simple',
            default => 'none',
        };
    }
}
