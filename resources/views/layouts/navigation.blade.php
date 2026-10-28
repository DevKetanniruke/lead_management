@php
    $user = Auth::user();
    $link = fn (string $route) => Route::has($route);
@endphp

<aside
    class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-slate-900 transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 no-scrollbar"
    :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
    aria-label="Sidebar">

    {{-- Brand --}}
    <div class="flex h-16 shrink-0 items-center justify-between px-5">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-500 text-white">
                <x-icon name="leads" class="h-5 w-5" />
            </span>
            <span class="text-base font-semibold tracking-tight text-white">{{ config('app.name') }}</span>
        </a>
        <button type="button" class="rounded-md p-1 text-slate-400 hover:text-white lg:hidden" @click="sidebar = false" aria-label="Close sidebar">
            <x-icon name="close" />
        </button>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 space-y-6 overflow-y-auto no-scrollbar px-3 py-4">
        <div class="space-y-1">
            <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Workspace</p>
            <x-sidebar-link :href="route('dashboard')" icon="home" :active="request()->routeIs('dashboard')">Dashboard</x-sidebar-link>
            <x-sidebar-link :href="route('leads.index')" icon="leads" :active="request()->routeIs('leads.*')">Leads</x-sidebar-link>
            <x-sidebar-link :href="route('pipeline.index')" icon="pipeline" :active="request()->routeIs('pipeline.*')">Pipeline</x-sidebar-link>
            <x-sidebar-link :href="route('follow-ups.index')" icon="clock" :active="request()->routeIs('follow-ups.*')">Follow-ups</x-sidebar-link>
        </div>

        @if ($link('clients.index') || $link('invoices.index') || $link('payments.index'))
            <div class="space-y-1">
                <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Sales</p>
                @if ($link('clients.index'))
                    <x-sidebar-link :href="route('clients.index')" icon="clients" :active="request()->routeIs('clients.*', 'conversions.*')">Clients</x-sidebar-link>
                @endif
                @if ($link('invoices.index'))
                    <x-sidebar-link :href="route('invoices.index')" icon="invoice" :active="request()->routeIs('invoices.*')">Invoices</x-sidebar-link>
                @endif
                @if ($link('payments.index'))
                    <x-sidebar-link :href="route('payments.index')" icon="payments" :active="request()->routeIs('payments.*')">Payments</x-sidebar-link>
                @endif
            </div>
        @endif

        @if ($link('reports.index') && $user->can('reports.view'))
            <div class="space-y-1">
                <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Insights</p>
                <x-sidebar-link :href="route('reports.index')" icon="reports" :active="request()->routeIs('reports.*')">Reports</x-sidebar-link>
            </div>
        @endif

        @if ($user->canAny(['users.manage', 'audit.view', 'settings.manage']))
            <div class="space-y-1">
                <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Administration</p>
                @can('users.manage')
                    <x-sidebar-link :href="route('users.index')" icon="team" :active="request()->routeIs('users.*')">User Management</x-sidebar-link>
                    <x-sidebar-link :href="route('roles.index')" icon="shield" :active="request()->routeIs('roles.*')">Roles &amp; Permissions</x-sidebar-link>
                @endcan
                @can('audit.view')
                    <x-sidebar-link :href="route('audit.index')" icon="log" :active="request()->routeIs('audit.*')">Activity Log</x-sidebar-link>
                @endcan
                @if ($link('settings.edit') && $user->can('settings.manage'))
                    <x-sidebar-link :href="route('settings.edit')" icon="settings" :active="request()->routeIs('settings.*')">Settings</x-sidebar-link>
                @endif
            </div>
        @endif
    </nav>

    {{-- Current user --}}
    <div class="shrink-0 border-t border-slate-800 p-3">
        <div class="flex items-center gap-3 px-2 py-2">
            <x-avatar :user="$user" tone="bg-indigo-500/20 text-indigo-300" />
            <div class="min-w-0">
                <p class="truncate text-sm font-medium text-white">{{ $user->name }}</p>
                <p class="truncate text-xs text-slate-400">{{ $user->roleLabel() }}</p>
            </div>
        </div>
        <div class="mt-1 space-y-1">
            <x-sidebar-link :href="route('profile.edit')" icon="profile" :active="request()->routeIs('profile.*')">Profile</x-sidebar-link>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-400 transition hover:bg-slate-800 hover:text-white">
                    <x-icon name="logout" class="h-5 w-5 shrink-0 text-slate-500 group-hover:text-slate-300" />
                    Log out
                </button>
            </form>
        </div>
    </div>
</aside>
