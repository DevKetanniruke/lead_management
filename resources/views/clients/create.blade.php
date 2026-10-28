<x-app-layout title="New Client">
    <x-slot name="header">
        <h2 class="truncate text-lg font-semibold text-slate-900">New Client</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl">
        @include('clients._form', ['action' => route('clients.store')])
    </div>
</x-app-layout>
