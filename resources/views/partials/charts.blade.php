{{--
    Chart.js helpers shared by the dashboard and reports.
    Palette: reference categorical slots 1–2 (blue, orange) for two-series charts, slot 1 for single series,
    and the reference ordinal blue ramp (steps 250→650) for ordered buckets such as receivables aging.
    Every chart has a "View as table" fallback next to it, so no value depends on hover or color alone.
--}}
@once
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    window.CrmCharts = (() => {
        const SERIES = ['#2a78d6', '#eb6834'];
        const ORDINAL = ['#86b6ef', '#5598e7', '#2a78d6', '#1c5cab', '#104281'];
        const INK = '#52514e', GRID = '#eceef1', SURFACE = '#ffffff';
        const charts = {};

        if (window.Chart) {
            Chart.defaults.font.family = 'Inter, ui-sans-serif, system-ui, sans-serif';
            Chart.defaults.font.size = 12;
            Chart.defaults.color = INK;
            Chart.defaults.maintainAspectRatio = false;
        }

        const scales = (money, currency, stacked = false) => ({
            x: { grid: { display: false }, border: { color: GRID }, ticks: { maxRotation: 0, autoSkipPadding: 12 }, stacked },
            y: {
                beginAtZero: true, stacked,
                grid: { color: GRID, drawTicks: false }, border: { display: false },
                ticks: { padding: 8, precision: money ? undefined : 0, callback: (v) => money ? compact(v, currency) : v },
            },
        });

        const compact = (v, currency) => {
            try { return new Intl.NumberFormat(currency === 'INR' ? 'en-IN' : 'en-US', { style: 'currency', currency, notation: 'compact', maximumFractionDigits: 1 }).format(v); }
            catch (e) { return v; }
        };
        const full = (v, currency) => {
            try { return new Intl.NumberFormat(currency === 'INR' ? 'en-IN' : 'en-US', { style: 'currency', currency }).format(v); }
            catch (e) { return v; }
        };

        const tooltip = (money, currency) => ({
            backgroundColor: '#0f172a', padding: 10, cornerRadius: 6, boxPadding: 4, usePointStyle: true,
            titleColor: '#e2e8f0', bodyColor: '#ffffff', bodyFont: { weight: '600' },
            callbacks: { label: (ctx) => {
                const v = ctx.chart.options.indexAxis === 'y' ? ctx.parsed.x : ctx.parsed.y;
                return ` ${money ? full(v, currency) : v.toLocaleString()}  ${ctx.dataset.label}`;
            } },
        });

        function render(id, config) {
            const el = document.getElementById(id);
            if (!el || !window.Chart) return;
            charts[id]?.destroy();
            charts[id] = new Chart(el, config);
        }

        return {
            /** Two-series line chart with an index tooltip (the crosshair finds the X). */
            line(id, labels, series) {
                render(id, {
                    type: 'line',
                    data: { labels, datasets: series.map((s, i) => ({
                        label: s.label, data: s.data, borderColor: SERIES[i], backgroundColor: SERIES[i],
                        borderWidth: 2, tension: 0.25, pointRadius: labels.length > 20 ? 0 : 3, pointHoverRadius: 5,
                        pointBorderColor: SURFACE, pointBorderWidth: 2, pointStyle: 'line',
                    })) },
                    options: {
                        interaction: { mode: 'index', intersect: false },
                        plugins: { legend: { display: series.length > 1, position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 18 } }, tooltip: tooltip(false) },
                        scales: scales(false),
                    },
                });
            },

            /** Bar chart: one series uses slot 1, two series slots 1–2, `ordinal` uses the ordered ramp. */
            bar(id, labels, series, { money = false, currency = 'INR', ordinal = false, horizontal = false } = {}) {
                render(id, {
                    type: 'bar',
                    data: { labels, datasets: series.map((s, i) => ({
                        label: s.label, data: s.data,
                        backgroundColor: ordinal ? ORDINAL.slice(0, labels.length) : SERIES[i],
                        hoverBackgroundColor: ordinal ? ORDINAL.slice(0, labels.length) : SERIES[i],
                        borderRadius: 4, borderSkipped: 'start', borderWidth: 0,
                        maxBarThickness: 36, categoryPercentage: 0.7, barPercentage: series.length > 1 ? 0.92 : 0.8,
                    })) },
                    options: {
                        indexAxis: horizontal ? 'y' : 'x',
                        interaction: { mode: 'index', intersect: false },
                        plugins: { legend: { display: series.length > 1, position: 'top', align: 'end', labels: { boxWidth: 12, boxHeight: 12 } }, tooltip: tooltip(money, currency) },
                        scales: horizontal ? { x: scales(money, currency).y, y: scales(money, currency).x } : scales(money, currency),
                    },
                });
            },
        };
    })();
</script>
@endonce
