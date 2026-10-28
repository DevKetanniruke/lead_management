<?php

use Database\Seeders\LeadSourceSeeder;
use Database\Seeders\LeadStatusSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lead forms need statuses and sources to choose from, so a plain `migrate`
 * (without `db:seed`) must not leave those dropdowns empty. Only empty tables
 * are filled, so stages and sources customised by users are never touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('lead_statuses')->exists()) {
            (new LeadStatusSeeder)->run();
        }

        if (! DB::table('lead_sources')->exists()) {
            (new LeadSourceSeeder)->run();
        }
    }

    public function down(): void
    {
        // Seeded rows may already be referenced by leads; leave them in place.
    }
};
