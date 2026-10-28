<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Permissions::sync();
    }

    public function down(): void
    {
        // Keep the role and its permissions to avoid removing access unexpectedly.
    }
};
