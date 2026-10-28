@php $field = 'border-slate-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500'; @endphp

<x-app-layout :title="$user->name">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="truncate text-lg font-semibold text-slate-900">{{ $user->name }}</h2>
            <a href="{{ route('users.edit', $user) }}"><x-secondary-button><x-icon name="pencil" class="h-4 w-4" /> Edit</x-secondary-button></a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        <x-flash />

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Profile --}}
            <x-card class="lg:col-span-1">
                <div class="flex items-center gap-4">
                    <x-avatar :user="$user" size="h-16 w-16 text-xl" />
                    <div class="min-w-0">
                        <p class="truncate text-lg font-semibold text-slate-900">{{ $user->name }}</p>
                        <p class="text-sm text-slate-500">{{ $user->roleLabel() }}{{ $user->department ? ' · '.$user->department : '' }}</p>
                        <x-badge class="mt-1" :color="$user->is_active ? 'green' : 'gray'">{{ $user->is_active ? 'Active' : 'Inactive since '.$user->deactivated_at?->format('M j, Y') }}</x-badge>
                    </div>
                </div>
                <dl class="mt-6 space-y-3 text-sm">
                    @foreach ([
                        'Email' => $user->email,
                        'Phone' => $user->phone,
                        'Last login' => $user->last_login_at?->format('M j, Y H:i') ?? 'Never',
                        'Created' => $user->created_at->format('M j, Y'),
                    ] as $label => $value)
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="truncate text-right font-medium text-slate-900">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>

                {{-- Account actions --}}
                <div class="mt-6 space-y-2 border-t border-slate-100 pt-5">
                    @unless ($user->is(auth()->user()))
                        @if ($user->is_active)
                            <form method="POST" action="{{ route('users.deactivate', $user) }}"
                                  data-confirm="{{ $user->name }} will be signed out immediately and cannot log in until reactivated. Their records stay intact." data-confirm-title="Deactivate this user?" data-confirm-button="Deactivate">
                                @csrf @method('PATCH')
                                <button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50"><x-icon name="ban" class="h-4 w-4" /> Deactivate account</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('users.activate', $user) }}">
                                @csrf @method('PATCH')
                                <button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50"><x-icon name="check" class="h-4 w-4" /> Activate account</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('users.password-reset', $user) }}">
                            @csrf
                            <button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"><x-icon name="mail" class="h-4 w-4" /> Send password reset link</button>
                        </form>
                        <form method="POST" action="{{ route('users.reset-access', $user) }}"
                              data-confirm="This ends all of {{ $user->name }}'s sessions and invalidates their current password. They'll get an email link to choose a new one." data-confirm-title="Reset access?" data-confirm-button="Reset access">
                            @csrf
                            <button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"><x-icon name="key" class="h-4 w-4" /> Reset access (sign out everywhere)</button>
                        </form>
                        <form method="POST" action="{{ route('users.destroy', $user) }}"
                              data-confirm="{{ $records ? 'This user owns business records, so deletion will be refused. Deactivate or reassign instead.' : 'This permanently deletes the user account.' }}" data-confirm-title="Delete {{ $user->name }}?" data-confirm-button="Delete">
                            @csrf @method('DELETE')
                            <button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-slate-500 hover:bg-slate-50 hover:text-red-700"><x-icon name="trash" class="h-4 w-4" /> Delete user</button>
                        </form>
                    @else
                        <p class="text-sm text-slate-500">This is your account. Manage your password from your <a href="{{ route('profile.edit') }}" class="text-indigo-600 hover:underline">profile</a>.</p>
                    @endunless
                </div>
            </x-card>

            {{-- Performance --}}
            <div class="space-y-6 lg:col-span-2">
                <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                    <x-stat label="Assigned leads" :value="number_format($stats['leads'])" icon="leads" />
                    <x-stat label="Open leads" :value="number_format($stats['open'])" icon="pipeline" tone="bg-sky-50 text-sky-600" />
                    <x-stat label="Won" :value="number_format($stats['won'])" :sub="money($stats['won_value']).' deal value'" icon="trophy" tone="bg-emerald-50 text-emerald-600" />
                    <x-stat label="Win rate" :value="$stats['conversion_rate'] !== null ? $stats['conversion_rate'].'%' : '—'" sub="Won ÷ (won + lost)" icon="sparkles" tone="bg-amber-50 text-amber-600" />
                </div>

                @if ($records)
                    <x-card title="Hand over work">
                        <p class="text-sm text-slate-600">
                            Owns: {{ collect($records)->map(fn ($n, $type) => number_format($n).' '.$type)->implode(' · ') }}.
                            Move their assigned leads and managed clients to someone else, e.g. before deactivating.
                        </p>
                        <form method="POST" action="{{ route('users.reassign', $user) }}" class="mt-4 flex flex-wrap items-center gap-3"
                              data-confirm="All leads and clients currently assigned to {{ $user->name }} will move to the selected user." data-confirm-title="Reassign records?" data-confirm-button="Reassign" data-confirm-tone="primary">
                            @csrf
                            <select name="to_user_id" required class="{{ $field }}" aria-label="Reassign to">
                                <option value="">Reassign to…</option>
                                @foreach ($reassignTo as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                            <x-primary-button>Reassign</x-primary-button>
                        </form>
                        <x-input-error :messages="$errors->get('to_user_id')" class="mt-2" />
                    </x-card>
                @endif

                <x-card title="Assigned leads" :padding="false">
                    <div id="user-leads" data-ajax-table>
                        <ul class="divide-y divide-slate-100">
                            @forelse ($leads as $lead)
                                <li class="flex items-center justify-between gap-4 px-5 py-3">
                                    <div class="min-w-0">
                                        <a href="{{ route('leads.show', $lead) }}" class="block truncate text-sm font-medium text-slate-900 hover:text-indigo-600">{{ $lead->name }}</a>
                                        <p class="truncate text-xs text-slate-500">{{ $lead->company ?: '—' }} · {{ money($lead->value) }}</p>
                                    </div>
                                    <x-lead-status-badge :status="$lead->status" />
                                </li>
                            @empty
                                <li><x-empty-state icon="leads" title="No assigned leads" /></li>
                            @endforelse
                        </ul>
                        @if ($leads->hasPages())<div class="border-t border-slate-100 p-3">{{ $leads->links() }}</div>@endif
                    </div>
                </x-card>
            </div>
        </div>
    </div>
</x-app-layout>
