<x-app-layout title="Edit Invoice">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <h2 class="truncate text-lg font-semibold text-slate-900">Edit Draft {{ $invoice->number }}</h2>
            <x-badge color="gray">Draft</x-badge>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <x-flash />
        @endif
        @include('invoices._form', ['action' => route('invoices.update', $invoice), 'method' => 'PUT'])
    </div>
</x-app-layout>
