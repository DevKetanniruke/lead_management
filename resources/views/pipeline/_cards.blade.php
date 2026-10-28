@php $canConvert = auth()->user()->can('leads.convert'); @endphp

@foreach ($leads as $lead)
    <div class="cursor-grab rounded-lg border border-slate-200 bg-white p-3 shadow-sm" data-lead="{{ $lead->id }}">
        <a href="{{ route('leads.show', $lead) }}" class="text-sm font-medium text-indigo-600 hover:underline">{{ $lead->name }}</a>
        <div class="text-xs text-slate-500">{{ $lead->company }}</div>
        <div class="mt-2 flex justify-between text-xs text-slate-600">
            <span>{{ number_format($lead->value, 0) }}</span>
            <span>{{ $lead->assignee?->name }}</span>
        </div>
        <div class="mt-1 text-xs text-slate-400">Added by {{ $lead->creator?->name ?? 'Not recorded' }}</div>
        @if (isset($status) && $status->is_won)
            <div class="mt-2 border-t border-slate-100 pt-2 text-xs">
                @if ($lead->client_id)
                    <span class="inline-flex items-center gap-1 font-medium text-emerald-700"><x-icon name="check" class="h-3.5 w-3.5" /> Client created</span>
                @elseif ($canConvert)
                    <a href="{{ route('conversions.create', $lead) }}" class="inline-flex items-center gap-1 font-medium text-indigo-600 hover:underline"><x-icon name="convert" class="h-3.5 w-3.5" /> Convert to client</a>
                @endif
            </div>
        @endif
    </div>
@endforeach
