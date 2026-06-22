@extends('layouts.app')

@php
    $title = 'Reporte de Ventas por Cliente';
@endphp

@section('content')
    <div class="px-4 py-6">
        {{-- Encabezado --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 tracking-tight">{{ $title }}</h1>
                <p class="text-sm text-gray-500 mt-1">Análisis de fidelidad y consumo por cliente.</p>
            </div>
        </div>

        {{-- Filtros --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-8">
            <form action="{{ route('reports.sales_by_customer') }}" method="GET" class="flex flex-wrap items-end gap-4">
                <div class="flex-1 min-w-[200px]">
                    <x-form.date-picker name="date_from" defaultDate="{{ $dateFrom }}" value="{{ $dateFrom }}"
                        label="Desde" />
                </div>

                <div class="flex-1 min-w-[200px]">
                    <x-form.date-picker name="date_to" defaultDate="{{ $dateTo }}" value="{{ $dateTo }}" label="Hasta" />
                </div>

                <div class="flex gap-2">
                    <button type="submit"
                        class="bg-[#C43B25] hover:bg-[#A3311E] text-white px-6 h-11 rounded-xl font-bold text-sm transition-all flex items-center gap-2 shadow-sm">
                        <i class="ri-search-line"></i> Buscar
                    </button>
                    <a href="{{ route('reports.sales_by_customer') }}"
                        class="bg-white border border-gray-200 text-gray-600 px-6 h-11 rounded-xl font-bold text-sm hover:bg-gray-50 transition-all flex items-center gap-2">
                        <i class="ri-refresh-line"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>

        @if(isset($rows) && $rows->isNotEmpty())
            {{-- Resumen de tarjetas --}}
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-orange-50 flex items-center justify-center text-orange-500">
                        <i class="ri-money-dollar-circle-line text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Monto Total</p>
                        <p class="text-xl font-bold text-gray-800">S/ {{ number_format($grandTotal, 2) }}</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-500">
                        <i class="ri-shopping-bag-3-line text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">N° Ventas</p>
                        <p class="text-xl font-bold text-gray-800">{{ $grandCount }}</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center text-purple-500">
                        <i class="ri-user-heart-line text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Ticket Promedio</p>
                        <p class="text-xl font-bold text-gray-800">S/
                            {{ number_format($grandCount > 0 ? $grandTotal / $grandCount : 0, 2) }}
                        </p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center text-green-500">
                        <i class="ri-group-line text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Clientes Únicos</p>
                        <p class="text-xl font-bold text-gray-800">{{ $rows->count() }}</p>
                    </div>
                </div>
            </div>

            {{-- Gráfico --}}
            <div class="grid grid-cols-1 lg:grid-cols-1 gap-6 mb-8">
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 flex flex-col items-center min-h-[450px]">
                    <h4 class="text-base font-bold text-gray-700 mb-6 text-center w-full">Top 5 Clientes por Consumo</h4>
                    <div class="relative w-full flex-1 flex items-center justify-center">
                        <canvas id="customerDoughnutChart" style="max-height: 350px;"></canvas>
                    </div>
                </div>
            </div>

            {{-- Tabla --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                    <h3 class="font-bold text-gray-700">Detalle por Cliente</h3>
                </div>
                <div class="overflow-x-auto p-6">
                    <table id="tabla-clientes" class="w-full text-sm text-left">
                        <thead>
                            <tr class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                                <th class="px-4 py-3 bg-gray-50/50 rounded-l-lg">#</th>
                                <th class="px-4 py-3 bg-gray-50/50 text-center">Cliente</th>
                                <th class="px-4 py-3 bg-gray-50/50 text-center">N° Compras</th>
                                <th class="px-4 py-3 bg-gray-50/50 text-center">Total Consumido</th>
                                <th class="px-4 py-3 bg-gray-50/50 text-center rounded-r-lg">% Del Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($rows as $index => $row)
                                <tr class="hover:bg-gray-50 transition-colors group">
                                    <td class="px-4 py-4 font-medium text-gray-400">#{{ $index + 1 }}</td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 font-bold text-[10px]">
                                                {{ substr($row->person_name ?? 'PG', 0, 2) }}
                                            </div>
                                            <span
                                                class="font-bold text-gray-700">{{ $row->person_name ?: 'Público General' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-center font-medium">{{ $row->sales_count }}</td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="font-bold text-gray-800">S/ {{ number_format($row->total_amount, 2) }}</span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-3 justify-center">
                                            @php $percentage = $grandTotal > 0 ? ($row->total_amount / $grandTotal) * 100 : 0; @endphp
                                            <div
                                                class="flex-1 max-w-[100px] h-1.5 bg-gray-100 rounded-full overflow-hidden hidden sm:block">
                                                <div class="h-full bg-[#FF4622] rounded-full" style="width: {{ $percentage }}%">
                                                </div>
                                            </div>
                                            <span
                                                class="font-bold text-gray-500 text-[11px]">{{ number_format($percentage, 1) }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-12 text-center">
                <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-300">
                    <i class="ri-user-search-line text-4xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-700">No se encontraron ventas</h3>
                <p class="text-gray-400 mt-2">Intenta cambiando el rango de fechas para ver resultados.</p>
            </div>
        @endif
    </div>

    @push('scripts')
        {{-- DataTables --}}
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
        <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

        {{-- Chart.js --}}
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        <script>
            (function () {
                function initTabla() {
                    const tableEl = document.getElementById('tabla-clientes');
                    if (!tableEl) return;

                    // Esperar a que jQuery y DataTables estén listos
                    if (typeof $ === 'undefined' || !$.fn || !$.fn.DataTable) {
                        setTimeout(initTabla, 100);
                        return;
                    }

                    if ($.fn.DataTable.isDataTable('#tabla-clientes')) {
                        $('#tabla-clientes').DataTable().destroy();
                    }

                    $('#tabla-clientes').DataTable({
                        paging: true,
                        pageLength: 25,
                        searching: true,
                        ordering: true,
                        order: [[3, 'desc']],
                        language: {
                            search: 'Buscar:',
                            lengthMenu: 'Mostrar _MENU_',
                            info: 'Mostrando _START_ a _END_ de _TOTAL_ clientes',
                            paginate: { previous: '‹', next: '›' },
                            zeroRecords: 'Sin resultados',
                            emptyTable: 'No hay datos',
                        },
                        dom: 'rt<"mt-6 flex flex-col md:flex-row justify-between items-center gap-4"lip>'
                    });
                }

                function initChart() {
                    const canvas = document.getElementById('customerDoughnutChart');
                    if (!canvas) return;

                    if (typeof Chart === 'undefined') {
                        setTimeout(initChart, 200);
                        return;
                    }

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
                                        '#C43B25', '#F97316', '#FBBF24', '#10B981', '#3B82F6', '#6366F1', '#8B5CF6',
                                        '#EC4899', '#06B6D4', '#F43F5E'
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
                                cutout: '65%',
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
            })();
        </script>
    @endpush
    <style>
        #tabla-clientes_wrapper .dataTables_filter input,
        #tabla-clientes_wrapper .dataTables_length select {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 13px;
            outline: none;
            margin-left: 4px;
        }

        #tabla-clientes_wrapper .dataTables_paginate .paginate_button {
            border-radius: 6px !important;
            padding: 4px 10px !important;
        }

        #tabla-clientes_wrapper .dataTables_paginate .paginate_button.current {
            background: #f97316 !important;
            border-color: #f97316 !important;
            color: white !important;
        }

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