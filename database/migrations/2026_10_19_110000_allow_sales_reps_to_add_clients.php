<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sales executives may now add clients manually. Permissions::sync() only grants
 * defaults for newly created permissions, so existing installs need this one-off grant.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = Role::where('name', 'sales_rep')->where('guard_name', 'web')->first();

        if ($role && Permission::where('name', 'clients.create')->where('guard_name', 'web')->exists()) {
            $role->givePermissionTo('clients.create');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Role::where('name', 'sales_rep')->where('guard_name', 'web')->first()?->revokePermissionTo('clients.create');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
