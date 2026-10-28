@props(['column', 'sort', 'dir'])

<a href="{{ request()->fullUrlWithQuery(['sort' => $column, 'dir' => $sort === $column && $dir === 'asc' ? 'desc' : 'asc', 'page' => null]) }}"
   data-ajax class="inline-flex items-center gap-1 hover:text-slate-700">
    {{ $slot }}
    @if ($sort === $column)<span aria-hidden="true">{{ $dir === 'asc' ? '▲' : '▼' }}</span>@endif
</a>
