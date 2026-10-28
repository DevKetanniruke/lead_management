<x-app-layout title="Edit Client">
    <x-slot name="header">
        <h2 class="truncate text-lg font-semibold text-slate-900">Edit Client: {{ $client->displayName() }}</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl">
        @include('clients._form', ['action' => route('clients.update', $client), 'method' => 'PUT'])
    </div>
</x-app-layout>
