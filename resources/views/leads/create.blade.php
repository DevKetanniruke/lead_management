<x-app-layout>
    <x-slot name="header">
        <h2 class="truncate text-lg font-semibold text-slate-900">New Lead</h2>
    </x-slot>

    <div class="">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-6">
                @include('leads._form', ['action' => route('leads.store')])
            </div>
        </div>
    </div>
</x-app-layout>
