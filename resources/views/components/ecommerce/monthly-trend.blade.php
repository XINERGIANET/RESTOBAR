@props([
    'monthlySales'     => [],
    'monthlyPurchases' => [],
    'incomeTrend'      => [],
    'expenseTrend'     => [],
    'monthlyLabels'    => [],
])

<div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">

    <h3 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2 mb-6">
        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#FF4622]/10 text-[#FF4622]">
            <i class="ri-line-chart-line text-xl"></i>
        </span>
        Tendencia Mensual
    </h3>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        <div class="rounded-xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
            <p class="mb-3 text-sm font-semibold text-[#2979ff] dark:text-blue-400 flex items-center gap-2">
                <i class="ri-bar-chart-grouped-line"></i> Ventas y Compras Mensual
            </p>
            <div id="chartMonthlyVC" class="w-full" style="height:260px; overflow:hidden;"></div>
        </div>

        <div class="rounded-xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
            <p class="mb-3 text-sm font-semibold text-[#008000] dark:text-green-400 flex items-center gap-2">
                <i class="ri-bar-chart-grouped-line"></i> Entradas y Salidas (Últimos Meses)
            </p>
            <div id="chartMonthlyES" class="w-full" style="height:260px; overflow:hidden;"></div>
        </div>

    </div>
</div>

<script>
    document.addEventListener('turbo:load', function () {
        const el1 = document.getElementById('chartMonthlyVC');
        const el2 = document.getElementById('chartMonthlyES');
        if (!el1 || !el2) return;

        if (window._chartMonthlyVC) {
            try { window._chartMonthlyVC.destroy(); } catch(e) {}
            window._chartMonthlyVC = null;
        }
        if (window._chartMonthlyES) {
            try { window._chartMonthlyES.destroy(); } catch(e) {}
            window._chartMonthlyES = null;
        }
        el1.innerHTML = '';
        el2.innerHTML = '';

        const labels   = @json($monthlyLabels);
        const ventas   = @json($monthlySales);
        const compras  = @json($monthlyPurchases);
        const entradas = @json($incomeTrend);
        const salidas  = @json($expenseTrend);

        const sharedOptions = {
            chart: {
                type: 'bar',
                height: 260,
                width: '100%',
                toolbar: { show: false },
                fontFamily: 'inherit',
                parentHeightOffset: 0,
                redrawOnWindowResize: true,
            },
            xaxis: {
                categories: labels,
                labels: {
                    rotate: 0,
                    style: { fontSize: '11px' },
                },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: {
                labels: {
                    style: { fontSize: '10px' },
                    formatter: v => v >= 1000 ? 'S/' + (v/1000).toFixed(0)+'k' : 'S/'+v.toFixed(0),
                },
            },
            dataLabels: { enabled: false },
            plotOptions: {
                bar: {
                    borderRadius: 3,
                    columnWidth: Math.max(20, Math.min(60, 300 / Math.max(labels.length, 1))) + '%',
                },
            },
            tooltip: { y: { formatter: v => 'S/' + Number(v).toFixed(2) } },
            legend: { position: 'top', fontSize: '11px' },
            grid: { strokeDashArray: 4, borderColor: '#e5e7eb', padding: { left: 0, right: 0 } },
        };

        window._chartMonthlyVC = new ApexCharts(el1, {
            ...sharedOptions,
            series: [
                { name: 'Ventas',  data: ventas  },
                { name: 'Compras', data: compras },
            ],
            colors: ['#2979ff', '#3f51b5'],
        });
        window._chartMonthlyVC.render();

        window._chartMonthlyES = new ApexCharts(el2, {
            ...sharedOptions,
            series: [
                { name: 'Entradas', data: entradas },
                { name: 'Salidas',  data: salidas  },
            ],
            colors: ['#008000', '#FFA500'],
        });
        window._chartMonthlyES.render();
    });
</script>
