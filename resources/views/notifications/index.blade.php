<x-app-layout title="Notifications">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="truncate text-lg font-semibold text-slate-900">Notifications</h2>
            @if (auth()->user()->unreadNotifications()->exists())
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <x-secondary-button type="submit"><x-icon name="check" class="h-4 w-4" /> Mark all read</x-secondary-button>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-4">
        <x-flash />

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <ul class="divide-y divide-slate-100">
                @forelse ($notifications as $n)
                    <li>
                        <a href="{{ route('notifications.open', $n->id) }}" class="flex items-start gap-3 px-5 py-4 hover:bg-slate-50">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $n->read_at ? 'bg-transparent' : 'bg-indigo-500' }}" aria-hidden="true"></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm {{ $n->read_at ? 'text-slate-700' : 'font-semibold text-slate-900' }}">{{ $n->data['title'] ?? 'Notification' }}</p>
                                @if (! empty($n->data['body']))<p class="truncate text-sm text-slate-500">{{ $n->data['body'] }}</p>@endif
                            </div>
                            <span class="shrink-0 text-xs text-slate-400">{{ $n->created_at->diffForHumans() }}</span>
                        </a>
                    </li>
                @empty
                    <li><x-empty-state icon="bell" title="No notifications yet">You'll see lead assignments, conversions, invoices and payments here.</x-empty-state></li>
                @endforelse
            </ul>
        </div>
        {{ $notifications->links() }}
    </div>
</x-app-layout>
