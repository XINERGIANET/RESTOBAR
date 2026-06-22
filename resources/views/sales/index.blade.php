@extends('layouts.app')

@section('content')
    <div>
        @php
            use Illuminate\Support\Facades\Route;

            $viewId = request('view_id');
            $operacionesCollection = collect($operaciones ?? []);
            $topOperations = $operacionesCollection->where('type', 'T');
            $rowOperations = $operacionesCollection->where('type', 'R');
            $branchClientsCollection = collect($branchClients ?? []);
            $convertibleDocumentTypesCollection = collect($convertibleDocumentTypes ?? []);
            if ($convertibleDocumentTypesCollection->isEmpty()) {
                $convertibleDocumentTypesCollection = collect($documentTypes ?? [])->filter(function ($documentType) {
                    $name = mb_strtolower(trim((string) ($documentType->name ?? '')), 'UTF-8');
                    return str_contains($name, 'boleta') || str_contains($name, 'factura');
                })->values();
            }
            $convertClientOptions = $branchClientsCollection
                ->map(function ($client) {
                    $clientName = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
                    $clientDocument = trim((string) ($client->document_number ?? ''));
                    $clientLabel = trim(($clientDocument !== '' ? $clientDocument . ' - ' : '') . $clientName);

                    return [
                        'id' => $client->id,
                        'description' => $clientLabel !== '' ? $clientLabel : 'Cliente',
                    ];
                })
                ->values()
                ->all();
            $firstConvertibleDocumentTypeId = (int) ($convertibleDocumentTypesCollection->first()?->id ?? 0);

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

        <script>
            window.__salesConvertClientOptions = @json($convertClientOptions);
        </script>

        <x-common.page-breadcrumb pageTitle="Ventas" />

        <x-common.component-card title="Listado de ventas" desc="Gestiona las ventas registradas.">
            <div class="flex flex-col gap-4">
                <form method="GET" class="w-full flex flex-col gap-4">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif

                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                        <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center flex-wrap">

                            <x-ui.per-page-selector :per-page="$perPage" :submit-form="false" />

                            <div class="relative flex-1 min-w-[200px]">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                                    <i class="ri-search-line"></i>
                                </span>
                                <input type="text" name="search" value="{{ $search }}" placeholder="Buscar..."
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-12 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                            <div class="flex gap-2 w-full sm:w-auto">
                                <x-ui.button size="md" variant="primary" type="submit"
                                    class="h-11 w-full sm:w-auto px-6 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95"
                                    style="background-color: #C43B25; border-color: #C43B25;">
                                    <i class="ri-search-line text-gray-100"></i>
                                    <span class="font-medium text-gray-100 hidden sm:inline">Buscar</span>
                                </x-ui.button>
                                <x-ui.link-button size="md" variant="outline"
                                    href="{{ route('sales.index', $viewId ? ['view_id' => $viewId] : []) }}"
                                    class="h-11 w-full sm:w-auto px-6 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                                    <i class="ri-refresh-line"></i>
                                    <span class="font-medium hidden sm:inline">Limpiar</span>
                                </x-ui.link-button>
                            </div>
                        </div>

                        <div class="flex-shrink-0">
                            @if ($topOperations->isNotEmpty())
                                @foreach ($topOperations as $operation)
                                    @php
                                        $topColor = $operation->color ?: '#FF4622';
                                        $topTextColor = '#FFFFFF';
                                        $topStyle = "background-color: {$topColor}; color: {$topTextColor};";
                                        $topActionUrl = $resolveActionUrl($operation->action ?? '', null, $operation);
                                    @endphp
                                    <x-ui.link-button size="md" variant="primary" style="{{ $topStyle }}"
                                        href="{{ $topActionUrl }}" class="h-11">
                                        <i class="{{ $operation->icon }}"></i>
                                        <span>{{ $operation->name }}</span>
                                    </x-ui.link-button>
                                @endforeach
                            @else
                                <x-ui.link-button size="md" variant="primary"
                                    style="background-color: #FF4622; color: #FFFFFF;"
                                    href="{{ route('sales.create', $viewId ? ['view_id' => $viewId] : []) }}"
                                    class="h-11">
                                    <i class="ri-add-line"></i>
                                    <span>Nueva Venta</span>
                                </x-ui.link-button>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap items-end justify-between gap-3 w-full">
                        <div class="flex flex-wrap items-end  gap-3">
                            @php
                                $filterClass = 'shrink-0';
                                $labelClass = 'mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400';
                                $inputClass =
                                    'h-11 w-full lg:w-[155px] rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-2 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90 dark:focus:border-[#FF4622]';
                            @endphp

                            <div class="{{ $filterClass }}">
                                <label class="{{ $labelClass }}">Desde</label>
                                <x-form.date-picker name="date_from" :defaultDate="$dateFrom" dateFormat="Y-m-d" />
                            </div>
                            <div class="{{ $filterClass }}">
                                <label class="{{ $labelClass }}">Hasta</label>
                                <x-form.date-picker name="date_to" :defaultDate="$dateTo" dateFormat="Y-m-d" />
                            </div>
                            <div class="{{ $filterClass }}">
                                <label class="{{ $labelClass }}">Método de pago</label>
                                <select name="payment_method_id" class="{{ $inputClass }}">
                                    <option value="">Todos</option>
                                    @foreach ($paymentMethods ?? [] as $pm)
                                        <option value="{{ $pm->id }}" @selected(($paymentMethodId ?? '') == $pm->id)>
                                            {{ $pm->description ?? $pm->id }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="{{ $filterClass }}">
                                <label class="{{ $labelClass }}">Tipo de documento</label>
                                <select name="document_type_id" class="{{ $inputClass }}">
                                    <option value="">Todos</option>
                                    @foreach ($documentTypes ?? [] as $dt)
                                        <option value="{{ $dt->id }}" @selected(($documentTypeId ?? '') == $dt->id)>
                                            {{ $dt->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="{{ $filterClass }}">
                                <label class="{{ $labelClass }}">Caja</label>
                                <select name="cash_register_id" class="{{ $inputClass }}" disabled>
                                    @foreach ($cashRegisters ?? [] as $cr)
                                        <option value="{{ $cr->id }}" @selected(($cashRegisterId ?? '') == $cr->id)>
                                            {{ $cr->number ?? $cr->id }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="{{ $filterClass }}">
                                <label class="{{ $labelClass }}">Turno</label>
                                <select name="cash_shift_relation_id" class="{{ $inputClass }}">
                                    <option value="">Todos</option>
                                    @foreach ($cashShiftSessions ?? [] as $csr)
                                        @php
                                            $shiftName = $csr->cashMovementStart?->shift?->name ?? 'Turno';
                                            $started = $csr->started_at ? \Illuminate\Support\Carbon::parse($csr->started_at)->format('Y-m-d H:i:s') : '';
                                            $csrStatus = (string) ($csr->status ?? '');
                                            $statusLabel = $csrStatus === '1' ? 'En curso' : 'Cerrado';
                                            $csrLabel = $shiftName . ($started ? ' | ' . $started : '') . ' (' . $statusLabel . ')';
                                        @endphp
                                        <option value="{{ $csr->id }}" @selected(($cashShiftRelationId ?? '') == $csr->id)>
                                            {{ $csrLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="{{ $filterClass }}">
                                <label class="{{ $labelClass }}">Tipo de venta</label>
                                <select name="sale_type" class="{{ $inputClass }}">
                                    <option value="">Todos</option>
                                    <option value="IN_SITU" @selected(($saleType ?? '') == 'IN_SITU')>En Local</option>
                                    <option value="TAKE_AWAY" @selected(($saleType ?? '') == 'TAKE_AWAY')>Para Llevar</option>
                                    <option value="DELIVERY" @selected(($saleType ?? '') == 'DELIVERY')>Delivery</option>
                                </select>
                            </div>
                        </div>
                        <div class="shrink-0 flex flex-wrap items-center gap-2">
                            <button type="button" onclick="descargarPdf()"
                                data-pdf-url="{{ route(
                                    'admin.sales.pdf',
                                    array_filter([
                                        'view_id' => $viewId ?? null,
                                        'date_from' => $dateFrom,
                                        'date_to' => $dateTo,
                                        'search' => $search,
                                        'document_type_id' => $documentTypeId ?? null,
                                        'payment_method_id' => $paymentMethodId ?? null,
                                        'cash_register_id' => $cashRegisterId ?? null,
                                        'cash_shift_relation_id' => $cashShiftRelationId ?? null,
                                        'sale_type' => $saleType ?? null,
                                    ]),
                                ) }}"
                                class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-orange-600 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
                                <i class="ri-file-pdf-line text-base"></i>
                                <span>Descargar PDF</span>
                            </button>
                            <button type="button" onclick="descargarExcel()"
                                data-excel-url="{{ route(
                                    'admin.sales.excel',
                                    array_filter([
                                        'view_id' => $viewId ?? null,
                                        'date_from' => $dateFrom,
                                        'date_to' => $dateTo,
                                        'search' => $search,
                                        'document_type_id' => $documentTypeId ?? null,
                                        'payment_method_id' => $paymentMethodId ?? null,
                                        'cash_register_id' => $cashRegisterId ?? null,
                                        'cash_shift_relation_id' => $cashShiftRelationId ?? null,
                                        'sale_type' => $saleType ?? null,
                                    ]),
                                ) }}"
                                class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                                <i class="ri-file-excel-2-line text-base"></i>
                                <span>Descargar Excel</span>
                            </button>
                            @if (!empty($thermalPrintEnabled) && ($thermalPrinters ?? collect())->count() > 1)
                                <div class="flex w-full sm:w-auto flex-col gap-1">
                                    <label class="text-[11px] font-medium text-gray-600 dark:text-gray-400">Ticketera
                                        (reimpresión)</label>
                                    <select id="sales-index-thermal-printer"
                                        class="h-11 min-w-[200px] rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 shadow-theme-xs dark:border-gray-600 dark:bg-gray-900 dark:text-white/90">
                                        <option value="">Predeterminada</option>
                                        @foreach ($thermalPrinters as $tp)
                                            <option value="{{ $tp->id }}">{{ $tp->name }} — {{ $tp->ip }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </div>
                    </div>
                </form>
            </div>

            <div x-data="{ openRow: null }"
                class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full min-w-[1100px]">
                    <thead>
                        <tr style="background-color: #FF4622; color: #FFFFFF;">
                            <th class="px-5 py-3 text-left sm:px-6 first:rounded-tl-xl sticky-left-header">
                                <p class="font-semibold text-white text-center text-theme-xs uppercase">#</p>
                            </th>
                            <th class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Comprobante</p>
                            </th>
                            <th class= "px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Subtotal</p>
                            </th>
                            <th class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">IGV</p>
                            </th>
                            <th class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Total</p>
                            </th>
                            <th class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Persona</p>
                            </th>
                            <th class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Situación</p>
                            </th>
                            <th class="px-5 py-3 text-center sm:px-6 last:rounded-tr-xl">
                                <p class="font-semibold text-white text-theme-xs uppercase">Acciones</p>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sales as $sale)
                            <tr
                                class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                                <td class="px-4 text-center justify-center py-4 sm:px-6 sticky-left">
                                    <div class="flex items-center justify-center gap-2">
                                        <button type="button"
                                            @click="openRow === {{ $sale->id }} ? openRow = null : openRow = {{ $sale->id }}"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-[#FF4622] text-white transition hover:bg-[#C43B25]">
                                            <i class="ri-add-line" x-show="openRow !== {{ $sale->id }}"></i>
                                            <i class="ri-subtract-line" x-show="openRow === {{ $sale->id }}"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <div>
                                        @php
                                            $displayNumber = trim((string) ($sale->electronic_invoice_number ?? ''));
                                            if ($displayNumber === '') {
                                                $displayNumber = strtoupper(substr($sale->documentType->name, 0, 1)) . ($sale->salesMovement->series ?? '') . '-' . $sale->number;
                                            }
                                        @endphp
                                        <p class="font-bold text-gray-800 text-theme-sm dark:text-white/90">
                                            {{ $displayNumber }}
                                        </p>
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 uppercase font-medium">
                                            {{ $sale->documentType?->name ?? '-' }}
                                        </p>
                                    </div>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="text-gray-800 text-theme-sm dark:text-white/90">S/
                                        {{ number_format((float) ($sale->salesMovement?->subtotal ?? 0), 2) }}</p>
                                </td>
                                <td class="px-5 text-center py-4 sm:px-6">
                                    <p class="text-gray-800 text-theme-sm dark:text-white/90">S/
                                        {{ number_format((float) ($sale->salesMovement?->tax ?? 0), 2) }}</p>
                                </td>
                                <td class="px-5 text-center py-4 sm:px-6">
                                    <p class="font-bold text-[#FF4622] text-theme-sm dark:text-[#FF4622]/80">S/ 
                                        {{ number_format((float) ($sale->salesMovement?->total ?? 0), 2) }}</p>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="text-gray-800 text-theme-sm dark:text-white/90 truncate max-w-[150px]"
                                        title="{{ $sale->person_name ?? 'Público General' }}">
                                        {{ $sale->person_name ?? 'Público General' }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    @php
                                        $status = $sale->status ?? 'A';
                                        $badgeColor = 'success';
                                        $badgeText = 'Activo';
                                        if ($status === 'P') {
                                            $badgeColor = 'warning';
                                            $badgeText = 'Pendiente';
                                        } elseif ($status !== 'A') {
                                            $badgeColor = 'error';
                                            $badgeText = 'Inactivo';
                                        }
                                    @endphp
                                    <x-ui.badge variant="light" color="{{ $badgeColor }}">
                                        {{ $badgeText }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-5 text-center py-4 sm:px-6">
                                    <div class="flex items-center justify-center gap-2">
                                        @php
                                            $documentName = mb_strtolower(trim((string) ($sale->documentType?->name ?? '')), 'UTF-8');
                                            $canConvertTicket = str_contains($documentName, 'ticket');
                                        @endphp
                                        @if ($canConvertTicket)
                                            <div class="relative group">
                                                <button type="button"
                                                    @click="$dispatch('open-convert-ticket-modal', {
                                                        saleId: {{ $sale->id }},
                                                        currentPersonId: {{ $sale->person_id ? (int) $sale->person_id : 'null' }}
                                                    })"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-violet-600 text-white shadow-sm transition hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500/40"
                                                    aria-label="Convertir a boleta o factura">
                                                    <i class="ri-file-transfer-line"></i>
                                                </button>
                                                <span
                                                    class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                    Convertir a boleta/factura
                                                    <span
                                                        class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                </span>
                                            </div>
                                        @endif
                                        @if (!empty($thermalPrintEnabled))
                                            <div class="relative group">
                                                <button type="button"
                                                    data-thermal-print-sale="{{ $sale->id }}"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-teal-600 text-white shadow-sm transition hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500/40"
                                                    aria-label="Imprimir comprobante en ticketera">
                                                    <i class="ri-printer-line"></i>
                                                </button>
                                                <span
                                                    class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                    Imprimir ticketera (mismo formato que cobro)
                                                    <span
                                                        class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                </span>
                                            </div>
                                        @endif
                                        @if ($rowOperations->isNotEmpty())
                                            @foreach ($rowOperations as $operation)
                                                @php
                                                    $action = $operation->action ?? '';
                                                    $isDelete = str_contains($action, 'destroy');
                                                    $isCharge = str_contains($action, 'charge');
                                                    $isPrint = str_contains($action, 'print');
                                                    if ($isCharge && ($sale->status ?? 'A') !== 'P') {
                                                        continue;
                                                    }

                                                    $actionUrl = $resolveActionUrl($action, $sale, $operation);
                                                    if ($isCharge && $actionUrl !== '#') {
                                                        $separator = str_contains($actionUrl, '?') ? '&' : '?';
                                                        $actionUrl .=
                                                            $separator . 'movement_id=' . urlencode($sale->id);
                                                    }

                                                    $buttonColor = $operation->color ?: '#FF4622';
                                                    $buttonTextColor = str_contains($action, 'edit')
                                                        ? '#111827'
                                                        : '#FFFFFF';
                                                    $buttonStyle = "background-color: {$buttonColor}; color: {$buttonTextColor};";
                                                    $variant = $isDelete
                                                        ? 'eliminate'
                                                        : (str_contains($action, 'edit')
                                                            ? 'edit'
                                                            : 'primary');
                                                    $variant = $isPrint ? 'outline' : $variant;
                                                @endphp

                                                @if ($isDelete)
                                                    <form method="POST" action="{{ $actionUrl }}"
                                                        class="relative group js-swal-delete"
                                                        data-swal-title="Eliminar venta?"
                                                        data-swal-text="Se eliminara la venta {{ $sale->number }}. Esta accion no se puede deshacer."
                                                        data-swal-confirm="Si, eliminar" data-swal-cancel="Cancelar"
                                                        data-swal-confirm-color="#ef4444"
                                                        data-swal-cancel-color="#6b7280">
                                                        @csrf
                                                        @method('DELETE')
                                                        @if ($viewId)
                                                            <input type="hidden" name="view_id"
                                                                value="{{ $viewId }}">
                                                        @endif
                                                        <x-ui.button size="icon" variant="{{ $variant }}"
                                                            type="submit" className="rounded-xl"
                                                            style="{{ $buttonStyle }}"
                                                            aria-label="{{ $operation->name }}">
                                                            <i class="{{ $operation->icon }}"></i>
                                                        </x-ui.button>
                                                        <span
                                                            class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                            {{ $operation->name }}
                                                            <span
                                                                class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                        </span>
                                                    </form>
                                                @elseif ($isPrint)
                                                    <div class="relative group">
                                                        <x-ui.link-button size="icon" variant="outline"
                                                            href="{{ $actionUrl }}" className="rounded-xl"
                                                            style="{{ $buttonStyle }}"
                                                            aria-label="{{ $operation->name }}" target="_blank">
                                                            <i class="{{ $operation->icon }}"></i>
                                                        </x-ui.link-button>
                                                        <span
                                                            class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                            {{ $operation->name }}
                                                            <span
                                                                class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                        </span>
                                                    </div>
                                                @else
                                                    <div class="relative group">
                                                        <x-ui.link-button size="icon" variant="{{ $variant }}"
                                                        href="{{ $actionUrl }}" className="rounded-xl"
                                                        style="{{ $buttonStyle }}" aria-label="{{ $operation->name }}">
                                                        <i class="{{ $operation->icon }}"></i>
                                                    </x-ui.link-button>
                                                    <span
                                                        class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-3 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-[100] shadow-xl">
                                                        {{ $operation->name }}
                                                        <span
                                                            class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                    </span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        @else
                                            @if (($sale->status ?? 'A') === 'P')
                                                <div class="relative group">
                                                    <x-ui.link-button size="icon" variant="primary"
                                                        href="{{ route('sales.charge', array_merge(['movement_id' => $sale->id], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                        className="bg-success-500 text-white hover:bg-success-600 ring-0 rounded-full"
                                                        style="border-radius: 100%; background-color: #10B981; color: #FFFFFF;"
                                                        aria-label="Cobrar">
                                                        <i class="ri-money-dollar-circle-line"></i>
                                                    </x-ui.link-button>
                                                    <span
                                                        class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50"
                                                        style="transition-delay: 0.5s;">Cobrar</span>
                                                </div>
                                            @endif
                                            <div class="relative group">
                                                <x-ui.link-button size="icon" variant="edit"
                                                    href="{{ route('sales.edit', array_merge([$sale], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                    className="bg-warning-500 text-white hover:bg-warning-600 ring-0 rounded-full"
                                                    style="border-radius: 100%; background-color: #FBBF24; color: #111827;"
                                                    aria-label="Editar">
                                                    <i class="ri-pencil-line"></i>
                                                </x-ui.link-button>
                                                <span
                                                    class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50"
                                                    style="transition-delay: 0.5s;">Editar</span>
                                            </div>
                                            @php
                                                $linkedOrderMovement = $sale->orderMovement ?? $sale->movement?->orderMovement;
                                                $linkedTable = $linkedOrderMovement?->table;
                                                $tableHasOtherPendingOrder = false;
                                                if ($linkedOrderMovement && $linkedTable) {
                                                    $tableHasOtherPendingOrder = \App\Models\OrderMovement::query()
                                                        ->where('table_id', $linkedTable->id)
                                                        ->where('id', '!=', $linkedOrderMovement->id)
                                                        ->whereIn('status', ['PENDIENTE', 'P'])
                                                        ->exists();
                                                }

                                                $deleteMessage = "Se eliminara la venta {$sale->number}. Esta accion no se puede deshacer.";
                                                if ($linkedOrderMovement && $linkedTable) {
                                                    $deleteMessage .= " El pedido asociado se volvera a cargar en la mesa {$linkedTable->name}.";
                                                    if ($tableHasOtherPendingOrder) {
                                                        $deleteMessage .= ' La mesa ya tiene otro pedido pendiente, por lo que conservara su estado actual.';
                                                    } else {
                                                        $deleteMessage .= ' La mesa volvera a estado ocupada.';
                                                    }
                                                }
                                            @endphp
                                            <form method="POST"
                                                action="{{ route('sales.destroy', array_merge([$sale], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                class="relative group js-swal-delete" data-swal-title="Eliminar venta?"
                                                data-swal-text="{{ $deleteMessage }}"
                                                data-swal-confirm="Si, eliminar" data-swal-cancel="Cancelar"
                                                data-swal-confirm-color="#ef4444" data-swal-cancel-color="#6b7280">
                                                @csrf
                                                @method('DELETE')
                                                @if ($viewId)
                                                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                @endif
                                                <x-ui.button size="icon" variant="eliminate" type="submit"
                                                    className="bg-error-500 text-white hover:bg-error-600 ring-0 rounded-full"
                                                    style="border-radius: 100%; background-color: #EF4444; color: #FFFFFF;"
                                                    aria-label="Eliminar">
                                                    <i class="ri-delete-bin-line"></i>
                                                </x-ui.button>
                                                <span
                                                    class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50"
                                                    style="transition-delay: 0.5s;">Eliminar</span>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            <tr x-show="openRow === {{ $sale->id }}" x-cloak
                                class="bg-gray-50/70 dark:bg-gray-800/40 border-b border-gray-100 justify-center dark:border-gray-800">
                                <td colspan="8" class="px-6 py-5">
                                    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 overflow-hidden shadow-sm">
                                        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80 flex items-center gap-2">
                                            <i class="ri-file-list-3-line text-[#FF4622]"></i>
                                            <h4 class="text-sm font-bold text-gray-700 dark:text-gray-200">Detalle de la venta #{{ $sale->salesMovement?->number ?? $sale->id }}</h4>
                                        </div>
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-sm text-left">
                                                <thead class="text-xs text-gray-500 uppercase bg-white dark:bg-gray-900 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800">
                                                    <tr>
                                                        <th class="px-4 py-3 font-semibold">Producto(s)</th>
                                                        <th class="px-4 py-3 font-semibold">Cantidad</th>
                                                        <th class="px-4 py-3 font-semibold">Fecha venta</th>
                                                        <th class="px-4 py-3 font-semibold text-right">Hora venta</th>
                                                        <th class="px-4 py-3 font-semibold">Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                        <tr class="hover:bg-gray-50/50 dark:hover:bg-white/5 transition-colors">
                                                            <td class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300 tabular-nums">
                                                                @foreach ($sale->salesMovement?->details as $detail)
                                                                    <span class="text-gray-600 text-xs dark:text-gray-400 truncate block max-w-full" title="{{ $detail->description }}">{{ $detail->description }}</span>
                                                                @endforeach
                                                            </td>
                                                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 tabular-nums">
                                                                @foreach ($sale->salesMovement?->details as $detail)
                                                                    <!--Cantidad de productos vendido y de cortesia-->
                                                                    <span class="text-gray-600 text-xs dark:text-gray-400 truncate block max-w-full" title="{{ $detail->quantity }}">
                                                                        {{ number_format((float) ($detail->quantity ?? 0), 2) }}
                                                                        @if ((float) ($detail->courtesy_quantity ?? 0) > 0)
                                                                            <span class="text-amber-500">({{ number_format((float) $detail->courtesy_quantity, 2) }} cortesía)</span>
                                                                        @endif
                                                                    </span>
                                                                @endforeach
                                                            </td>
                                                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 tabular-nums">{{ $sale->moved_at?->format('d/m/Y') ?? '-' }}</td>
                                                            <td class="px-4 py-3 text-right font-bold text-gray-700 dark:text-gray-300 tabular-nums">{{ $sale->moved_at?->format('H:i') ?? '-' }}</td>
                                                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                                                <x-ui.badge variant="light" color="{{ $sale->status === 'P' ? 'warning' : 'success' }}"
                                                                    class="inline-flex text-[11px] font-medium px-2 py-0.5">
                                                                    {{ $sale->status === 'P' ? 'Pendiente' : 'Activo' }}
                                                                </x-ui.badge>
                                                            </td>
                                                        </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-12">
                                    <div class="flex flex-col items-center gap-3 text-center text-sm text-gray-500">
                                        <div
                                            class="rounded-full bg-gray-100 p-3 text-gray-400 dark:bg-gray-800 dark:text-gray-300">
                                            <i class="ri-shopping-bag-3-line"></i>
                                        </div>
                                        <p class="text-base font-semibold text-gray-700 dark:text-gray-200">No hay ventas
                                            registradas.</p>
                                        <p class="text-gray-500">Crea la primera venta para comenzar.</p>
                                        <x-ui.link-button size="sm" variant="primary"
                                            style="background-color: #FF4622; color: #FFFFFF;"
                                            href="{{ route('sales.create', $viewId ? ['view_id' => $viewId] : []) }}">
                                            <i class="ri-add-line"></i>
                                            <span>Registrar venta</span>
                                        </x-ui.link-button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-500">
                    Mostrando
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $sales->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $sales->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $sales->total() }}</span>
                </div>
                <div>
                    {{ $sales->links() }}
                </div>
            </div>
        </x-common.component-card>
    </div>

    <div x-data="{ open: false, saleId: null, personId: '', documentTypeId: '{{ $firstConvertibleDocumentTypeId }}' }"
        x-on:open-convert-ticket-modal.window="
            open = true;
            saleId = $event.detail.saleId;
            personId = $event.detail.currentPersonId ?? '';
            documentTypeId = '{{ $firstConvertibleDocumentTypeId }}';
        "
        x-on:sales-convert-client-selected.window="personId = $event.detail.id"
        x-show="open" x-cloak
        class="fixed inset-0 z-[120] items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
        :class="{ 'flex': open }">
        <div @click.outside="open = false"
            class="w-full max-w-xl rounded-2xl bg-white shadow-2xl dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Convertir Ticket</h3>
                <button type="button" @click="open = false"
                    class="flex h-9 w-9 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <form method="POST"
                x-bind:action="saleId ? '{{ url('/admin/ventas') }}/' + saleId + '/convertir-electronico' : '#'"
                class="space-y-4 p-5">
                @csrf
                @if ($viewId)
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo de comprobante</label>
                    <select name="document_type_id" x-model="documentTypeId"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-800 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        @foreach ($convertibleDocumentTypesCollection as $documentType)
                            <option value="{{ $documentType->id }}">{{ $documentType->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Cliente</label>
                    <div class="flex gap-2 items-start">
                        <div class="min-w-0 flex-1">
                            <x-form.select.combobox :options="$convertClientOptions" x-model="personId"
                                name="person_id" placeholder="Buscar cliente..." :hide-icon="true"
                                :clearable="true" class="w-full" />
                        </div>
                        @if ($branch ?? null)
                            <button type="button" title="Nuevo cliente"
                                onclick="window.dispatchEvent(new CustomEvent('open-person-modal'))"
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white text-[#FF4622] hover:bg-[#FF4622]/10 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-[#FF4622]/20">
                                <i class="ri-add-line text-xl"></i>
                            </button>
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Para factura debes elegir un cliente con RUC válido.
                    </p>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="open = false"
                        class="rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                        Cancelar
                    </button>
                    <button type="submit"
                        class="rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700">
                        Convertir y emitir
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if ($branch ?? null)
        <x-ui.modal x-data="{ open: false }" @open-person-modal.window="open = true"
            @close-person-modal.window="open = false" :isOpen="false" :showCloseButton="false" class="max-w-4xl z-[140]">
            <div class="p-6 sm:p-8 bg-white dark:bg-gray-800">
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FF4622]/10 text-[#FF4622] dark:bg-[#FF4622]/20 dark:text-[#FF4622]">
                            <i class="ri-user-add-line text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Registrar / Editar Cliente</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ingresa DNI y nombre de la persona.
                            </p>
                        </div>
                    </div>
                    <button type="button" @click="open = false"
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600 transition-colors">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>

                <form id="quick-client-form-sales-convert" method="POST" data-client-combobox-name="person_id"
                    action="{{ route('admin.companies.branches.people.store', [$branch->company_id ?? '0', $branch->id ?? '0']) }}"
                    class="space-y-6">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                    <input type="hidden" name="from_pos" value="1">
                    @include('orders._quick_client_form', ['person' => null])

                    <div class="flex flex-wrap gap-3 justify-end pt-4 border-t border-gray-100 dark:border-gray-700">
                        <button type="button" @click="open = false"
                            class="px-5 py-2.5 rounded-xl border border-gray-300 text-gray-700 font-semibold hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="px-5 py-2.5 rounded-xl bg-[#FF4622] text-white font-semibold hover:bg-[#C43B25] shadow-lg shadow-[#FF4622]/30 transition-all">
                            <i class="ri-save-line mr-1"></i> Guardar Cliente
                        </button>
                    </div>
                </form>
            </div>
        </x-ui.modal>
    @endif

    @push('scripts')
        @vite(['resources/js/qz-tray-init.js'])
        <script>
            (function() {
                const salesThermalPrintUrl = @json(route('sales.print.ticket.thermal'));
                const salesTicketPrintBaseUrl = @json(route('admin.sales.print.ticket', ['sale' => '__SALE__']));
                const salesIndexViewId = @json($viewId ?? '');

                function openSaleTicketPdfTab(movementId) {
                    if (!movementId) return;
                    let ticketUrl = salesTicketPrintBaseUrl.replace('__SALE__', movementId);
                    if (salesIndexViewId) {
                        ticketUrl += (ticketUrl.includes('?') ? '&' : '?') + 'view_id=' + encodeURIComponent(salesIndexViewId);
                    }
                    window.open(ticketUrl, '_blank', 'noopener,noreferrer');
                }

                function isQzTrayAvailable() {
                    try {
                        return typeof window.qz !== 'undefined' && window.qz !== null;
                    } catch (e) {
                        return false;
                    }
                }

                async function ensureQzTrayConnected(qzApi, printerNameForCert) {
                    if (!qzApi || !isQzTrayAvailable()) {
                        return false;
                    }
                    if (typeof window.__qzConnectWithCertPairFallback === 'function') {
                        return await window.__qzConnectWithCertPairFallback(qzApi, printerNameForCert);
                    }
                    if (qzApi.websocket.isActive()) {
                        return true;
                    }
                    try {
                        await qzApi.websocket.connect();
                        return qzApi.websocket.isActive();
                    } catch (e) {
                        console.warn('QZ Tray: conexión no disponible.', e);
                        return false;
                    }
                }

                function resolveStrictLocalPrinterName() {
                    const host = String(window.location.hostname || '').trim().toLowerCase();
                    const isLocalhost = ['localhost', '127.0.0.1', '::1'].includes(host);
                    return isLocalhost ? 'BARRA' : 'BARRA2';
                }

                function requiresStrictLocalQz(printerName) {
                    const target = String(printerName || '').trim().toLowerCase();
                    return target === 'barra2' || target.startsWith('barra2');
                }

                function thermalPrintToast(title, message, icon) {
                    if (window.Swal) {
                        Swal.fire({
                            toast: true,
                            position: 'bottom-end',
                            icon: icon || 'success',
                            title: title,
                            text: message,
                            showConfirmButton: false,
                            timer: 3200,
                            timerProgressBar: true,
                        });
                    } else {
                        alert((title ? title + ': ' : '') + (message || ''));
                    }
                }

                async function printThermalSaleReceipt(movementId) {
                    if (!movementId) return;
                    const qzApi = window.qz;
                    const sel = document.getElementById('sales-index-thermal-printer');
                    const printerId = sel && sel.value ? parseInt(sel.value, 10) : null;
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    const preferredPrinterName = resolveStrictLocalPrinterName();
                    const strictLocalQz = requiresStrictLocalQz(preferredPrinterName);
                    const body = { movement_id: movementId };
                    if (printerId) {
                        body.printer_id = printerId;
                    }

                    if (qzApi && await ensureQzTrayConnected(qzApi, preferredPrinterName)) {
                        try {
                            const tr = await fetch(salesThermalPrintUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                    Accept: 'application/json',
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify({ ...body, mode: 'qz' }),
                            });
                            const td = tr.headers.get('content-type')?.includes('application/json') ? await tr.json() : null;
                            if (!tr.ok || !td?.success || (!td?.ticket_pdf_b64 && !td?.payload_b64)) {
                                throw new Error(td?.message || 'No se pudo obtener el ticket del servidor.');
                            }
                            let printerName = preferredPrinterName || td.printer_name || '';
                            if (!printerName) {
                                printerName = await qzApi.printers.getDefault();
                            }
                            if (!printerName) {
                                openSaleTicketPdfTab(movementId);
                                return;
                            }
                            const paperMm = (parseInt(td.paper_width, 10) || 58) === 80 ? 80 : 58;
                            const sizeOpts = { units: 'mm', size: { width: paperMm, height: 200 } };
                            const configPdf = qzApi.configs.create(printerName, { ...sizeOpts, scaleContent: true });
                            const configRaw = qzApi.configs.create(printerName, { ...sizeOpts, scaleContent: false });
                            if (td.ticket_pdf_b64 && td.qz_print_format === 'pdf') {
                                try {
                                    await qzApi.print(configPdf, [{
                                        type: 'pixel',
                                        format: 'pdf',
                                        flavor: 'base64',
                                        data: td.ticket_pdf_b64,
                                    }]);
                                } catch (pdfErr) {
                                    console.warn('QZ Tray: PDF ticket, reintento RAW', pdfErr);
                                    await qzApi.print(configRaw, [{
                                        type: 'raw',
                                        format: 'base64',
                                        data: td.payload_b64,
                                    }]);
                                }
                            } else {
                                await qzApi.print(configRaw, [{
                                    type: 'raw',
                                    format: 'base64',
                                    data: td.payload_b64,
                                }]);
                            }
                            thermalPrintToast('Impresión', 'Comprobante enviado a "' + printerName + '".', 'success');
                        } catch (e) {
                            console.warn('QZ Ticket listado:', e);
                            if (strictLocalQz) {
                                openSaleTicketPdfTab(movementId);
                                return;
                            }
                            try {
                                const tr = await fetch(salesThermalPrintUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': csrf,
                                        Accept: 'application/json',
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify(body),
                                });
                                const td = tr.headers.get('content-type')?.includes('application/json') ? await tr.json() : null;
                                if (tr.ok && td?.success) {
                                    thermalPrintToast('Impresión', td.message || 'Enviado a la ticketera.', 'success');
                                } else {
                                    openSaleTicketPdfTab(movementId);
                                }
                            } catch (e2) {
                                openSaleTicketPdfTab(movementId);
                            }
                        }
                        return;
                    }

                    if (strictLocalQz) {
                        openSaleTicketPdfTab(movementId);
                        return;
                    }

                    try {
                        const tr = await fetch(salesThermalPrintUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                Accept: 'application/json',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify(body),
                        });
                        const td = tr.headers.get('content-type')?.includes('application/json') ? await tr.json() : null;
                        if (tr.ok && td?.success) {
                            thermalPrintToast('Impresión', td.message || 'Enviado a la ticketera.', 'success');
                        } else {
                            openSaleTicketPdfTab(movementId);
                        }
                    } catch (e) {
                        openSaleTicketPdfTab(movementId);
                    }
                }

                window.printThermalSaleReceipt = printThermalSaleReceipt;

                document.addEventListener('click', function (e) {
                    const btn = e.target.closest('[data-thermal-print-sale]');
                    if (!btn) {
                        return;
                    }
                    e.preventDefault();
                    const id = parseInt(btn.getAttribute('data-thermal-print-sale'), 10);
                    if (id) {
                        printThermalSaleReceipt(id);
                    }
                });

                function runThermalReprintFromQuery() {
                    const params = new URLSearchParams(window.location.search);
                    const idRaw = params.get('thermal_reprint');
                    if (!idRaw) {
                        return;
                    }
                    const id = parseInt(idRaw, 10);
                    if (!id) {
                        return;
                    }
                    const stripThermalParam = function () {
                        params.delete('thermal_reprint');
                        const qs = params.toString();
                        const path = window.location.pathname + (qs ? '?' + qs : '');
                        window.history.replaceState({}, '', path);
                    };
                    const doneKey = 'thermal_reprint_done_' + id;
                    try {
                        if (sessionStorage.getItem(doneKey) === '1') {
                            stripThermalParam();
                            return;
                        }
                        sessionStorage.setItem(doneKey, '1');
                    } catch (err) {
                        // ignore
                    }
                    stripThermalParam();
                    setTimeout(function () {
                        printThermalSaleReceipt(id);
                    }, 500);
                }

                document.addEventListener('DOMContentLoaded', runThermalReprintFromQuery);
                document.addEventListener('turbo:load', runThermalReprintFromQuery);

                function showFlashToast() {
                    const msg = sessionStorage.getItem('flash_success_message');
                    if (!msg) return;
                    sessionStorage.removeItem('flash_success_message');
                    if (window.Swal) {
                        Swal.fire({
                            toast: true,
                            position: 'bottom-end',
                            icon: 'success',
                            title: msg,
                            showConfirmButton: false,
                            timer: 3500,
                            timerProgressBar: true
                        });
                    }
                }
                showFlashToast();
                document.addEventListener('turbo:load', showFlashToast);
            })();

            function descargarPdf() {
                const btn = document.querySelector('[data-pdf-url]');
                const baseUrl = btn ? btn.dataset.pdfUrl : "{{ route('admin.sales.pdf') }}";

                // Sobreescribir fechas con lo que tenga el picker en este momento
                const url = new URL(baseUrl, window.location.origin);
                const dfVal = document.querySelector('[name="date_from"]')?.value;
                const dtVal = document.querySelector('[name="date_to"]')?.value;
                if (dfVal) url.searchParams.set('date_from', dfVal);
                if (dtVal) url.searchParams.set('date_to', dtVal);

                window.open(url.toString(), '_blank');
            }

            function descargarExcel() {
                const btn = document.querySelector('[data-excel-url]');
                const baseUrl = btn ? btn.dataset.excelUrl : "{{ route('admin.sales.excel') }}";

                const url = new URL(baseUrl, window.location.origin);
                const dfVal = document.querySelector('[name=\"date_from\"]')?.value;
                const dtVal = document.querySelector('[name=\"date_to\"]')?.value;
                if (dfVal) url.searchParams.set('date_from', dfVal);
                if (dtVal) url.searchParams.set('date_to', dtVal);

                window.open(url.toString(), '_blank');
            }

            function setupSalesConvertQuickClientForm() {
                const form = document.getElementById('quick-client-form-sales-convert');
                if (!form || form.dataset.boundSalesConvert === '1') return;
                form.dataset.boundSalesConvert = '1';
                form.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalText = submitBtn ? submitBtn.innerHTML : '';
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML =
                            '<i class="ri-loader-4-line animate-spin mr-1"></i> Guardando...';
                    }
                    try {
                        const fd = new FormData(form);
                        const res = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            body: fd
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data?.success || !data?.id) {
                            const validation = data?.errors && typeof data.errors === 'object' ?
                                Object.values(data.errors).flat().join('\n') :
                                '';
                            throw new Error(validation || data?.message || 'No se pudo crear el cliente.');
                        }

                        const comboName = form.dataset.clientComboboxName || 'person_id';
                        const label = (data.description && String(data.description).trim() !== '') ?
                            data.description :
                            ([data.name, data.document_number].filter(Boolean).join(' - ') || 'Cliente');
                        const newOpts = [...(window.__salesConvertClientOptions || [])];
                        if (!newOpts.some(o => String(o.id) === String(data.id))) {
                            newOpts.push({
                                id: data.id,
                                description: label
                            });
                        } else {
                            const o = newOpts.find(x => String(x.id) === String(data.id));
                            if (o) {
                                o.description = label;
                            }
                        }
                        window.__salesConvertClientOptions = newOpts;
                        window.dispatchEvent(new CustomEvent('update-combobox-options', {
                            detail: {
                                name: comboName,
                                options: newOpts
                            }
                        }));
                        window.dispatchEvent(new CustomEvent('sales-convert-client-selected', {
                            detail: { id: data.id }
                        }));
                        window.dispatchEvent(new CustomEvent('close-person-modal'));
                    } catch (err) {
                        alert(err?.message || 'Error al crear cliente.');
                    } finally {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
                    }
                });
            }
            setupSalesConvertQuickClientForm();
            document.addEventListener('DOMContentLoaded', setupSalesConvertQuickClientForm);
            document.addEventListener('turbo:load', setupSalesConvertQuickClientForm);
        </script>
    @endpush
@endsection
