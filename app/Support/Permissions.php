<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Single source of truth for roles, permissions and their defaults.
 * Admin and Super Admin always hold every permission; other roles start from DEFAULTS and can be edited in the UI.
 */
class Permissions
{
    public const ADMIN = 'admin';

    public const SUPERADMIN = 'superadmin';

    public const ROLES = [
        'superadmin' => 'Super Admin',
        'admin' => 'Admin',
        'manager' => 'Manager',
        'sales_rep' => 'Sales Executive',
    ];

    /** Grouped for the role editor: group => [permission => description]. */
    public const GROUPS = [
        'Users' => [
            'users.manage' => 'Manage users, roles and permissions',
        ],
        'Leads' => [
            'leads.view_all' => 'View all leads (not just own)',
            'leads.assign' => 'Assign leads to team members',
            'leads.convert' => 'Convert leads into clients',
        ],
        'Clients' => [
            'clients.view_all' => 'View all clients (not just own)',
            'clients.create' => 'Add clients manually',
            'clients.update' => 'Edit client details and status',
            'clients.export' => 'Export client data',
        ],
        'Invoices' => [
            'invoices.view_all' => 'View all invoices (not just own clients)',
            'invoices.create' => 'Create, edit and duplicate draft invoices',
            'invoices.issue' => 'Issue invoices',
            'invoices.send' => 'Email invoices to clients',
            'invoices.void' => 'Void issued invoices',
        ],
        'Payments' => [
            'payments.record' => 'Record payments',
            'payments.reverse' => 'Reverse and correct payments',
        ],
        'Reports & settings' => [
            'reports.view' => 'View reports and team performance',
            'settings.manage' => 'Manage company, tax and invoice settings',
            'audit.view' => 'View the activity log',
        ],
    ];

    public const DEFAULTS = [
        'manager' => [
            'leads.view_all', 'leads.assign', 'leads.convert',
            'clients.view_all', 'clients.create', 'clients.update', 'clients.export',
            'invoices.view_all', 'invoices.create', 'invoices.issue', 'invoices.send', 'invoices.void',
            'payments.record', 'reports.view',
        ],
        'sales_rep' => ['leads.convert', 'clients.create'],
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(array_merge(...array_values(self::GROUPS)));
    }

    public static function roleLabel(?string $role): string
    {
        return self::ROLES[$role] ?? ucfirst(str_replace('_', ' ', (string) $role));
    }

    /**
     * Create missing roles/permissions. Admin gets everything; other roles only receive
     * defaults for permissions created in this run, so later edits in the UI are preserved.
     */
    public static function sync(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $roles = collect(array_keys(self::ROLES))->mapWithKeys(fn ($name) => [$name => Role::findOrCreate($name, 'web')]);

        $created = [];
        foreach (self::all() as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            if ($permission->wasRecentlyCreated) {
                $created[] = $name;
            }
        }

        $roles[self::ADMIN]->syncPermissions(self::all());
        $roles[self::SUPERADMIN]->syncPermissions(self::all());

        foreach (self::DEFAULTS as $role => $permissions) {
            $new = array_values(array_intersect($permissions, $created));
            if ($new) {
                $roles[$role]->givePermissionTo($new);
            }
        }

        $registrar->forgetCachedPermissions();
    }
}
