@php $field = 'border-slate-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500'; @endphp

<x-app-layout title="User Management">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="truncate text-lg font-semibold text-slate-900">User Management</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('roles.index') }}" class="hidden sm:block"><x-secondary-button><x-icon name="shield" class="h-4 w-4" /> Roles</x-secondary-button></a>
                <a href="{{ route('users.create') }}"><x-primary-button type="button"><x-icon name="plus" class="h-4 w-4" /> New User</x-primary-button></a>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-4">
        <x-flash />

        <form method="GET" data-ajax-filter="users-region" class="grid grid-cols-2 gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-5">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search name, email, phone, department…" class="{{ $field }} col-span-2" aria-label="Search users">
            <select name="role" class="{{ $field }}" aria-label="Filter by role">
                <option value="">All roles</option>
                @foreach ($roles as $value => $label)
                    <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status" class="{{ $field }}" aria-label="Filter by status">
                <option value="">Any status</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
            <div class="col-span-2 flex items-center gap-3 md:col-span-1">
                <x-primary-button>Filter</x-primary-button>
                <a href="{{ route('users.index') }}" class="text-sm text-slate-600 hover:underline">Reset</a>
            </div>
        </form>

        <div id="users-region" data-ajax-table class="space-y-4">
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="w-12 px-4 py-3 text-slate-500">S.No</th>
                            <th class="px-4 py-3"><x-sort-link column="name" :sort="$sort" :dir="$dir">User</x-sort-link></th>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Department</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Leads</th>
                            <th class="px-4 py-3"><x-sort-link column="last_login_at" :sort="$sort" :dir="$dir">Last login</x-sort-link></th>
                            <th class="px-4 py-3"><x-sort-link column="created_at" :sort="$sort" :dir="$dir">Created</x-sort-link></th>
                            <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 [&>tr:hover]:bg-slate-50">
                        @forelse ($users as $user)
                            <tr @class(['opacity-60' => ! $user->is_active])>
                                <td class="whitespace-nowrap px-4 py-3 text-xs font-medium text-slate-500">{{ $users->firstItem() ? $users->firstItem() + $loop->index : $loop->iteration }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <x-avatar :user="$user" />
                                        <div class="min-w-0">
                                            <a href="{{ route('users.show', $user) }}" class="font-medium text-slate-900 hover:text-indigo-600">{{ $user->name }}</a>
                                            <div class="truncate text-xs text-slate-500">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $user->phone ?: '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $user->roleLabel() }}</td>
                                <td class="px-4 py-3">{{ $user->department ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :color="$user->is_active ? 'green' : 'gray'">{{ $user->is_active ? 'Active' : 'Inactive' }}</x-badge>
                                </td>
                                <td class="px-4 py-3 text-right">{{ $user->assigned_leads_count }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $user->created_at->format('M j, Y') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <a href="{{ route('users.show', $user) }}" class="text-indigo-600 hover:underline">View</a>
                                    <a href="{{ route('users.edit', $user) }}" class="ms-3 text-slate-600 hover:underline">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10"><x-empty-state icon="team" title="No users found">Try a different search or filter.</x-empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $users->links() }}
        </div>
    </div>
</x-app-layout>
