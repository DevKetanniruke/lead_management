<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');                         // primary contact / client name
            $table->string('company_name')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('phone', 30)->nullable()->index();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('state_code', 5)->nullable();     // GST place-of-supply code, e.g. 27
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->default('India');
            $table->string('gstin', 20)->nullable()->index();
            $table->string('status', 20)->default('active')->index(); // active | inactive | archived
            $table->foreignId('account_manager_id')->nullable()->constrained('users')->nullOnDelete();
            // Original lead: unique, so one lead can never produce two clients.
            $table->foreignId('lead_id')->nullable()->unique()->constrained('leads')->nullOnDelete();
            $table->date('converted_at')->nullable()->index();
            $table->foreignId('converted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
