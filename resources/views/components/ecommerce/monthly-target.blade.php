@props(['topProducts' => [], 'incomeTrend' => [], 'expenseTrend' => []])

<div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6 shadow-sm h-full">
    <h3 class="text-sm font-bold text-gray-800 dark:text-white/90 mb-1">Platos más vendidos</h3>
    <p class="text-xs text-gray-400 mb-4">{{ now()->translatedFormat('F Y') }}</p>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-teal-600 text-white">
                    <th class="px-4 py-2.5 text-left font-semibold rounded-tl-lg">Plato</th>
                    <th class="px-4 py-2.5 text-right font-semibold rounded-tr-lg">Total Ventas</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topProducts as $i => $product)
                <tr class="{{ $i % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50 dark:bg-gray-800/50' }} border-b border-gray-100 dark:border-gray-700">
                    <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300 font-medium uppercase text-xs">
                        {{ $product['name'] }}
                    </td>
                    <td class="px-4 py-2.5 text-right text-gray-800 dark:text-white font-semibold text-xs">
                        S/. {{ number_format($product['total_sales'], 0) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
