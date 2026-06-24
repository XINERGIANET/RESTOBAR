@extends('layouts.app')
<script>
    window.dashboardData = @json($dashboardData);
</script>
@section('content')
    <div class="px-4 py-6 md:px-6 2xl:px-10">

        {{-- Filtro de fecha global --}}
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-800 dark:text-white">Dashboard</h2>
                <p class="text-xs text-gray-400 mt-0.5">
                    @if($dashboardData['startDate'] === $dashboardData['endDate'])
                        Mostrando datos del día:
                        <span class="font-semibold text-gray-600 dark:text-gray-300">
                            {{ \Carbon\Carbon::parse($dashboardData['startDate'])->format('d/m/Y') }}
                        </span>
                    @else
                        Mostrando datos del
                        <span class="font-semibold text-gray-600 dark:text-gray-300">
                            {{ \Carbon\Carbon::parse($dashboardData['startDate'])->format('d/m/Y') }}
                        </span>
                        al
                        <span class="font-semibold text-gray-600 dark:text-gray-300">
                            {{ \Carbon\Carbon::parse($dashboardData['endDate'])->format('d/m/Y') }}
                        </span>
                    @endif
                </p>
            </div>

            <form method="GET" action="{{ route('dashboard') }}" class="flex items-end gap-3" id="dashboardFilterForm">
                <div class="flex flex-col gap-1">
                    <label class="text-[11px] font-semibold uppercase tracking-widest text-gray-400">Inicio</label>
                    <x-form.date-picker name="start_date" :defaultDate="$dashboardData['startDate']" dateFormat="Y-m-d" :altInput="true"
                        altFormat="d/m/Y" placeholder="{{ now()->format('d/m/Y') }}" />
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[11px] font-semibold uppercase tracking-widest text-gray-400">Fin</label>
                    <x-form.date-picker name="end_date" :defaultDate="$dashboardData['endDate']" dateFormat="Y-m-d" :altInput="true"
                        altFormat="d/m/Y" placeholder="{{ now()->format('d/m/Y') }}" />
                </div>

                <button type="submit"
                    class="h-10 rounded-lg bg-[#124731] px-5 text-sm font-semibold text-white shadow hover:bg-[#e03d1c] transition-colors flex items-center gap-1.5">
                    <i class="ri-search-line"></i> Buscar
                </button>
            </form>
        </div>

        <!-- Summary Metrics -->
        <x-ecommerce.ecommerce-metrics :accounts="$dashboardData['accounts']" />

        <div class="mt-6">
            <x-ecommerce.products-sold-period
                :productsSold="$dashboardData['productsSoldInFilter']"
                :startDate="$dashboardData['startDate']"
                :endDate="$dashboardData['endDate']"
            />
        </div>

        <!-- Tendencia Diaria -->
        <x-ecommerce.daily-trend :dateRange="$dashboardData['dateRange']" :dailySales="$dashboardData['dailySales']" :dailyPurchases="$dashboardData['dailyPurchases']" :dailyEntradas="$dashboardData['dailyEntradas']"
            :dailySalidas="$dashboardData['dailySalidas']" />

        <!-- Platos mas vendidos -->
        <div class="flex flex-col lg:flex-row gap-6 mt-6">
            <div class="flex-1 min-w-0">
                <x-ecommerce.customer-demographic :topProducts="$dashboardData['topProducts']" />
            </div>
            <div class="flex-1 min-w-0">
                <x-ecommerce.monthly-target :topProducts="$dashboardData['topProducts']" />
            </div>
        </div>


        <!-- Tendencia Mensual -->
        <x-ecommerce.monthly-trend :monthlySales="$dashboardData['monthlySales']" :monthlyPurchases="$dashboardData['monthlyPurchases']" :incomeTrend="$dashboardData['incomeTrend']" :expenseTrend="$dashboardData['expenseTrend']"
            :monthlyLabels="$dashboardData['monthlyLabels']" />

    <!-- Reporte diario por vendedor -->
    <x-ecommerce.seller-daily-report
      :reportRows="$dashboardData['reportRows']"
      :reportChartData="$dashboardData['reportChartData']"
      :reportChartDataCompras="$dashboardData['reportChartDataCompras']"
      :reportMonthNames="$dashboardData['reportMonthNames']"
      :reportYear="$dashboardData['reportYear']"
      :reportTotalVentas="$dashboardData['reportTotalVentas']"
      :reportTotalCompras="$dashboardData['reportTotalCompras']"
      :reportNroDias="$dashboardData['reportNroDias']"
      :reportPromedio="$dashboardData['reportPromedio']"
      :reportType="$dashboardData['reportType']"
    />

    <!-- Reporte Vendedores -->
    <x-ecommerce.seller-report :sellerReport="$dashboardData['sellerReport']" :totalVentasAnual="$dashboardData['totalVentasAnual']" :mesesConVentas="$dashboardData['mesesConVentas']" :ventasPromedio="$dashboardData['ventasPromedio']"
            :monthlyLabels="$dashboardData['monthlyLabels']" />
    </div>

    <div class="mt-12 text-center text-xs text-blue-500/60 dark:text-gray-500">
        » Somos parte de <span class="font-bold">Xpandecorp</span>
    </div>
@endsection
