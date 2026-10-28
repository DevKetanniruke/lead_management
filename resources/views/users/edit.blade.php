<x-app-layout title="Edit User">
    <x-slot name="header">
        <h2 class="truncate text-lg font-semibold text-slate-900">Edit User: {{ $user->name }}</h2>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-4">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @include('users._form', ['action' => route('users.update', $user), 'method' => 'PUT'])
        </div>
    </div>
</x-app-layout>
