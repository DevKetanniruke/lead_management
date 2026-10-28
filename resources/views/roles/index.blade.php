<x-app-layout title="Roles & Permissions">
    <x-slot name="header">
        <h2 class="truncate text-lg font-semibold text-slate-900">Roles &amp; Permissions</h2>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        <x-flash />

        <p class="text-sm text-slate-600">
            Permissions are enforced on the server for every page and action. Users without a "view all" permission only see records they own or are assigned to.
        </p>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            @foreach ($roles as $role)
                @php
                    $locked = in_array($role->name, [\App\Support\Permissions::ADMIN, \App\Support\Permissions::SUPERADMIN], true);
                    $granted = $role->permissions->pluck('name')->all();
                @endphp
                <x-card :title="\App\Support\Permissions::roleLabel($role->name)">
                    <x-slot name="actions">
                        <span class="text-xs text-slate-500">{{ $role->users_count }} {{ Str::plural('user', $role->users_count) }}</span>
                    </x-slot>

                    <form method="POST" action="{{ route('roles.update', $role) }}" class="space-y-5">
                        @csrf @method('PUT')
                        @if ($locked)
                            <p class="rounded-lg bg-indigo-50 p-3 text-xs text-indigo-800">Privileged roles always have full access. This role cannot be restricted.</p>
                        @endif

                        @foreach ($groups as $group => $permissions)
                            <fieldset>
                                <legend class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $group }}</legend>
                                <div class="mt-2 space-y-2">
                                    @foreach ($permissions as $name => $description)
                                        <label class="flex items-start gap-2.5 text-sm {{ $locked ? 'text-slate-500' : 'text-slate-700' }}">
                                            <input type="checkbox" name="permissions[]" value="{{ $name }}" @checked(in_array($name, $granted, true)) @disabled($locked)
                                                   class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-60">
                                            <span>{{ $description }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach

                        @unless ($locked)
                            <x-primary-button>Save {{ \App\Support\Permissions::roleLabel($role->name) }}</x-primary-button>
                        @endunless
                    </form>
                </x-card>
            @endforeach
        </div>
    </div>
</x-app-layout>
