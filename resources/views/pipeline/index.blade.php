<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="truncate text-lg font-semibold text-slate-900">Pipeline</h2>
            <div class="flex items-center gap-2 text-sm text-slate-600">
                <span class="hidden sm:inline">Cards per column</span>
                <select id="per-column" aria-label="Cards per column" class="rounded-lg border-slate-300 py-1 ps-2 pe-8 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach (\App\Support\PerPage::OPTIONS as $size)
                        <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-slot>

    <div id="pipeline-root" x-data="{ error: null, convert: null }">
        <div class="max-w-full mx-auto">
            <p x-show="error" x-text="error" x-cloak class="mb-3 rounded-lg bg-red-50 p-3 text-sm text-red-800"></p>
            <div x-show="convert" x-cloak class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">
                <span><strong x-text="convert?.name"></strong> is marked won. Create the client record now?</span>
                <span class="flex items-center gap-3">
                    <a :href="convert?.url" class="rounded-lg bg-indigo-600 px-3 py-1.5 font-medium text-white hover:bg-indigo-500">Convert to client</a>
                    <button type="button" @click="convert = null" class="text-emerald-800 hover:underline">Later</button>
                </span>
            </div>

            <div class="flex gap-4 overflow-x-auto pb-4">
                @foreach ($statuses as $status)
                    @php $column = $leads[$status->id]; $total = $totals[$status->id] ?? 0; @endphp
                    <div class="w-72 shrink-0 rounded-xl bg-slate-100 p-3" data-column-wrap>
                        <div class="mb-3 flex items-center justify-between">
                            <x-lead-status-badge :status="$status" />
                            <span class="text-xs text-slate-500" data-count>{{ $total }}</span>
                        </div>

                        <div class="kanban-column min-h-[4rem] space-y-2" data-status="{{ $status->id }}" data-total="{{ $total }}">
                            @include('pipeline._cards', ['leads' => $column, 'status' => $status])
                        </div>

                        <button type="button" data-load-more data-url="{{ route('pipeline.column', $status) }}"
                                class="mt-3 hidden w-full rounded-lg border border-slate-300 bg-white py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-60">
                            Show more
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const perPage = {{ $perPage }};
        const alpineRoot = () => document.getElementById('pipeline-root');
        const columns = () => document.querySelectorAll('.kanban-column');

        document.getElementById('per-column').addEventListener('change', (e) => {
            const url = new URL(location.href);
            url.searchParams.set('per_page', e.target.value);
            location.href = url;
        });

        // Header count = total in the column; the button shows how many are still hidden.
        function refreshColumn(col) {
            const total = parseInt(col.dataset.total, 10);
            const remaining = total - col.children.length;
            const wrap = col.closest('[data-column-wrap]');
            wrap.querySelector('[data-count]').textContent = total;

            const btn = wrap.querySelector('[data-load-more]');
            btn.classList.toggle('hidden', remaining <= 0);
            btn.textContent = `Show more (${remaining} remaining)`;
        }

        function shift(col, delta) {
            col.dataset.total = Math.max(0, parseInt(col.dataset.total, 10) + delta);
            refreshColumn(col);
        }

        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-load-more]');
            if (!btn) return;

            const col = btn.closest('[data-column-wrap]').querySelector('.kanban-column');
            btn.disabled = true;
            btn.textContent = 'Loading…';

            try {
                const res = await fetch(`${btn.dataset.url}?offset=${col.children.length}&per_page=${perPage}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error();
                const data = await res.json();

                const tpl = document.createElement('template');
                tpl.innerHTML = data.html.trim();
                tpl.content.querySelectorAll('[data-lead]').forEach((card) => {
                    // skip cards already on the board (e.g. dragged in from another column)
                    if (!document.querySelector(`[data-lead="${card.dataset.lead}"]`)) col.appendChild(card);
                });
            } catch (err) {
                Alpine.$data(alpineRoot()).error = 'Could not load more leads. Please try again.';
            } finally {
                btn.disabled = false;
                refreshColumn(col);
            }
        });

        columns().forEach((column) => {
            refreshColumn(column);

            new Sortable(column, {
                group: 'leads',
                animation: 150,
                onEnd: async (evt) => {
                    if (evt.from === evt.to) return;
                    shift(evt.from, -1);
                    shift(evt.to, 1);

                    try {
                        const res = await fetch(`/leads/${evt.item.dataset.lead}/status`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            },
                            body: JSON.stringify({ lead_status_id: evt.to.dataset.status }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok) throw new Error(data.message || '');
                        Alpine.$data(alpineRoot()).error = null;
                        Alpine.$data(alpineRoot()).convert = data.convert_url ? { url: data.convert_url, name: data.lead_name } : null;
                    } catch (e) {
                        // revert the card to where it came from
                        evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex] ?? null);
                        shift(evt.from, 1);
                        shift(evt.to, -1);
                        Alpine.$data(alpineRoot()).error = e.message || 'Could not move the lead. Please try again.';
                    }
                },
            });
        });
    </script>
</x-app-layout>
