<x-app-layout title="New User">
    <x-slot name="header">
        <h2 class="truncate text-lg font-semibold text-slate-900">New User</h2>
    </x-slot>

    <div class="mx-auto max-w-3xl">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @include('users._form', ['action' => route('users.store')])
        </div>
    </div>
</x-app-layout>
