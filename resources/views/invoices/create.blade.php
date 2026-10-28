<x-app-layout title="New Invoice">
    <x-slot name="header">
        <h2 class="truncate text-lg font-semibold text-slate-900">New Invoice</h2>
    </x-slot>

    <div class="mx-auto max-w-6xl">
        @include('invoices._form', ['action' => route('invoices.store')])
    </div>
</x-app-layout>
