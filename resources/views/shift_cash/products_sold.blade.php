@extends('layouts.app')

@section('content')
    @php
        $from = $window['from'] ?? null;
        $to = $window['to'] ?? null;
        $fromStr = $from instanceof \Carbon\Carbon ? $from->format('d/m/Y H:i') : '-';
        $toStr = $to instanceof \Carbon\Carbon ? $to->format('d/m/Y H:i') : '-';
        $indexParams = ['cash_register_id' => $shift->cashMovementStart?->cash_register_id];
        if (!empty($viewId)) {
            $indexParams['view_id'] = $viewId;
        }
        $backUrl = route('shift-cash.index', $indexParams);
    @endphp

    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
        }
    </style>

    <x-common.page-breadcrumb pageTitle="Ventas por producto — turno de caja" />

    <x-common.component-card
        title="Productos vendidos en el turno"
        desc="Consolidado por producto según comprobantes de venta cobrados en esta caja en el rango del turno (no usa kardex). {{ $enCurso ? 'Turno en curso: datos hasta ahora.' : '' }}"
    >
        <div class="no-print mb-4 flex flex-wrap items-center gap-3">
            <x-ui.link-button size="md" variant="outline" href="{{ $backUrl }}">
                <i class="ri-arrow-left-line"></i> Volver a turnos
            </x-ui.link-button>
            <x-ui.button size="md" variant="primary" type="button" class="h-11 px-4" style="background-color: #C43B25;" onclick="window.print()">
                <i class="ri-printer-line"></i> Imprimir
            </x-ui.button>
        </div>

        <div class="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm dark:border-gray-700 dark:bg-gray-900/40">
            <p><span class="font-semibold text-gray-700 dark:text-gray-200">Caja:</span> {{ $shift->cashMovementStart?->cashRegister?->number ?? '—' }}</p>
            <p><span class="font-semibold text-gray-700 dark:text-gray-200">Empresa / sucursal:</span> {{ $shift->branch?->company?->legal_name ?? '—' }} — {{ $shift->branch?->legal_name ?? '—' }}</p>
            <p><span class="font-semibold text-gray-700 dark:text-gray-200">Periodo considerado:</span> {{ $fromStr }} → {{ $toStr }}</p>
            <p><span class="font-semibold text-gray-700 dark:text-gray-200">Estado del turno:</span>
                @if($enCurso)
                    <span class="text-emerald-600 font-medium">En curso</span>
                @else
                    <span class="text-gray-600">Cerrado</span>
                @endif
            </p>
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
            <table class="w-full min-w-[480px] text-left text-sm">
                <thead class="bg-[#FF4622] text-white">
                    <tr>
                        <th class="px-4 py-3 font-semibold uppercase tracking-wide">#</th>
                        <th class="px-4 py-3 font-semibold uppercase tracking-wide">Producto</th>
                        <th class="px-4 py-3 text-right font-semibold uppercase tracking-wide">Cantidad</th>
                        <th class="px-4 py-3 text-right font-semibold uppercase tracking-wide">Importe (S/)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($productsSold as $i => $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="px-4 py-3 text-gray-500">{{ $i + 1 }}</td>
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-white/90">{{ $row['product'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) ($row['qty'] ?? 0), 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium">{{ number_format((float) ($row['amount'] ?? 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-gray-500">
                                No hay ventas con detalle de producto registradas en este turno para la caja seleccionada.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if(count($productsSold) > 0)
                    <tfoot class="border-t-2 border-gray-300 bg-gray-100 font-semibold dark:border-gray-600 dark:bg-gray-800">
                        <tr>
                            <td colspan="2" class="px-4 py-3 text-right">Totales</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) ($totals['qty'] ?? 0), 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">S/ {{ number_format((float) ($totals['amount'] ?? 0), 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-common.component-card>
@endsection
