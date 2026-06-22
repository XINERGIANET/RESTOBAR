@props([
    'sellerReport'     => [],
    'totalVentasAnual' => 0,
    'mesesConVentas'   => 0,
    'ventasPromedio'   => 0,
    'monthlyLabels'    => [],
])

@php
    $allMonths = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
@endphp

{{-- CSS de DataTables (CDN) --}}
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">

<div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">

    <h3 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2 mb-5">
        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#FF4622]/10 text-[#FF4622]">
            <i class="ri-user-star-line text-xl"></i>
        </span>
        Detalle de Ventas por Vendedor
    </h3>

    {{-- Tarjetas resumen --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="rounded-xl p-4 text-white" style="background:#00bcd4;">
            <p class="text-xs font-semibold uppercase tracking-widest opacity-80">Total Ventas</p>
            <p class="text-2xl font-bold mt-1">S/. {{ number_format($totalVentasAnual, 2) }}</p>
        </div>
        <div class="rounded-xl p-4 text-white" style="background:#ff9800;">
            <p class="text-xs font-semibold uppercase tracking-widest opacity-80">Meses con Ventas</p>
            <p class="text-2xl font-bold mt-1">{{ $mesesConVentas }}</p>
        </div>
        <div class="rounded-xl p-4 text-white" style="background:#ffc107; color:#333;">
            <p class="text-xs font-semibold uppercase tracking-widest opacity-70">Ventas Promedio / Mes</p>
            <p class="text-2xl font-bold mt-1">S/. {{ number_format($ventasPromedio, 2) }}</p>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="overflow-x-auto">
        <table id="sellerReportTable" class="w-full text-sm" style="width:100%">
            <thead>
                <tr style="background:#1a3a5c; color:white;">
                    <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">Vendedores</th>
                    <th class="px-3 py-3 text-right font-semibold whitespace-nowrap">Total</th>
                    @foreach($allMonths as $m)
                    <th class="px-3 py-3 text-right font-semibold whitespace-nowrap">{{ $m }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($sellerReport as $i => $row)
                <tr class="{{ $i % 2 === 0 ? '' : 'bg-gray-50' }}">
                    <td class="px-3 py-2.5 font-medium text-gray-800 dark:text-gray-200 whitespace-nowrap uppercase">
                        {{ $row['seller'] }}
                    </td>
                    <td class="px-3 py-2.5 text-right font-bold text-blue-600 whitespace-nowrap">
                        {{ number_format($row['total'], 1) }}
                    </td>
                    @foreach($row['months'] as $monthTotal)
                    <td class="px-3 py-2.5 text-right text-gray-600 dark:text-gray-400 whitespace-nowrap">
                        {{ number_format($monthTotal, 2) }}
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- JS de DataTables (CDN) --}}
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js" defer></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js" defer></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js" defer></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js" defer></script>

<script>
    document.addEventListener('turbo:load', function initSellerTable() {
        if (!document.getElementById('sellerReportTable')) return;
        
        // Esperar a que jQuery y DataTables estén listos
        if (typeof $ === 'undefined' || !$.fn || !$.fn.DataTable) {
            setTimeout(initSellerTable, 100);
            return;
        }

        if ($.fn.DataTable.isDataTable('#sellerReportTable')) {
            $('#sellerReportTable').DataTable().destroy();
        }
        $('#sellerReportTable').DataTable({
            dom: '<"flex flex-wrap items-center gap-2 mb-3"lB><"flex justify-between items-center mb-2"f>rtip',
            buttons: [
                { extend: 'copy',  text: 'COPIAR',  className: 'px-3 py-1.5 rounded text-white text-xs font-bold', attr: { style:'background:#f97316' } },
                { extend: 'excel', text: 'EXCEL',   className: 'px-3 py-1.5 rounded text-white text-xs font-bold', attr: { style:'background:#16a34a' } },
                { extend: 'csv',   text: 'CSV',     className: 'px-3 py-1.5 rounded text-white text-xs font-bold', attr: { style:'background:#2563eb' } },
                { extend: 'print', text: 'IMPRIMIR',className: 'px-3 py-1.5 rounded text-white text-xs font-bold', attr: { style:'background:#7c3aed' } },
            ],
            pageLength: 10,
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json',
            },
            scrollX: true,
            fixedColumns: { leftColumns: 2 },
        });
    });
</script>
