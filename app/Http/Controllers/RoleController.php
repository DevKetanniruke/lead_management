<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::with('permissions')->withCount('users')->get()
            ->sortBy(fn (Role $r) => array_search($r->name, array_keys(Permissions::ROLES)))
            ->values();

        return view('roles.index', ['roles' => $roles, 'groups' => Permissions::GROUPS]);
    }

    /** Super Admin and Admin are locked to every permission; other roles are editable. */
    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if(in_array($role->name, [Permissions::ADMIN, Permissions::SUPERADMIN], true), 403, 'Privileged roles always have full access.');

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Permissions::all())],
        ]);

        $before = $role->permissions->pluck('name')->all();
        $after = array_values(array_unique($data['permissions'] ?? []));

        $role->syncPermissions($after);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        if ($added || $removed) {
            Audit::log('role.permissions_changed', $role, 'Permissions changed for '.Permissions::roleLabel($role->name), compact('added', 'removed'));
        }

        return back()->with('status', Permissions::roleLabel($role->name).' permissions saved.');
    }
}
