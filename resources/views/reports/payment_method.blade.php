@extends('layouts.app')

@section('content')
    <div class="px-4 pb-8">
        <x-common.page-breadcrumb path="Reportes" pageTitle="Método de Pago" />

        {{-- Filtros --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-6">
            <form action="{{ route('reports.payment_method') }}" method="GET" class="flex flex-wrap items-end gap-4">
                <div class="flex-1 min-w-[200px]">
                    <x-form.date-picker name="date_from" label="Desde" :defaultDate="$dateFrom" dateFormat="Y-m-d" />
                </div>
                <div class="flex-1 min-w-[200px]">
                    <x-form.date-picker name="date_to" label="Hasta" :defaultDate="$dateTo" dateFormat="Y-m-d" />
                </div>
                <div class="flex gap-2">
                    <x-ui.button size="md" variant="primary" type="submit" class="shadow-sm">
                        <i class="ri-search-2-line"></i>
                        <span>Filtrar</span>
                    </x-ui.button>
                </div>
            </form>
        </div>

        @if (isset($rows) && $rows->isNotEmpty())
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                {{-- Tarjetas resumen --}}
                <div class="lg:col-span-2 grid grid-cols-2 gap-4">
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 hover:shadow-md transition-shadow">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Total recaudado</p>
                        <p class="text-2xl font-bold text-gray-800">S/ {{ number_format($grandTotal, 2) }}</p>
                        <p class="text-[10px] text-gray-400 mt-1">Suma de todos los pagos realizados</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 hover:shadow-md transition-shadow">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Total transacciones</p>
                        <p class="text-2xl font-bold text-gray-800">{{ number_format($grandCount, 0) }}</p>
                        <p class="text-[10px] text-gray-400 mt-1">Cantidad de pagos procesados</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 hover:shadow-md transition-shadow">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Ticket Promedio</p>
                        <p class="text-2xl font-bold text-orange-600">S/ {{ $grandCount > 0 ? number_format($grandTotal / $grandCount, 2) : '0.00' }}</p>
                        <p class="text-[10px] text-gray-400 mt-1">Monto promedio por pago</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 hover:shadow-md transition-shadow">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Métodos usados</p>
                        <p class="text-2xl font-bold text-gray-800">{{ $rows->count() }}</p>
                        <p class="text-[10px] text-gray-400 mt-1">Diferentes formas de pago</p>
                    </div>
                </div>

                {{-- Gráfico de dona --}}
                <div
                    class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 flex flex-col hover:shadow-md transition-shadow lg:h-full min-h-[400px]">
                    <h4 class="text-sm font-bold text-gray-700 mb-4">Distribución de Pagos</h4>
                    <div class="relative flex-1 flex items-center justify-center">
                        <canvas id="paymentsDoughnutChart" style="max-height: 350px;"></canvas>
                    </div>
                </div>
            </div>

            {{-- Tabla --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                <h3 class="text-sm font-bold text-gray-700 mb-4">Detalle por medio de pago</h3>
                <div class="overflow-x-auto">
                    <table id="tabla-pagos" class="w-full text-sm border-collapse">
                        <thead class="bg-gray-50 text-xs font-bold text-gray-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-3 py-3 text-left">Medio de Pago</th>
                                <th class="px-3 py-3 text-right">Transacciones</th>
                                <th class="px-3 py-3 text-right">Monto Total</th>
                                <th class="px-3 py-3 text-right">% Participación</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                @php $pct = $grandTotal > 0 ? ($row->total_amount / $grandTotal) * 100 : 0; @endphp
                                <tr class="border-t border-gray-100 hover:bg-orange-50/40 transition-colors">
                                    <td class="px-3 py-2.5 font-medium text-gray-800">{{ $row->payment_method }}</td>
                                    <td class="px-3 py-2.5 text-right text-gray-600 tabular-nums">{{ $row->transactions_count }}</td>
                                    <td class="px-3 py-2.5 text-right font-semibold text-gray-800 tabular-nums">S/ {{ number_format($row->total_amount, 2) }}</td>
                                    <td class="px-3 py-2.5 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <div class="h-1.5 rounded-full bg-orange-400" style="width: {{ min(round($pct), 60) }}px; min-width: 2px;"></div>
                                            <span class="text-xs text-gray-500 tabular-nums w-10 text-right">{{ number_format($pct, 1) }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-20 flex flex-col items-center justify-center text-center">
                <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                    <i class="ri-calendar-event-line text-3xl text-gray-300"></i>
                </div>
                <h3 class="text-gray-800 font-bold text-lg">No hay datos</h3>
                <p class="text-gray-500 max-w-sm">No se encontraron movimientos de caja en el rango de fechas seleccionado.</p>
            </div>
        @endif
    </div>

@push('scripts')
    {{-- Chart.js --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        (function() {
            function initChart() {
                const canvas = document.getElementById('paymentsDoughnutChart');
                if (!canvas) return;

                // Si ya existe un gráfico en este canvas, destruirlo
                const existingChart = Chart.getChart(canvas);
                if (existingChart) {
                    existingChart.destroy();
                }

                // Verificar si Chart.js está cargado
                if (typeof Chart === 'undefined') {
                    setTimeout(initChart, 200);
                    return;
                }

                const ctx = canvas.getContext('2d');
                if (!ctx) return;

                // Pequeño delay para asegurar que el contenedor tenga dimensiones
                setTimeout(() => {
                    new Chart(canvas, {
                        type: 'doughnut',
                        data: {
                            labels: @json($chartLabels),
                            datasets: [{
                                data: @json($chartData),
                                backgroundColor: [
                                    '#C43B25', '#F97316', '#FBBF24', '#10B981', '#3B82F6', '#6366F1', '#8B5CF6'
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
                                        label: function(context) {
                                            const value = parseFloat(context.raw) || 0;
                                            const total = context.dataset.data.reduce((a, b) => a + parseFloat(b), 0);
                                            const pct = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                            return ` S/ ${value.toLocaleString('es-PE', {minimumFractionDigits: 2})} (${pct}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }, 50);
            }

            // Ejecutar inmediatamente si el DOM ya está listo
            if (document.readyState !== 'loading') {
                initChart();
            } else {
                document.addEventListener('DOMContentLoaded', initChart);
            }
            
            // Escuchar turbo:load para navegaciones subsiguientes
            // Usamos { once: true } si el script se re-evalúa, o simplemente confiamos en initChart() directo
            document.addEventListener('turbo:load', initChart, { once: true });
        })();
    </script>
@endpush
@endsection