@props(['title', 'subtitle' => null, 'icon' => null, 'href' => null, 'link' => 'View all', 'padding' => true])

<section {{ $attributes->merge(['class' => 'flex flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]']) }}>
    <header class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <div class="flex min-w-0 items-center gap-3">
            @if ($icon)
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                    <x-icon :name="$icon" class="h-[18px] w-[18px]" />
                </span>
            @endif
            <div class="min-w-0">
                <h3 class="truncate text-[15px] font-semibold text-slate-900">{{ $title }}</h3>
                @if ($subtitle)
                    <p class="truncate text-xs text-slate-500">{{ $subtitle }}</p>
                @endif
            </div>
        </div>
        @isset($actions)
            <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
        @elseif ($href)
            <a href="{{ $href }}" class="group inline-flex shrink-0 items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-indigo-600 transition hover:bg-indigo-50">
                {{ $link }} <span aria-hidden="true" class="transition group-hover:translate-x-0.5">&rarr;</span>
            </a>
        @endisset
    </header>
    <div @class(['flex-1', 'p-5' => $padding])>{{ $slot }}</div>
</section>
