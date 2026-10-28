@php
    $groups = ['Overdue' => [$overdue, 'text-red-600'], 'Today' => [$today, 'text-gray-900'], 'Upcoming' => [$upcoming, 'text-gray-900']];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="truncate text-lg font-semibold text-slate-900">Follow-ups</h2>
    </x-slot>

    <div class="">
        <div id="followups-region" data-ajax-table class="max-w-4xl mx-auto space-y-6">
            @foreach ($groups as $label => [$tasks, $color])
                <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-6">
                    <h3 class="font-semibold {{ $color }}">{{ $label }} <span class="text-sm font-normal text-gray-500">({{ number_format($tasks->total()) }})</span></h3>
                    <ul class="mt-3 divide-y divide-gray-100">
                        @forelse ($tasks as $task)
                            <li class="flex items-start justify-between gap-4 py-3 text-sm">
                                <div>
                                    <a href="{{ $task->subjectUrl() }}" class="font-medium text-indigo-600 hover:underline">{{ $task->subjectName() }}</a>
                                    @if ($task->client_id)<x-badge color="indigo" class="ms-1">Client</x-badge>@endif
                                    <div class="text-gray-700">{{ $task->body }}</div>
                                    <div class="text-xs text-gray-500">Due {{ $task->due_at->format('M j, H:i') }}</div>
                                </div>
                                <form method="POST" action="{{ route('activities.complete', $task) }}">
                                    @csrf @method('PATCH')
                                    <x-secondary-button type="submit">Done</x-secondary-button>
                                </form>
                            </li>
                        @empty
                            <li class="py-3 text-sm text-gray-500">Nothing here.</li>
                        @endforelse
                    </ul>
                    <div class="mt-4">{{ $tasks->links() }}</div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
