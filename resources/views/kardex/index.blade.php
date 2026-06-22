@extends('layouts.app')

@section('content')
    <div>
        <x-common.page-breadcrumb pageTitle="Kardex" />

        <x-common.component-card title="Kardex" :desc="'Consulta el historial de movimientos de inventario por producto.' .
            ($branch ? ' Sucursal: ' . e($branch->legal_name) . '.' : '')">

            <div class="flex flex-col gap-4">
                <form method="GET" action="{{ route('kardex.index') }}" class="mb-6">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    <div class="flex flex-col xl:flex-row gap-2 items-end justify-between">

                        <x-ui.per-page-selector class="flex-none" :per-page="$perPage" />

                        {{-- 1. Product --}}
                        <div class="w-full">
                            <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-400">Producto</label>
                            <select name="product_id" required
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                <option value="all" @selected($productId == 'all')>Todos</option>
                                @foreach ($products as $p)
                                    <option value="{{ $p->id }}" @selected($productId == $p->id)>
                                        {{ $p->code }} - {{ \Illuminate\Support\Str::limit($p->description, 15) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- 2. Source --}}
                        <div class="w-full">
                            <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-400">Fuente</label>
                            <select name="source"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                <option value="all" @selected($sourceFilter == 'all')>Todas</option>
                                <option value="warehouse" @selected($sourceFilter == 'warehouse')>Almacén</option>
                                <option value="sales" @selected($sourceFilter == 'sales')>Ventas</option>
                            </select>
                        </div>

                        {{-- 3. Movement Type --}}
                        <div class="w-full">
                            <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-400">Tipo</label>
                            <select name="movement_type"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                <option value="all" @selected($typeFilter == 'all')>Todos</option>
                                @foreach ($availableTypes as $type)
                                    <option value="{{ $type }}" @selected($typeFilter == $type)>
                                        {{ \Illuminate\Support\Str::limit($type, 12) }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- 4. From Date --}}
                        <div class="w-full">
                            <x-form.date-picker name="date_from" label="Desde" placeholder="Inicio" :defaultDate="$dateFrom"
                                dateFormat="Y-m-d" class="h-10 !mb-0" />
                        </div>

                        {{-- 5. To Date --}}
                        <div class="w-full">
                            <x-form.date-picker name="date_to" label="Hasta" placeholder="Fin" :defaultDate="$dateTo"
                                dateFormat="Y-m-d" class="h-10 !mb-0" />
                        </div>

                        {{-- 6. Botones: Buscar, Limpiar y Exportar PDF en una sola fila --}}
                        <div class="flex flex-nowrap items-center gap-2 shrink-0">
                            <x-ui.button type="submit" size="sm" variant="primary"
                                style="background-color: #FF4622; border-color: #FF4622;">
                                <i class="ri-search-line"></i>
                                Buscar
                            </x-ui.button>
                            <x-ui.link-button href="{{ route('kardex.index', $viewId ? ['view_id' => $viewId] : []) }}"
                                size="sm" variant="outline">
                                <i class="ri-refresh-line"></i>
                                Limpiar
                            </x-ui.link-button>
                            @php
                                $pdfQuery = array_filter(
                                    array_merge($viewId ? ['view_id' => $viewId] : [], [
                                        'product_id' => $productId,
                                        'source' => $sourceFilter,
                                        'movement_type' => $typeFilter,
                                        'date_from' => $dateFrom,
                                        'date_to' => $dateTo,
                                    ]),
                                );
                            @endphp
                            <span class="hidden sm:inline w-px h-6 bg-gray-200 dark:bg-gray-600 mx-0.5"
                                aria-hidden="true"></span>
                        </div>
                    </div>
                </form>
                <div class="flex flex-wrap justify-end gap-2">
                    @foreach ($topOperations as $operation)
                        @php
                            $topTextColor = $resolveTextColor($operation);
                            $topColor = $operation->color ?: '#3B82F6';
                            $topStyle = "background-color: {$topColor}; color: {$topTextColor};";
                            $isKardexPdf = in_array($operation->action, ['kardex.pdf', 'kardex.exportPdf'], true);

                            if ($isKardexPdf) {
                                continue;
                            }

                            $topActionUrl = $resolveActionUrl($operation->action ?? '', [], $operation);
                        @endphp
                        <x-ui.link-button size="sm" variant="primary" style="{{ $topStyle }}" href="{{ $topActionUrl }}">
                            <i class="{{ $operation->icon }}"></i>
                            <span>{{ $operation->name }}</span>
                        </x-ui.link-button>
                    @endforeach

                    <x-ui.link-button href="{{ route('kardex.exportPdf', $pdfQuery) }}" size="sm" variant="primary"
                        target="_blank" rel="noopener"
                        style="background-color: #ef4444; border-color: #ef4444; color: #fff;"
                        title="Exportar PDF con los filtros actuales">
                        <i class="ri-file-pdf-line"></i>
                        <span>Exportar PDF</span>
                    </x-ui.link-button>
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span>Total</span>
                    <x-ui.badge size="sm" variant="light" color="info">{{ $movements->total() }}</x-ui.badge>
                </div>
            </div>

            {{-- Movement Table --}}
            <div
                class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] overflow-x-auto">
                <table class="w-full min-w-[1100px]">
                    <thead>
                        <tr class="text-white text-center" style="background-color: #FF4622;">
                            @if ($showAllProducts ?? false)
                                <th class="px-3 py-3 text-xs font-semibold uppercase sm:px-4 first:rounded-tl-xl">Producto
                                </th>
                            @endif
                            <th
                                class="px-3 py-3 text-xs font-semibold uppercase sm:px-4 {{ !($showAllProducts ?? false) ? 'first:rounded-tl-xl' : '' }}">
                                Fecha</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase sm:px-4">Tipo</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase sm:px-4">Unidad</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase sm:px-4">Stock ant.</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase sm:px-4">Cantidad</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase sm:px-4">Stock actual</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase sm:px-4">P. unit.</th>
                            <th class="px-3 py-3 text-xs font-semibold uppercase sm:px-4 last:rounded-tr-xl">Origen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movements as $idx => $m)
                            @php
                                $tipo = $m['type'] ?? '-';
                                $tipoStyle = match (true) {
                                    str_starts_with($tipo, 'Entrada')
                                        => 'background-color: #d1fae5; color: #065f46; border: 1px solid #10b981;',
                                    str_starts_with($tipo, 'Salida')
                                        => 'background-color: #fee2e2; color: #991b1b; border: 1px solid #ef4444;',
                                    $tipo === 'Saldo inicial'
                                        => 'background-color: #f1f5f9; color: #334155; border: 1px solid #94a3b8;',
                                    default => 'background-color: #FFD8C2; color: #065f46; border: 1px solid #FF6F2E;',
                                };
                            @endphp
                            <tr
                                class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5 {{ $idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-gray-50/50 dark:bg-white/[0.02]' }}">
                                @if ($showAllProducts ?? false)
                                    <td class="px-3 py-2.5 text-sm text-center text-gray-800 dark:text-white/90 sm:px-4">
                                        {{ $m['product_code'] ?? '-' }} - {{ $m['product_description'] ?? '-' }}
                                    </td>
                                @endif
                                <td
                                    class="px-3 py-2.5 text-sm text-center text-gray-800 dark:text-white/90 sm:px-4 whitespace-nowrap">
                                    {{ $m['date'] ? date('d/m/Y H:i', strtotime($m['date'])) : '-' }}
                                </td>
                                <td class="px-3 py-2.5 text-center sm:px-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                        style="{{ $tipoStyle }}">{{ $tipo }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-sm text-center text-gray-600 dark:text-gray-400 sm:px-4">
                                    {{ $m['unit'] ?? '-' }}
                                </td>
                                <td
                                    class="px-3 py-2.5 text-sm text-center text-gray-700 dark:text-gray-300 sm:px-4 font-medium">
                                    {{ $m['type'] === 'Saldo inicial' ? '-' : number_format($m['previous_stock'] ?? 0, 2) }}
                                </td>
                                <td
                                    class="px-3 py-2.5 text-sm text-center text-gray-700 dark:text-gray-300 sm:px-4 font-medium">
                                    {{ ($m['quantity'] ?? 0) > 0 ? number_format($m['quantity'], 2) : '-' }}
                                </td>
                                <td
                                    class="px-3 py-2.5 text-sm text-center font-semibold text-gray-800 dark:text-white sm:px-4">
                                    {{ number_format($m['balance'] ?? 0, 2) }}
                                </td>
                                <td class="px-3 py-2.5 text-sm text-center text-gray-600 dark:text-gray-400 sm:px-4">
                                    {{ isset($m['unit_price']) && $m['unit_price'] !== null ? 'S/ ' . number_format($m['unit_price'], 2) : '-' }}
                                </td>
                                <td
                                    class="px-3 py-2.5 text-sm text-center text-gray-600 dark:text-gray-400 sm:px-4 text-xs">
                                    {{ $m['origin'] ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $showAllProducts ?? false ? 9 : 8 }}" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center gap-3 text-gray-500 dark:text-gray-400">
                                        <div class="rounded-full bg-gray-100 p-4 dark:bg-gray-800">
                                            <i class="ri-file-list-3-line text-3xl"></i>
                                        </div>
                                        <p class="text-sm font-medium">
                                            @if ($productId === 'all')
                                                No hay movimientos de productos en el período seleccionado.
                                            @elseif ($productId)
                                                No hay movimientos en el período seleccionado.
                                            @else
                                                Seleccione un producto y haga clic en Consultar para ver el kardex.
                                            @endif
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-common.component-card>

        <div class="mt-5 mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between px-4 sm:px-6">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Mostrando
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $movements->firstItem() ?? 0 }}</span>
                -
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $movements->lastItem() ?? 0 }}</span>
                de
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $movements->total() }}</span>
            </div>
            <div class="flex-none pagination-simple">
                {{ $movements->links() }}
            </div>
        </div>
    </div>
@endsection
