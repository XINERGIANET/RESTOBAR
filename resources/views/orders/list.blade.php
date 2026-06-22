@extends('layouts.app')

@section('content')
    <div x-data="{}">
        @php
            use Illuminate\Support\Facades\Route;

            $viewId = request('view_id');
            $operacionesCollection = collect($operaciones ?? []);
            $topOperations = $operacionesCollection->where('type', 'T');
            $rowOperations = $operacionesCollection->where('type', 'R');

            $resolveActionUrl = function ($action, $model = null, $operation = null) use ($viewId) {
                if (!$action) {
                    return '#';
                }

                if (str_starts_with($action, '/') || str_starts_with($action, 'http')) {
                    $url = $action;
                } else {
                    $routeCandidates = [$action];
                    if (!str_starts_with($action, 'admin.')) {
                        $routeCandidates[] = 'admin.' . $action;
                    }
                    $routeCandidates = array_merge(
                        $routeCandidates,
                        array_map(fn($name) => $name . '.index', $routeCandidates),
                    );

                    $routeName = null;
                    foreach ($routeCandidates as $candidate) {
                        if (Route::has($candidate)) {
                            $routeName = $candidate;
                            break;
                        }
                    }

                    if ($routeName) {
                        try {
                            $url = $model ? route($routeName, $model) : route($routeName);
                        } catch (\Exception $e) {
                            $url = '#';
                        }
                    } else {
                        $url = '#';
                    }
                }

                $targetViewId = $viewId;
                if ($operation && !empty($operation->view_id_action)) {
                    $targetViewId = $operation->view_id_action;
                }

                if ($targetViewId && $url !== '#') {
                    $separator = str_contains($url, '?') ? '&' : '?';
                    $url .= $separator . 'view_id=' . urlencode($targetViewId);
                }

                return $url;
            };
        @endphp

        <x-common.page-breadcrumb pageTitle="Pedidos" />

        <x-common.component-card title="Listado de pedidos" desc="Gestiona los pedidos registrados.">
            <div class="flex flex-col gap-4">
                <form method="GET" class="flex flex-col gap-4">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                
                    <div class="rounded-xl border border-gray-200/90 bg-gradient-to-b from-slate-50/90 to-white p-4 shadow-sm dark:border-gray-800 dark:from-gray-900/50 dark:to-gray-900/30 sm:p-5 space-y-4">
                
                        {{-- Fila 1: Per page + Buscar + Botones --}}
                        <div class="flex flex-col sm:flex-row sm:items-end gap-3">
                            <div class="w-full sm:w-28 shrink-0">
                                <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">Por página</label>
                                <select name="per_page"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90">
                                    @foreach ([10, 20, 50, 100] as $size)
                                        <option value="{{ $size }}" @selected($perPage == $size)>{{ $size }} / página</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="relative flex-1">
                                <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">Buscar</label>
                                <span class="absolute left-3 top-[calc(100%-2.3rem)] text-gray-400 pointer-events-none">
                                    <i class="ri-search-line"></i>
                                </span>
                                <input type="text" name="search" value="{{ $search }}"
                                    placeholder="Buscar por numero, persona o usuario"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 pl-10 text-sm text-gray-800 placeholder:text-gray-400 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                            </div>
                            <div class="flex gap-2 shrink-0 items-end">
                                <x-ui.button size="md" variant="primary" type="submit"
                                    class="h-11 px-5 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95"
                                    style="background-color: #C43B25; border-color: #C43B25;">
                                    <i class="ri-search-line text-gray-100"></i>
                                    <span class="font-medium text-gray-100">Buscar</span>
                                </x-ui.button>
                                <x-ui.link-button size="md" variant="outline"
                                    href="{{ route('orders.list', $viewId ? ['view_id' => $viewId] : []) }}"
                                    class="h-11 px-5 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                                    <i class="ri-refresh-line"></i>
                                    <span class="font-medium">Limpiar</span>
                                </x-ui.link-button>
                            </div>
                        </div>
                
                        {{-- Fila 2: Filtros + PDF --}}
                        <div class="grid grid-cols-2 gap-3 border-t border-gray-100 pt-4 dark:border-gray-700/80 md:grid-cols-4 lg:grid-cols-8 md:items-end">
                
                            @php $selectClass = 'h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90'; $labelClass = 'mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400'; @endphp
                
                            <div class="col-span-1 [&_label]:mb-1 [&_label]:text-xs [&_label]:font-medium [&_label]:text-gray-600 dark:[&_label]:text-gray-400">
                                <x-form.date-picker name="date_from" label="Desde" :defaultDate="$dateFrom" dateFormat="Y-m-d" class="w-full" />
                            </div>
                            <div class="col-span-1 [&_label]:mb-1 [&_label]:text-xs [&_label]:font-medium [&_label]:text-gray-600 dark:[&_label]:text-gray-400">
                                <x-form.date-picker name="date_to" label="Hasta" :defaultDate="$dateTo" dateFormat="Y-m-d" class="w-full" />
                            </div>
                            <div class="col-span-1">
                                <label class="{{ $labelClass }}">Método de pago</label>
                                <select name="payment_method_id" class="{{ $selectClass }}">
                                    <option value="">Todos</option>
                                    @foreach ($paymentMethods ?? [] as $pm)
                                        <option value="{{ $pm->id }}" @selected(($paymentMethodId ?? '') == $pm->id)>{{ $pm->description ?? $pm->id }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-1">
                                <label class="{{ $labelClass }}">Tipo documento</label>
                                <select name="document_type_id" class="{{ $selectClass }}">
                                    <option value="">Todos</option>
                                    @foreach ($documentTypes ?? [] as $dt)
                                        <option value="{{ $dt->id }}" @selected(($documentTypeId ?? '') == $dt->id)>{{ $dt->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-1">
                                <label class="{{ $labelClass }}">Caja</label>
                                <select name="cash_register_id" class="{{ $selectClass }}" disabled>
                                    @foreach ($cashRegisters ?? [] as $cr)
                                        <option value="{{ $cr->id }}" @selected(($cashRegisterId ?? '') == $cr->id)>{{ $cr->number ?? $cr->id }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-1 lg:col-span-2">
                                <label class="{{ $labelClass }}">Turno</label>
                                <select name="cash_shift_relation_id" class="{{ $selectClass }}">
                                    <option value="">Todos</option>
                                    @foreach ($cashShiftSessions ?? [] as $csr)
                                        @php
                                            $shiftName = $csr->cashMovementStart?->shift?->name ?? 'Turno';
                                            $started = $csr->started_at ? \Illuminate\Support\Carbon::parse($csr->started_at)->format('Y-m-d H:i:s') : '';
                                            $csrStatus = (string) ($csr->status ?? '');
                                            $statusLabel = $csrStatus === '1' ? 'En curso' : 'Cerrado';
                                            $csrLabel = $shiftName . ($started ? ' | ' . $started : '') . ' (' . $statusLabel . ')';
                                        @endphp
                                        <option value="{{ $csr->id }}" @selected(($cashShiftRelationId ?? '') == $csr->id)>{{ $csrLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-1">
                                <label class="{{ $labelClass }}">Estado</label>
                                <select name="status" class="{{ $selectClass }}">
                                    <option value="">Todos</option>
                                    <option value="CANCELADO"  @selected(($status ?? '') == 'CANCELADO')>Cancelado</option>
                                    <option value="PENDIENTE"  @selected(($status ?? '') == 'PENDIENTE')>Pendiente</option>
                                    <option value="FINALIZADO" @selected(($status ?? '') == 'FINALIZADO')>Finalizado</option>
                                </select>
                            </div>
                
                            {{-- Botón PDF alineado al fondo --}}
                            <div class="col-span-1 flex items-end">
                                <button type="button"
                                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-orange-600 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                                    onclick="window.location.href='{{ route('orders.pdf', array_filter([
                                        'view_id'                => $viewId ?? null,
                                        'date_from'              => $dateFrom,
                                        'date_to'                => $dateTo,
                                        'search'                 => $search,
                                        'document_type_id'       => $documentTypeId ?? null,
                                        'payment_method_id'      => $paymentMethodId ?? null,
                                        'cash_register_id'       => $cashRegisterId ?? null,
                                        'cash_shift_relation_id' => $cashShiftRelationId ?? null,
                                        'status'                 => $status ?? null,
                                    ])) }}'">
                                    <i class="ri-file-pdf-line text-base"></i>
                                    <span>PDF</span>
                                </button>
                            </div>
                        </div>
                
                    </div>
                </form>
            </div>
    </div>
    <div x-data="{ openRow: null }"
        class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <table class="w-full table-fixed text-xs">
            <thead class="bg-[#FF4622] text-white">
                <tr>
                    <th class="w-14 px-2 py-2 text-center first:rounded-tl-xl font-bold uppercase tracking-wider">
                        #
                    </th>
                    <th class="w-20 px-2 py-2 text-left font-bold uppercase tracking-wider">
                        Número
                    </th>
                    <th class="w-20 px-2 py-2 text-center font-bold uppercase tracking-wider">
                        Total
                    </th>
                    <th class="w-24 px-2 py-2 text-center font-bold uppercase tracking-wider">
                        Fecha
                    </th>
                    <th class="w-24 px-2 py-2 text-left font-bold uppercase tracking-wider">
                        Persona
                    </th>
                    <th class="w-24 px-2 py-2 text-center font-bold uppercase tracking-wider">
                        Tipo de servicio
                    </th>
                    <th class="w-24 px-2 py-2 text-center last:rounded-tr-xl font-bold uppercase tracking-wider">
                        Estado
                    </th>
                    <th class="w-24 px-2 py-2 text-center font-bold uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    @php
                        $rowStatus = strtoupper((string) ($order->status ?? 'PENDIENTE'));
                        $situationStatus = strtoupper((string) ($order->movement?->status ?? 'A'));
                        $rowStatusColor = in_array($rowStatus, ['FINALIZADO', 'F'], true)
                            ? 'success'
                            : (in_array($rowStatus, ['CANCELADO', 'I'], true)
                                ? 'error'
                                : 'warning');
                        $rowStatusText = in_array($rowStatus, ['FINALIZADO', 'F'], true)
                            ? 'Finalizado'
                            : (in_array($rowStatus, ['CANCELADO', 'I'], true)
                                ? 'Cancelado'
                                : 'Pendiente');
                        /** Cobro / cancelación / cierre; si no hay, último movimiento del documento */
                        $orderListAt = $order->finished_at ?? $order->movement?->moved_at;
                        $situationColor = in_array($situationStatus, ['A', '1'], true) ? 'success' : 'error';
                        $situationText = in_array($situationStatus, ['A', '1'], true) ? 'Activado' : 'Inactivo';
                    @endphp
                    <tr
                        class="border-b border-gray-100 transition hover:bg-gray-100/50 dark:border-gray-800 dark:hover:bg-white/5 {{ $loop->iteration % 2 === 0 ? 'bg-gray-50/60 dark:bg-gray-800/30' : 'bg-white dark:bg-transparent' }}">
                        <td class="px-2 py-2 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <button type="button"
                                    @click="openRow === {{ $order->id }} ? openRow = null : openRow = {{ $order->id }}"
                                    class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#FF4622] text-white transition hover:opacity-90">
                                    <i class="ri-add-line text-xs" x-show="openRow !== {{ $order->id }}"></i>
                                    <i class="ri-subtract-line text-xs" x-show="openRow === {{ $order->id }}"></i>
                                </button>
                            </div>
                        </td>
                        <td class="px-2 py-2">
                            @php
                                $num = $order->movement?->number ?? null;
                                $numDisplay = $num
                                    ? (ctype_digit((string) $num)
                                        ? str_pad($num, 8, '0', STR_PAD_LEFT)
                                        : $num)
                                    : '-';
                            @endphp
                            <span class="text-gray-700 badge badge-sm badge-primary dark:text-gray-300 truncate block max-w-full"
                                title="{{ $numDisplay }}">{{ $numDisplay }}</span>
                        </td>
                        <td class="px-2 py-2 text-center">
                            <span
                                class="text-gray-700 font-medium dark:text-gray-300 tabular-nums">{{ number_format((float) ($order->total ?? 0), 2) }}</span>
                        </td>
                        <td class="px-2 py-2 text-center">
                            <span
                                class="text-gray-600 dark:text-gray-400 tabular-nums">{{ $orderListAt?->format('d/m/Y H:i') ?? '-' }}</span>
                        </td>
                        <td class="px-2 py-2 overflow-hidden">
                            <span class="text-gray-600 dark:text-gray-400 truncate block max-w-full"
                                title="{{ $order->movement?->person_name ?? '-' }}">{{ $order->movement?->person_name ?? '-' }}</span>
                        </td>
                        <td class="px-2 py-2 text-center justify-center items-center overflow-hidden">
                            @php
                                $serviceType = strtoupper(trim((string) ($order->service_type ?? '')));
                                $serviceLabel = match ($serviceType) {
                                    'IN_SITU' => 'Local',
                                    'TAKE_AWAY', 'TAKE_OUT' => 'Para llevar',
                                    'DELIVERY' => 'Delivery',
                                    default => '-',
                                };
                        
                                $serviceColor = match ($serviceType) {
                                    'IN_SITU' => 'success',
                                    'TAKE_AWAY', 'TAKE_OUT' => 'warning',
                                    'DELIVERY' => 'info',
                                    default => 'light',
                                };
                            @endphp
                        
                            <x-ui.badge
                                variant="light"
                                color="{{ $serviceColor }}"
                                class="inline-flex justify-center items-center text-center text-[11px] font-medium px-2 py-0.5"
                                title="{{ $serviceType ?: '-' }}"
                            >
                                {{ $serviceLabel }}
                            </x-ui.badge>
                        </td>
                        
                        <td class="px-2 py-2 text-center">
                            <x-ui.badge variant="light" color="{{ $rowStatusColor }}"
                                class="inline-flex justify-center items-center text-center text-[11px] font-medium px-2 py-0.5">
                                {{ $rowStatusText }}
                            </x-ui.badge>
                        </td>
                        <td class="px-2 py-2 text-center">
                            <div class="flex flex-wrap items-center justify-center gap-2">
                                @if ($order->movement_id)
                                    <x-ui.button size="icon" variant="primary" type="button"
                                        onclick="printThermalTicket({{ (int) $order->movement_id }})"
                                        title="Imprimir ticket / comprobante"
                                        class="flex-1 sm:flex-none h-11 px-6 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95">
                                        <i class="ri-printer-line"></i>
                                    </x-ui.button>
                                @else
                                    <x-ui.button size="icon" variant="outline" title="Sin movimiento">
                                        <i class="ri-printer-line"></i>
                                    </x-ui.button>
                                @endif
                            </div>
                        </td>
                    </tr>

                    {{-- Acordeón: Detalle del pedido --}}
                    <tr x-show="openRow === {{ $order->id }}" x-cloak x-transition
                        class="bg-slate-50 dark:bg-slate-800/40 border-b border-gray-200 dark:border-gray-800">
                        <td colspan="8" class="px-6 py-5">
                            <div
                                class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 overflow-hidden shadow-sm">
                                <div
                                    class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80 flex items-center gap-2">
                                    <i class="ri-file-list-3-line text-[#FF4622]"></i>
                                    <h4 class="text-xs font-bold text-gray-700 dark:text-gray-200">Detalle del pedido
                                        #{{ $order->movement?->number ?? $order->id }}</h4>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm text-left">
                                        <thead
                                            class="text-gray-500 uppercase bg-white dark:bg-gray-900 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800">
                                            <tr>
                                                <th class="px-4 py-3 font-semibold">Producto(s)</th>
                                                <th class="px-4 py-3 font-semibold">Cantidad</th>
                                                <th class="px-4 py-3 font-semibold text-right">Hora comanda</th>
                                                <th class="px-4 py-3 font-semibold">Estado</th>
                                                <th class="px-4 py-3 font-semibold">Mesa</th>
                                                <th class="px-4 py-3 font-semibold">Mozo</th>
                                            </tr>
                                            
                                        </thead>
                                        <tbody class="text-sm">
                                            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/5 transition-colors">
                                                <td
                                                    class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300 tabular-nums">
                                                    @foreach ($order->details as $detail)
                                                        <span
                                                            class="text-gray-600 dark:text-gray-400 truncate block max-w-full"
                                                            title="{{ $detail->description }}">{{ $detail->description }}</span>
                                                    @endforeach
                                                </td>
                                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 tabular-nums">
                                                    @foreach ($order->details as $detail)
                                                        <span
                                                            class="text-gray-600 dark:text-gray-400 truncate block max-w-full"
                                                            title="{{ $detail->quantity }}">
                                                            {{ number_format((float) ($detail->quantity ?? 0), 2) }}
                                                            @if ((float) ($detail->courtesy_quantity ?? 0) > 0)
                                                                <span
                                                                    class="text-amber-500">({{ number_format((float) $detail->courtesy_quantity, 2) }}
                                                                    cortesía)</span>
                                                            @endif
                                                            @if ((float) ($detail->takeaway_quantity ?? 0) > 0)
                                                                <span
                                                                    class="text-orange-600 dark:text-orange-400">({{ number_format((float) $detail->takeaway_quantity, 2) }}
                                                                    llevar)</span>
                                                            @endif
                                                        </span>
                                                    @endforeach
                                                </td>
                                                <td
                                                    class="px-4 py-3 text-right font-bold text-gray-700 dark:text-gray-300 tabular-nums">
                                                    {{ $orderListAt?->format('H:i') ?? '-' }}</td>
                                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                                    <x-ui.badge variant="light" color="{{ $rowStatusColor }}"
                                                        class="inline-flex justify-center items-center text-center text-[11px] font-medium px-2 py-0.5">
                                                        {{ $rowStatusText }}
                                                    </x-ui.badge>
                                                </td>
                                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                                    <span
                                                        class="text-gray-600 dark:text-gray-400 truncate block max-w-full"
                                                        title="{{ $order->table?->name ?? '-' }}">{{ $order->table?->name ?? '-' }}</span>
                                                </td>
                                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                                    <span
                                                        class="text-gray-600 dark:text-gray-400 truncate block max-w-full"
                                                        title="{{ $order->movement?->responsible_name ?? '-' }}">{{ $order->movement?->responsible_name ?? '-' }}</span>
                                                </td>
                                            </tr>
                                            @if ($order->status == 'CANCELADO')
                                            <tr>
                                                <td colspan="5" class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                                    <span class="text-gray-600 dark:text-gray-400 truncate block max-w-full"
                                                        title="{{ $order->movement?->comment ?? '-' }}"> <strong>Motivo de cancelación:</strong> {{ $order->movement?->comment ?? '-' }}</span>
                                                </td>
                                            </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12">
                            <div class="flex flex-col items-center gap-3 text-center text-xs text-gray-500">
                                <div
                                    class="rounded-full bg-gray-100 p-3 text-gray-400 dark:bg-gray-800 dark:text-gray-300">
                                    <i class="ri-restaurant-2-line"></i>
                                </div>
                                <p class="font-semibold text-gray-700 dark:text-gray-200">No hay pedidos registrados.</p>
                                <p class="text-gray-500">Crea el primer pedido desde Salones de pedidos.</p>
                                <x-ui.link-button size="sm" variant="primary"
                                    href="{{ route('orders.index', $viewId ? ['view_id' => $viewId] : []) }}"
                                    style="background-color: #FF4622; border-color: #FF4622;">
                                    <i class="ri-add-line"></i>
                                    <span>Ir a pedidos</span>
                                </x-ui.link-button>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>



        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-gray-500">
                Mostrando
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $orders->firstItem() ?? 0 }}</span>
                -
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $orders->lastItem() ?? 0 }}</span>
                de
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $orders->total() }}</span>
            </div>
            <div>
                {{ $orders->links() }}
            </div>
        </div>
        </x-common.component-card>
    </div>

    <script>
        async function printThermalTicket(movementId) {
            if (!movementId) return;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const fallbackUrl = @json(route('admin.sales.print.ticket', ['sale' => '__SALE__'])).replace('__SALE__', movementId);

            const askForPdf = (msg) => {
                if (confirm(msg + '\n\n¿Desea abrir el ticket en formato PDF?')) {
                    const currentUrl = new URL(window.location.href);
                    const viewId = currentUrl.searchParams.get('view_id');
                    let finalUrl = fallbackUrl;
                    if (viewId) {
                        finalUrl += (finalUrl.includes('?') ? '&' : '?') + 'view_id=' + encodeURIComponent(viewId);
                    }
                    window.open(finalUrl, '_blank', 'noopener,noreferrer');
                }
            };

            try {
                const r = await fetch(@json(route('sales.print.ticket.thermal')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ movement_id: movementId }),
                });
                const data = r.headers.get('content-type')?.includes('application/json') ? await r.json() : null;
                if (!r.ok || !data?.success) {
                    const msg = data?.message || 'No se pudo enviar el ticket a la ticketera.';
                    askForPdf(msg);
                    return;
                }
                const okMsg = data?.message || 'Ticket enviado a la ticketera.';
                if (typeof showNotification === 'function') {
                    showNotification('Impresión', okMsg, 'success');
                } else {
                    alert(okMsg);
                }
            } catch (e) {
                const msg = (e && e.message) ? e.message : 'Error de red al conectar con la ticketera.';
                askForPdf(msg);
            }
        }
    </script>
@endsection
