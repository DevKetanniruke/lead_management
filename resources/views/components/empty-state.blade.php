@props(['icon' => 'search', 'title', 'action' => null, 'href' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-12 text-center']) }}>
    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
        <x-icon :name="$icon" class="h-6 w-6" />
    </span>
    <h3 class="mt-3 text-sm font-semibold text-slate-900">{{ $title }}</h3>
    @if (trim($slot))
        <p class="mt-1 max-w-sm text-sm text-slate-500">{{ $slot }}</p>
    @endif
    @if ($action && $href)
        <a href="{{ $href }}" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500">
            <x-icon name="plus" class="h-4 w-4" /> {{ $action }}
        </a>
    @endif
</div>
