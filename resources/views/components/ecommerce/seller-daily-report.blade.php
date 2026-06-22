@props([
    'reportRows'             => [],
    'reportChartData'        => [],
    'reportChartDataCompras' => [],
    'reportMonthNames'       => [],
    'reportYear'             => null,
    'reportTotalVentas'      => 0,
    'reportTotalCompras'     => 0,
    'reportNroDias'          => 0,
    'reportPromedio'         => 0,
    'reportType'             => 'ventas',
])

@php $allMonths = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre']; @endphp

<div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">

    <h3 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2 mb-5">
        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#FF4622]/10 text-[#FF4622]">
            <i class="ri-calendar-line text-xl"></i>
        </span>
        Resumen de {{ ucfirst($reportType === 'ambos' ? 'Ventas y Compras' : $reportType) }} por Mes
        @if($reportYear)
            <span class="text-sm font-normal text-gray-400 ml-1">{{ $reportYear }}</span>
        @endif
    </h3>

    {{-- Filtro: solo tipo --}}
    <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-3 mb-6">
        <input type="hidden" name="start_date" value="{{ request('start_date', now()->format('Y-m-d')) }}">
        <input type="hidden" name="end_date"   value="{{ request('end_date',   now()->format('Y-m-d')) }}">

        <div class="flex flex-col gap-1">
            <label class="text-[11px] font-semibold uppercase tracking-widest text-gray-400">Ver</label>
            <select name="report_type"
                class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-[#FF4622]/40 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                <option value="ventas"  {{ $reportType === 'ventas'  ? 'selected' : '' }}>Ventas</option>
                <option value="compras" {{ $reportType === 'compras' ? 'selected' : '' }}>Compras</option>
                <option value="ambos"   {{ $reportType === 'ambos'   ? 'selected' : '' }}>Ambos</option>
            </select>
        </div>

        <button type="submit"
            class="h-10 rounded-lg bg-[#FF4622] px-5 text-sm font-semibold text-white shadow hover:bg-[#e03d1c] transition-colors flex items-center gap-1.5">
            <i class="ri-search-line"></i> Buscar
        </button>
    </form>

    {{-- Gráfica de línea mensual --}}
    <div class="mb-5 rounded-xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
        <p class="mb-3 text-sm font-semibold text-gray-600 dark:text-gray-300">
            Gráfica mensual de {{ $reportType === 'ambos' ? 'Ventas y Compras' : ucfirst($reportType) }}
        </p>
        <div id="chartSellerMonthly" style="height:220px; width:100%; overflow:hidden;"></div>
    </div>
</div>

<script>
    document.addEventListener('turbo:load', function () {

        // Gráfica mensual
        const chartEl = document.getElementById('chartSellerMonthly');
        if (chartEl) {
            if (window._chartSellerMonthly) {
                try { window._chartSellerMonthly.destroy(); } catch(e) {}
                window._chartSellerMonthly = null;
            }
            chartEl.innerHTML = '';

            const labels       = @json($reportMonthNames);
            const dataVentas   = @json($reportChartData);
            const dataCompras  = @json($reportChartDataCompras);
            const reportType   = @json($reportType);

            let series, colors;
            if (reportType === 'ambos') {
                series = [{ name: 'Ventas', data: dataVentas }, { name: 'Compras', data: dataCompras }];
                colors = ['#2979ff', '#f44336'];
            } else if (reportType === 'compras') {
                series = [{ name: 'Compras', data: dataCompras }];
                colors = ['#f44336'];
            } else {
                series = [{ name: 'Ventas', data: dataVentas }];
                colors = ['#2979ff'];
            }

            if ([...dataVentas, ...dataCompras].some(v => v > 0)) {
                window._chartSellerMonthly = new ApexCharts(chartEl, {
                    series,
                    chart: { type: 'line', height: 220, width: '100%', toolbar: { show: false }, fontFamily: 'inherit', parentHeightOffset: 0 },
                    stroke: { curve: 'smooth', width: 2 },
                    markers: { size: 4 },
                    colors,
                    xaxis: { categories: labels, labels: { style: { fontSize: '11px' } }, axisBorder: { show: false } },
                    yaxis: { labels: { formatter: v => v >= 1000 ? 'S/'+(v/1000).toFixed(1)+'k' : 'S/'+v.toFixed(0), style: { fontSize: '10px' } } },
                    legend: { position: 'top', fontSize: '11px' },
                    dataLabels: { enabled: false },
                    tooltip: { y: { formatter: v => 'S/' + v.toFixed(2) } },
                    grid: { strokeDashArray: 4, borderColor: '#e5e7eb', padding: { left: 0, right: 0 } },
                });
                window._chartSellerMonthly.render();
            }
        }

        // DataTable
        if (typeof $.fn === 'undefined' || !$.fn.DataTable) return;
        if ($.fn.DataTable.isDataTable('#sellerMonthlyTable')) $('#sellerMonthlyTable').DataTable().destroy();
        $('#sellerMonthlyTable').DataTable({
            dom: '<"flex flex-wrap items-center gap-2 mb-3"lB><"mb-2"f>rtip',
            buttons: [
                { extend: 'copy',  text: 'COPIAR',   className: 'px-3 py-1.5 rounded text-white text-xs font-bold', attr: { style:'background:#f97316' } },
                { extend: 'excel', text: 'EXCEL',    className: 'px-3 py-1.5 rounded text-white text-xs font-bold', attr: { style:'background:#16a34a' } },
                { extend: 'csv',   text: 'CSV',      className: 'px-3 py-1.5 rounded text-white text-xs font-bold', attr: { style:'background:#2563eb' } },
                { extend: 'print', text: 'IMPRIMIR', className: 'px-3 py-1.5 rounded text-white text-xs font-bold', attr: { style:'background:#7c3aed' } },
            ],
            pageLength: 10,
            scrollX: true,
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' },
        });
    });
</script>
