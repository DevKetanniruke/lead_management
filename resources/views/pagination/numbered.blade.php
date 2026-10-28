@php
    $base = 'inline-flex min-w-[2.25rem] items-center justify-center border px-3 py-1.5 text-sm font-medium -ms-px';
    $link = $base.' border-slate-300 bg-white text-indigo-600 hover:bg-indigo-50';
    $muted = $base.' border-slate-300 cursor-not-allowed text-slate-400 bg-slate-50';
    $active = $base.' z-10 border-indigo-600 bg-indigo-600 text-white font-semibold';
@endphp

@if ($paginator->total() > 0)
    <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-600">
            <div class="flex items-center gap-2">
                <span>Show</span>
                <select data-per-page
                        aria-label="Entries per page"
                        onchange="window.handlePerPage ? window.handlePerPage(this) : (function(s){ const u = new URL(location.href); u.searchParams.set('per_page', s.value); [...u.searchParams.keys()].filter(k => k === 'page' || k.endsWith('_page')).forEach(k => u.searchParams.delete(k)); location.href = u.toString(); })(this)"
                        class="rounded-lg border-slate-300 py-1 ps-2 pe-8 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach (\App\Support\PerPage::OPTIONS as $size)
                        <option value="{{ $size }}" @selected($paginator->perPage() === $size)>{{ $size }}</option>
                    @endforeach
                </select>
                <span>entries</span>
            </div>
            <span>Showing <strong>{{ $paginator->firstItem() }}</strong> to <strong>{{ $paginator->lastItem() }}</strong> of <strong>{{ number_format($paginator->total()) }}</strong></span>
        </div>

        @if ($paginator->hasPages())
            <nav aria-label="Pagination">
                <ul class="inline-flex flex-wrap rounded-lg shadow-sm [&>li:first-child>*]:rounded-s-lg [&>li:last-child>*]:rounded-e-lg">
                    {{-- Previous --}}
                    <li>
                        @if ($paginator->onFirstPage())
                            <span class="{{ $muted }}" aria-disabled="true">&laquo;</span>
                        @else
                            <a href="{{ $paginator->previousPageUrl() }}" data-ajax rel="prev" class="{{ $link }}" aria-label="Previous">&laquo;</a>
                        @endif
                    </li>

                    {{-- Numbers --}}
                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <li><span class="{{ $muted }}">{{ $element }}</span></li>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                <li>
                                    @if ($page == $paginator->currentPage())
                                        <span class="{{ $active }}" aria-current="page">{{ $page }}</span>
                                    @else
                                        <a href="{{ $url }}" data-ajax class="{{ $link }}" aria-label="Page {{ $page }}">{{ $page }}</a>
                                    @endif
                                </li>
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next --}}
                    <li>
                        @if ($paginator->hasMorePages())
                            <a href="{{ $paginator->nextPageUrl() }}" data-ajax rel="next" class="{{ $link }}" aria-label="Next">&raquo;</a>
                        @else
                            <span class="{{ $muted }}" aria-disabled="true">&raquo;</span>
                        @endif
                    </li>
                </ul>
            </nav>
        @endif
    </div>
@endif
