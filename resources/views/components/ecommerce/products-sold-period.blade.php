@props([
    'productsSold' => [],
    'startDate' => null,
    'endDate' => null,
])

@php
    $totalQty = collect($productsSold)->sum(fn ($r) => (float) ($r['qty'] ?? 0));
    $totalAmount = collect($productsSold)->sum(fn ($r) => (float) ($r['amount'] ?? 0));
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6 shadow-sm">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between mb-4">
        <div>
            <h3 class="text-sm font-bold text-gray-800 dark:text-white/90">Productos vendidos (periodo filtrado)</h3>
            <p class="text-xs text-gray-400 mt-0.5">
                Según fechas del dashboard; respeta sucursal y caja en sesión.
            </p>
        </div>
        <a href="{{ route('dashboard.productsSoldPdf', ['start_date' => $startDate, 'end_date' => $endDate]) }}"
            target="_blank" rel="noopener"
            class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 shrink-0">
            <i class="ri-file-pdf-2-line text-base text-red-600"></i>
            Descargar PDF
        </a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-100 dark:border-gray-800">
        <table class="min-w-full text-left text-xs">
            <thead class="bg-gray-50 dark:bg-gray-900/50 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="px-3 py-2.5">#</th>
                    <th class="px-3 py-2.5">Producto</th>
                    <th class="px-3 py-2.5 text-right">Cantidad</th>
                    <th class="px-3 py-2.5 text-right">Importe</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-700 dark:text-gray-300">
                @forelse($productsSold as $i => $row)
                    <tr class="hover:bg-gray-50/80 dark:hover:bg-white/[0.02]">
                        <td class="px-3 py-2 text-gray-400">{{ $i + 1 }}</td>
                        <td class="px-3 py-2 font-medium">{{ $row['product'] }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $row['qty'], 2) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">S/{{ number_format((float) $row['amount'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-8 text-center text-gray-400">No hay ventas con detalle en este periodo.</td>
                    </tr>
                @endforelse
            </tbody>
            @if(count($productsSold))
                <tfoot class="bg-gray-50/90 dark:bg-gray-900/70 text-xs font-bold text-gray-800 dark:text-white">
                    <tr>
                        <td colspan="2" class="px-3 py-2.5">Total</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($totalQty, 2) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">S/{{ number_format($totalAmount, 2) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
