{{-- Period selector (Today / 7 / 30 / 90 days / Custom). Expects $range (App\Support\DateRange) and $route. --}}
@php $field = 'rounded-lg border-slate-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500'; @endphp

<div class="flex flex-wrap items-center gap-2" x-data="{ custom: @js($range->key === 'custom') }">
    <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5 shadow-sm" role="group" aria-label="Period">
        @foreach (App\Support\DateRange::PERIODS as $key => $label)
            @if ($key === 'custom')
                <button type="button" @click="custom = !custom"
                        class="rounded-md px-3 py-1.5 text-xs font-medium transition {{ $range->key === 'custom' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50' }}">{{ $label }}</button>
            @else
                <a href="{{ route($route, ['period' => $key]) }}"
                   class="rounded-md px-3 py-1.5 text-xs font-medium transition {{ $range->key === $key ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50' }}"
                   @if ($range->key === $key) aria-current="true" @endif>{{ $label }}</a>
            @endif
        @endforeach
    </div>
    <form method="GET" action="{{ route($route) }}" x-show="custom" x-cloak class="flex flex-wrap items-center gap-2">
        <input type="hidden" name="period" value="custom">
        <input type="date" name="from" value="{{ $range->from->toDateString() }}" max="{{ now()->toDateString() }}" class="{{ $field }}" aria-label="From date" required>
        <span class="text-sm text-slate-400">to</span>
        <input type="date" name="to" value="{{ $range->to->toDateString() }}" class="{{ $field }}" aria-label="To date" required>
        <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">Apply</button>
    </form>
</div>
