@php
    $cur = $finance['currency'];
    $stageRows = $stages->map(fn ($s) => [$s['status']->name, $s['count'], money($s['value'])]);
@endphp

<x-app-layout title="Reports">
    <x-slot name="header">
        <h2 class="truncate text-lg font-semibold text-slate-900">Reports</h2>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h3 class="text-xl font-semibold tracking-tight text-slate-900">{{ $range->label() }}</h3>
                <p class="text-sm text-slate-500">{{ $range->from->format('M j, Y') }} – {{ $range->to->format('M j, Y') }} · amounts in {{ $cur }}</p>
            </div>
            @include('partials.range-filter', ['route' => 'reports.index'])
        </div>

        {{-- CRM metrics --}}
        <section aria-labelledby="crm-heading">
            <h4 id="crm-heading" class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Leads &amp; clients</h4>
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-7">
                <x-stat label="Total leads" :value="number_format($crm['total_leads'])" sub="All time" />
                <x-stat label="New leads" :value="number_format($crm['new_leads'])" sub="Created in period" />
                <x-stat label="Qualified" :value="number_format($crm['qualified_leads'])" sub="Reached Qualified+" />
                <x-stat label="Converted" :value="number_format($crm['converted_clients'])" sub="New clients" />
                <x-stat label="Conversion rate" :value="$crm['conversion_rate'] !== null ? $crm['conversion_rate'].'%' : '—'" sub="Of leads created" />
                <x-stat label="Lost" :value="number_format($crm['lost_leads'])" sub="Marked lost" />
                <x-stat label="Active clients" :value="number_format($crm['active_clients'])" sub="Current" />
            </div>
        </section>

        {{-- Financial metrics --}}
        <section aria-labelledby="fin-heading">
            <h4 id="fin-heading" class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Invoicing &amp; payments</h4>
            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
                <x-stat label="Total invoiced" :value="money($finance['total_invoiced'], $cur)" sub="Issued in period" />
                <x-stat label="Payments received" :value="money($finance['payments_received'], $cur)" sub="In period" />
                <x-stat label="Outstanding" :value="money($finance['outstanding'], $cur)" sub="As of today" />
                <x-stat label="Overdue" :value="money($finance['overdue_amount'], $cur)" :sub="$finance['overdue_count'].' invoices · today'" :alert="$finance['overdue_count'] > 0" />
                <x-stat label="Paid invoices" :value="number_format($finance['paid_invoices'])" sub="Issued in period" />
                <x-stat label="Unpaid invoices" :value="number_format($finance['unpaid_invoices'])" sub="Issued in period" />
            </div>
        </section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-card title="Lead conversion trend">
                <div class="h-64"><canvas id="chart-trend" role="img" aria-label="Leads created versus converted over time"></canvas></div>
                @include('reports._table', ['headers' => ['Period', 'Created', 'Converted'], 'rows' => collect($trend['labels'])->map(fn ($l, $i) => [$l, $trend['created'][$i], $trend['converted'][$i]])])
            </x-card>

            <x-card title="Leads by pipeline stage">
                <div class="h-64"><canvas id="chart-stages" role="img" aria-label="Number of leads in each pipeline stage"></canvas></div>
                @include('reports._table', ['headers' => ['Stage', 'Leads', 'Value'], 'rows' => $stageRows])
            </x-card>

            <x-card title="Invoiced vs collected (monthly)">
                <x-slot name="actions"><a href="{{ route('reports.export', ['report' => 'monthly'] + $range->query()) }}" class="text-sm font-medium text-indigo-600 hover:underline">CSV</a></x-slot>
                <div class="h-64"><canvas id="chart-monthly" role="img" aria-label="Amount invoiced and collected per month"></canvas></div>
                @include('reports._table', ['headers' => ['Month', 'Invoiced', 'Collected'], 'rows' => collect($monthly['labels'])->map(fn ($l, $i) => [$l, money($monthly['invoiced'][$i], $cur), money($monthly['collected'][$i], $cur)])])
            </x-card>

            <x-card title="Outstanding receivables by age">
                <x-slot name="actions"><a href="{{ route('reports.export', ['report' => 'aging']) }}" class="text-sm font-medium text-indigo-600 hover:underline">CSV</a></x-slot>
                <div class="h-64"><canvas id="chart-aging" role="img" aria-label="Outstanding amount by days past due"></canvas></div>
                @include('reports._table', ['headers' => ['Age', 'Invoices', 'Outstanding'], 'rows' => collect($aging)->map(fn ($b) => [$b['label'], $b['count'], money($b['amount'], $cur)])->values()])
            </x-card>
        </div>

        <x-card title="Sales performance by user" :padding="false">
            <x-slot name="actions"><a href="{{ route('reports.export', ['report' => 'sales-performance'] + $range->query()) }}" class="text-sm font-medium text-indigo-600 hover:underline">CSV</a></x-slot>
            @php $maxWon = max(1, (float) $performance->max(fn ($r) => (float) $r['won_value'])); @endphp
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="w-12 px-5 py-3 text-slate-500">S.No</th>
                            <th class="px-5 py-3">User</th>
                            <th class="px-5 py-3 text-right">Leads</th>
                            <th class="px-5 py-3 text-right">Open</th>
                            <th class="px-5 py-3 text-right">Won</th>
                            <th class="px-5 py-3 text-right">Lost</th>
                            <th class="px-5 py-3 text-right">Win rate</th>
                            <th class="px-5 py-3">Won value</th>
                            <th class="px-5 py-3 text-right">Invoiced</th>
                            <th class="px-5 py-3 text-right">Collected</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($performance as $row)
                            <tr>
                                <td class="whitespace-nowrap px-5 py-3 text-xs font-medium text-slate-500">{{ $loop->iteration }}</td>
                                <td class="whitespace-nowrap px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <x-avatar :user="$row['user']" size="h-7 w-7 text-xs" />
                                        <div>
                                            <p class="font-medium text-slate-900">{{ $row['user']->name }}</p>
                                            <p class="text-xs text-slate-500">{{ $row['user']->roleLabel() }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right">{{ $row['leads'] }}</td>
                                <td class="px-5 py-3 text-right">{{ $row['open'] }}</td>
                                <td class="px-5 py-3 text-right font-medium">{{ $row['won'] }}</td>
                                <td class="px-5 py-3 text-right">{{ $row['lost'] }}</td>
                                <td class="px-5 py-3 text-right">{{ $row['conversion_rate'] !== null ? $row['conversion_rate'].'%' : '—' }}</td>
                                <td class="px-5 py-3" style="min-width: 10rem;">
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 flex-1 rounded-full bg-slate-100" aria-hidden="true">
                                            <div class="h-1.5 rounded-full" style="width: {{ round((float) $row['won_value'] / $maxWon * 100) }}%; background: #2a78d6;"></div>
                                        </div>
                                        <span class="whitespace-nowrap text-xs text-slate-700">{{ money($row['won_value'], $cur) }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">{{ money($row['invoiced'], $cur) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">{{ money($row['collected'], $cur) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10"><x-empty-state icon="team" title="No sales activity in this period" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">Win rate = won ÷ (won + lost) in the period. Invoiced and collected are for clients each user manages.</p>
        </x-card>
    </div>

    @include('partials.charts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const cur = @js($cur);
            CrmCharts.line('chart-trend', @js($trend['labels']), [
                { label: 'Created', data: @js($trend['created']) },
                { label: 'Converted', data: @js($trend['converted']) },
            ]);
            CrmCharts.bar('chart-stages', @js($stages->map(fn ($s) => $s['status']->name)->values()), [
                { label: 'Leads', data: @js($stages->pluck('count')->values()) },
            ], { horizontal: true });
            CrmCharts.bar('chart-monthly', @js($monthly['labels']), [
                { label: 'Invoiced', data: @js($monthly['invoiced']) },
                { label: 'Collected', data: @js($monthly['collected']) },
            ], { money: true, currency: cur });
            CrmCharts.bar('chart-aging', @js(collect($aging)->pluck('label')->values()), [
                { label: 'Outstanding', data: @js(collect($aging)->map(fn ($b) => (float) $b['amount'])->values()) },
            ], { money: true, currency: cur, ordinal: true });
        });
    </script>
</x-app-layout>
