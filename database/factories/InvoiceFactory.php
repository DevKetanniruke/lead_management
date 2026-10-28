<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Raw invoice rows for query/scope tests. Real invoices are created through InvoiceService.
 *
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'TST-'.fake()->unique()->numberBetween(1, 999999),
            'client_id' => Client::factory(),
            'status' => Invoice::ISSUED,
            'invoice_date' => today()->subDays(10),
            'due_date' => today()->addDays(5),
            'currency' => 'INR',
            'tax_system' => 'none',
            'tax_type' => 'none',
            'subtotal' => '1000.00',
            'taxable_total' => '1000.00',
            'grand_total' => '1000.00',
            'amount_paid' => '0.00',
            'balance_due' => '1000.00',
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => Invoice::DRAFT]);
    }

    public function void(): static
    {
        return $this->state(fn () => ['status' => Invoice::VOID, 'balance_due' => '0.00', 'voided_at' => now()]);
    }

    public function overdue(int $days = 10): static
    {
        return $this->state(fn () => ['invoice_date' => today()->subDays($days + 15), 'due_date' => today()->subDays($days)]);
    }

    public function amount(string $total, string $paid = '0.00'): static
    {
        return $this->state(fn () => [
            'subtotal' => $total, 'taxable_total' => $total, 'grand_total' => $total,
            'amount_paid' => $paid, 'balance_due' => bcsub($total, $paid, 2),
        ]);
    }
}
