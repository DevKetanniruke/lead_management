<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;

/** Creates the CRM permissions and grants role defaults (idempotent, keeps later edits). */
return new class extends Migration
{
    public function up(): void
    {
        Permissions::sync();
    }

    public function down(): void
    {
        // Permissions are left in place; removing them would silently strip access.
    }
};
