<li class="flex items-start justify-between gap-4 py-3">
    <div class="min-w-0">
        <div class="text-xs text-slate-500">
            <span class="font-medium uppercase">{{ $activity->type === 'task' ? 'Follow-up' : $activity->type }}</span>
            · {{ $activity->user?->name ?? 'Unknown' }} · {{ $activity->created_at->diffForHumans() }}
            @if ($activity->lead) · <a href="{{ route('leads.show', $activity->lead) }}" class="text-indigo-600 hover:underline">{{ $activity->lead->name }}</a>@endif
            @if ($activity->type === 'task')
                · due {{ $activity->due_at?->format('M j, H:i') }}
                @if ($activity->completed_at) · <span class="text-emerald-600">done</span> @endif
            @endif
        </div>
        <div class="mt-1 whitespace-pre-line {{ $activity->completed_at ? 'text-slate-400 line-through' : 'text-slate-900' }}">{{ $activity->body }}</div>
    </div>
    @if ($editable)
        <div class="flex shrink-0 gap-3">
            @if ($activity->type === 'task')
                <form method="POST" action="{{ route('activities.complete', $activity) }}">
                    @csrf @method('PATCH')
                    <button class="text-indigo-600 hover:underline">{{ $activity->completed_at ? 'Reopen' : 'Done' }}</button>
                </form>
            @endif
            <form method="POST" action="{{ route('activities.destroy', $activity) }}" data-confirm="Remove this entry?" data-confirm-button="Remove">
                @csrf @method('DELETE')
                <button class="text-slate-500 hover:underline">Remove</button>
            </form>
        </div>
    @endif
</li>
