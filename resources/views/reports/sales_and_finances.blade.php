@extends('layouts.app')
@section('content')
    <div class="px-4 pb-8">
        <x-common.page-breadcrumb path="Reportes" pageTitle="Ventas y Finanzas" />
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-5 mb-6">
            <div x-data="financeFilters('{{ $period ?? '' }}')" class="space-y-4">
                {{-- Atajos Rápidos --}}
                <div class="flex flex-wrap items-center gap-2 pb-2 border-b border-gray-100 mb-2">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider mr-2">Atajos:</span>
                    <button type="button" @click="setPreset('today')" :class="activePreset === 'today' ? 'bg-orange-600 text-white' : 'bg-gray-50 text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200">Hoy</button>
                    <button type="button" @click="setPreset('yesterday')" :class="activePreset === 'yesterday' ? 'bg-orange-600 text-white' : 'bg-gray-50 text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200">Ayer</button>
                    <button type="button" @click="setPreset('this_week')" :class="activePreset === 'this_week' ? 'bg-orange-600 text-white' : 'bg-gray-50 text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200">Esta Semana</button>
                    <button type="button" @click="setPreset('last_7_days')" :class="activePreset === 'last_7_days' ? 'bg-orange-600 text-white' : 'bg-gray-50 text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200">Últimos 7 días</button>
                    <button type="button" @click="setPreset('this_month')" :class="activePreset === 'this_month' ? 'bg-orange-600 text-white' : 'bg-gray-50 text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200">Este Mes</button>
                    <button type="button" @click="setPreset('last_30_days')" :class="activePreset === 'last_30_days' ? 'bg-orange-600 text-white' : 'bg-gray-50 text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200">Últimos 30 días</button>
                    <button type="button" @click="setPreset('this_year')" :class="activePreset === 'this_year' ? 'bg-orange-600 text-white' : 'bg-gray-50 text-gray-600 hover:bg-gray-100'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200">Este Año</button>
                </div>

                <form id="filterForm" action="{{ route('reports.sales_and_finances') }}" method="GET">
                    @if(request('view_id'))
                        <input type="hidden" name="view_id" value="{{ request('view_id') }}">
                    @endif
                    <input type="hidden" name="period" x-model="activePreset">
                    <div class="flex flex-wrap items-end gap-4" @date-change="activePreset = ''">
                        <div class="flex-1 min-w-[140px]">
                            <x-form.date-picker id="date_from" name="date_from" label="Desde" :defaultDate="$dateFrom" dateFormat="Y-m-d" />
                        </div>
                        <div class="flex-1 min-w-[140px]">
                            <x-form.date-picker id="date_to" name="date_to" label="Hasta" :defaultDate="$dateTo" dateFormat="Y-m-d" />
                        </div>
                        <div class="w-32">
                            <label for="group_by" class="block text-sm font-medium text-gray-700 mb-1.5">Agrupar por</label>
                            <div class="relative">
                                <select name="group_by" id="group_by" 
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-white pl-3 pr-8 py-2 text-sm text-gray-800 transition-all focus:border-orange-500 focus:ring-1 focus:ring-orange-500 appearance-none outline-none">
                                    <option value="day" @selected(($groupBy ?? '') == 'day')>Día</option>
                                    <option value="week" @selected(($groupBy ?? '') == 'week')>Semana</option>
                                    <option value="month" @selected(($groupBy ?? '') == 'month')>Mes</option>
                                </select>
                                <i class="ri-arrow-down-s-line absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                            </div>
                        </div>
                        <div class="flex gap-2 items-end">
                            <x-ui.link-button size="md" variant="outline" href="{{ route('reports.sales_and_finances') }}"
                                class="h-11 px-5 border-gray-200 text-gray-600 hover:bg-gray-50 transition-all duration-200">
                                <i class="ri-refresh-line"></i>
                                <span class="font-medium">Reset</span>
                            </x-ui.link-button>
                            <x-ui.button size="md" variant="primary" type="submit"
                                class="h-11 px-8 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95 text-white"
                                style="background-color: #C43B25; border-color: #C43B25;">
                                <i class="ri-search-line"></i>
                                <span class="font-medium">Actualizar</span>
                            </x-ui.button>
                        </div>
                    </div>
                </form>
            </div>
            <!--Exportar PDF y excel-->
            <div class="flex gap-2 mt-4">
                <x-ui.link-button size="md" style="background-color: #008b23; border-color: #008b23;" variant="primary"
                    type="button" onclick="exportarExcel()">
                    <i class="ri-file-excel-2-line"></i>
                    <span>Excel</span>
                </x-ui.link-button>
                <x-ui.link-button size="md" style="background-color: #C43B25; border-color: #C43B25;" variant="primary"
                    type="button" onclick="exportarPDF()">
                    <i class="ri-file-pdf-line"></i>
                    <span>PDF</span>
                </x-ui.link-button>
            </div>
        </div>

        {{-- Tarjetas Resumen --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div
                class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 hover:shadow-md transition-all duration-300">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 bg-green-50 rounded-xl">
                        <i class="ri-money-dollar-circle-line text-2xl text-green-600"></i>
                    </div>
                </div>
                <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">Total Ventas</p>
                <h3 class="text-2xl font-bold text-gray-800 tabular-nums">S/ {{ number_format($totalSales, 2) }}</h3>
                <p class="text-xs text-green-500 mt-2 flex items-center gap-1">
                    <i class="ri-arrow-up-line"></i> Ingresos totales del periodo
                </p>
            </div>

            <div
                class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 hover:shadow-md transition-all duration-300">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 bg-red-50 rounded-xl">
                        <i class="ri-shopping-cart-2-line text-2xl text-red-600"></i>
                    </div>
                </div>
                <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">Total Compras / Gastos</p>
                <h3 class="text-2xl font-bold text-gray-800 tabular-nums">S/ {{ number_format($totalPurchases, 2) }}</h3>
                <p class="text-xs text-red-500 mt-2 flex items-center gap-1">
                    <i class="ri-arrow-down-line"></i> Egresos totales del periodo
                </p>
            </div>

            <div
                class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 hover:shadow-md transition-all duration-300">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 bg-blue-50 rounded-xl">
                        <i class="ri-scales-3-line text-2xl text-blue-600"></i>
                    </div>
                </div>
                <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">Utilidad Neta</p>
                <h3 class="text-2xl font-bold {{ $netProfit >= 0 ? 'text-blue-600' : 'text-red-600' }} tabular-nums">
                    S/ {{ number_format($netProfit, 2) }}
                </h3>
                <p class="text-xs text-gray-400 mt-2">Diferencia entre ingresos y egresos</p>
            </div>
        </div>

        {{-- Gráfico de Comparación --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-6">
            <h4 class="text-sm font-bold text-gray-700 mb-6">Comparativa de Ventas vs Compras (Agrupado por {{ match($groupBy ?? 'day') { 'week' => 'Semana', 'month' => 'Mes', default => 'Día' } }})</h4>
            <div class="relative h-[400px]">
                <canvas id="financeComparisonChart"></canvas>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            function financeFilters(initialPreset = '') {
                return {
                    activePreset: initialPreset,
                    setPreset(preset) {
                        this.activePreset = preset;
                        const today = new Date();
                        let from, to, groupBy = 'day';

                        const formatDate = (date) => {
                            const d = new Date(date);
                            let month = '' + (d.getMonth() + 1);
                            let day = '' + d.getDate();
                            let year = d.getFullYear();
                            if (month.length < 2) month = '0' + month;
                            if (day.length < 2) day = '0' + day;
                            return [year, month, day].join('-');
                        };

                        switch (preset) {
                            case 'today':
                                from = to = today;
                                break;
                            case 'yesterday':
                                from = to = new Date(new Date().setDate(today.getDate() - 1));
                                break;
                            case 'this_week':
                                const first = today.getDate() - today.getDay() + (today.getDay() === 0 ? -6 : 1);
                                from = new Date(new Date().setDate(first));
                                to = today;
                                break;
                            case 'last_7_days':
                                from = new Date(new Date().setDate(today.getDate() - 6));
                                to = today;
                                break;
                            case 'this_month':
                                from = new Date(today.getFullYear(), today.getMonth(), 1);
                                to = today;
                                break;
                            case 'last_30_days':
                                from = new Date(new Date().setDate(today.getDate() - 29));
                                to = today;
                                break;
                            case 'this_year':
                                from = new Date(today.getFullYear(), 0, 1);
                                to = today;
                                groupBy = 'month';
                                break;
                        }

                        // Actualizar Flatpickrs si existen
                        const pickerFrom = document.getElementById('date_from')._flatpickr;
                        const pickerTo = document.getElementById('date_to')._flatpickr;
                        if (pickerFrom) pickerFrom.setDate(formatDate(from));
                        if (pickerTo) pickerTo.setDate(formatDate(to));
                        
                        document.getElementById('group_by').value = groupBy;

                        // Enviar automáticamente después de un breve delay para que se vea el cambio
                        setTimeout(() => {
                            document.getElementById('filterForm').submit();
                        }, 200);
                    }
                }
            }

            (function () {
                function initChart() {
                    const canvas = document.getElementById('financeComparisonChart');
                    if (!canvas) return;

                    if (typeof Chart === 'undefined') {
                        setTimeout(initChart, 200);
                        return;
                    }

                    const existingChart = Chart.getChart(canvas);
                    if (existingChart) existingChart.destroy();

                    const ctx = canvas.getContext('2d');

                    // Gradientes
                    const salesGradient = ctx.createLinearGradient(0, 0, 0, 400);
                    salesGradient.addColorStop(0, 'rgba(34, 197, 94, 0.2)');
                    salesGradient.addColorStop(1, 'rgba(34, 197, 94, 0)');

                    const purchasesGradient = ctx.createLinearGradient(0, 0, 0, 400);
                    purchasesGradient.addColorStop(0, 'rgba(239, 68, 68, 0.2)');
                    purchasesGradient.addColorStop(1, 'rgba(239, 68, 68, 0)');

                    // Pequeño delay para asegurar que el contenedor tenga dimensiones
                    setTimeout(() => {
                        new Chart(canvas, {
                        type: 'line',
                        data: {
                            labels: @json($dates),
                            datasets: [
                                {
                                    label: 'Ventas',
                                    data: @json($chartSales),
                                    borderColor: '#22c55e',
                                    backgroundColor: salesGradient,
                                    fill: true,
                                    tension: 0.4,
                                    borderWidth: 3,
                                    pointRadius: 4,
                                    pointBackgroundColor: '#22c55e'
                                },
                                {
                                    label: 'Compras',
                                    data: @json($chartPurchases),
                                    borderColor: '#ef4444',
                                    backgroundColor: purchasesGradient,
                                    fill: true,
                                    tension: 0.4,
                                    borderWidth: 3,
                                    pointRadius: 4,
                                    pointBackgroundColor: '#ef4444'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                intersect: false,
                                mode: 'index',
                            },
                            plugins: {
                                legend: {
                                    position: 'top',
                                    labels: {
                                        usePointStyle: true,
                                        padding: 20,
                                        font: { size: 12, family: "'Inter', sans-serif" }
                                    }
                                },
                                tooltip: {
                                    backgroundColor: '#1e293b',
                                    padding: 12,
                                    cornerRadius: 8,
                                    callbacks: {
                                        label: function (context) {
                                            let label = context.dataset.label || '';
                                            if (label) label += ': ';
                                            if (context.parsed.y !== null) {
                                                label += new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(context.parsed.y);
                                            }
                                            return label;
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        display: true,
                                        color: '#f1f5f9'
                                    },
                                    ticks: {
                                        callback: function (value) {
                                            return 'S/ ' + value.toLocaleString();
                                        },
                                        font: { size: 11 }
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        font: { size: 11 }
                                    }
                                }
                            }
                        }
                    });
                }, 50);
            }

                if (document.readyState !== 'loading') {
                    initChart();
                } else {
                    document.addEventListener('DOMContentLoaded', initChart);
                }
                document.addEventListener('turbo:load', initChart, { once: true });
            })();

            function exportarExcel() {
                const filters = new URLSearchParams(new FormData(document.querySelector('form'))).toString();
                window.location.href = "{{ route('reports.sales_and_finances.excel') }}?" + filters;
            }

            function exportarPDF() {
                const filters = new URLSearchParams(new FormData(document.querySelector('form'))).toString();
                window.location.href = "{{ route('reports.sales_and_finances.pdf') }}?" + filters;
            }
        </script>
    @endpush
@endsection