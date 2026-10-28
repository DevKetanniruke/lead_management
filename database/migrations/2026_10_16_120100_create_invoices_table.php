<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 60)->unique();          // unique even across soft-deleted drafts
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();

            // Lifecycle state: draft | issued | void. Paid / partially paid / overdue are derived.
            $table->string('status', 20)->default('draft')->index();
            $table->date('invoice_date');
            $table->date('due_date')->index();
            $table->char('currency', 3)->default('INR');

            // Tax: system at the time of calculation and how GST was split.
            $table->string('tax_system', 10)->default('gst');   // gst | simple | none
            $table->string('tax_type', 12)->default('none');     // cgst_sgst | igst | simple | none
            $table->string('place_of_supply', 5)->nullable();

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('taxable_total', 15, 2)->default(0);
            $table->decimal('cgst_total', 15, 2)->default(0);
            $table->decimal('sgst_total', 15, 2)->default(0);
            $table->decimal('igst_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);   // net of reversals, kept in sync by the payment service
            $table->decimal('balance_due', 15, 2)->default(0);

            $table->string('payment_terms')->nullable();
            $table->text('notes')->nullable();                   // customer-facing
            $table->text('internal_notes')->nullable();
            $table->text('terms')->nullable();

            // Snapshots frozen at issue so later edits to settings or the client never change an issued invoice.
            $table->json('seller_details')->nullable();
            $table->json('billing_details')->nullable();

            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('due_reminder_sent_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();

            $table->foreignId('duplicated_from_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('amends_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
            $table->index(['status', 'due_date']);
            $table->index('invoice_date');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('description', 500);
            $table->string('hsn_sac', 20)->nullable();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);      // amount off this line
            $table->decimal('tax_rate', 5, 2)->default(0);       // percent
            $table->decimal('line_subtotal', 15, 2);             // quantity × unit price
            $table->decimal('taxable_amount', 15, 2);            // after discount
            $table->decimal('cgst_amount', 15, 2)->default(0);
            $table->decimal('sgst_amount', 15, 2)->default(0);
            $table->decimal('igst_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
