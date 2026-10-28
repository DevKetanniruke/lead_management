<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Notes and follow-ups can belong to a client as well as to a lead. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('lead_id')->constrained()->cascadeOnDelete();
            $table->index(['client_id', 'created_at']);
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedBigInteger('lead_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'created_at']);
            $table->dropConstrainedForeignId('client_id');
            // lead_id stays nullable: making it required again could fail on client-only rows.
        });
    }
};
