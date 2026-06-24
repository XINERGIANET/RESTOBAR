@extends('layouts.app')

@section('content')
    <div class="px-4 pb-8">
        <x-common.page-breadcrumb path="Reportes" pageTitle="Consolidado de Productos" />

        <div class="bg-white p-5 rounded">
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-5 mb-6">
                <form action="{{ route('reports.consolidated_products') }}" method="GET">
                    @if(request('view_id'))
                        <input type="hidden" name="view_id" value="{{ request('view_id') }}">
                    @endif
                    <div class="flex flex-wrap items-end gap-4">
                        <div class="flex-1 min-w-[160px]">
                            <x-form.date-picker name="date_from" label="Desde" :defaultDate="$dateFrom"
                                dateFormat="Y-m-d" />
                        </div>
                        <div class="flex-1 min-w-[160px]">
                            <x-form.date-picker name="date_to" label="Hasta" :defaultDate="$dateTo" dateFormat="Y-m-d" />
                        </div>
                        <!--Por defecto todas las categorias-->
                        <div class="flex flex-col gap-1 flex-1 min-w-[180px]">
                            <x-form.select.combobox :clearable="true" label="Categoría" :clearOnFocus="true"
                                value="{{ $categoryId }}" :options="$categories" name="category_id" />
                        </div>
                        <!--Tipo de reporte (Mas vendidos, menos vendidos)-->
                        <div class="flex flex-col gap-1 flex-1 min-w-[180px]">
                            <label for="type" class="text-sm font-medium text-gray-700">Tipo</label>
                            <select name="type" id="type"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-orange-500 focus:ring-1 focus:ring-orange-400 outline-none">
                                <option value="mas_vendidos" @selected(request('type') == 'mas_vendidos')>Más vendidos
                                </option>
                                <option value="menos_vendidos" @selected(request('type') == 'menos_vendidos')>Menos vendidos
                                </option>
                            </select>
                        </div>
                        <div class="flex gap-2 items-end">
                            <x-ui.link-button size="md" variant="outline"
                                href="{{ route('reports.consolidated_products') }}"
                                class="h-11 px-5 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                                <i class="ri-refresh-line"></i>
                                <span class="font-medium">Limpiar</span>
                            </x-ui.link-button>
                        </div>
                        <x-ui.button size="md" variant="primary" type="submit"
                            class="h-11 px-5 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95"
                            style="background-color: #0A2E1F; border-color: #0A2E1F;">
                            <i class="ri-search-line text-gray-100"></i>
                            <span class="font-medium text-gray-100">Buscar</span>
                        </x-ui.button>
                    </div>
                </form>
                <!--Exportar PDF y excel-->
                <div class="flex gap-2 mt-4">
                    <x-ui.link-button size="md" style="background-color: #008b23; border-color: #008b23;" variant="primary"
                        type="button" onclick="exportarExcel()">
                        <i class="ri-file-excel-2-line"></i>
                        <span>Excel</span>
                    </x-ui.link-button>
                    <x-ui.link-button size="md" style="background-color: #0A2E1F; border-color: #0A2E1F;" variant="primary"
                        type="button" onclick="exportarPDF()">
                        <i class="ri-file-pdf-line"></i>
                        <span>PDF</span>
                        </x-ui.button>
                </div>
            </div>
            {{-- Tarjetas resumen y Gráfico --}}
            @if(isset($rows) && $rows->isNotEmpty())
                @php
                    $results = collect($rows);
                    $sortedRows = $results->sortByDesc('total_amount');
                    $topProducts = $sortedRows->take(10);
                    $otherTotal = $sortedRows->skip(10)->sum('total_amount');

                    $chartLabels = $topProducts->pluck('product_name')->toArray();
                    $chartData = $topProducts->pluck('total_amount')->map(fn($v) => round((float) $v, 2))->toArray();

                    if ($otherTotal > 0) {
                        $chartLabels[] = 'Otros';
                        $chartData[] = $otherTotal;
                    }
                @endphp

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    {{-- Tarjetas resumen --}}
                    <div class="lg:col-span-2 grid grid-cols-2 gap-4">
                        <div
                            class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 hover:shadow-md transition-shadow">
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Total vendido</p>
                            <p class="text-2xl font-bold text-gray-800">S/ {{ number_format($grandTotal, 2) }}</p>
                            <p class="text-[10px] text-gray-400 mt-1">Total recaudado (con IGV)</p>
                        </div>
                        <div
                            class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 hover:shadow-md transition-shadow">
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Venta Neta (Base)</p>
                            <p class="text-2xl font-bold text-gray-800">S/ {{ number_format($grandTotalNet, 2) }}</p>
                            <p class="text-[10px] text-gray-400 mt-1">Monto sin impuestos</p>
                        </div>
                        <div
                            class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 hover:shadow-md transition-shadow">
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Impuestos (IGV)</p>
                            <p class="text-2xl font-bold text-blue-600">S/ {{ number_format($grandTax, 2) }}</p>
                            <p class="text-[10px] text-gray-400 mt-1">Impuesto generado</p>
                        </div>
                        <div
                            class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 hover:shadow-md transition-shadow">
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Unidades vendidas</p>
                            <p class="text-2xl font-bold text-gray-800">{{ number_format($grandQuantity, 0) }}</p>
                            <p class="text-[10px] text-gray-400 mt-1">Cantidad total de productos</p>
                        </div>
                    </div>

                    {{-- Gráfico de dona --}}
                    <div
                        class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 flex flex-col hover:shadow-md transition-shadow lg:h-full min-h-[400px]">
                        <h4 class="text-sm font-bold text-gray-700 mb-4">Ventas por Producto</h4>
                        <div class="relative flex-1 flex items-center justify-center">
                            <canvas id="productDoughnutChart" style="max-height: 350px;"></canvas>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Tabla principal --}}
            <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                <h3 class="text-sm font-bold text-gray-700">Detalle por producto</h3>
            </div>

            <div class="overflow-x-auto">
                <table id="tabla-consolidado" class="w-full text-sm border rounded border-gray-200">
                    <thead class="bg-gray-50 text-xs font-bold text-gray-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-3 text-center w-10">#</th>
                            <th class="px-3 py-3 text-left">Producto</th>
                            <th class="px-3 py-3 text-left">Categoría</th>
                            <th class="px-3 py-3 text-right">Cantidad</th>
                            <th class="px-3 py-3 text-right">Cortesías</th>
                            <th class="px-3 py-3 text-right">Precio prom.</th>
                            <th class="px-3 py-3 text-right">Descuento</th>
                            <th class="px-3 py-3 text-right">Total</th>
                            <th class="px-3 py-3 text-right">% del total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows ?? [] as $i => $row)
                            @php $pct = $grandTotal > 0 ? ($row->total_amount / $grandTotal) * 100 : 0; @endphp
                            <tr class="border-t border-gray-100 hover:bg-orange-50/40 transition-colors">
                                <td class="px-3 py-2.5 text-center text-gray-400 text-xs font-mono">{{ $i + 1 }}</td>
                                <td class="px-3 py-2.5">
                                    <span class="font-medium text-gray-800">{{ $row->product_name }}</span>
                                    @if($row->product_code)
                                        <span class="block text-xs text-gray-400 font-mono">{{ $row->product_code }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-gray-500 text-xs">{{ $row->category_name ?: '—' }}</td>
                                <td class="px-3 py-2.5 text-right text-gray-700 tabular-nums">
                                    {{ number_format($row->total_quantity, 2) }}
                                </td>
                                <td class="px-3 py-2.5 text-right tabular-nums">
                                    @if($row->total_courtesy > 0)
                                        <span class="text-amber-500 font-medium">{{ number_format($row->total_courtesy, 0) }}</span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-right text-gray-600 tabular-nums">
                                    S/ {{ number_format($row->avg_price, 2) }}
                                </td>
                                <td class="px-3 py-2.5 text-right tabular-nums">
                                    @if($row->total_discount > 0)
                                        <span class="text-red-500">- S/ {{ number_format($row->total_discount, 2) }}</span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-right font-semibold text-gray-800 tabular-nums">
                                    S/ {{ number_format($row->total_amount, 2) }}
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <div class="h-1.5 rounded-full bg-orange-400"
                                            style="width: {{ min(round($pct * 0.7), 60) }}px; min-width: 2px;"></div>
                                        <span
                                            class="text-xs text-gray-500 tabular-nums w-10 text-right">{{ number_format($pct, 1) }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    @if(isset($rows) && $rows->isNotEmpty())
                        <tfoot class="bg-gray-50 border-t-2 border-gray-300 font-bold text-gray-700 text-sm">
                            <tr>
                                <td colspan="3" class="px-3 py-3 text-right uppercase text-xs tracking-wide">Totales</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ number_format($grandQuantity, 2) }}</td>
                                <td class="px-3 py-3"></td>
                                <td class="px-3 py-3"></td>
                                <td class="px-3 py-3 text-right text-red-500 tabular-nums">
                                    @if($grandDiscount > 0) - S/ {{ number_format($grandDiscount, 2) }} @else — @endif
                                </td>
                                <td class="px-3 py-3 text-right tabular-nums">S/ {{ number_format($grandTotal, 2) }}</td>
                                <td class="px-3 py-3 text-right text-xs">100%</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

        </div>
    </div>

    @push('scripts')
        {{-- DataTables --}}
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
        <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

        {{-- Chart.js --}}
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        <script>
            (function () {
                let _dtConsolidado = null;

                function initTabla() {
                    const tableEl = document.getElementById('tabla-consolidado');
                    if (!tableEl) return;

                    // Esperar a que jQuery y DataTables estén listos
                    if (typeof $ === 'undefined' || !$.fn || !$.fn.DataTable) {
                        setTimeout(initTabla, 100);
                        return;
                    }

                    if ($.fn.DataTable.isDataTable('#tabla-consolidado')) {
                        $('#tabla-consolidado').DataTable().destroy();
                    }

                    _dtConsolidado = $('#tabla-consolidado').DataTable({
                        paging: true,
                        pageLength: 25,
                        searching: true,
                        ordering: true,
                        columnDefs: [{ orderable: false, targets: [0, 8] }],
                        order: [[7, 'desc']],
                        language: {
                            search: 'Buscar:',
                            lengthMenu: 'Mostrar _MENU_ registros',
                            info: 'Mostrando _START_ a _END_ de _TOTAL_ productos',
                            paginate: { previous: '‹', next: '›' },
                            zeroRecords: 'Sin resultados',
                            emptyTable: 'No hay datos',
                        },
                        dom: 'rt<"mt-6 flex flex-col md:flex-row justify-between items-center gap-4"lip>'
                    });
                }

                function initChart() {
                    const canvas = document.getElementById('productDoughnutChart');
                    if (!canvas) return;

                    // Verificar si Chart.js está cargado, si no, reintentar en breve
                    if (typeof Chart === 'undefined') {
                        setTimeout(initChart, 200);
                        return;
                    }

                    // Esperar a que el contenedor tenga dimensiones
                    setTimeout(() => {
                        const ctx = canvas.getContext('2d');
                        if (!ctx) return;

                        const existingChart = Chart.getChart(canvas);
                        if (existingChart) existingChart.destroy();

                        new Chart(canvas, {
                            type: 'doughnut',
                            data: {
                                labels: @json($chartLabels ?? []),
                                datasets: [{
                                    data: @json($chartData ?? []),
                                    backgroundColor: [
                                        '#0A2E1F', '#F97316', '#FBBF24', '#10B981', '#3B82F6', '#6366F1', '#8B5CF6',
                                        '#EC4899', '#06B6D4', '#F43F5E', '#14B8A6', '#94A3B8'
                                    ],
                                    hoverOffset: 15,
                                    borderWidth: 3,
                                    borderColor: '#ffffff'
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                animation: { duration: 1200, easing: 'easeOutQuart' },
                                cutout: '60%',
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                        labels: {
                                            usePointStyle: true,
                                            padding: 20,
                                            font: { size: 11, family: "'Inter', sans-serif", weight: '500' },
                                            color: '#64748b'
                                        }
                                    },
                                    tooltip: {
                                        backgroundColor: '#1e293b',
                                        padding: 12,
                                        cornerRadius: 8,
                                        callbacks: {
                                            label: function (context) {
                                                const value = parseFloat(context.raw) || 0;
                                                const total = context.dataset.data.reduce((a, b) => a + parseFloat(b), 0);
                                                const pct = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                                return ` S/ ${value.toLocaleString('es-PE', { minimumFractionDigits: 2 })} (${pct}%)`;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    }, 150);
                }

                function boot() {
                    initTabla();
                    initChart();
                }

                if (document.readyState !== 'loading') {
                    boot();
                } else {
                    document.addEventListener('DOMContentLoaded', boot);
                }

                document.addEventListener('turbo:load', boot, { once: true });

                // Exponer exportarExcel globalmente
                window.exportarExcel = function () {
                    const filters = new URLSearchParams(new FormData(document.querySelector('form'))).toString();
                    window.location.href = "{{ route('reports.consolidated_products.excel') }}?" + filters;
                };

                window.exportarPDF = function () {
                    const filters = new URLSearchParams(new FormData(document.querySelector('form'))).toString();
                    window.location.href = "{{ route('reports.consolidated_products.pdf') }}?" + filters;
                };
            })();
        </script>
    @endpush

    <style>
        @media print {

            nav,
            header,
            aside,
            form,
            button,
            .no-print {
                display: none !important;
            }
        }

        #tabla-consolidado_wrapper .dataTables_filter input,
        #tabla-consolidado_wrapper .dataTables_length select {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 13px;
            outline: none;
            margin-left: 4px;
        }

        #tabla-consolidado_wrapper .dataTables_paginate .paginate_button {
            border-radius: 6px !important;
            padding: 4px 10px !important;
        }

        #tabla-consolidado_wrapper .dataTables_paginate .paginate_button.current {
            background: #f97316 !important;
            border-color: #f97316 !important;
            color: white !important;
        }

        /* Ajustes para el nuevo wrapper de DataTables */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            float: none !important;
            margin: 0 !important;
            display: inline-block !important;
        }

        .dataTables_wrapper .dataTables_paginate {
            text-align: right !important;
        }
    </style>
@endsection
