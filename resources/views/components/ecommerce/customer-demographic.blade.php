@props(['topProducts' => []])

@php
    $colors = ['#3B82F6','#10B981','#F59E0B','#EF4444','#8B5CF6','#EC4899','#06B6D4','#84CC16','#F97316','#6366F1'];
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6 shadow-sm h-full flex flex-col">
    <h3 class="text-sm font-bold text-gray-800 dark:text-white/90 mb-1">Platos más vendidos</h3>
    <p class="text-xs text-gray-400 mb-3">{{ now()->translatedFormat('F Y') }}</p>

    {{-- Donut sin leyenda: ocupa todo el alto → centro exacto --}}
    <div class="flex justify-center">
        <div id="topProductsDonut" style="height: 240px; width: 240px; flex-shrink: 0;"></div>
    </div>

    {{-- Leyenda custom en HTML --}}
    <div class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1.5 overflow-y-auto" style="max-height: 120px;">
        @foreach($topProducts as $i => $product)
        <div class="flex items-center gap-1.5 min-w-0">
            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                  style="background: {{ $colors[$i % count($colors)] }};"></span>
            <span class="text-[10px] text-gray-600 dark:text-gray-400 truncate">
                {{ $product['name'] }}
            </span>
        </div>
        @endforeach
    </div>
</div>

<script>
    document.addEventListener('turbo:load', function () {
        const chartElement = document.getElementById('topProductsDonut');
        if (!chartElement) return;

        const data   = @json(collect($topProducts)->pluck('count')->map(fn($v) => (int)$v)->values());
        const labels = @json(collect($topProducts)->pluck('name')->values());
        const colors = @json(array_values($colors));
        const total  = data.reduce((a, b) => a + b, 0);

        if (!data.length || total === 0) return;

        setTimeout(() => {
            if (window.topProductsDonutInstance) {
                try { window.topProductsDonutInstance.destroy(); } catch(e) {}
                window.topProductsDonutInstance = null;
            }
            chartElement.innerHTML = '';

            window.topProductsDonutInstance = new ApexCharts(chartElement, {
                series: data,
                chart: {
                    type: 'donut',
                    height: 240,
                    width: 240,
                    fontFamily: 'inherit',
                    parentHeightOffset: 0,
                    redrawOnParentResize: true,
                    offsetY: 0,
                },
                labels: labels,
                colors: colors,
                stroke: { show: false },
                dataLabels: { enabled: false },
                legend: { show: false },
                plotOptions: {
                    pie: {
                        offsetY: 0,
                        donut: {
                            size: '65%',
                            labels: {
                                show: true,
                                name: {
                                    show: true,
                                    fontSize: '12px',
                                    fontWeight: 600,
                                    color: '#6b7280',
                                    offsetY: -4,
                                },
                                value: {
                                    show: true,
                                    fontSize: '28px',
                                    fontWeight: 700,
                                    color: '#111827',
                                    offsetY: 8,
                                    formatter: () => String(total),
                                },
                                total: {
                                    show: true,
                                    showAlways: true,
                                    label: 'Total',
                                    fontSize: '12px',
                                    fontWeight: 600,
                                    color: '#6b7280',
                                    formatter: () => String(total),
                                },
                            },
                        },
                    },
                },
                tooltip: { y: { formatter: v => v + ' pedidos' } },
                grid: { padding: { top: 0, bottom: 0, left: 0, right: 0 } },
            });
            window.topProductsDonutInstance.render();
        }, 150);
    });
</script>
