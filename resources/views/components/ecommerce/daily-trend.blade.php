@props([
    'dateRange'      => [],
    'dailySales'     => [],
    'dailyPurchases' => [],
    'dailyEntradas'  => [],
    'dailySalidas'   => [],
])

<div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">

    <div class="mb-6">
        <h3 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#FF4622]/10 text-[#FF4622]">
                <i class="ri-bar-chart-2-line text-xl"></i>
            </span>
            Tendencia Diaria
        </h3>
    </div>

    {{-- Gráficas --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Ventas vs Compras --}}
        <div class="rounded-xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
            <p class="mb-3 text-sm font-semibold text-[#2979ff] dark:text-blue-400 flex items-center gap-2">
                <i class="ri-bar-chart-grouped-line"></i> Ventas y Compras diario
            </p>
            <div id="chartDailyVentasCompras" class="min-h-[260px]"></div>
        </div>
        {{-- Entradas vs Salidas --}}
        <div class="rounded-xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
            <p class="mb-3 text-sm font-semibold text-[#008000] dark:text-green-400 flex items-center gap-2">
                <i class="ri-bar-chart-grouped-line"></i> Entradas y Salidas diario
            </p>
            <div id="chartDailyEntradasSalidas" class="min-h-[260px]"></div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('turbo:load', function () {
        const el1 = document.getElementById('chartDailyVentasCompras');
        const el2 = document.getElementById('chartDailyEntradasSalidas');
        if (!el1 || !el2) return;

        // Destruir instancias previas para evitar duplicados al navegar
        if (window._chartDailyVC) {
            try { window._chartDailyVC.destroy(); } catch(e) {}
            window._chartDailyVC = null;
        }
        if (window._chartDailyES) {
            try { window._chartDailyES.destroy(); } catch(e) {}
            window._chartDailyES = null;
        }
        el1.innerHTML = '';
        el2.innerHTML = '';

        const dates    = @json($dateRange);
        const ventas   = @json($dailySales);
        const compras  = @json($dailyPurchases);
        const entradas = @json($dailyEntradas);
        const salidas  = @json($dailySalidas);

        // Mostrar solo dd/mm para que quepan
        const dateLabels = dates.map(d => {
            const parts = d.split('-');
            return parts[2] + '/' + parts[1];
        });

        const sharedOptions = {
            chart: {
                type: 'bar',
                height: 260,
                toolbar: { show: false },
                fontFamily: 'inherit',
                parentHeightOffset: 0,
            },
            xaxis: {
                categories: dateLabels,
                labels: {
                    rotate: -45,
                    style: { fontSize: '10px' },
                    trim: false,
                },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: {
                labels: {
                    style: { fontSize: '10px' },
                    formatter: v => {
                        if (v >= 1000) return 'S/' + (v / 1000).toFixed(1) + 'k';
                        return 'S/' + v.toFixed(0);
                    },
                },
            },
            dataLabels: { enabled: false },
            plotOptions: { bar: { borderRadius: 3, columnWidth: '60%' } },
            tooltip: { y: { formatter: v => 'S/' + v.toFixed(2) } },
            legend: { position: 'top', fontSize: '11px' },
            grid: { strokeDashArray: 4, borderColor: '#e5e7eb', padding: { left: 4, right: 4 } },
        };

        window._chartDailyVC = new ApexCharts(el1, {
            ...sharedOptions,
            series: [
                { name: 'Ventas',  data: ventas  },
                { name: 'Compras', data: compras },
            ],
            colors: ['#2979ff', '#3f51b5'],
        });
        window._chartDailyVC.render();

        window._chartDailyES = new ApexCharts(el2, {
            ...sharedOptions,
            series: [
                { name: 'Entradas', data: entradas },
                { name: 'Salidas',  data: salidas  },
            ],
            colors: ['#008000', '#FFA500'],
        });
        window._chartDailyES.render();
    });
</script>
