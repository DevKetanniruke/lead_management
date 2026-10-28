@php
    // Tone palette shared by the KPI cards (full class names so the Tailwind scanner picks them up).
    $tones = [
        'indigo' => ['chip' => 'bg-indigo-50 text-indigo-600 ring-indigo-100', 'bar' => 'from-indigo-500 to-violet-500'],
        'sky' => ['chip' => 'bg-sky-50 text-sky-600 ring-sky-100', 'bar' => 'from-sky-400 to-cyan-500'],
        'violet' => ['chip' => 'bg-violet-50 text-violet-600 ring-violet-100', 'bar' => 'from-violet-500 to-fuchsia-500'],
        'emerald' => ['chip' => 'bg-emerald-50 text-emerald-600 ring-emerald-100', 'bar' => 'from-emerald-400 to-teal-500'],
        'teal' => ['chip' => 'bg-teal-50 text-teal-600 ring-teal-100', 'bar' => 'from-teal-400 to-emerald-500'],
        'slate' => ['chip' => 'bg-slate-100 text-slate-600 ring-slate-200', 'bar' => 'from-slate-300 to-slate-400'],
        'blue' => ['chip' => 'bg-blue-50 text-blue-600 ring-blue-100', 'bar' => 'from-blue-500 to-indigo-500'],
        'amber' => ['chip' => 'bg-amber-50 text-amber-600 ring-amber-100', 'bar' => 'from-amber-400 to-orange-500'],
        'red' => ['chip' => 'bg-red-50 text-red-600 ring-red-100', 'bar' => 'from-red-500 to-rose-500'],
    ];

    // key => [label, icon, tone]; values come from $initial and are refreshed via Alpine.
    $groups = [
        'leads' => [
            'title' => 'Leads & clients',
            'subtitle' => 'Pipeline health and customer growth',
            'icon' => 'leads',
            'cols' => 'sm:grid-cols-2 xl:grid-cols-4',
            'cards' => [
                'total' => ['Total leads', 'leads', 'indigo'],
                'new' => ['New leads', 'sparkles', 'sky'],
                'qualified' => ['Qualified leads', 'pipeline', 'violet'],
                'converted' => ['Converted clients', 'trophy', 'emerald'],
                'conversion_rate' => ['Conversion rate', 'convert', 'teal'],
                'lost' => ['Lost leads', 'ban', 'slate'],
                'active_clients' => ['Active clients', 'clients', 'blue'],
                'followups' => ['Follow-ups due', 'clock', 'amber'],
            ],
        ],
        'finance' => [
            'title' => 'Invoicing & payments',
            'subtitle' => 'Billing, collections and receivables',
            'icon' => 'invoice',
            'cols' => 'sm:grid-cols-2 xl:grid-cols-3',
            'cards' => [
                'invoiced' => ['Total invoiced', 'invoice', 'indigo'],
                'received' => ['Payments received', 'payments', 'emerald'],
                'outstanding' => ['Outstanding', 'clock', 'amber'],
                'overdue' => ['Overdue amount', 'alert', 'amber'],
                'paid_invoices' => ['Paid invoices', 'check', 'emerald'],
                'unpaid_invoices' => ['Unpaid invoices', 'invoice', 'slate'],
            ],
        ],
    ];

    $stageColors = [
        'blue' => '#3b82f6', 'indigo' => '#6366f1', 'purple' => '#a855f7', 'yellow' => '#eab308',
        'orange' => '#f97316', 'green' => '#22c55e', 'red' => '#ef4444',
    ];
    $agingColors = ['#86b6ef', '#5598e7', '#2a78d6', '#1c5cab', '#104281'];
    $avatarTones = ['bg-indigo-100 text-indigo-700', 'bg-sky-100 text-sky-700', 'bg-emerald-100 text-emerald-700', 'bg-amber-100 text-amber-700', 'bg-rose-100 text-rose-700', 'bg-violet-100 text-violet-700', 'bg-teal-100 text-teal-700'];
    $initials = fn (?string $s) => collect(preg_split('/\s+/', trim((string) $s)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') ?: '?';
    $tone = fn (?string $s) => $avatarTones[crc32((string) $s) % count($avatarTones)];

    $totalStageLeads = $stages->sum('count');
    $maxStage = max(1, $stages->max('count'));
    $currency = $initial['charts']['monthly']['currency'];
    $agingTotal = collect($aging)->sum(fn ($b) => (float) $b['amount']);

    $user = auth()->user();
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

    $quickActions = array_filter([
        Route::has('invoices.create') && $user->can('create', App\Models\Invoice::class) ? ['New invoice', 'invoice', route('invoices.create')] : null,
        Route::has('payments.create') && $user->can('create', App\Models\Payment::class) ? ['Record payment', 'payments', route('payments.create')] : null,
        ['Follow-ups', 'clock', route('follow-ups.index')],
    ]);
@endphp

<x-app-layout title="Dashboard">
    <x-slot name="header">
        <div class="flex items-center gap-2 text-sm">
            <x-icon name="home" class="h-4 w-4 text-slate-400" />
            <span class="font-semibold text-slate-900">Dashboard</span>
            <span class="hidden text-slate-300 sm:inline">/</span>
            <span class="hidden text-slate-500 sm:inline">{{ now()->format('l, F j, Y') }}</span>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-8" x-data="dashboardCards(@js($initial), '{{ route('dashboard.stats') }}')">

        {{-- Hero: greeting, quick actions and period control --}}
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950 to-indigo-800 px-6 py-7 text-white shadow-xl shadow-indigo-900/10 sm:px-8 sm:py-8">
            <div aria-hidden="true" class="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-indigo-500/30 blur-3xl"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-28 left-1/3 h-64 w-64 rounded-full bg-violet-500/20 blur-3xl"></div>
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;"></div>

            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-200/80">{{ $greeting }} · {{ now()->format('l, F j') }}</p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">Welcome back, {{ strtok($user->name, ' ') }} <span aria-hidden="true">👋</span></h1>
                    <p class="mt-2 max-w-xl text-sm text-indigo-100/80">
                        Here's how your leads, clients and invoices are tracking for
                        <span class="font-semibold text-white" x-text="label">{{ $initial['label'] }}</span>.
                    </p>

                    <div class="mt-5 flex flex-wrap items-center gap-2">
                        <a href="{{ route('leads.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-white/60">
                            <x-icon name="plus" class="h-4 w-4" /> New lead
                        </a>
                        @foreach ($quickActions as [$qaLabel, $qaIcon, $qaHref])
                            <a href="{{ $qaHref }}" class="inline-flex items-center gap-2 rounded-xl bg-white/10 px-3.5 py-2 text-sm font-medium text-white ring-1 ring-inset ring-white/15 backdrop-blur transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/60">
                                <x-icon :name="$qaIcon" class="h-4 w-4 opacity-80" /> {{ $qaLabel }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <div class="flex flex-col gap-3 lg:items-end">
                    <div class="flex items-center gap-2 text-xs text-indigo-100/80">
                        <template x-if="!error">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 ring-1 ring-inset ring-white/10">
                                <span class="relative flex h-2 w-2">
                                    <span class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" :class="loading && 'animate-ping'"></span>
                                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                                </span>
                                Live · updated <span x-text="updatedAt">{{ $initial['updated_at'] }}</span>
                            </span>
                        </template>
                        <button type="button" x-show="error" x-cloak @click="refresh(true)" class="inline-flex items-center gap-1.5 rounded-full bg-red-500/20 px-2.5 py-1 font-medium text-red-100 ring-1 ring-inset ring-red-300/30 hover:bg-red-500/30">
                            <x-icon name="refresh" class="h-3.5 w-3.5" /> Couldn't refresh · retry
                        </button>
                        <button type="button" x-show="!error" @click="refresh(true)" class="rounded-full p-1.5 text-indigo-100/80 transition hover:bg-white/10 hover:text-white" aria-label="Refresh now">
                            <x-icon name="refresh" class="h-4 w-4" ::class="loading && 'animate-spin'" />
                        </button>
                    </div>

                    <div class="no-scrollbar -mx-1 max-w-full overflow-x-auto px-1">
                        <div class="inline-flex rounded-xl bg-white/10 p-1 ring-1 ring-inset ring-white/15 backdrop-blur" role="group" aria-label="Period">
                            @foreach ($periods as $key => $periodLabel)
                                <button type="button" @click="{{ $key === 'custom' ? 'showCustom = !showCustom' : "setPeriod('$key')" }}"
                                        :class="period === '{{ $key }}' ? 'bg-white text-indigo-700 shadow-sm' : 'text-indigo-100 hover:bg-white/10 hover:text-white'"
                                        :aria-pressed="period === '{{ $key }}'"
                                        class="whitespace-nowrap rounded-lg px-3 py-1.5 text-xs font-semibold transition">{{ $periodLabel }}</button>
                            @endforeach
                        </div>
                    </div>

                    <form x-show="showCustom" x-cloak x-transition @submit.prevent="setCustom()" class="flex flex-wrap items-center gap-2 [color-scheme:dark]">
                        <input type="date" x-model="from" max="{{ now()->toDateString() }}" required aria-label="From date" class="rounded-lg border-white/20 bg-white/10 py-1.5 text-sm text-white focus:border-white/40 focus:ring-white/30">
                        <span class="text-xs text-indigo-200">to</span>
                        <input type="date" x-model="to" required aria-label="To date" class="rounded-lg border-white/20 bg-white/10 py-1.5 text-sm text-white focus:border-white/40 focus:ring-white/30">
                        <button class="rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Apply</button>
                    </form>
                </div>
            </div>
        </section>

        {{-- Attention strip: only when something is overdue --}}
        <div x-show="cards.overdue.alert || cards.followups.alert" @if (! (($initial['cards']['overdue']['alert'] ?? false) || ($initial['cards']['followups']['alert'] ?? false))) x-cloak @endif
             x-transition class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-gradient-to-r from-amber-50 to-orange-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700"><x-icon name="alert" class="h-5 w-5" /></span>
                <div>
                    <p class="text-sm font-semibold text-amber-900">A few things need your attention</p>
                    <p class="text-xs text-amber-800/80">Clearing overdue items keeps cash flow and follow-ups on track.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a x-show="cards.overdue.alert" :href="cards.overdue.href" href="{{ $initial['cards']['overdue']['href'] }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-amber-800 shadow-sm ring-1 ring-inset ring-amber-200 hover:bg-amber-50">
                    <x-icon name="invoice" class="h-3.5 w-3.5" /> <span x-text="cards.overdue.sub">{{ $initial['cards']['overdue']['sub'] }}</span>
                </a>
                <a x-show="cards.followups.alert" :href="cards.followups.href" href="{{ $initial['cards']['followups']['href'] }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-amber-800 shadow-sm ring-1 ring-inset ring-amber-200 hover:bg-amber-50">
                    <x-icon name="clock" class="h-3.5 w-3.5" /> <span x-text="cards.followups.sub">{{ $initial['cards']['followups']['sub'] }}</span>
                </a>
            </div>
        </div>

        {{-- KPI cards: server-rendered first, then kept fresh by Alpine --}}
        @foreach ($groups as $group)
            <section aria-labelledby="kpi-{{ $loop->index }}">
                <div class="mb-4 flex items-end justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white text-slate-500 shadow-sm ring-1 ring-slate-200"><x-icon :name="$group['icon']" class="h-4 w-4" /></span>
                        <div>
                            <h2 id="kpi-{{ $loop->index }}" class="text-base font-semibold text-slate-900">{{ $group['title'] }}</h2>
                            <p class="text-xs text-slate-500">{{ $group['subtitle'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 {{ $group['cols'] }} transition-opacity duration-150" :class="loading && 'opacity-60'">
                    @foreach ($group['cards'] as $key => [$cardLabel, $icon, $toneKey])
                        @php
                            $c = $initial['cards'][$key];
                            $alertable = array_key_exists('alert', $c);
                            $isAlert = $c['alert'] ?? false;
                            $t = $tones[$toneKey];
                            $a = $tones['red'];
                        @endphp
                        <a href="{{ $c['href'] }}" :href="cards.{{ $key }}.href"
                           class="group relative flex flex-col overflow-hidden rounded-2xl border bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)] transition duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-slate-200/70 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 {{ $isAlert ? 'border-red-200' : 'border-slate-200/80' }}"
                           @if ($alertable) :class="cards.{{ $key }}.alert ? 'border-red-200' : 'border-slate-200/80'" @endif>
                            {{-- Accent line --}}
                            <span aria-hidden="true" class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r opacity-0 transition group-hover:opacity-100 {{ $isAlert ? $a['bar'] : $t['bar'] }}"
                                  @if ($alertable) :class="cards.{{ $key }}.alert ? '{{ $a['bar'] }}' : '{{ $t['bar'] }}'" @endif></span>

                            <div class="flex items-start justify-between gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset {{ $isAlert ? $a['chip'] : $t['chip'] }}"
                                      @if ($alertable) :class="cards.{{ $key }}.alert ? '{{ $a['chip'] }}' : '{{ $t['chip'] }}'" @endif>
                                    @if ($alertable)
                                        <x-icon name="alert" class="h-5 w-5" x-show="cards.{{ $key }}.alert" :x-cloak="! $isAlert" />
                                        <x-icon :name="$icon" class="h-5 w-5" x-show="!cards.{{ $key }}.alert" :x-cloak="$isAlert" />
                                    @else
                                        <x-icon :name="$icon" class="h-5 w-5" />
                                    @endif
                                </span>

                                <span x-show="cards.{{ $key }}.trend !== null" @if ($c['trend'] === null) x-cloak @endif
                                      class="inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset"
                                      :class="cards.{{ $key }}.trend >= 0 ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-red-50 text-red-700 ring-red-200'"
                                      :title="'Compared with the previous period'">
                                    <span x-text="cards.{{ $key }}.trend >= 0 ? '↑' : '↓'"></span>
                                    <span x-text="Math.abs(cards.{{ $key }}.trend) + '%'">{{ $c['trend'] !== null ? abs($c['trend']).'%' : '' }}</span>
                                </span>
                            </div>

                            <p class="mt-4 text-sm font-medium text-slate-500">{{ $cardLabel }}</p>
                            <p class="mt-1 truncate text-[1.65rem] font-bold leading-tight tracking-tight text-slate-900 tabular-nums" x-text="cards.{{ $key }}.value" title="{{ $c['value'] }}">{{ $c['value'] }}</p>

                            <div class="mt-3 flex items-center justify-between gap-2 border-t border-slate-100 pt-3">
                                <p class="truncate text-xs text-slate-500" x-text="cards.{{ $key }}.sub">{{ $c['sub'] }}</p>
                                <span aria-hidden="true" class="shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-indigo-500">&rarr;</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach

        {{-- Charts --}}
        <section class="space-y-6" aria-label="Charts">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <x-dashboard.panel title="Lead conversion trend" subtitle="Leads created vs converted" icon="convert" class="lg:col-span-2">
                    <div class="mb-4 grid grid-cols-3 gap-3">
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <p class="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-slate-500"><span class="h-2 w-2 rounded-full" style="background:#2a78d6"></span>Created</p>
                            <p class="mt-0.5 text-lg font-semibold text-slate-900 tabular-nums" x-text="trendTotals.created.toLocaleString()"></p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <p class="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-slate-500"><span class="h-2 w-2 rounded-full" style="background:#eb6834"></span>Converted</p>
                            <p class="mt-0.5 text-lg font-semibold text-slate-900 tabular-nums" x-text="trendTotals.converted.toLocaleString()"></p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Rate</p>
                            <p class="mt-0.5 text-lg font-semibold text-slate-900 tabular-nums" x-text="trendTotals.rate"></p>
                        </div>
                    </div>
                    <div class="h-64"><canvas id="dash-trend" role="img" aria-label="Leads created versus converted over the selected period"></canvas></div>
                </x-dashboard.panel>

                <x-dashboard.panel title="Pipeline stages" subtitle="{{ number_format($totalStageLeads) }} leads across {{ $stages->count() }} stages" icon="pipeline" :href="route('pipeline.index')" link="Open">
                    <div class="space-y-4">
                        @forelse ($stages as $s)
                            @php $color = $stageColors[$s['status']->color] ?? '#94a3b8'; $share = $totalStageLeads ? round($s['count'] / $totalStageLeads * 100) : 0; @endphp
                            <div>
                                <div class="mb-1.5 flex items-center justify-between gap-2 text-xs">
                                    <span class="flex min-w-0 items-center gap-2 font-medium text-slate-700">
                                        <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $color }}"></span>
                                        <span class="truncate">{{ $s['status']->name }}</span>
                                    </span>
                                    <span class="shrink-0 tabular-nums text-slate-500"><span class="font-semibold text-slate-900">{{ number_format($s['count']) }}</span> · {{ $share }}%</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full transition-all duration-700" style="width: {{ max($s['count'] ? 3 : 0, round($s['count'] / $maxStage * 100)) }}%; background: {{ $color }};"></div>
                                </div>
                            </div>
                        @empty
                            <x-empty-state icon="pipeline" title="No stages configured" class="!py-6" />
                        @endforelse
                    </div>
                </x-dashboard.panel>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <x-dashboard.panel title="Invoiced vs collected" subtitle="Monthly billing against cash received" icon="payments" class="lg:col-span-2">
                    <div class="mb-4 grid grid-cols-3 gap-3">
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <p class="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-slate-500"><span class="h-2 w-2 rounded-sm" style="background:#2a78d6"></span>Invoiced</p>
                            <p class="mt-0.5 truncate text-lg font-semibold text-slate-900 tabular-nums" x-text="money(monthlyTotals.invoiced)"></p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <p class="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-slate-500"><span class="h-2 w-2 rounded-sm" style="background:#eb6834"></span>Collected</p>
                            <p class="mt-0.5 truncate text-lg font-semibold text-slate-900 tabular-nums" x-text="money(monthlyTotals.collected)"></p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Collection rate</p>
                            <p class="mt-0.5 text-lg font-semibold text-slate-900 tabular-nums" x-text="monthlyTotals.rate"></p>
                        </div>
                    </div>
                    <div class="h-64"><canvas id="dash-monthly" role="img" aria-label="Amount invoiced and collected per month"></canvas></div>
                </x-dashboard.panel>

                <x-dashboard.panel title="Receivables by age" subtitle="Outstanding balance by days past due" icon="clock">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total outstanding</p>
                    <p class="mt-0.5 text-2xl font-bold tracking-tight text-slate-900 tabular-nums">{{ money($agingTotal, $currency) }}</p>
                    <div class="mt-4 h-40"><canvas id="dash-aging" role="img" aria-label="Outstanding receivables by days past due"></canvas></div>
                    <dl class="mt-4 divide-y divide-slate-100 text-xs">
                        @foreach ($aging as $b)
                            <div class="flex items-center justify-between gap-2 py-2">
                                <dt class="flex items-center gap-2 text-slate-600"><span class="h-2 w-2 rounded-sm" style="background: {{ $agingColors[$loop->index] ?? '#94a3b8' }}"></span>{{ $b['label'] }}</dt>
                                <dd class="tabular-nums"><span class="font-semibold text-slate-900">{{ money($b['amount'], $currency) }}</span> <span class="text-slate-400">· {{ $b['count'] }}</span></dd>
                            </div>
                        @endforeach
                    </dl>
                </x-dashboard.panel>
            </div>
        </section>

        {{-- Alerts & activity --}}
        <section class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3" aria-label="Invoices and conversions">
            <x-dashboard.panel title="Overdue invoices" subtitle="Past their due date" icon="alert" :href="route('invoices.index', ['status' => 'overdue'])" :padding="false">
                <ul class="divide-y divide-slate-100">
                    @forelse ($overdueAlerts as $inv)
                        <li>
                            <a href="{{ route('invoices.show', $inv) }}" class="flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600"><x-icon name="invoice" class="h-4 w-4" /></span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $inv->number }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $inv->client?->displayName() }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-sm font-semibold text-slate-900 tabular-nums">{{ money($inv->balance_due, $inv->currency) }}</p>
                                    <span class="mt-0.5 inline-flex rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700">{{ $inv->daysOverdue() }}d overdue</span>
                                </div>
                            </a>
                        </li>
                    @empty
                        <li class="flex flex-col items-center px-5 py-10 text-center">
                            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"><x-icon name="check" class="h-5 w-5" /></span>
                            <p class="mt-2 text-sm font-semibold text-slate-900">All clear</p>
                            <p class="text-xs text-slate-500">No overdue invoices.</p>
                        </li>
                    @endforelse
                </ul>
            </x-dashboard.panel>

            <x-dashboard.panel title="Due soon" subtitle="Next 14 days" icon="calendar" :href="route('invoices.index', ['status' => 'unpaid'])" :padding="false">
                <ul class="divide-y divide-slate-100">
                    @forelse ($upcomingDue as $inv)
                        <li>
                            <a href="{{ route('invoices.show', $inv) }}" class="flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50">
                                <span class="flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-xl border {{ $inv->due_date->isToday() ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-slate-200 bg-white text-slate-700' }}">
                                    <span class="text-[9px] font-semibold uppercase leading-none">{{ $inv->due_date->format('M') }}</span>
                                    <span class="text-sm font-bold leading-tight">{{ $inv->due_date->format('j') }}</span>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $inv->number }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $inv->client?->displayName() }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-sm font-semibold text-slate-900 tabular-nums">{{ money($inv->balance_due, $inv->currency) }}</p>
                                    <p class="text-[11px] {{ $inv->due_date->isToday() ? 'font-semibold text-amber-700' : 'text-slate-500' }}">{{ $inv->due_date->isToday() ? 'Due today' : 'in '.(int) now()->startOfDay()->diffInDays($inv->due_date->copy()->startOfDay()).'d' }}</p>
                                </div>
                            </a>
                        </li>
                    @empty
                        <li class="flex flex-col items-center px-5 py-10 text-center">
                            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-400"><x-icon name="calendar" class="h-5 w-5" /></span>
                            <p class="mt-2 text-sm font-semibold text-slate-900">Nothing due soon</p>
                            <p class="text-xs text-slate-500">No invoices due in the next two weeks.</p>
                        </li>
                    @endforelse
                </ul>
            </x-dashboard.panel>

            <x-dashboard.panel title="Recent conversions" subtitle="Leads that became clients" icon="trophy" :href="route('clients.index')" link="Clients" :padding="false" class="md:col-span-2 xl:col-span-1">
                <ul class="divide-y divide-slate-100">
                    @forelse ($recentConversions as $client)
                        @php $name = $client->displayName(); @endphp
                        <li>
                            <a href="{{ route('clients.show', $client) }}" class="flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold {{ $tone($name) }}">{{ $initials($name) }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $name }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $client->accountManager?->name ?? 'No manager' }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700" title="{{ $client->converted_at->format('M j, Y') }}">{{ $client->converted_at->format('M j') }}</span>
                            </a>
                        </li>
                    @empty
                        <li class="flex flex-col items-center px-5 py-10 text-center">
                            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-400"><x-icon name="trophy" class="h-5 w-5" /></span>
                            <p class="mt-2 text-sm font-semibold text-slate-900">No conversions yet</p>
                            <p class="text-xs text-slate-500">Converted leads will show up here.</p>
                        </li>
                    @endforelse
                </ul>
            </x-dashboard.panel>
        </section>

        <section class="grid grid-cols-1 gap-6 lg:grid-cols-3" aria-label="Leads and follow-ups">
            <x-dashboard.panel title="Recent leads" subtitle="Latest additions to your pipeline" icon="leads" :href="route('leads.index')" :padding="false" class="lg:col-span-2">
                <ul class="divide-y divide-slate-100">
                    @forelse ($recentLeads as $lead)
                        <li>
                            <a href="{{ route('leads.show', $lead) }}" class="flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold {{ $tone($lead->name) }}">{{ $initials($lead->name) }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $lead->name }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $lead->company ?: 'No company' }} <span class="text-slate-300">·</span> {{ $lead->assignee?->name ?? 'Unassigned' }}</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <span class="hidden text-xs text-slate-400 sm:inline">{{ $lead->created_at->diffForHumans() }}</span>
                                    <x-lead-status-badge :status="$lead->status" />
                                </div>
                            </a>
                        </li>
                    @empty
                        <li><x-empty-state icon="leads" title="No leads yet" action="New lead" :href="route('leads.create')">Add your first lead to start building your pipeline.</x-empty-state></li>
                    @endforelse
                </ul>
            </x-dashboard.panel>

            <x-dashboard.panel title="Next follow-ups" subtitle="Your open tasks by due date" icon="clock" :href="route('follow-ups.index')" :padding="false">
                <ol class="relative px-5 py-2">
                    @forelse ($upcomingTasks as $task)
                        @php $late = $task->due_at->isPast(); @endphp
                        <li class="relative flex gap-3 py-3">
                            @unless ($loop->last)
                                <span aria-hidden="true" class="absolute left-[7px] top-7 h-full w-px bg-slate-200"></span>
                            @endunless
                            <span class="relative mt-1 flex h-4 w-4 shrink-0 items-center justify-center rounded-full ring-4 ring-white {{ $late ? 'bg-red-100' : 'bg-indigo-100' }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $late ? 'bg-red-500' : 'bg-indigo-500' }}"></span>
                            </span>
                            <div class="min-w-0 flex-1">
                                <a href="{{ $task->subjectUrl() }}" class="block truncate text-sm font-semibold text-slate-900 hover:text-indigo-600">{{ $task->subjectName() }}</a>
                                @if ($task->body)
                                    <p class="line-clamp-2 text-xs text-slate-500">{{ $task->body }}</p>
                                @endif
                                <p class="mt-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium {{ $late ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-600' }}">
                                    <x-icon name="clock" class="h-3 w-3" />
                                    {{ $late ? 'Overdue · ' : '' }}{{ $task->due_at->isToday() ? 'Today, '.$task->due_at->format('H:i') : $task->due_at->format('M j, H:i') }}
                                </p>
                            </div>
                        </li>
                    @empty
                        <li class="flex flex-col items-center py-10 text-center">
                            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"><x-icon name="check" class="h-5 w-5" /></span>
                            <p class="mt-2 text-sm font-semibold text-slate-900">You're all caught up</p>
                            <p class="text-xs text-slate-500">No open follow-ups.</p>
                        </li>
                    @endforelse
                </ol>
            </x-dashboard.panel>
        </section>

        @if ($performance)
            @php $maxWon = max(1, (float) $performance->max(fn ($r) => (float) $r['won_value'])); @endphp
            <x-dashboard.panel title="Top performers" subtitle="Sales by user · {{ $initial['label'] }}" icon="reports" :href="route('reports.index')" link="Full report" :padding="false">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/60 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                <th class="w-14 px-5 py-3">Rank</th>
                                <th class="px-5 py-3">User</th>
                                <th class="px-5 py-3 text-right">Leads</th>
                                <th class="px-5 py-3 text-right">Won</th>
                                <th class="px-5 py-3">Win rate</th>
                                <th class="px-5 py-3 text-right">Won value</th>
                                <th class="px-5 py-3 text-right">Collected</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($performance as $row)
                                @php
                                    $medal = ['bg-amber-100 text-amber-700 ring-amber-200', 'bg-slate-100 text-slate-600 ring-slate-200', 'bg-orange-100 text-orange-700 ring-orange-200'][$loop->index] ?? 'bg-white text-slate-500 ring-slate-200';
                                    $rate = $row['conversion_rate'];
                                @endphp
                                <tr class="transition hover:bg-slate-50/70">
                                    <td class="px-5 py-3">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold ring-1 ring-inset {{ $medal }}">{{ $loop->iteration }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <x-avatar :user="$row['user']" size="h-8 w-8 text-xs" />
                                            <span class="font-semibold text-slate-900">{{ $row['user']->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-right tabular-nums text-slate-600">{{ number_format($row['leads']) }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums text-slate-600">{{ number_format($row['won']) }}</td>
                                    <td class="min-w-[9rem] px-5 py-3">
                                        @if ($rate !== null)
                                            <div class="flex items-center gap-2">
                                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-500" style="width: {{ min(100, $rate) }}%"></div></div>
                                                <span class="w-10 text-right text-xs font-semibold tabular-nums text-slate-700">{{ $rate }}%</span>
                                            </div>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right">
                                        <span class="font-semibold tabular-nums text-slate-900">{{ money($row['won_value'], $currency) }}</span>
                                        <div class="ml-auto mt-1 h-1 w-20 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-400" style="width: {{ round((float) $row['won_value'] / $maxWon * 100) }}%"></div></div>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums text-slate-600">{{ money($row['collected'], $currency) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-dashboard.panel>
        @endif
    </div>

    @include('partials.charts')
    <script>
        function dashboardCards(initial, statsUrl) {
            const STORAGE_KEY = 'dashboard.period';
            const REFRESH_MS = 60000;
            const aging = @js(collect($aging)->values());
            const num = (arr) => (arr || []).reduce((s, v) => s + (parseFloat(v) || 0), 0);

            return {
                period: initial.period,
                from: initial.from,
                to: initial.to,
                label: initial.label,
                cards: initial.cards,
                charts: JSON.parse(JSON.stringify(initial.charts)),
                updatedAt: initial.updated_at,
                showCustom: initial.period === 'custom',
                loading: false,
                error: false,
                requestId: 0,

                get trendTotals() {
                    const created = num(this.charts.trend.created), converted = num(this.charts.trend.converted);
                    return { created, converted, rate: created ? Math.round(converted / created * 100) + '%' : '—' };
                },

                get monthlyTotals() {
                    const invoiced = num(this.charts.monthly.invoiced), collected = num(this.charts.monthly.collected);
                    return { invoiced, collected, rate: invoiced ? Math.round(collected / invoiced * 100) + '%' : '—' };
                },

                money(v) {
                    const currency = this.charts.monthly.currency;
                    try {
                        return new Intl.NumberFormat(currency === 'INR' ? 'en-IN' : 'en-US', { style: 'currency', currency, notation: v >= 1e6 ? 'compact' : 'standard', maximumFractionDigits: v >= 1e6 ? 1 : 0 }).format(v);
                    } catch (e) { return v.toLocaleString(); }
                },

                init() {
                    this.drawCharts(initial.charts);
                    CrmCharts.bar('dash-aging', aging.map((b) => b.label), [{ label: 'Outstanding', data: aging.map((b) => parseFloat(b.amount)) }],
                        { money: true, currency: initial.charts.monthly.currency, ordinal: true });

                    // Restore the last chosen preset (per browser), then keep numbers fresh.
                    try {
                        const saved = localStorage.getItem(STORAGE_KEY);
                        if (saved && saved !== this.period && !new URLSearchParams(location.search).has('period')) {
                            this.setPeriod(saved);
                        }
                    } catch (e) {}

                    setInterval(() => this.refresh(), REFRESH_MS);
                    document.addEventListener('visibilitychange', () => { if (!document.hidden) this.refresh(); });
                },

                drawCharts(charts) {
                    this.charts = JSON.parse(JSON.stringify(charts));
                    CrmCharts.line('dash-trend', charts.trend.labels, [
                        { label: 'Created', data: charts.trend.created },
                        { label: 'Converted', data: charts.trend.converted },
                    ]);
                    CrmCharts.bar('dash-monthly', charts.monthly.labels, [
                        { label: 'Invoiced', data: charts.monthly.invoiced },
                        { label: 'Collected', data: charts.monthly.collected },
                    ], { money: true, currency: charts.monthly.currency });
                },

                setPeriod(period) {
                    this.period = period;
                    this.showCustom = false;
                    try { localStorage.setItem(STORAGE_KEY, period); } catch (e) {}
                    return this.refresh(true);
                },

                setCustom() {
                    if (!this.from || !this.to) return;
                    this.period = 'custom';
                    return this.refresh(true);
                },

                async refresh(force = false) {
                    if (document.hidden && !force) return;

                    const id = ++this.requestId;
                    this.loading = true;
                    const params = new URLSearchParams({ period: this.period });
                    if (this.period === 'custom') { params.set('from', this.from); params.set('to', this.to); }

                    try {
                        const res = await fetch(`${statsUrl}?${params}`, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (!res.ok) throw new Error(res.status);
                        const data = await res.json();

                        // Ignore stale responses if the user switched period again meanwhile.
                        if (id !== this.requestId) return;

                        const chartsChanged = force || data.period !== this.period;
                        this.cards = data.cards;
                        this.label = data.label;
                        this.period = data.period;
                        this.from = data.from;
                        this.to = data.to;
                        this.updatedAt = data.updated_at;
                        this.error = false;
                        if (chartsChanged) this.drawCharts(data.charts);
                    } catch (e) {
                        if (id === this.requestId) this.error = true;
                    } finally {
                        if (id === this.requestId) this.loading = false;
                    }
                },
            };
        }
    </script>
</x-app-layout>
