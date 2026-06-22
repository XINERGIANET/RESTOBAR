@extends('layouts.app')

@push('head')
    <meta name="qz-sign-url" content="{{ route('qz.sign') }}">
    <meta name="qz-certificate-url" content="{{ route('qz.certificate') }}">
    <meta name="qz-signature-algorithm" content="{{ config('qz.signature_algorithm', 'SHA512') }}">
    <script>
        window.__qzSecondaryFirstPrinterNames = @json(config('qz.secondary_first_printer_names', []));
        window.__qzTertiaryFirstPrinterNames = @json(config('qz.tertiary_first_printer_names', []));
        window.__qzKitchenSkipClientQzWhenPrinterHasIp = @json((bool) config('qz.kitchen_skip_client_qz_when_printer_has_ip', true));
        window.__qzKitchenComandaDisableClientOnTouch = @json((bool) config('qz.kitchen_comanda_disable_client_qz_on_touch_devices', true));
    </script>
    @vite(['resources/js/qz-tray-init.js'])

@endpush

@section('title', 'Punto de Venta')

@section('content')
    <div class="px-4 md:px-6 pt-4 pb-2 flex flex-col">
        <div class="flex items-center justify-between shrink-0"></div>
        @php
            $isCounterSale = $isCounterSale ?? false;
            $viewId = request('view_id');
            $breadcrumbAreaName = $table->area?->name ?? ($area?->name ?? 'Sin área');
        @endphp
        @if (!empty($isCounterSale))
            <x-common.page-breadcrumb pageTitle="Nueva venta (mostrador)" :breadcrumbs="[
                ['label' => 'Ventas', 'url' => route('sales.index', $viewId ? ['view_id' => $viewId] : [])],
                ['label' => 'Nueva venta', 'active' => true],
            ]" />
        @else
            <x-common.page-breadcrumb pageTitle="{{ $breadcrumbAreaName }} | Mesa {{ $table->name ?? $table->id }}"
                :breadcrumbs="[
                    ['label' => 'Salones', 'url' => route('orders.index')],
                    ['label' => 'Mesa ' . ($table->name ?? $table->id), 'active' => true],
                ]" />
        @endif

        <div class="flex flex-col bg-gray-50 dark:bg-gray-950">
            @php
                $serverTableData = [
                    'id' => $table->id,
                    'table_id' => !empty($isCounterSale) ? null : $table->id,
                    'area_id' => $table->area_id ?? ($area->id ?? null),
                    'name' => $table->name ?? $table->id,
                    'waiter' => $pendingWaiterName ?? ($user?->name ?? 'Sin asignar'),
                    'waiter_id' => $pendingWaiterId ?? null,
                    'person_id' => $pendingClientId ?? null,
                    'clientName' => $pendingClientName ?? 'CLIENTES VARIOS',
                    'status' => $table->situation ?? 'libre',
                    'items' => [],
                    'people_count' => (int) ($pendingPeopleCount ?? ($table->capacity ?? 1)),
                    'service_type' => $pendingServiceType ?? 'IN_SITU',
                    'delivery_address' => $pendingDeliveryAddress ?? '',
                    'contact_phone' => $pendingContactPhone ?? '',
                    'delivery_amount' => (float) ($pendingDeliveryAmount ?? 0),
                    'takeaway_disposable_charge' => ((float) ($pendingTakeawayDisposableAmount ?? 0)) > 0,
                    'takeaway_disposable_amount' => (float) ($pendingTakeawayDisposableAmount ?? 0),
                    'original_area_id' => $area->id ?? null,
                    'original_area_name' => $area->name ?? 'Sin área',
                    'delivery_area_id' => !empty($isCounterSale) ? null : $deliveryAreaId ?? null,
                ];

                $peopleCollection = $people ?? collect();
                $clientOptions = $peopleCollection
                    ->map(function ($p) {
                        $name = trim(($p->first_name ?? '') . ' ' . ($p->last_name ?? ''));
                        if ($name === '' && !empty($p->document_number)) {
                            $name = $p->document_number;
                        }
                        $document = trim((string) ($p->document_number ?? ''));
                        $description = trim($document !== '' ? $document . ' - ' . $name : $name);
                        return [
                            'id' => $p->id,
                            'description' => $description,
                            'client_name' => $name,
                            'document_number' => $document,
                        ];
                    })
                    ->unique(function ($item) {
                        return implode('|', [
                            trim((string) ($item['document_number'] ?? '0')) ?: '0',
                            mb_strtolower(trim((string) ($item['client_name'] ?? '')), 'UTF-8'),
                        ]);
                    })
                    ->values()
                    ->all();
                $waitersCollection = $waiters ?? collect();
                $waiterOptions = $waitersCollection
                    ->map(function ($w) {
                        $name = trim(($w->first_name ?? '') . ' ' . ($w->last_name ?? ''));
                        return [
                            'id' => $w->id,
                            'description' => $name,
                        ];
                    })
                    ->values()
                    ->all();
            @endphp
            <script>
                window.__orderClientOptions = @json($clientOptions);
            </script>
            <script>
                window.__orderWaiterOptions = @json($waiterOptions);
            </script>
            <script>
                (function() {
                    const serverTable = @json($serverTableData);
                    const startFresh = @json($startFresh ?? false);
                    const serverOrderMovementId = @json($pendingOrderMovementId ?? null);
                    const serverMovementId = @json($pendingMovementId ?? null);
                    const serverPendingItems = @json($pendingItems ?? []);
                    let db = JSON.parse(localStorage.getItem('restaurantDB')) || {};
                    let activeKey = @json($posStorageKey ?? null) || `table-{{ $table->id }}`;
                    const tableIsFree = (serverTable.status || '').toLowerCase() === 'libre';
                    const useFreshOrder = startFresh || tableIsFree;
                    if (useFreshOrder && db[activeKey]) {
                        delete db[activeKey];
                        localStorage.setItem('restaurantDB', JSON.stringify(db));
                    }
                    let currentTable = (useFreshOrder || !db[activeKey]) ? serverTable : db[activeKey];
                    if (!currentTable.people_count) currentTable.people_count = {{ $pendingPeopleCount ?? 1 }};
                    if (serverOrderMovementId) {
                        currentTable = {
                            ...currentTable,
                            ...serverTable
                        };
                        currentTable.order_movement_id = serverOrderMovementId;
                        currentTable.movement_id = serverMovementId;
                        currentTable.items = Array.isArray(serverPendingItems) ? serverPendingItems : [];
                        currentTable.items.forEach(it => {
                            it.complements = Array.isArray(it.complements) ? it.complements.filter(Boolean) : [];
                            it.savedQty = parseFloat(it.qty) ?? parseFloat(it.quantity) ?? 0;
                            it.savedCourtesyQty = parseFloat(it.courtesyQty) ?? parseFloat(it.courtesy_quantity) ?? 0;
                            it.savedTakeawayQty = parseFloat(it.takeawayQty) ?? parseFloat(it.takeaway_quantity) ?? 0;
                            if (it.takeawayQty == null || isNaN(parseFloat(it.takeawayQty))) it.takeawayQty = 0;
                            const q = parseFloat(it.qty) || 0;
                            let t = parseFloat(it.takeawayQty) || 0;
                            if (t > q) it.takeawayQty = q;
                            it.priceManual = true;
                        });
                        currentTable.cancellations = [];
                        db[activeKey] = currentTable;
                        localStorage.setItem('restaurantDB', JSON.stringify(db));
                    } else {
                        currentTable.order_movement_id = null;
                        currentTable.movement_id = null;
                        currentTable.items = currentTable.items || [];
                        currentTable.cancellations = [];
                        (currentTable.items || []).forEach(it => {
                            it.complements = Array.isArray(it.complements) ? it.complements.filter(Boolean) : [];
                            if (it.takeawayQty == null || isNaN(parseFloat(it.takeawayQty))) it.takeawayQty = 0;
                            const q = parseFloat(it.qty) || 0;
                            let t = parseFloat(it.takeawayQty) || 0;
                            if (t > q) it.takeawayQty = q;
                        });
                    }
                    if (!serverOrderMovementId || tableIsFree) {
                        currentTable.cancellations = [];
                    }
                    currentTable.cancellations = currentTable.cancellations || [];
                    if (currentTable.takeaway_disposable_charge === undefined) {
                        currentTable.takeaway_disposable_charge = !!serverTable.takeaway_disposable_charge;
                    }
                    if (currentTable.takeaway_disposable_amount == null || isNaN(parseFloat(currentTable
                            .takeaway_disposable_amount))) {
                        currentTable.takeaway_disposable_amount = parseFloat(serverTable.takeaway_disposable_amount) || 0;
                    }
                    window.currentTable = currentTable;
                    window.serverTable = serverTable;
                    window.activeKey = activeKey;
                    window.db = db;
                    window.tableIsFree = tableIsFree;
                    window.__splitAccount = {
                        enabled: @json($split_account_enabled ?? false),
                        lines: @json($pending_split_lines ?? []),
                        remainingTotal: @json($pending_split_remaining_total),
                        splitMode: @json($pending_split_mode),
                        lockedToAmount: @json($split_locked_to_amount ?? false)
                    };
                })();
            </script>

            <!-- Barra de Información: Mesa, Mozo y Comensales -->
            <div class="px-3 sm:px-4 mb-2 flex-none">
                <div
                    class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-3 sm:p-4 shadow-sm flex items-center justify-between gap-2 sm:gap-4">
                    <div class="flex items-center gap-2 sm:gap-8 min-w-0 flex-1">
                        <!-- Mesa -->
                        <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-1">
                            <div class="hidden">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider leading-none mb-1">
                                    Mesa / Área</p>
                                <div class="flex items-center gap-1.5">
                                    <span id="pos-table-name"
                                        class="font-black text-gray-900 dark:text-white text-base">--</span>
                                    <span class="text-gray-300 dark:text-gray-700">·</span>
                                    <span id="pos-table-area"
                                        class="text-sm font-medium text-gray-500 dark:text-gray-400">--</span>
                                </div>
                            </div>
                        </div>

                        @if (!($isMozo ?? false))
                            <!-- Mozo -->
                            <div class="flex items-center gap-3" x-data="{
                                waiterId: null,
                                init() {
                                    if (window.currentTable && window.currentTable.waiter_id) {
                                        this.waiterId = window.currentTable.waiter_id;
                                    } else if ((window.__orderWaiterOptions || []).length > 0) {
                                        const firstWaiter = window.__orderWaiterOptions[0];
                                        this.waiterId = firstWaiter?.id ?? null;
                                    }
                                    this.$watch('waiterId', v => {
                                        if (!v) return;
                                        const opts = window.__orderWaiterOptions || [];
                                        const sel = opts.find(o => String(o.id) === String(v));
                                        if (sel && window.currentTable) {
                                            window.currentTable.waiter = sel.description;
                                            window.currentTable.waiter_id = sel.id;
                                            const el = document.getElementById('pos-waiter-name-display');
                                            if (el) el.innerText = sel.description;
                                            if (typeof saveDB === 'function') saveDB();
                                        }
                                    });
                                }
                            }">

                                <div class="min-w-[140px] sm:min-w-[180px]">
                                    <p
                                        class="text-[10px] font-bold text-gray-400 uppercase tracking-wider leading-none mb-1">
                                        Mozo</p>
                                    <x-form.select.combobox :options="$waiterOptions" x-model="waiterId"
                                        placeholder="Elegir mozo..." :compact="true" class="w-full" />
                                    <span id="pos-waiter-name-display" class="hidden">--</span>
                                </div>
                            </div>
                        @endif
                        {{-- Comensal: visible para todos (incl. mozo); el selector "Mozo" solo si no es perfil mozo --}}
                        <div class="flex items-center gap-3">
                            <div class="min-w-0 flex-1 sm:min-w-[220px]">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider leading-none mb-1">
                                    Comensal</p>
                                <div class="relative">
                                    <input type="text" id="header-client-name"
                                        value="{{ old('client_name', $pendingClientName ?? 'CLIENTES VARIOS') }}"
                                        placeholder="Escribir nombre del comensal..."
                                        oninput="updateHeaderClientName(this.value)"
                                        class="w-full h-11 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 pr-10 text-sm text-gray-800 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-[#FF4622]/30 focus:border-[#FF4622] outline-none transition-all">
                                    <button type="button" onclick="clearHeaderClientName()"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 flex h-7 w-7 items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-red-500 dark:hover:bg-gray-700 dark:hover:text-red-400 transition-colors"
                                        title="Limpiar comensal">
                                        <i class="ri-close-line text-base"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Comensales -->
                    <div class="shrink-0 flex items-center gap-2">
                        <div class="flex h-10 w-10 items-center justify-center text-gray-400">
                            <i class="ri-group-line"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider leading-none mb-1">
                                Nro Personas</p>
                            <input type="number" id="diners-input" min="1" max="50" value="1"
                                class="w-11 h-8 text-center text-sm font-semibold rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#FF4622]/20 focus:border-[#FF4622] outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                onchange="currentTable.people_count = parseInt(this.value, 10) || 1; this.value = currentTable.people_count; saveDB();">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col lg:flex-row bg-gray-50/50 dark:bg-gray-950/50 gap-3 p-3">
                <div class="flex-1 min-w-0 p-3 sm:p-4 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-800 flex flex-col">
                    <div class="flex flex-col flex-1 min-w-0">
                        <div class="shrink-0 border-gray-300 px-2 sm:px-4 pt-3 pb-4">
                            <div class="flex items-center justify-between">
                            </div>
                            <div id="categories-grid"
                                class="flex flex-row flex-wrap gap-1.5 sm:gap-2 overflow-x-auto pb-3 overscroll-x-contain">
                            </div>
                        </div>
                        <div class="px-2 sm:px-4 pt-3 pb-2 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white shrink-0">Productos</h2>
                            <div class="relative flex-1 max-w-md">
                                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                <input type="text" id="search-products"
                                    class="w-full pl-10 pr-10 py-2 text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-800 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-[#FF4622]/30 focus:border-[#FF4622] outline-none transition-all"
                                    placeholder="Buscar por nombre o código interno (lector)...">
                                <button type="button" id="search-products-clear" onclick="clearProductSearch()"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hidden">
                                    <i class="ri-close-circle-fill text-lg"></i>
                                </button>
                            </div>

                        </div>
                        <div class="pt-2 sm:pt-3">
                            <div id="products-grid"
                                class="px-2 sm:px-4 md:px-5 p-3 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2 sm:gap-4 content-start pb-6">
                            </div>
                        </div>
                    </div>
                </div>
                <aside class="lg:w-[450px] w-full md:w-[350px] lg:shrink-0 mx-auto lg:mx-0 flex-none bg-white dark:bg-gray-900 border-t lg:border-t-0 lg:border-l border-gray-200 dark:border-gray-800 flex flex-col rounded-2xl overflow-hidden shadow-sm">
                    {{-- Tabs Resumen | Cobro (Cobro oculto para Mozo) --}}
                    <div class="w-full shrink-0 px-3 pt-3">
                        <div class="grid gap-3 {{ $canCharge ?? true ? 'grid-cols-2' : 'grid-cols-1' }}">
                            <button type="button" id="tab-resumen" onclick="window.switchAsideTab?.('resumen')"
                                class="py-3 px-4 text-sm font-bold transition-all rounded-full bg-[#FF4622] text-white shadow-sm border border-[#FF4622] {{ !($canCharge ?? true) ? 'w-full' : '' }}">
                                Resumen
                            </button>
                            @if ($canCharge ?? true)
                                <button type="button" id="tab-cobro" onclick="window.switchAsideTab?.('cobro')"
                                    class="py-3 px-4 text-sm font-bold transition-all rounded-full bg-white dark:bg-gray-900 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:border-[#FF4622]/30 hover:text-[#FF4622] dark:hover:text-[#FF4622]">
                                    Cobro
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Contenido Resumen --}}
                    <div id="aside-resumen" class="mt-3 flex flex-col flex-1 min-h-0 overflow-hidden">
                        {{-- Datos Delivery --}}
                        <div id="delivery-info-container"
                            class="hidden p-3 bg-[#FF4622]/5 dark:bg-[#FF4622]/10 border-b border-[#FF4622]/20 dark:border-[#FF4622]/30 space-y-2 overflow-hidden">
                            <div class="flex flex-col gap-2">
                                <div class="flex-1 min-w-0">
                                    <label
                                        class="block text-[10px] font-bold uppercase text-[#FF4622] dark:text-[#FF4622] mb-1">Dirección
                                        de Entrega</label>
                                    <input type="text" id="delivery-address" oninput="updateDeliveryInfo()"
                                        placeholder="Av. Siempre Viva 123"
                                        class="w-full py-1.5 px-2 text-xs rounded border border-[#FF4622]/30 focus:ring-1 focus:ring-[#FF4622] outline-none">
                                </div>
                                <div class="flex gap-2">
                                    <div class="flex-1 min-w-0">
                                        <label
                                            class="block text-[10px] font-bold uppercase text-[#FF4622] dark:text-[#FF4622] mb-1">Teléfono
                                            Contacto</label>
                                        <input type="text" id="delivery-phone" oninput="updateDeliveryInfo()"
                                            placeholder="999..."
                                            class="w-full py-1.5 px-2 text-xs rounded border border-[#FF4622]/30 focus:ring-1 focus:ring-[#FF4622] outline-none">
                                    </div>
                                    <div class="w-24">
                                        <label
                                            class="block text-[10px] font-bold uppercase text-[#FF4622] dark:text-[#FF4622] mb-1">Costo
                                            Delivery</label>
                                        <input type="number" step="0.5" id="delivery-amount"
                                            oninput="updateDeliveryInfo()" placeholder="0.00"
                                            class="w-full py-1.5 px-2 text-xs rounded border border-[#FF4622]/30 focus:ring-1 focus:ring-[#FF4622] outline-none">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Datos Para Llevar --}}
                        <div id="takeaway-info-container"
                            class="hidden p-3 bg-orange-50 dark:bg-orange-900/20 border-b border-orange-100 dark:border-orange-800 space-y-2">
                            <div class="flex flex-col gap-2">
                                <div class="flex gap-2">
                                    <div class="flex-1 min-w-0">
                                        <label
                                            class="block text-[10px] font-bold uppercase text-orange-600 dark:text-orange-400 mb-1">Nombre
                                            de Retiro (Opcional)</label>
                                        <input type="text" id="takeaway-client-name" oninput="updateTakeAwayInfo()"
                                            placeholder="Quien recoge..."
                                            class="w-full py-1.5 px-2 text-xs rounded border border-orange-200 focus:ring-1 focus:ring-orange-400 outline-none">
                                    </div>
                                    <div class="w-28">
                                        <label
                                            class="block text-[10px] font-bold uppercase text-orange-600 dark:text-orange-400 mb-1">Hora
                                            Retiro</label>
                                        <input type="time" id="takeaway-time" oninput="updateTakeAwayInfo()"
                                            class="w-full py-1.5 px-2 text-xs rounded border border-orange-200 focus:ring-1 focus:ring-orange-400 outline-none">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Descartables (para llevar): opcional, suma al total --}}
                        <div id="takeaway-disposable-panel"
                            class="hidden shrink-0 p-3 bg-amber-50/80 dark:bg-amber-950/25 border-b border-amber-100 dark:border-amber-900/50 space-y-2">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="checkbox" id="takeaway-disposable-charge"
                                    class="rounded border-amber-300 text-orange-600 focus:ring-orange-500"
                                    onchange="updateTakeawayDisposableInfo()">
                                <span class="text-[11px] font-bold uppercase text-amber-800 dark:text-amber-200">Cobrar
                                    descartables (llevar)</span>
                            </label>
                            <div class="flex items-end gap-2">
                                <div class="flex-1 min-w-0">
                                    <label for="takeaway-disposable-amount"
                                        class="block text-[10px] font-bold uppercase text-amber-700 dark:text-amber-300 mb-1">Monto
                                        descartables (S/)</label>
                                    <input type="number" step="0.01" min="0" id="takeaway-disposable-amount"
                                        disabled oninput="updateTakeawayDisposableInfo(false)"
                                        onblur="updateTakeawayDisposableInfo(true)" placeholder="0.00"
                                        class="w-full py-1.5 px-2 text-xs rounded border border-amber-200 dark:border-amber-800 bg-white dark:bg-zinc-900 focus:ring-1 focus:ring-amber-400 outline-none disabled:opacity-50">
                                </div>
                            </div>
                        </div>

                        <div id="cart-container"
                            class="flex-1 min-h-0 overflow-y-auto p-3 sm:p-4 space-y-2 sm:space-y-2.5 bg-white dark:bg-gray-900">
                        </div>
                        <div id="cancelled-platos-container"
                            class="shrink-0 hidden border-t border-gray-200 dark:border-gray-700 bg-amber-50 dark:bg-amber-900/20 p-3 sm:p-4 max-h-40 overflow-y-auto">
                            <p
                                class="text-xs font-semibold text-amber-800 dark:text-amber-200 mb-2 flex items-center gap-1">
                                <i class="ri-error-warning-line"></i> Platos anulados
                            </p>
                            <div id="cancelled-platos-list" class="space-y-1.5 text-xs"></div>
                        </div>
                        <div class="shrink-0 p-4 sm:p-5 border-t border-gray-200 dark:border-gray-700">
                            <div class="space-y-2 sm:space-y-3 text-xs sm:text-sm">
                                <div class="flex justify-between text-gray-500 font-medium">
                                    <span>Subtotal</span>
                                    <span class="text-slate-700 dark:text-slate-300" id="ticket-subtotal">$0.00</span>
                                </div>
                                <div class="flex justify-between text-gray-500 font-medium">
                                    <span>Impuestos</span>
                                    <span class="text-slate-700 dark:text-slate-300" id="ticket-tax">$0.00</span>
                                </div>
                                <div id="ticket-delivery-row"
                                    class="hidden flex justify-between text-gray-500 font-medium">
                                    <span>Delivery</span>
                                    <span class="text-slate-700 dark:text-slate-300" id="ticket-delivery">$0.00</span>
                                </div>
                                <div id="ticket-takeaway-disposable-row"
                                    class="hidden flex justify-between text-gray-500 font-medium">
                                    <span>Descartables (llevar)</span>
                                    <span class="text-slate-700 dark:text-slate-300"
                                        id="ticket-takeaway-disposable">$0.00</span>
                                </div>
                                <div id="ticket-credit-days-row"
                                    class="hidden flex justify-between text-gray-500 font-medium">
                                    <span>Días de crédito</span>
                                    <span class="text-slate-700 dark:text-slate-300 tabular-nums"
                                        id="ticket-credit-days-display">0</span>
                                </div>
                                <div id="ticket-due-date-row"
                                    class="hidden flex justify-between text-gray-500 font-medium">
                                    <span>Fecha vencimiento</span>
                                    <span class="text-slate-700 dark:text-slate-300 tabular-nums"
                                        id="ticket-due-date-display">—</span>
                                </div>
                                <div class="border-t border-dashed border-gray-300 dark:border-gray-600 my-2"></div>
                                <p id="ticket-split-remaining-hint"
                                    class="hidden text-[11px] text-slate-500 dark:text-slate-400 leading-snug mb-1"></p>
                                <div class="flex justify-between items-center">
                                    <span id="ticket-total-label"
                                        class="text-base sm:text-lg font-bold text-slate-800 dark:text-white">Total a
                                        Pagar</span>
                                    <span class="text-xl sm:text-2xl font-black text-[#FF4622] dark:text-[#FF4622]"
                                        id="ticket-total">$0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Contenido Cobro (oculto para Mozo) --}}
                    @if ($canCharge ?? true)
                        <div id="aside-cobro" class="hidden mt-3 flex-col flex-1 min-h-0 overflow-hidden">
                            <div class="flex-1 min-h-0 overflow-y-auto p-4 sm:p-5 space-y-4">
                                <div class="flex items-center justify-center gap-2 w-full">
                                    <div class="flex-1 min-w-0" id="order-client-picker" x-data="{
                                        clientId: @json($pendingClientId ?? null),
                                        init() {
                                            if (window.currentTable?.person_id) {
                                                this.clientId = window.currentTable.person_id;
                                            }
                                            this.$watch('clientId', v => {
                                                const opts = window.__orderClientOptions || [];
                                                const sel = opts.find(o => String(o.id) === String(v));
                                                const clientName = sel ? (sel.client_name || sel.description || 'CLIENTES VARIOS') : 'CLIENTES VARIOS';
                                                const clientLabel = sel ? (sel.description || clientName) : 'CLIENTES VARIOS';
                                                if (window.currentTable) {
                                                    window.currentTable.person_id = v ? parseInt(v, 10) : null;
                                                    window.currentTable.clientName = clientName;
                                                    window.currentTable.clientLabel = clientLabel;
                                                    if (typeof saveDB === 'function') saveDB();
                                                }
                                                const ci = document.getElementById('cobro-client-input');
                                                if (ci) ci.value = clientLabel;
                                            });
                                        }
                                    }">
                                        <x-form.select.combobox :clearOnFocus="true" :options="$clientOptions" x-model="clientId"
                                            name="header_client_id" placeholder="Elegir cliente..." :compact="true"
                                            input-id="order_client_search" class="w-full" :hide-icon="true"
                                            :clearable="true" />
                                    </div>
                                    <button type="button"
                                        class="inline-flex shrink-0 items-center justify-center h-9 w-9 rounded-lg bg-white border border-gray-200 text-gray-400 hover:bg-[#FF4622]/10 hover:text-[#C43B25] hover:border-[#FF4622]/30 shadow-sm transition-colors"
                                        @click="$dispatch('open-person-modal')" title="Nuevo cliente">
                                        <i class="ri-user-add-line text-base"></i>
                                    </button>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label
                                            class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Documento</label>
                                        <select id="cobro-document-type"
                                            class="w-full py-2.5 px-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-slate-700 dark:text-slate-200 text-sm">
                                            @forelse(($documentTypes ?? []) as $dt)
                                                <option value="{{ optional($dt)->id }}" @selected((int) ($defaultDocumentTypeId ?? 0) === (int) optional($dt)->id)>
                                                    {{ optional($dt)->name ?? '' }}</option>
                                            @empty
                                                <option value="">Sin documentos</option>
                                            @endforelse
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Caja</label>
                                        <select id="cobro-cash-register" disabled
                                            class="w-full py-2.5 px-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-100 dark:bg-gray-800/70 text-slate-700 dark:text-slate-200 text-sm cursor-not-allowed">
                                            @forelse(($cashRegisters ?? []) as $cr)
                                                <option value="{{ optional($cr)->id }}" @selected((int) session('cash_register_id') === (int) optional($cr)->id)>
                                                    {{ optional($cr)->number ?? 'Caja ' . optional($cr)->id }}
                                                </option>
                                            @empty
                                                <option value="">Sin cajas</option>
                                            @endforelse
                                        </select>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Tipo de venta</label>
                                        <select id="cobro-sale-type" class="w-full py-2.5 px-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-slate-700 dark:text-slate-200 text-sm" onchange="toggleCobroCreditFields()">
                                            <option value="CONTADO">Contado</option>
                                            <option value="CREDITO">Crédito / Deuda</option>
                                        </select>
                                    </div>
                                    <div id="cobro-credit-fields" class="hidden grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Días de crédito</label>
                                            <input type="number" id="cobro-credit-days" min="0" value="0" class="w-full py-2.5 px-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-slate-700 dark:text-slate-200 text-sm" oninput="calculateCobroDueDate()">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Fecha vencimiento</label>
                                            <input type="date" id="cobro-due-date" onchange="syncTicketCreditSummary()"
                                                class="w-full py-2.5 px-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-slate-700 dark:text-slate-200 text-sm">
                                        </div>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Detalle
                                            en comprobante</label>
                                        <select id="cobro-detail-mode"
                                            class="w-full py-2.5 px-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-slate-700 dark:text-slate-200 text-sm"
                                            onchange="toggleCobroDetailGlosa()">
                                            <option value="DETALLADO">Detallado</option>
                                            <option value="CONSUMO">Por consumo</option>
                                            <option value="GLOSA">Glosa personalizada</option>
                                        </select>
                                    </div>
                                    <div id="cobro-detail-glosa-wrapper" class="hidden">
                                        <label
                                            class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Glosa</label>
                                        <input type="text" id="cobro-detail-glosa"
                                            class="w-full py-2.5 px-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-slate-700 dark:text-slate-200 text-sm"
                                            placeholder="Escribe el detalle que saldra en el comprobante">
                                    </div>
                                </div>
                                @if (!empty($split_account_enabled))
                                    <div class="flex flex-wrap items-center gap-2 mb-3">
                                        <button type="button" onclick="openSplitAccountModal()"
                                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800/80 px-3 py-2 text-sm font-semibold text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors shadow-sm">
                                            <i class="ri-scissors-cut-line text-lg text-[#FF4622]"></i>
                                            <span>Dividir cuenta</span>
                                        </button>
                                        <span id="split-inline-status"
                                            class="text-xs text-slate-500 dark:text-slate-400 max-w-[14rem]"></span>
                                        <input type="checkbox" id="split-dividir-cuenta" class="hidden"
                                            aria-hidden="true">
                                    </div>
                                @endif
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <label
                                            class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Métodos
                                            de pago</label>
                                        <button type="button" id="cobro-btn-add-payment-method"
                                            onclick="addCobroPaymentMethod()"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-[#FF4622] px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-[#C43B25] active:scale-95 transition-colors shrink-0">
                                            <i class="ri-add-line text-sm"></i> Agregar
                                        </button>
                                    </div>
                                    <div id="cobro-payment-methods-list" class="space-y-3 max-h-48 overflow-y-auto pr-1">
                                    </div>
                                    <div
                                        class="mt-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-100 dark:bg-gray-800/80 px-3 py-2.5 space-y-2">
                                        <div id="cobro-order-total-row"
                                            class="hidden flex justify-between items-center gap-2 border-b border-gray-200/80 dark:border-gray-600/80 pb-2">
                                            <span
                                                class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Total
                                                del pedido</span>
                                            <span
                                                class="text-xs font-semibold tabular-nums text-slate-600 dark:text-slate-300"
                                                id="cobro-order-total-display">S/ 0.00</span>
                                        </div>
                                        <div class="flex justify-between items-center gap-2">
                                            <span class="text-xs font-semibold text-gray-600 dark:text-gray-300"
                                                id="cobro-total-label">Total pagado</span>
                                            <span class="text-base font-bold text-slate-800 dark:text-white tabular-nums"
                                                id="cobro-total-paid">S/ 0.00</span>
                                        </div>
                                        <p id="cobro-split-footnote"
                                            class="hidden text-[11px] leading-snug text-slate-500 dark:text-slate-400">Este
                                            cobro es por una parte del pedido. La mesa no se libera hasta saldar el saldo
                                            pendiente.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Botones Guardar / Cobrar: visibles según pestaña activa --}}
                    <div
                        class="shrink-0 mt-auto p-4 sm:p-5 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 rounded-b-2xl">
                        {{-- Footer Resumen: solo Guardar y Precuenta --}}
                        <div id="footer-resumen" class="flex justify-between items-center gap-3">
                            <button type="button" id="btn-precuenta"
                                class="hidden py-2.5 px-4 rounded-xl bg-slate-100 text-slate-700 font-bold text-xs sm:text-sm border border-slate-200 hover:bg-slate-200 active:scale-95 transition-all flex justify-center items-center gap-2"
                                style="display: none;">
                                <i class="ri-save-line text-base"></i>
                                <span>Precuenta</span>
                            </button>
                            @if (empty($isCounterSale))
                                <button type="button" id="btn-guardar" onclick="processOrder()"
                                    class="py-2.5 px-4 rounded-xl bg-gray-500 text-white font-bold text-xs sm:text-sm shadow-lg hover:bg-gray-600 active:scale-95 transition-all flex justify-center items-center gap-2">
                                    <i class="ri-send-plane-2-line text-base"></i>
                                    <span>Enviar</span>
                                </button>
                            @endif
                        </div>
                        {{-- Footer Cobro: solo Cobrar (oculto para Mozo) --}}
                        @if ($canCharge ?? true)
                            <div id="footer-cobro" class="hidden flex flex-col items-end gap-1.5">
                                <p id="cobro-split-footer-hint"
                                    class="hidden max-w-[18rem] text-right text-[10px] text-slate-500 dark:text-slate-400 leading-tight">
                                    Si queda saldo pendiente en el pedido, la mesa sigue ocupada hasta el cobro final.</p>
                                <button type="button" onclick="processOrderPayment()"
                                    class="py-2.5 px-4 rounded-xl bg-[#FF4622] text-white font-bold text-xs sm:text-sm shadow-lg hover:bg-[#C43B25] active:scale-95 transition-all flex justify-center items-center gap-2">
                                    <i class="ri-bank-card-line text-base"></i>
                                    <span id="footer-cobro-btn-label">Cobrar</span>
                                </button>
                            </div>
                        @endif
                    </div>
                </aside>

            </div>

            @if (!empty($split_account_enabled))
                <div id="split-account-modal" class="fixed inset-0 z-[110] hidden" role="dialog" aria-modal="true"
                    aria-labelledby="split-modal-title">
                    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-[1px]" onclick="closeSplitAccountModal()">
                    </div>
                    <div
                        class="absolute inset-3 sm:inset-6 md:inset-auto md:left-1/2 md:top-1/2 md:-translate-x-1/2 md:-translate-y-1/2 md:max-w-lg md:w-full max-h-[min(92vh,540px)] flex flex-col rounded-2xl bg-white dark:bg-gray-900 shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden relative z-10">
                        <div
                            class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shrink-0">
                            <div class="flex items-center gap-2 min-w-0">
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#FF4622]/10 text-[#FF4622] dark:bg-[#FF4622]/20">
                                    <i class="ri-scissors-cut-line text-xl"></i>
                                </div>
                                <h2 id="split-modal-title"
                                    class="text-sm font-black uppercase tracking-wide text-slate-800 dark:text-white truncate">
                                    División de cuenta</h2>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span id="split-remaining-badge"
                                    class="text-xs font-bold tabular-nums text-slate-600 dark:text-slate-300 whitespace-nowrap">Pendiente:
                                    S/ 0.00</span>
                                <button type="button" onclick="closeSplitAccountModal()"
                                    class="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-gray-800"
                                    aria-label="Cerrar"><i class="ri-close-line text-xl"></i></button>
                            </div>
                        </div>
                        <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-4">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Elige cómo cobrar <strong>esta
                                    parte</strong>. Luego ajusta los métodos de pago para que sumen exactamente ese monto.
                            </p>
                            <div>
                                <span
                                    class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Modo</span>
                                <div
                                    class="flex rounded-xl border border-gray-200 dark:border-gray-600 p-0.5 bg-gray-100/80 dark:bg-gray-800/80">
                                    <button type="button" id="split-mode-tab-products"
                                        onclick="setSplitModeTab('products')"
                                        class="split-mode-tab flex-1 py-2.5 px-3 rounded-lg text-sm font-semibold transition-colors">Por
                                        productos</button>
                                    <button type="button" id="split-mode-tab-amount" onclick="setSplitModeTab('amount')"
                                        class="split-mode-tab flex-1 py-2.5 px-3 rounded-lg text-sm font-semibold transition-colors">Por
                                        monto</button>
                                </div>
                                <select id="split-mode" class="sr-only" tabindex="-1" aria-hidden="true">
                                    <option value="products">Por productos</option>
                                    <option value="amount">Por monto</option>
                                </select>
                            </div>
                            <div id="split-products-wrap" class="space-y-2">
                                <div
                                    class="max-h-52 overflow-y-auto rounded-xl border border-gray-200 dark:border-gray-600">
                                    <table class="w-full text-xs text-slate-700 dark:text-slate-200">
                                        <thead
                                            class="sticky top-0 z-[1] bg-slate-100/95 dark:bg-slate-800/95 backdrop-blur-sm">
                                            <tr class="border-b border-gray-200 dark:border-gray-600">
                                                <th class="text-left py-2 px-2 font-semibold">Producto</th>
                                                <th class="text-center py-2 px-1 font-semibold w-14">Pend.</th>
                                                <th class="text-right py-2 px-2 font-semibold min-w-[9rem]">Cobrar</th>
                                            </tr>
                                        </thead>
                                        <tbody id="split-products-tbody"></tbody>
                                    </table>
                                </div>
                            </div>
                            <div id="split-amount-wrap" class="hidden">
                                <label
                                    class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Monto
                                    a cobrar (S/)</label>
                                <input type="number" step="0.01" min="0" id="split-amount-input"
                                    class="w-full py-2.5 px-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-slate-700 dark:text-slate-200 text-sm tabular-nums"
                                    placeholder="0.00">
                            </div>
                            <p id="split-hint-locked" class="hidden text-xs text-slate-600 dark:text-slate-400">Este
                                pedido ya inició división por monto; solo puede continuar en ese modo.</p>
                        </div>
                        <div
                            class="flex flex-wrap gap-2 justify-end px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/95 shrink-0">
                            <button type="button" onclick="clearSplitDivision()"
                                class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-gray-700">Quitar
                                división</button>
                            <button type="button" onclick="closeSplitAccountModal()"
                                class="px-4 py-2 rounded-xl text-sm font-semibold border border-gray-300 dark:border-gray-600 text-slate-700 dark:text-slate-200 hover:bg-gray-100 dark:hover:bg-gray-800">Cancelar</button>
                            <button type="button" onclick="applySplitAccountModal()"
                                class="px-4 py-2 rounded-xl text-sm font-bold bg-[#FF4622] text-white hover:bg-[#C43B25] shadow">Aplicar</button>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Modal para crear/editar cliente rápido --}}
            <x-ui.modal x-data="{ open: false }" @open-person-modal.window="open = true"
                @close-person-modal.window="open = false" :isOpen="false" :showCloseButton="false" class="max-w-4xl z-[100]">
                <div class="p-6 sm:p-10 bg-white dark:bg-gray-800 relative">
                    <button type="button" @click="open = false"
                        class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-red-100 hover:text-red-500 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-red-900/30 dark:hover:text-red-400 transition-all">
                        <i class="ri-close-line text-xl"></i>
                    </button>

                    <div class="mb-6 flex items-center justify-between gap-4 pr-12">
                        <div class="flex items-center gap-3 min-w-0">
                            <div
                                class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#FF4622]/10 text-[#FF4622] dark:bg-[#FF4622]/20 dark:text-[#FF4622] shadow-sm">
                                <i class="ri-user-add-line text-2xl"></i>
                            </div>
                            <h3
                                class="text-lg sm:text-xl font-black text-gray-900 dark:text-white uppercase tracking-tight">
                                Registrar / Editar Cliente
                            </h3>
                        </div>
                    </div>

                    <form method="POST" id="quick-client-form"
                        action="{{ route('admin.companies.branches.people.store', [$branch->company_id ?? '0', $branch->id ?? '0']) }}"
                        class="space-y-6">
                        @csrf
                        <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                        <input type="hidden" name="from_pos" value="1">
                        @include('orders._quick_client_form', ['person' => null])

                        <div class="flex flex-wrap gap-3 justify-end pt-4">
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

            <div id="notification"
                class="fixed top-24 right-8 z-50 max-w-sm opacity-0 pointer-events-none transition-opacity duration-300"
                aria-live="polite"></div>

            {{-- Toast: producto agregado (igual que en ventas) --}}
            <div id="toast-container"
                class="fixed top-20 left-1/2 -translate-x-1/2 z-50 pointer-events-none flex flex-col gap-2 w-auto max-w-sm">
                <div id="add-to-cart-notification"
                    class="transform transition-all duration-300 -translate-y-10 opacity-0 pointer-events-none bg-slate-800 text-white shadow-2xl rounded-full px-6 py-3 flex items-center gap-3 min-w-[200px]">
                    <i class="ri-check-line text-green-400 text-xl"></i>
                    <div>
                        <p class="text-[10px] uppercase font-bold text-gray-400">Agregado</p>
                        <p id="notification-product-name" class="text-sm font-bold text-white truncate max-w-[180px]">
                            Producto
                        </p>
                    </div>
                </div>
            </div>

            {{-- removeQuantityModal: lógica en métodos + x-on: para evitar conflictos Blade/@ y SyntaxError en Alpine AsyncFunction --}}
            <script>
                (function() {
                    const isMozoFromServer = @json($isMozo ?? false);

                    function removeQuantityModalFactory() {
                        return {
                            open: false,
                            indexToRemove: null,
                            quantityToRemove: 1,
                            maxQty: 1,
                            productName: '',
                            reasonToRemove: '',
                            isComandado: false,
                            onOpenRemoveQuantityModal($event) {
                                if (isMozoFromServer) {
                                    if (window.Swal) {
                                        window.Swal.fire({
                                            icon: 'info',
                                            title: 'No permitido',
                                            text: 'El perfil Mozo no puede anular cantidades ya comandadas.'
                                        });
                                    }
                                    return;
                                }
                                const d = $event.detail;
                                if (d && d.maxQty >= 1) {
                                    this.open = true;
                                    this.indexToRemove = d.index != null ? d.index : null;
                                    this.maxQty = Math.max(1, d.maxQty != null ? d.maxQty : 1);
                                    this.productName = d.productName || 'Producto';
                                    this.quantityToRemove = 1;
                                    this.reasonToRemove = '';
                                    this.isComandado = !!d.isComandado;
                                    this.$nextTick(() => {
                                        if (this.$refs.qtyInput) this.$refs.qtyInput.value = 1;
                                    });
                                }
                            },
                            onCloseRemoveQuantityModal() {
                                this.open = false;
                                this.indexToRemove = null;
                                this.quantityToRemove = 1;
                                this.maxQty = 1;
                                this.productName = '';
                                this.reasonToRemove = '';
                                this.isComandado = false;
                            },
                            onQtyInput($event) {
                                const val = $event.target.value;
                                if (val === '' || val === null) return;
                                const v = parseInt(val, 10);
                                this.quantityToRemove = (Number.isNaN(v) || v < 1) ? 1 : Math.min(this.maxQty, Math.max(1, v));
                                $event.target.value = this.quantityToRemove;
                            },
                            onQtyBlur($event) {
                                if ($event.target.value === '' || Number.isNaN(parseInt($event.target.value, 10))) {
                                    this.quantityToRemove = 1;
                                    $event.target.value = 1;
                                }
                            },
                            onConfirmRemoveQuantity() {
                                if (this.indexToRemove == null || this.quantityToRemove < 1) return;
                                if (this.isComandado && !String(this.reasonToRemove || '').trim()) return;
                                const q = Math.min(this.quantityToRemove, this.maxQty);
                                if (typeof window.applyRemoveQuantity === 'function') {
                                    window.applyRemoveQuantity(this.indexToRemove, q, String(this.reasonToRemove || '').trim());
                                }
                                this.onCloseRemoveQuantityModal();
                            },
                        };
                    }
                    window.removeQuantityModal = removeQuantityModalFactory;

                    function registerRemoveQuantityModalData() {
                        if (!window.Alpine || typeof window.Alpine.data !== 'function') return;
                        if (window.__removeQuantityModalAlpineRegistered) return;
                        window.Alpine.data('removeQuantityModal', removeQuantityModalFactory);
                        window.__removeQuantityModalAlpineRegistered = true;
                    }
                    document.addEventListener('alpine:init', registerRemoveQuantityModalData);
                    registerRemoveQuantityModalData();
                })();
            </script>
            {{-- Modal eliminar por cantidad (se abre al presionar la basurita en una línea del pedido) --}}
            <x-ui.modal x-data="removeQuantityModal" x-on:open-remove-quantity-modal.window="onOpenRemoveQuantityModal($event)"
                x-on:close-remove-quantity-modal.window="onCloseRemoveQuantityModal()" :showCloseButton="false"
                class="max-w-4xl z-[100]">
                <div class="p-6 sm:p-8 bg-white dark:bg-gray-800">
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400">
                                <i class="ri-delete-bin-line text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Eliminar cantidad del pedido
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-300 mt-0.5"
                                    x-text="productName ? (productName + ' · Cantidad actual: ' + maxQty) : ''"></p>
                            </div>
                        </div>
                        <button type="button" x-on:click="onCloseRemoveQuantityModal()"
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600 transition-colors">
                            <i class="ri-close-line text-xl"></i>
                        </button>
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Cantidad a
                            eliminar</label>
                        <input type="number" x-ref="qtyInput" x-on:input="onQtyInput($event)"
                            x-on:blur="onQtyBlur($event)" min="1" :max="maxQty"
                            class="w-24 text-center text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1"
                            x-text="'Entre 1 y ' + maxQty + (maxQty === 1 ? ' unidad' : ' unidades')"></p>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mt-2" x-show="maxQty >= 1">
                            Quedarán: <span x-text="Math.max(0, maxQty - quantityToRemove)"></span> <span
                                x-text="(maxQty - quantityToRemove) === 1 ? 'unidad' : 'unidades'"></span>
                        </p>
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Razón <span x-show="isComandado" class="text-red-600">(requerida)</span>
                        </label>
                        <textarea x-model="reasonToRemove" rows="2" placeholder="Ej: pedido equivocado, cliente canceló..."
                            class="w-full text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 focus:ring-2 focus:ring-red-500 focus:border-red-500 resize-none"></textarea>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-600">
                        <button type="button" x-on:click="onCloseRemoveQuantityModal()"
                            class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            Cancelar
                        </button>
                        <button type="button" x-on:click="onConfirmRemoveQuantity()"
                            :disabled="isComandado && !reasonToRemove.trim()"
                            :class="isComandado && !reasonToRemove.trim() ? 'opacity-50 cursor-not-allowed' : ''"
                            class="px-4 py-2 rounded-xl bg-red-600 text-white hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            Eliminar <span
                                x-text="quantityToRemove > 1 ? quantityToRemove + ' unidades' : '1 unidad'"></span>
                        </button>
                    </div>
                </div>
            </x-ui.modal>
            <style>
                .notification-show {
                    transform: translateY(0) !important;
                    opacity: 1 !important;
                }
            </style>

            <script>
                (function() {
                    const serverPendingCancelledDetails = @json($pendingCancelledDetails ?? []);
                    const serverOrderMovementId = @json($pendingOrderMovementId ?? null);
                    const serverMovementId = @json($pendingMovementId ?? null);
                    const waiterPinEnabled = @json($waiterPinEnabled ?? false);
                    const isMozoProfile = @json($isMozo ?? false);
                    const validateWaiterPinUrl = @json(route('orders.validateWaiterPin'));
                    const waiterPinBranchId = @json((int) session('branch_id'));
                    const branchDisplayName = @json($branch?->legal_name ?? ($branch?->company?->legal_name ?? 'SUCURSAL'));
                    const branchAddressForTicket = @json(trim((string) ($branch->address ?? '')));
                    const counterPosMode = @json(!empty($isCounterSale));
                    const afterPaymentIndexUrl = @json($afterPaymentIndexUrl ?? route('orders.index'));
                    const cobroPaymentMethods = @json($paymentMethods ?? []);
                    const cobroPaymentGateways = @json($paymentGateways ?? []);
                    const cobroCards = @json($cards ?? []);
                    const cobroDigitalWallets = @json($digitalWallets ?? []);
                    const cobroBanks = @json($banks ?? []);
                    const salesThermalPrintUrl = @json(route('sales.print.ticket.thermal'));
                    const salesTicketPrintBaseUrl = @json(route('admin.sales.print.ticket', ['sale' => '__SALE__']));
                    const kitchenThermalPrintUrl = @json(route('orders.print.kitchen.thermal'));
                    const orderPreAccountPrintUrl = @json(route('orders.print.preaccount.thermal'));
                    const orderPreAccountPdfLinkUrl = @json(route('orders.print.preaccount.pdf.link'));
                    const salesDraftUrl = @json(route('sales.draft'));
                    const salesChargeUrl = @json(route('sales.charge'));
                    const salesViewIdParam = @json($viewId ?? null);

                    let autoSaveTimer = null;

                    async function openPdfLinkInNewTab(endpointUrl, payload) {
                        const popup = window.open('', '_blank');
                        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                        const response = await fetch(endpointUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify(payload),
                        });

                        if (!response.ok) {
                            if (popup) popup.close();
                            throw new Error('No se pudo generar el PDF.');
                        }

                        const data = await response.json();
                        if (!data?.url) {
                            if (popup) popup.close();
                            throw new Error('No se pudo obtener la URL del PDF.');
                        }

                        if (popup) {
                            popup.location.href = data.url;
                        } else {
                            window.open(data.url, '_blank');
                        }
                    }

                    function openSaleTicketPdfTab(movementId) {
                        if (!movementId) return;
                        const url = new URL(
                            salesTicketPrintBaseUrl.replace('__SALE__', encodeURIComponent(String(movementId))),
                            window.location.origin
                        );
                        const currentViewId = @json($viewId ?? null);
                        if (currentViewId) {
                            url.searchParams.set('view_id', currentViewId);
                        }
                        window.open(url.toString(), '_blank', 'noopener,noreferrer');
                    }

                    function getStoredWaiter() {
                        try {
                            const key = `waiterPin:${waiterPinBranchId}`;
                            const raw = sessionStorage.getItem(key);
                            if (!raw) return null;
                            const data = JSON.parse(raw);
                            if (!data || !data.person_id) return null;
                            const ts = Number(data.ts || 0);
                            if (!ts || (Date.now() - ts) > (12 * 60 * 60 * 1000)) {
                                sessionStorage.removeItem(key);
                                return null;
                            }
                            return data;
                        } catch (e) {
                            return null;
                        }
                    }

                    async function ensureWaiterPin() {
                        if (!waiterPinEnabled) return true;
                        const existing = getStoredWaiter();
                        if (existing) {
                            // Mostrar mozo real en la UI
                            currentTable.waiter = existing.name || currentTable.waiter;
                            const el = document.getElementById('pos-waiter-name');
                            if (el && existing.name) el.innerText = existing.name;
                            return true;
                        }
                        if (!window.Swal) return false;

                        while (true) {
                            const result = await Swal.fire({
                                title: 'PIN de mozo',
                                input: 'password',
                                inputLabel: 'Ingrese su PIN para tomar pedidos',
                                inputPlaceholder: 'PIN',
                                inputAttributes: {
                                    autocomplete: 'off'
                                },
                                showCancelButton: true,
                                confirmButtonText: 'Ingresar',
                                cancelButtonText: 'Cancelar',
                                reverseButtons: true,
                                inputValidator: (value) => {
                                    if (!value || !String(value).trim()) {
                                        return 'Ingrese el PIN.';
                                    }
                                    return null;
                                }
                            });
                            if (!result.isConfirmed) {
                                return false;
                            }
                            try {
                                const pin = String(result.value || '').trim();
                                const res = await fetch(validateWaiterPinUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                            ?.getAttribute('content') || '',
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    body: JSON.stringify({
                                        pin
                                    })
                                });
                                const data = await res.json().catch(() => null);
                                if (data && data.success && data.waiter && data.waiter.person_id) {
                                    const key = `waiterPin:${waiterPinBranchId}`;
                                    const payload = {
                                        ...data.waiter,
                                        ts: Date.now()
                                    };
                                    sessionStorage.setItem(key, JSON.stringify(payload));
                                    currentTable.waiter = payload.name || currentTable.waiter;
                                    const el = document.getElementById('pos-waiter-name');
                                    if (el && payload.name) el.innerText = payload.name;
                                    saveDB();
                                    return true;
                                }
                                await Swal.fire({
                                    toast: true,
                                    position: 'bottom-end',
                                    icon: 'error',
                                    title: data?.message || 'PIN inválido.',
                                    showConfirmButton: false,
                                    timer: 2500
                                });
                            } catch (e) {
                                await Swal.fire({
                                    toast: true,
                                    position: 'bottom-end',
                                    icon: 'error',
                                    title: 'No se pudo validar el PIN.',
                                    showConfirmButton: false,
                                    timer: 2500
                                });
                            }
                        }
                    }

                    function init() {
                        document.body.style.removeProperty('overflow');
                        document.body.style.removeProperty('overflow-y');
                        document.body.style.removeProperty('overflow-x');
                        document.documentElement.style.removeProperty('overflow');
                        document.documentElement.style.removeProperty('overflow-y');
                        document.documentElement.style.removeProperty('overflow-x');

                        // Si requiere PIN, pedirlo al abrir la mesa
                        if (waiterPinEnabled && !isMozoProfile) {
                            ensureWaiterPin();
                        }
                        // Marcar la mesa como ocupada al abrir la vista (no aplica en venta mostrador)
                        if (!counterPosMode) {
                            const tableId = currentTable.table_id ?? currentTable.id ?? {{ $table->id }};
                            fetch('{{ route('orders.openTable') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute(
                                        'content') || '',
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({
                                    table_id: tableId
                                })
                            }).catch(() => {});
                        }

                        // Inicializar datos de la mesa
                        if (document.getElementById('pos-table-name')) {
                            document.getElementById('pos-table-name').innerText = currentTable.name ||
                                "{{ str_pad($table->name ?? $table->id, 2, '0', STR_PAD_LEFT) }}";
                        }
                        if (document.getElementById('pos-table-area')) {
                            document.getElementById('pos-table-area').innerText = currentTable.area ||
                                "{{ $table->area?->name ?? ($area?->name ?? 'Sin área') }}";
                        }
                        if (document.getElementById('pos-waiter-name')) {
                            document.getElementById('pos-waiter-name').innerText = currentTable.waiter ||
                                "{{ $user?->name ?? 'Sin asignar' }}";
                        }
                        if (document.getElementById('pos-client-name')) {
                            document.getElementById('pos-client-name').innerText = currentTable.clientName ||
                                "CLIENTES VARIOS";
                        }
                        const headerClientNameInput = document.getElementById('header-client-name');
                        if (headerClientNameInput) {
                            headerClientNameInput.value = currentTable.clientName || '';
                        }
                        const cobroClientInput = document.getElementById('cobro-client-input');
                        if (cobroClientInput) {
                            cobroClientInput.value = currentTable.clientLabel || currentTable.clientName || "CLIENTES VARIOS";
                        }
                        const dinersInput = document.getElementById('diners-input');
                        if (dinersInput && currentTable.people_count) {
                            dinersInput.value = currentTable.people_count;
                        }

                        // Inicializar datos de servicio y delivery (automático por área; mostrador siempre IN_SITU)
                        if (!counterPosMode && currentTable.area_id == serverTable.delivery_area_id) {
                            currentTable.service_type = 'DELIVERY';
                        } else {
                            currentTable.service_type = 'IN_SITU';
                        }

                        // Actualizar visibilidad de datos delivery / takeaway
                        if (typeof changeServiceType === 'function') {
                            changeServiceType(currentTable.service_type || 'IN_SITU');
                        }

                        const addrInput = document.getElementById('delivery-address');
                        const phoneInput = document.getElementById('delivery-phone');
                        const amountInput = document.getElementById('delivery-amount');
                        const takeawayNameInput = document.getElementById('takeaway-client-name');
                        const takeawayTimeInput = document.getElementById('takeaway-time');

                        if (addrInput) addrInput.value = currentTable.delivery_address || '';
                        if (phoneInput) phoneInput.value = currentTable.contact_phone || '';
                        if (amountInput) amountInput.value = currentTable.delivery_amount || '';
                        if (takeawayNameInput) takeawayNameInput.value = currentTable.client_name_extra ||
                        ''; // Usamos un campo extra para no pisar el clientName principal
                        if (takeawayTimeInput) takeawayTimeInput.value = currentTable.delivery_time || '';
                        const dispChk = document.getElementById('takeaway-disposable-charge');
                        const dispAmt = document.getElementById('takeaway-disposable-amount');
                        if (dispChk) dispChk.checked = !!currentTable.takeaway_disposable_charge;
                        if (dispAmt) {
                            const dr = parseFloat(currentTable.takeaway_disposable_amount);
                            dispAmt.value = (!isNaN(dr) && dr > 0 ? dr : 0).toFixed(2);
                            dispAmt.disabled = !dispChk || !dispChk.checked;
                        }
                        refreshCartPricesFromServer();
                        renderCategories();
                        ensureMobileQuickFilters();
                        renderProducts();
                        renderTicket();
                        syncPreAccountVisibility();
                        syncCobroTabState();
                        renderCancelledSection();
                        fixScrollLayout();

                        function updateSearchClearVisibility() {
                            const inp = document.getElementById('search-products');
                            const btn = document.getElementById('search-products-clear');
                            if (btn) btn.classList.toggle('hidden', !inp || !inp.value.trim());
                        }
                        window.clearProductSearch = function() {
                            const inp = document.getElementById('search-products');
                            if (inp) {
                                inp.value = '';
                                productSearchQuery = '';
                                inp.focus();
                                updateSearchClearVisibility();
                                renderProducts();
                            }
                        };
                        document.addEventListener('input', function(e) {
                            if (e.target && e.target.id === 'search-products') {
                                productSearchQuery = (e.target.value || '').trim();
                                updateSearchClearVisibility();
                                renderProducts();
                            }
                        });
                        document.addEventListener('keyup', function(e) {
                            if (e.target && e.target.id === 'search-products') {
                                productSearchQuery = (e.target.value || '').trim();
                                updateSearchClearVisibility();
                                renderProducts();
                            }
                        });
                        document.addEventListener('keydown', function(e) {
                            if (e.target && e.target.id === 'search-products' && e.key === 'Escape')
                            clearProductSearch();
                        });
                        updateSearchClearVisibility();
                        ensureCounterSaleDefaultClientSelected();
                        setTimeout(focusProductSearchInput, 80);
                        setTimeout(() => {
                            ensureMobileQuickFilters();
                        }, 120);
                        if (currentTable.items && currentTable.items.length > 0) {
                            // setTimeout(scheduleAutoSave, 800);
                        }
                        if (typeof addCobroPaymentMethod === 'function' && document.getElementById('cobro-payment-methods-list')
                            ?.children.length === 0) {
                            addCobroPaymentMethod();
                        }
                        // Si viene con cobro=1 (desde botón Cobrar en mesas), abrir pestaña Cobro
                        if (new URLSearchParams(window.location.search).get('cobro') === '1' && typeof switchAsideTab ===
                            'function' && canAccessCobroTab()) {
                            setTimeout(() => switchAsideTab('cobro'), 100);
                        }
                        const btnPrecuenta = document.getElementById('btn-precuenta');
                        if (btnPrecuenta && !btnPrecuenta.dataset.boundPrecuenta) {
                            btnPrecuenta.dataset.boundPrecuenta = '1';
                            btnPrecuenta.addEventListener('click', () => {
                                printPreAccountTicket();
                            });
                        }
                        if (new URLSearchParams(window.location.search).get('pre_account') === '1' && currentTable
                            ?.order_movement_id) {
                            setTimeout(() => {
                                printPreAccountTicket();
                            }, 250);
                        }
                    }

                    function fixScrollLayout() {
                        function run() {
                            const grid = document.getElementById('products-grid');
                            const cart = document.getElementById('cart-container');
                            if (grid) {
                                grid.scrollTop = 0;
                                void grid.offsetHeight;
                            }
                            if (cart) {
                                cart.scrollTop = 0;
                                void cart.offsetHeight;
                            }
                            window.scrollTo(0, 0);
                        }
                        requestAnimationFrame(() => {
                            requestAnimationFrame(() => {
                                run();
                                setTimeout(run, 100);
                            });
                        });
                    }

                    // Función para escapar HTML y prevenir XSS
                    function syncPreAccountVisibility() {
                        const btnPrecuenta = document.getElementById('btn-precuenta');
                        if (!btnPrecuenta) return;
                        const items = Array.isArray(currentTable?.items) ? currentTable.items : [];
                        const hasItems = items.length > 0;
                        const hasCommandedItems = hasItems && items.some(item => {
                            const savedQty = parseFloat(item?.savedQty);
                            return Number.isFinite(savedQty) && savedQty > 0;
                        });
                        const hasServerPendingOrder = !!serverOrderMovementId && !window.tableIsFree;
                        const hasCurrentSavedOrder = hasServerPendingOrder &&
                            Number(currentTable?.order_movement_id || 0) > 0 &&
                            Number(currentTable?.order_movement_id || 0) === Number(serverOrderMovementId || 0);
                        const shouldShow = hasCurrentSavedOrder && hasItems && hasCommandedItems;
                        btnPrecuenta.classList.toggle('hidden', !shouldShow);
                        btnPrecuenta.style.display = shouldShow ? '' : 'none';
                    }

                    function canAccessCobroTab() {
                        const items = Array.isArray(currentTable?.items) ? currentTable.items : [];
                        const hasItems = items.length > 0;
                        if (counterPosMode) {
                            return hasItems;
                        }
                        const hasCommandedItems = hasItems && items.some(item => {
                            const savedQty = parseFloat(item?.savedQty);
                            return Number.isFinite(savedQty) && savedQty > 0;
                        });
                        const hasServerPendingOrder = !!serverOrderMovementId && !window.tableIsFree;
                        const hasCurrentSavedOrder = hasServerPendingOrder &&
                            Number(currentTable?.order_movement_id || 0) > 0 &&
                            Number(currentTable?.order_movement_id || 0) === Number(serverOrderMovementId || 0);
                        return hasCurrentSavedOrder && hasItems && hasCommandedItems;
                    }

                    function syncCobroTabState() {
                        const btnCobro = document.getElementById('tab-cobro');
                        if (!btnCobro) return;
                        const enabled = canAccessCobroTab();

                        if (enabled) {
                            btnCobro.removeAttribute('disabled');
                        } else {
                            btnCobro.setAttribute('disabled', 'disabled');
                        }
                        btnCobro.classList.toggle('opacity-50', !enabled);
                        btnCobro.classList.toggle('cursor-not-allowed', !enabled);
                        btnCobro.classList.toggle('pointer-events-none', !enabled);
                        if (enabled) {
                            btnCobro.title = 'Cobro';
                        } else if (counterPosMode) {
                            btnCobro.title = 'Agrega al menos un producto para cobrar';
                        } else {
                            btnCobro.title = 'Disponible cuando el pedido ya fue enviado';
                        }

                        if (!enabled) {
                            const cobro = document.getElementById('aside-cobro');
                            if (cobro && !cobro.classList.contains('hidden')) {
                                switchAsideTab('resumen');
                            }
                        }
                    }

                    function escapeHtml(text) {
                        if (!text) return '';
                        const div = document.createElement('div');
                        div.textContent = text;
                        return div.innerHTML;
                    }

                    const PLACEHOLDER_IMG =
                        'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="200" height="200"%3E%3Crect fill="%23e5e7eb" width="200" height="200"/%3E%3Ctext fill="%239ca3af" font-family="sans-serif" font-size="14" dy="10.5" font-weight="bold" x="50%25" y="50%25" text-anchor="middle"%3ESin imagen%3C/text%3E%3C/svg%3E';

                    // Función para obtener la URL de la imagen
                    function getImageUrl(imagePath) {
                        if (!imagePath || imagePath === 'null' || imagePath === null || imagePath === '') {
                            return PLACEHOLDER_IMG;
                        }
                        // Si ya es una URL completa, retornarla
                        if (imagePath.startsWith('http://') || imagePath.startsWith('https://') || imagePath.startsWith(
                                'data:')) {
                            return imagePath;
                        }
                        // Si es una ruta relativa que empieza con /, retornarla tal cual
                        if (imagePath.startsWith('/')) {
                            return imagePath;
                        }
                        // Si es una ruta de storage, ya viene con asset() desde el servidor
                        return imagePath;
                    }
                    window.getImageUrl = getImageUrl;

                    // Datos de productos, categorías y productBranches desde el servidor.
                    const serverProductBranches = @json($productBranches ?? []);
                    const areaPrinterNames = @json($areaPrinterNames ?? []);
                    @php
                        $serverCategoriesPayload = collect($categories ?? [])
                            ->map(fn ($c) => [
                                'id' => $c->id,
                                'name' => $c->description ?? '',
                                'img' => $c->image ? asset('storage/' . $c->image) : null,
                            ])
                            ->values()
                            ->all();
                    @endphp
                    const serverCategories = @json($serverCategoriesPayload);
                    const serverProductsRaw = @json($products ?? []);

                    // Stock de ingredientes por producto con receta activa: { "productId": { yield_quantity, ingredients[] } }
                    const recipeStockData = @json($recipeStockData ?? []);

                    /**
                     * Para un producto con receta, calcula el máximo de unidades vendibles
                     * según el stock actual de cada ingrediente.
                     * Retorna { max: int, ingredient: obj } o null si no hay receta.
                     */
                    function getIngredientUsageKey(ingredient) {
                        const ingProductId = parseInt(ingredient?.product_id, 10);
                        if (ingProductId > 0) {
                            return `product:${ingProductId}`;
                        }
                        return `name:${String(ingredient?.name || '').trim().toLowerCase()}`;
                    }

                    function buildIngredientConsumptionMap(qtyOverridesByProduct = {}) {
                        const productQtyInCartMap = new Map();
                        (currentTable?.items || []).forEach((item) => {
                            const productId = parseInt(item?.pId ?? item?.product_id, 10) || 0;
                            if (!productId) return;
                            const qty = getItemVirtualPendingQty(item);
                            if (qty <= 0) return;
                            productQtyInCartMap.set(productId, (productQtyInCartMap.get(productId) || 0) + qty);
                        });

                        Object.entries(qtyOverridesByProduct || {}).forEach(([k, v]) => {
                            const productId = parseInt(k, 10);
                            if (!productId) return;
                            const qty = Math.max(0, parseFloat(v) || 0);
                            productQtyInCartMap.set(productId, qty);
                        });

                        const ingredientConsumptionMap = new Map();
                        productQtyInCartMap.forEach((productQtyInCart, productId) => {
                            const recipe = recipeStockData[String(productId)];
                            if (!recipe || !Array.isArray(recipe.ingredients) || recipe.ingredients.length === 0)
                        return;
                            const yieldQty = parseFloat(recipe.yield_quantity) || 1;
                            if (yieldQty <= 0) return;

                            recipe.ingredients.forEach((ingredient) => {
                                const qtyPerPortion = (parseFloat(ingredient?.quantity) || 0) / yieldQty;
                                if (qtyPerPortion <= 0) return;
                                const consumedQty = productQtyInCart * qtyPerPortion;
                                const ingKey = getIngredientUsageKey(ingredient);
                                ingredientConsumptionMap.set(ingKey, (ingredientConsumptionMap.get(ingKey) ||
                                    0) + consumedQty);
                            });
                        });

                        return ingredientConsumptionMap;
                    }

                    function getRecipeAvailability(productId, qtyInCart = null) {
                        const recipe = recipeStockData[String(productId)];
                        if (!recipe || !Array.isArray(recipe.ingredients) || recipe.ingredients.length === 0) return null;
                        const yieldQty = parseFloat(recipe.yield_quantity) || 1;
                        const currentQty = Math.max(0, parseFloat(qtyInCart ?? getCurrentProductVirtualQtyInCart(productId)) ||
                            0);
                        const ingredientConsumptionMap = buildIngredientConsumptionMap({
                            [String(productId)]: currentQty,
                        });
                        let maxAdditional = Infinity;
                        let limitingIng = null;
                        const ingredients = [];

                        for (const ing of recipe.ingredients) {
                            const baseStock = parseFloat(ing.stock) || 0;
                            const qtyPerPortion = (parseFloat(ing.quantity) || 0) / yieldQty;
                            const consumed = qtyPerPortion > 0 ?
                                (ingredientConsumptionMap.get(getIngredientUsageKey(ing)) || 0) :
                                0;
                            const remaining = baseStock - consumed;
                            const additionalByIng = qtyPerPortion > 0 ? (remaining / qtyPerPortion) : Infinity;

                            if (additionalByIng < maxAdditional) {
                                maxAdditional = additionalByIng;
                                limitingIng = {
                                    ...ing,
                                    remaining,
                                    base_stock: baseStock,
                                    consumed,
                                    qty_per_portion: qtyPerPortion,
                                };
                            }

                            ingredients.push({
                                ...ing,
                                remaining,
                                base_stock: baseStock,
                                consumed,
                                qty_per_portion: qtyPerPortion,
                            });
                        }

                        if (!isFinite(maxAdditional)) return null;
                        return {
                            current_qty: currentQty,
                            max_additional: Math.max(0, Math.floor(maxAdditional)),
                            limiting_ingredient: limitingIng,
                            ingredients,
                        };
                    }
                

                    /**
                     * Compatibilidad: máximo total de unidades para un producto con receta.
                     * (stock base, sin descontar carrito)
                     */
                    function getRecipeMaxQty(productId) {
                        const availability = getRecipeAvailability(productId, 0);
                        if (!availability) return null;
                        return {
                            max: Math.max(0, Math.floor((availability.current_qty || 0) + (availability.max_additional || 0))),
                            ingredient: availability.limiting_ingredient || null,
                        };
                    }

                    /** Muestra advertencia de stock de receta y retorna false si hay que bloquear. */
                    function checkRecipeStock(productId, currentQtyInCart, qtyToAdd) {
                        const availability = getRecipeAvailability(productId, currentQtyInCart);
                        if (!availability) return true; // sin receta: sin restricción
                        if (qtyToAdd > availability.max_additional) {
                            const ing = availability.limiting_ingredient;
                            const remaining = ing ? Math.max(0, parseFloat(ing.remaining) || 0) : 0;
                            const detail = ing ?
                                `Ingrediente "${ing.name}" tiene ${remaining.toFixed(2)} disponible(s). Solo puedes agregar ${availability.max_additional} unidad(es) más.` :
                                `Stock de ingredientes insuficiente. Solo puedes agregar ${availability.max_additional} unidad(es) más.`;
                            showNotification('Stock insuficiente (receta)', detail, 'warning');
                            return false;
                        }
                        return true;
                    }
                    const categoryIdsInBranch = (serverCategories || []).map(c => Number(c.id));
                    // Solo productos que tienen productBranch en esta sucursal Y cuya categoría está en category_branch (está en serverCategories).
                    const serverProducts = (serverProductsRaw || []).filter(p =>
                        serverProductBranches.some(pb => Number(pb.product_id) === Number(p.id)) &&
                        categoryIdsInBranch.includes(Number(p.category_id))
                    );

                    function findProductBranchByProductId(productId) {
                        const targetId = Number(productId);
                        if (!targetId || !Array.isArray(serverProductBranches)) return null;
                        return serverProductBranches.find(pb => Number(pb.product_id) === targetId) || null;
                    }

                    function normalizeComplements(list) {
                        return (Array.isArray(list) ? list : [])
                            .map(item => String(item || '').trim())
                            .filter(Boolean);
                    }

                    function getComplementSignature(list) {
                        return normalizeComplements(list)
                            .slice()
                            .sort((a, b) => a.localeCompare(b))
                            .join('|');
                    }

                    function getItemGroupingKey(item) {
                        const productId = parseInt(item?.pId ?? item?.product_id, 10) || 0;
                        return [
                            productId,
                            getComplementSignature(item?.complements),
                            String(item?.note || '').trim()
                        ].join('::');
                    }

                    function formatComplementsLabel(list) {
                        const normalized = normalizeComplements(list);
                        return normalized.length ? normalized.join(', ') : '';
                    }

                    function getCurrentProductQtyInCart(productId, excludeIndex = null) {
                        return (currentTable.items || []).reduce((sum, item, index) => {
                            if (excludeIndex !== null && index === excludeIndex) {
                                return sum;
                            }
                            const itemId = parseInt(item?.pId ?? item?.product_id, 10) || 0;
                            if (itemId !== Number(productId)) {
                                return sum;
                            }
                            return sum + (parseFloat(item?.qty) || 0);
                        }, 0);
                    }

                    function getItemVirtualPendingQty(item) {
                        const qty = Math.max(0, parseFloat(item?.qty) || 0);
                        const hasSavedOrder = Number(currentTable?.order_movement_id || 0) > 0;
                        if (!hasSavedOrder) return qty;
                        const savedQty = parseFloat(item?.savedQty);
                        if (!Number.isFinite(savedQty)) return qty;
                        return Math.max(0, qty - Math.max(0, savedQty));
                    }

                    function getCurrentProductVirtualQtyInCart(productId, excludeIndex = null) {
                        return (currentTable.items || []).reduce((sum, item, index) => {
                            if (excludeIndex !== null && index === excludeIndex) {
                                return sum;
                            }
                            const itemId = parseInt(item?.pId ?? item?.product_id, 10) || 0;
                            if (itemId !== Number(productId)) {
                                return sum;
                            }
                            return sum + getItemVirtualPendingQty(item);
                        }, 0);
                    }

                    async function promptProductDetailSelection(prod) {
                        const detailOptions = normalizeComplements(prod?.detail_options);
                        if (!detailOptions.length) {
                            return {
                                qty: 1,
                                complements: []
                            };
                        }

                        if (!window.Swal) {
                            return {
                                qty: 1,
                                complements: []
                            };
                        }

                        const html = `
                                <div class="text-left overflow-x-hidden">
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">Cantidad</label>
                                    <input id="swal-product-detail-qty" type="number" min="1" value="1" class="!mt-0 !mb-4 block h-11 rounded-lg border border-slate-200 px-3 text-base text-slate-700 outline-none" style="width: 100%; max-width: 100%; box-sizing: border-box; margin-left: 0; margin-right: 0;" />
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">Detalles</label>
                                    <div class="max-h-56 overflow-y-auto overflow-x-hidden rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                        ${detailOptions.map((option, index) => `
                                                        <label class="flex items-center gap-2 py-1 text-sm text-slate-700">
                                                            <input type="checkbox" class="swal-product-detail-option" value="${index}" ${index === 0 ? 'autofocus' : ''}>
                                                            <span>${escapeHtml(option)}</span>
                                                        </label>
                                                    `).join('')}
                                    </div>
                                </div>
                            `;

                        const result = await Swal.fire({
                            title: prod?.name || 'Configurar producto',
                            html,
                            width: 420,
                            focusConfirm: false,
                            showCancelButton: true,
                            confirmButtonText: 'Agregar',
                            cancelButtonText: 'Cancelar',
                            preConfirm: () => {
                                const qtyInput = document.getElementById('swal-product-detail-qty');
                                const qty = Math.max(1, parseInt(qtyInput?.value || '1', 10) || 1);
                                const complements = Array.from(document.querySelectorAll(
                                        '.swal-product-detail-option:checked'))
                                    .map(input => detailOptions[parseInt(input.value || '-1', 10)] ?? '')
                                    .map(value => String(value || '').trim())
                                    .filter(Boolean);
                                return {
                                    qty,
                                    complements
                                };
                            }
                        });

                        return result.isConfirmed ? result.value : null;
                    }

                    /** Nombre de impresora QZ (printers_branch) asignado al producto en esta sucursal (primera ticketera). */
                    function resolveQzPrinterName(productId) {
                        const id = parseInt(productId, 10);
                        if (!id || !serverProductBranches?.length) return null;
                        const pb = serverProductBranches.find(p => Number(p.product_id) === id);
                        const n = pb && pb.qz_printer_name ? String(pb.qz_printer_name).trim() : '';
                        return n || null;
                    }

                    /** Nombres de impresora QZ asignadas por pivote product_branch_printer (puede ser varias). */
                    function resolveQzPrinterNames(productId) {
                        const id = parseInt(productId, 10);
                        if (!id || !serverProductBranches?.length) return [];
                        const pb = serverProductBranches.find(p => Number(p.product_id) === id);
                        const list = Array.isArray(pb?.qz_printer_names) ? pb.qz_printer_names : [];
                        const cleaned = list
                            .map(n => String(n || '').trim())
                            .filter(n => !!n);
                        if (cleaned.length) return cleaned;
                        const single = resolveQzPrinterName(id);
                        return single ? [single] : [];
                    }

                    /** Devuelve metadatos de impresoras QZ para un producto (name, width). */
                    function resolveQzPrinters(productId) {
                        const id = parseInt(productId, 10);
                        if (!id || !serverProductBranches?.length) return [];
                        const pb = serverProductBranches.find(p => Number(p.product_id) === id);
                        const list = Array.isArray(pb?.qz_printers) ? pb.qz_printers : [];
                        return list
                            .map((it) => ({
                                name: String(it?.name || '').trim(),
                                width: String(it?.width || '').trim(),
                                ip: String(it?.ip || '').trim(),
                            }))
                            .filter((it) => it.name);
                    }

                    /** Ancho de ticket por impresora (sale de printers_branch.width). */
                    function resolvePrinterWidthByName(printerName) {
                        const target = String(printerName || '').trim().toLowerCase();
                        if (!target) return 58;
                        if (target === 'barra' || target === 'barra2' || target.startsWith('barra')) {
                            return 80;
                        }
                        for (let i = 0; i < serverProductBranches.length; i++) {
                            const pb = serverProductBranches[i];
                            const plist = Array.isArray(pb?.qz_printers) ? pb.qz_printers : [];
                            for (let j = 0; j < plist.length; j++) {
                                const p = plist[j];
                                const n = String(p?.name || '').trim().toLowerCase();
                                if (n && n === target) {
                                    const w = parseInt(String(p?.width || '').replace(/[^\d]/g, ''), 10);
                                    if (!isNaN(w) && (w === 58 || w === 80)) return w;
                                }
                            }
                        }
                        return 58; // default seguro
                    }

                    /**
                     * Comanda: ticketera con IP en sucursal → RAW vía Laravel (sin QZ en este navegador).
                     */
                    function kitchenComandaPrinterUsesServerThermal(printerName) {
                        if (window.__qzKitchenSkipClientQzWhenPrinterHasIp === false) {
                            return false;
                        }
                        const rawName = String(printerName || '').trim();
                        const target = rawName.toLowerCase();
                        // Misma ticketera que el cobro en la 2.ª PC (BARRA2 / qz2): la comanda debe ir por QZ
                        // en este navegador. Si en BD hay IP de producto, el RAW desde el servidor no llega al USB/local de la PC2.
                        if (typeof window.__qzPrinterRequiresSecondaryCertFirst === 'function') {
                            if (window.__qzPrinterRequiresSecondaryCertFirst(rawName)) {
                                return false;
                            }
                        } else {
                            const compact = target.replace(/\s+/g, '');
                            if (compact === 'barra2' || compact.startsWith('barra2')) {
                                return false;
                            }
                        }
                        if (!target || !Array.isArray(serverProductBranches)) {
                            return false;
                        }
                        for (let i = 0; i < serverProductBranches.length; i++) {
                            const plist = serverProductBranches[i]?.qz_printers;
                            if (!Array.isArray(plist)) {
                                continue;
                            }
                            for (let j = 0; j < plist.length; j++) {
                                const p = plist[j];
                                if (String(p?.name || '').trim().toLowerCase() !== target) {
                                    continue;
                                }
                                const ip = String(p?.ip || '').trim();
                                if (ip !== '') {
                                    return true;
                                }
                            }
                        }
                        return false;
                    }

                    /** Escapa texto para HTML (comanda impresa como pixel/HTML en Epson u otras no-RAW). */
                    function escapeHtmlForQzPrint(text) {
                        return String(text)
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/\"/g, '&quot;');
                    }

                    /**
                     * RAW para térmicas; si falla (p. ej. Epson tinta), reintenta como HTML/pixel.
                     * Esto permite "imprimir en todos" aunque no todos acepten RAW.
                     */
                    async function printTicketWithQz(qzApi, printerName, plainText) {
                        const paperWidth = resolvePrinterWidthByName(printerName);
                        const paperMm = paperWidth === 80 ? 80 : 58;
                        const config = qzApi.configs.create(printerName, {
                            units: 'mm',
                            size: {
                                width: paperMm,
                                height: 200
                            },
                            margins: 0,
                        });
                        try {
                            // Reemplaza caracteres especiales del español a equivalentes ASCII
                            // para que la ticketera térmica los imprima correctamente (PC437/PC850).
                            function toEscPos(text) {
                                return String(text || '')
                                    .replace(/á/g, 'a').replace(/Á/g, 'A')
                                    .replace(/é/g, 'e').replace(/É/g, 'E')
                                    .replace(/í/g, 'i').replace(/Í/g, 'I')
                                    .replace(/ó/g, 'o').replace(/Ó/g, 'O')
                                    .replace(/ú/g, 'u').replace(/Ú/g, 'U')
                                    .replace(/ü/g, 'u').replace(/Ü/g, 'U')
                                    .replace(/ñ/g, 'n').replace(/Ñ/g, 'N')
                                    .replace(/¿/g, '?').replace(/¡/g, '!');
                            }
                            // ESC/POS: init + código de página PC850 (español) + contenido + feeds + corte
                            // Separa líneas para aplicar tamaños distintos:
                            //   - primera línea (COMANDA / ANULADO / PRECUENTA): doble alto + doble ancho (título)
                            //   - separadores (===...): tamaño normal
                            //   - cabecera (Mesa, Mozo, Fecha, Area, Salon, Hora): tamaño normal
                            //   - líneas de producto (Producto + cantidad al final): negrita + doble alto
                            //   - resto (Nota, Estado, Motivo): normal
                            const rawContent = toEscPos(plainText);
                            const rawLines = rawContent.split('\n');
                            let formattedContent = '';
                            for (let li = 0; li < rawLines.length; li++) {
                                const line = rawLines[li];
                                const trimmed = line.trim();
                                const isSep = /^=+$/.test(trimmed);
                                const isHeader = li === 1 ||
                                    /^(Mesa|Mozo|Fecha\/Hora|Fecha|Area|Salon|Hora|Producto|Cant|Total|Subtotal)/.test(
                                        trimmed) ||
                                    /^(Mesa |COMANDA|COCINA|PRECUENTA|ANULADO)/.test(trimmed);
                                const isMeta = /^(Nota|Estado|Motivo|DETALLE|S\/\.)/.test(trimmed) || trimmed === '';
                                if (li === 0) {
                                    // Título principal: solo negrita
                                    formattedContent += '\x1B\x45\x01' + line + '\x1B\x45\x00\n';
                                } else if (isSep || isHeader || isMeta) {
                                    // Separadores, cabecera y metadatos: tamaño normal
                                    formattedContent += '\x1B\x21\x00' + line + '\n';
                                } else {
                                    // Líneas de producto: negrita + doble alto
                                    formattedContent += '\x1B\x45\x01' + // ESC E 1 → negrita ON
                                        '\x1B\x21\x10' + // ESC ! 0x10 → doble alto
                                        line +
                                        '\x1B\x45\x00' + // ESC E 0 → negrita OFF
                                        '\x1B\x21\x00\n'; // tamaño normal
                                }
                            }
                            const ticketCommands =
                                '\x1B\x40' + // ESC @ (init/reset)
                                '\x1B\x74\x02' + // ESC t 2 → code page PC850 (Latin-1, incluye español)
                                formattedContent +
                                '\n\n' +
                                '\x1D\x56\x42\x10'; // GS V B 16 → avance + corte parcial

                            await qzApi.print(config, [{
                                type: 'raw',
                                format: 'command',
                                flavor: 'plain',
                                data: ticketCommands,
                            }]);
                            return;
                        } catch (rawErr) {
                            console.warn('QZ Tray: RAW no disponible en "' + printerName + '", usando HTML.', rawErr);
                        }

                        const htmlLines = String(plainText || '')
                            .split('\n')
                            .map((line) => {
                                const t = String(line || '');
                                const trimmed = t.trimStart();
                                if (trimmed.startsWith('Hora:') || trimmed.startsWith('Nota:')) {
                                    return '<span class="meta">' + escapeHtmlForQzPrint(t) + '</span>';
                                }
                                return escapeHtmlForQzPrint(t);
                            })
                            .join('\n');

                        const html = '<!DOCTYPE html><html><head><meta charset="utf-8" style="font-size:15pt;">' +
                            '<style>@page{size:' + paperMm + 'mm auto;margin:0;}html,body{width:' + paperMm +
                            'mm;margin:0;padding:0;}' +
                            'body{font-family:Segoe UI,Arial,sans-serif;}' +
                            'pre{white-space:pre-wrap;word-wrap:break-word;margin:0;padding:0;font-family:inherit;line-height:1.2;}' +
                            '.meta{font-size:8pt;color:#555;}</style></head><body><pre>' +
                            htmlLines + '</pre></body></html>';

                        await qzApi.print(config, [{
                            type: 'pixel',
                            format: 'html',
                            flavor: 'plain',
                            data: html,
                        }]);
                    }

                    function formatDateTimeForTicket(dt) {
                        const d = dt instanceof Date ? dt : new Date();
                        const dd = String(d.getDate()).padStart(2, '0');
                        const mm = String(d.getMonth() + 1).padStart(2, '0');
                        const yy = d.getFullYear();
                        const hh = String(d.getHours()).padStart(2, '0');
                        const mi = String(d.getMinutes()).padStart(2, '0');
                        return `${dd}/${mm}/${yy} ${hh}:${mi}`;
                    }

                    function normalizeCancellationsForTicket(rawCancellations) {
                        const list = Array.isArray(rawCancellations) ? rawCancellations : [];
                        const map = new Map();
                        list.forEach((c) => {
                            const name = String(c?.name ?? c?.description ?? 'Producto').trim() || 'Producto';
                            const reason = String(c?.cancel_reason ?? '').trim();
                            const productId = parseInt(c?.product_id ?? c?.pId ?? 0, 10) || 0;
                            const qty = parseFloat(c?.qtyCanceled ?? c?.quantity ?? 1) || 1;
                            const key = `${productId}|${name}|${reason}`;
                            if (!map.has(key)) {
                                map.set(key, {
                                    ...c,
                                    name,
                                    cancel_reason: reason,
                                    qtyCanceled: qty
                                });
                                return;
                            }
                            const current = map.get(key);
                            current.qtyCanceled = (parseFloat(current.qtyCanceled ?? 0) || 0) + qty;
                            map.set(key, current);
                        });
                        return Array.from(map.values());
                    }

                    function buildPreAccountTicketText(table, groupedItems, canceledItems, paperWidth = 80) {
                        const lineWidth = paperWidth === 80 ? 48 : 24;
                        const colQty = paperWidth === 80 ? 3 : 2;
                        const colPrice = paperWidth === 80 ? 6 : 5;
                        const compactGap = ' ';
                        const sep = '='.repeat(lineWidth) + '\n';
                        const normalizedCanceledItems = normalizeCancellationsForTicket(canceledItems);
                        const hasCanceled = normalizedCanceledItems.length > 0;

                        function padEndSafe(str, length) {
                            const s = String(str ?? '').trim();
                            if (s.length >= length) return s.slice(0, length);
                            return s + ' '.repeat(length - s.length);
                        }

                        function padCenterSafe(str, length) {
                            const s = String(str ?? '').trim();
                            if (s.length >= length) return s.slice(0, length);
                            return ' '.repeat(Math.floor((length - s.length) / 2)) + s + ' '.repeat(length - s.length - Math
                                .floor((length - s.length) / 2));
                        }

                        function padStartSafe(str, length) {
                            const s = String(str ?? '').trim();
                            if (s.length >= length) return s.slice(-length);
                            return ' '.repeat(length - s.length) + s;
                        }

                        function formatQty(qty) {
                            return Number.isInteger(qty) ? String(qty) : qty.toFixed(2);
                        }

                        const area = String(table?.original_area_name || 'Sin area').trim();
                        const branchName = String(branchDisplayName || table?.original_location_name || table?.location_name ||
                            'SUCURSAL').trim();
                        const mesaLabel = String(table?.name ?? table?.table_id ?? '-');
                        const mozo = String(table?.waiter || 'Sin asignar').trim();
                        const fechaHora = formatDateTimeForTicket(new Date());
                        const cuenta = String(table?.order_movement_number || table?.order_movement_id || table?.movement_id ||
                            '').trim();
                        const buildFieldLine = (label) => {
                            const prefix = `${label}: `;
                            return prefix + '_'.repeat(Math.max(6, lineWidth - prefix.length));
                        };

                        let txt = '';
                        if (hasCanceled) txt += 'ANULADO\n';
                        txt += padCenterSafe(branchName.toUpperCase(), lineWidth) + '\n';
                        const branchAddr = String(branchAddressForTicket || '').trim();
                        if (branchAddr) {
                            const words = branchAddr.split(/\s+/);
                            let line = '';
                            words.forEach((w) => {
                                const test = (line ? line + ' ' : '') + w;
                                if (test.length > lineWidth) {
                                    if (line) txt += padCenterSafe(line, lineWidth) + '\n';
                                    line = w;
                                } else {
                                    line = test;
                                }
                            });
                            if (line) txt += padCenterSafe(line, lineWidth) + '\n';
                        }
                        txt += '\n';
                        txt += sep;
                        txt += 'Cant' + compactGap + 'Descr' + compactGap + 'P.U.' + compactGap + 'Subt' + '\n';
                        txt += sep;

                        (groupedItems || []).forEach((it, index) => {
                            const name = String(it?.name || 'Producto').trim();
                            const qty = parseFloat(it?.qty ?? 1) || 1;
                            const price = parseFloat(it?.price ?? 0) || 0;
                            const amount = qty * price;
                            const courtesyQty = Math.max(0, Math.min(qty, parseFloat(it?.courtesyQty ?? 0) || 0));
                            const takeawayQty = Math.max(0, Math.min(qty, parseFloat(it?.takeawayQty ?? 0) || 0));
                            const compactLine =
                                `${formatQty(qty)}${compactGap}${name}${compactGap}${price.toFixed(2)}${compactGap}${amount.toFixed(2)}`;
                            const maxNameLength = Math.max(6, lineWidth - String(formatQty(qty)).length - price.toFixed(
                                2).length - amount.toFixed(2).length - 3);
                            txt += compactLine.length <= lineWidth ?
                                compactLine + '\n' :
                                `${formatQty(qty)}${compactGap}${name.slice(0, maxNameLength)}${compactGap}${price.toFixed(2)}${compactGap}${amount.toFixed(2)}\n`;
                            if (courtesyQty > 0 || takeawayQty > 0) {
                                const tags = [];
                                if (courtesyQty > 0) tags.push('Cortesia: ' + courtesyQty);
                                if (takeawayQty > 0) tags.push('Llevar: ' + takeawayQty);
                                txt += tags.join(' | ') + '\n';
                            }
                            if (index < (groupedItems.length - 1)) {
                                txt += sep;
                            }
                        });

                        if (hasCanceled) {
                            txt += 'DETALLE ANULADO\n';
                            txt += sep;
                            normalizedCanceledItems.forEach((c) => {
                                const cName = String(c?.name || c?.description || 'Producto').trim();
                                const cQty = parseFloat(c?.qtyCanceled ?? c?.quantity ?? 1) || 1;
                                txt += padEndSafe(formatQty(cQty), colQty) + padEndSafe(cName, lineWidth - colQty) +
                                    '\n';
                                if (c?.cancel_reason && String(c.cancel_reason).trim()) {
                                    txt += 'Motivo: ' + String(c.cancel_reason).trim() + '\n';
                                }
                                txt += sep;
                            });
                        }

                        const totals = getTotalsWithDelivery(groupedItems || []);
                        txt += sep;
                        txt += 'TOTAL' + compactGap + (totals.total || 0).toFixed(2) + '\n';
                        txt += '\n';
                        txt += `MESA ${mesaLabel}` + (area ? ' - ' + area.toUpperCase() : '') + '\n';
                        txt += `Mesero: ${mozo}\n`;
                        txt += sep;
                        txt += 'Boleta[] Factura[] Consumo[] Detal.[]\n';
                        txt += buildFieldLine('Razon Soc.') + '\n';
                        txt += buildFieldLine('Direcci.') + '\n';
                        txt += buildFieldLine('DNI/RUC.') + '\n';
                        txt += '\n';
                        txt += `Fecha: ${fechaHora}\n`;
                        txt += '<<No valido como documento contable>>\n';
                        return txt;
                    }

                    function buildPaymentTicketText(table, groupedItems, paymentMethods, totals, paperWidth = 80) {
                        const lineWidth = paperWidth === 80 ? 48 : 24;
                        const colQty = 4;
                        const colPrice = 10;
                        const colName = lineWidth - colQty - colPrice;
                        const sep = '='.repeat(lineWidth) + '\n';

                        function padEndSafe(str, length) {
                            const s = String(str ?? '').trim();
                            if (s.length >= length) return s.slice(0, length);
                            return s + ' '.repeat(length - s.length);
                        }

                        function padCenterSafe(str, length) {
                            const s = String(str ?? '').trim();
                            if (s.length >= length) return s.slice(0, length);
                            return ' '.repeat(Math.floor((length - s.length) / 2)) + s + ' '.repeat(length - s.length - Math
                                .floor((length - s.length) / 2));
                        }

                        function padStartSafe(str, length) {
                            const s = String(str ?? '').trim();
                            if (s.length >= length) return s.slice(-length);
                            return ' '.repeat(length - s.length) + s;
                        }

                        const area = String(table?.original_area_name || 'Sin area').trim();
                        const salon = String(table?.original_location_name || table?.location_name || 'Salon').trim();
                        const mesaLabel = String(table?.name ?? table?.table_id ?? '-');
                        const mozo = String(table?.waiter || 'Sin asignar').trim();
                        const fechaHora = formatDateTimeForTicket(new Date());

                        let txt = '';
                        txt += padCenterSafe('COMPROBANTE', lineWidth) + '\n';
                        txt += `Salon: ${salon}\n`;
                        txt += `Area: ${area} (Mesa: ${mesaLabel})\n`;
                        txt += `Mozo: ${mozo}\n`;
                        txt += `Fecha/Hora: ${fechaHora}\n`;
                        txt += sep;
                        txt += padEndSafe('Producto', colName) + padCenterSafe('Cant', colQty) + padStartSafe('P. unitario',
                            colPrice) + '\n';
                        txt += sep;

                        (groupedItems || []).forEach((it) => {
                            const name = String(it?.name || 'Producto').trim();
                            const qty = parseFloat(it?.qty ?? 1) || 1;
                            const price = parseFloat(it?.price ?? 0) || 0;
                            txt += padEndSafe(name, colName) + padCenterSafe(String(qty), colQty) + padStartSafe('S/.' +
                                price.toFixed(2), colPrice) + '\n';
                        });

                        txt += sep;
                        txt += padEndSafe('Subtotal', lineWidth - 10) + padStartSafe('S/. ' + (totals.subtotal || 0).toFixed(2),
                            10) + '\n';
                        txt += padEndSafe('Impuestos', lineWidth - 10) + padStartSafe('S/. ' + (totals.tax || 0).toFixed(2),
                            10) + '\n';
                        if ((totals.deliveryFee || 0) > 0) {
                            txt += padEndSafe('Delivery', lineWidth - 10) + padStartSafe('S/. ' + totals.deliveryFee.toFixed(2),
                                10) + '\n';
                        }
                        if ((totals.takeawayDisposableFee || 0) > 0) {
                            txt += padEndSafe('Descartables', lineWidth - 10) + padStartSafe('S/. ' + totals
                                .takeawayDisposableFee.toFixed(2), 10) + '\n';
                        }
                        txt += padEndSafe('TOTAL', lineWidth - 10) + padStartSafe('S/. ' + (totals.total || 0).toFixed(2), 10) +
                            '\n';
                        txt += sep;

                        if (Array.isArray(paymentMethods) && paymentMethods.length > 0) {
                            txt += 'PAGOS\n';
                            paymentMethods.forEach((pm) => {
                                const methodName = String(pm?.payment_method_name || pm?.payment_method || 'Método')
                                    .trim();
                                const amount = parseFloat(pm?.amount || 0) || 0;
                                txt += padEndSafe(methodName, lineWidth - colPrice) + padStartSafe('S/. ' + amount
                                    .toFixed(2), colPrice) + '\n';
                            });
                            const paid = paymentMethods.reduce((sum, pm) => sum + (parseFloat(pm?.amount || 0) || 0), 0);
                            const change = paid - (totals.total || 0);
                            if (change > 0) {
                                txt += sep;
                                txt += padEndSafe('Cambio', lineWidth - colPrice) + padStartSafe('S/. ' + change.toFixed(2),
                                    colPrice) + '\n';
                            }
                        }

                        txt += '\n';
                        return txt;
                    }

                    function buildPaymentTicketTextApproved(table, groupedItems, paymentMethods, totals, paperWidth = 80) {
                        const lineWidth = paperWidth === 80 ? 48 : 32;
                        const colQty = paperWidth === 80 ? 5 : 4;
                        const colPrice = paperWidth === 80 ? 9 : 7;
                        const colAmount = paperWidth === 80 ? 9 : 7;
                        const colGap = 2;
                        const colName = Math.max(8, lineWidth - colQty - colGap - colPrice - colAmount);
                        const sep = '='.repeat(lineWidth) + '\n';

                        function padEndSafe(str, length) {
                            const s = String(str ?? '').trim();
                            if (s.length >= length) return s.slice(0, length);
                            return s + ' '.repeat(length - s.length);
                        }

                        function padCenterSafe(str, length) {
                            const s = String(str ?? '').trim();
                            if (s.length >= length) return s.slice(0, length);
                            return ' '.repeat(Math.floor((length - s.length) / 2)) + s + ' '.repeat(length - s.length - Math
                                .floor((length - s.length) / 2));
                        }

                        function padStartSafe(str, length) {
                            const s = String(str ?? '').trim();
                            if (s.length >= length) return s.slice(-length);
                            return ' '.repeat(length - s.length) + s;
                        }

                        function wrapText(str, length) {
                            const source = String(str ?? '').trim();
                            if (!source) return ['-'];
                            const words = source.split(/\s+/);
                            const lines = [];
                            let current = '';
                            words.forEach((word) => {
                                const candidate = current ? `${current} ${word}` : word;
                                if (candidate.length <= length) {
                                    current = candidate;
                                    return;
                                }
                                if (current) lines.push(current);
                                current = word.length > length ? word.slice(0, length) : word;
                            });
                            if (current) lines.push(current);
                            return lines.length ? lines : ['-'];
                        }

                        function formatQty(qty) {
                            return Number.isInteger(qty) ? String(qty) : qty.toFixed(2);
                        }

                        const area = String(table?.original_area_name || 'Sin area').trim();
                        const branchName = String(branchDisplayName || table?.original_location_name || table?.location_name ||
                            'SUCURSAL').trim();
                        const mesaLabel = String(table?.name ?? table?.table_id ?? '-');
                        const mozo = String(table?.waiter || 'Sin asignar').trim();
                        const fechaHora = formatDateTimeForTicket(new Date());

                        let txt = '';
                        txt += padCenterSafe(branchName.toUpperCase(), lineWidth) + '\n';
                        txt += padCenterSafe('COMPROBANTE', lineWidth) + '\n';
                        txt += '\n';
                        txt += `Mesa: ${mesaLabel}` + (area ? ' - ' + area.toUpperCase() : '') + '\n';
                        txt += `Mesero: ${mozo}\n`;
                        txt += `Fecha: ${fechaHora}\n`;
                        txt += sep;
                        txt += padEndSafe('Cant.', colQty) +
                            ' '.repeat(colGap) +
                            padEndSafe('Descr.', colName) +
                            padStartSafe('P.Unit.', colPrice) +
                            padStartSafe('Subt.', colAmount) + '\n';
                        txt += sep;

                        (groupedItems || []).forEach((it, index) => {
                            const name = String(it?.name || 'Producto').trim();
                            const qty = parseFloat(it?.qty ?? 1) || 1;
                            const price = parseFloat(it?.price ?? 0) || 0;
                            const amount = qty * price;
                            const nameLines = wrapText(name, colName);

                            txt += padEndSafe(formatQty(qty), colQty) +
                                ' '.repeat(colGap) +
                                padEndSafe(nameLines[0], colName) +
                                padStartSafe(price.toFixed(2), colPrice) +
                                padStartSafe(amount.toFixed(2), colAmount) + '\n';

                            for (let i = 1; i < nameLines.length; i += 1) {
                                txt += padEndSafe('', colQty) +
                                    ' '.repeat(colGap) +
                                    padEndSafe(nameLines[i], colName) +
                                    padStartSafe('', colPrice) +
                                    padStartSafe('', colAmount) + '\n';
                            }

                            if (index < groupedItems.length - 1) {
                                txt += sep;
                            }
                        });

                        txt += sep;
                        txt += padEndSafe('Subtotal', lineWidth - 12) + padStartSafe((totals.subtotal || 0).toFixed(2), 12) +
                            '\n';
                        txt += padEndSafe('Impuestos', lineWidth - 12) + padStartSafe((totals.tax || 0).toFixed(2), 12) + '\n';
                        if ((totals.deliveryFee || 0) > 0) {
                            txt += padEndSafe('Delivery', lineWidth - 12) + padStartSafe(totals.deliveryFee.toFixed(2), 12) +
                                '\n';
                        }
                        if ((totals.takeawayDisposableFee || 0) > 0) {
                            txt += padEndSafe('Descartables', lineWidth - 12) + padStartSafe(totals.takeawayDisposableFee
                                .toFixed(2), 12) + '\n';
                        }
                        txt += padEndSafe('TOTAL', lineWidth - 12) + padStartSafe((totals.total || 0).toFixed(2), 12) + '\n';

                        if (Array.isArray(paymentMethods) && paymentMethods.length > 0) {
                            txt += '\n';
                            txt += 'Pagos\n';
                            paymentMethods.forEach((pm) => {
                                const methodName = String(pm?.payment_method_name || pm?.payment_method || 'Metodo')
                                    .trim();
                                const amount = parseFloat(pm?.amount || 0) || 0;
                                txt += padEndSafe(methodName, lineWidth - 12) + padStartSafe(amount.toFixed(2), 12) +
                                    '\n';
                            });
                            const paid = paymentMethods.reduce((sum, pm) => sum + (parseFloat(pm?.amount || 0) || 0), 0);
                            const change = paid - (totals.total || 0);
                            if (change > 0) {
                                txt += padEndSafe('Cambio', lineWidth - 12) + padStartSafe(change.toFixed(2), 12) + '\n';
                            }
                        }

                        txt += '\n';
                        return txt;
                    }

                    function resolvePreAccountPrinterName() {
                        try {
                            const localPrinter = String(localStorage.getItem('xinergia_local_printer_name') ||
                                localStorage.getItem('xinergia_print_bridge_printer') || '').trim();
                            if (localPrinter) return localPrinter;
                        } catch (e) {}
                        const host = String(window.location.hostname || '').trim().toLowerCase();
                        const isLocalhost = ['localhost', '127.0.0.1', '::1'].includes(host);
                        return isLocalhost ? 'BARRA' : 'BARRA2';
                    }

                    function requiresStrictLocalQz(printerName) {
                        if (typeof window.__qzPrinterRequiresTertiaryCertFirst === 'function' &&
                            window.__qzPrinterRequiresTertiaryCertFirst(printerName)) {
                            return true;
                        }
                        if (typeof window.__qzPrinterRequiresSecondaryCertFirst === 'function' &&
                            window.__qzPrinterRequiresSecondaryCertFirst(printerName)) {
                            return true;
                        }
                        const target = String(printerName || '').trim().toLowerCase();
                        return target === 'barra2' || target.startsWith('barra2') || target === 'barra3' || target.startsWith('barra3');
                    }

                    function openPreAccountPdfTab() {
                        const groupedItems = getItemsGroupedByProduct();
                        const canceledItems = Array.isArray(currentTable?.cancellations) ? currentTable.cancellations : [];
                        if (!groupedItems.length && !canceledItems.length) {
                            if (typeof showNotification === 'function') {
                                showNotification('Precuenta', 'No hay productos para generar la precuenta.', 'warning');
                            }
                            return;
                        }

                        // Detectar el ancho real de la impresora configurada (80mm o 58mm)
                        const printerName = resolvePreAccountPrinterName(groupedItems);
                        const paperWidth = resolvePrinterWidthByName(printerName);
                        const ticketText = buildPreAccountTicketText(currentTable, groupedItems, canceledItems, paperWidth);
                        openPdfLinkInNewTab(orderPreAccountPdfLinkUrl, {
                            title: 'Precuenta',
                            ticket_text: ticketText,
                            paper_width: paperWidth,
                        }).catch((error) => {
                            console.error('Precuenta PDF:', error);
                            if (typeof showNotification === 'function') {
                                showNotification('Impresión', 'No se pudo generar el PDF de precuenta.', 'error');
                            }
                        });
                    }

                    function isQzTrayAvailable() {
                        try {
                            return typeof window.qz !== 'undefined' && window.qz !== null;
                        } catch (e) {
                            return false;
                        }
                    }

                    function markQzTrayUnavailable() {
                        try {
                            window.sessionStorage?.setItem('qzTrayUnavailable', '1');
                        } catch (e) {
                            // ignore
                        }
                    }

                    async function ensureQzTrayConnected(qzApi, printerNameForCert) {
                        if (!qzApi || !isQzTrayAvailable()) {
                            return false;
                        }
                        if (typeof window.__qzConnectWithCertPairFallback !== 'function') {
                            for (let w = 0; w < 40; w++) {
                                await new Promise((r) => setTimeout(r, 50));
                                if (typeof window.__qzConnectWithCertPairFallback === 'function') {
                                    break;
                                }
                            }
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

                    /**
                     * Impresión de precuenta vía QZ (USB/local). Usado como principal o fallback si el servidor falla (p. ej. PowerShell USB).
                     */
                    async function tryPrintPrecuentaWithQz(qzApi, printerName, ticketText) {
                        if (!qzApi || !isQzTrayAvailable()) {
                            return false;
                        }
                        try {
                            if (!await ensureQzTrayConnected(qzApi, printerName)) {
                                return false;
                            }
                            let currentPrinterName = printerName ? String(printerName).trim() : '';
                            if (!currentPrinterName) {
                                currentPrinterName = await qzApi.printers.getDefault();
                            }
                            if (!currentPrinterName) {
                                return false;
                            }
                            await printTicketWithQz(qzApi, currentPrinterName, ticketText);
                            return true;
                        } catch (e) {
                            console.warn('Precuenta: impresión QZ Tray', e);
                            return false;
                        }
                    }

                    async function printPreAccountTicket() {
                        const qzApi = window.qz;

                        const groupedItems = getItemsGroupedByProduct();
                        const canceledItems = Array.isArray(currentTable?.cancellations) ? currentTable.cancellations : [];
                        if (!groupedItems.length && !canceledItems.length) {
                            if (typeof showNotification === 'function') {
                                showNotification('Precuenta', 'No hay productos para imprimir.', 'warning');
                            }
                            return;
                        }

                        const resolvePreAccountPrinterNameLocal = () => resolvePreAccountPrinterName(groupedItems);
                        const printerName = resolvePreAccountPrinterNameLocal();
                        const paperWidth = resolvePrinterWidthByName(printerName);
                        const ticketText = buildPreAccountTicketText(currentTable, groupedItems, canceledItems, paperWidth);
                        const strictLocalQz = requiresStrictLocalQz(printerName);

                        let qzFailed = false;
                        // Si QZ está disponible y funciona, intentar imprimir por QZ
                        if (qzApi && await ensureQzTrayConnected(qzApi, printerName)) {
                            let currentPrinterName = printerName;
                            try {
                                // Si no hay impresora asignada en productos, usar la impresora por defecto de QZ
                                if (!currentPrinterName) {
                                    currentPrinterName = await qzApi.printers.getDefault();
                                }
                                if (!currentPrinterName) {
                                    throw new Error('No se encontró ninguna impresora disponible en QZ Tray.');
                                }
                                await printTicketWithQz(qzApi, currentPrinterName, ticketText);
                                if (typeof showNotification === 'function') {
                                    showNotification('Precuenta', 'Ticket enviado a "' + currentPrinterName + '".',
                                        'success');
                                }
                                return;
                            } catch (e) {
                                qzFailed = true;
                                if (strictLocalQz) {
                                    openPreAccountPdfTab();
                                    return;
                                }
                                if (typeof showNotification === 'function') {
                                    showNotification('Impresión', 'QZ no disponible. Intentando impresora de red...',
                                        'warning');
                                }
                            }
                        }

                        if (strictLocalQz) {
                            openPreAccountPdfTab();
                            return;
                        }

                        // Fallback: ticketera por red (server-side ESC/POS usando el endpoint de pedidos)
                        const movementId = currentTable?.movement_id;
                        if (!movementId) {
                            openPreAccountPdfTab();
                            return;
                        }
                        try {
                            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                            const tr = await fetch(orderPreAccountPrintUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                    'Accept': 'application/json'
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify({
                                    ticket_text: ticketText,
                                    printer_name: printerName || ''
                                }),
                            });
                            const td = tr.headers.get('content-type')?.includes('application/json') ? await tr.json() :
                            null;
                            if (tr.ok && td?.success) {
                                if (typeof showNotification === 'function') showNotification('Precuenta', td.message ||
                                    'Ticket enviado.', 'success');
                            } else {
                                const qzOk = await tryPrintPrecuentaWithQz(qzApi, printerName, ticketText);
                                if (qzOk) {
                                    if (typeof showNotification === 'function') {
                                        showNotification('Precuenta',
                                            'Impreso con QZ Tray (el servidor no pudo usar la impresora USB).',
                                            'success');
                                    }
                                } else {
                                    openPreAccountPdfTab();
                                }
                            }
                        } catch (e) {
                            const qzOk = await tryPrintPrecuentaWithQz(qzApi, printerName, ticketText);
                            if (qzOk) {
                                if (typeof showNotification === 'function') {
                                    showNotification('Precuenta', 'Impreso con QZ Tray (error al contactar el servidor).',
                                        'success');
                                }
                            } else {
                                openPreAccountPdfTab();
                            }
                        }
                    }

                    /**
                     * Solo en PC terminal (default BARRA2 / cert qz2): comanda vía QZ en el navegador.
                     * En PC principal (default BARRA): sin QZ en comanda (evita Allow) — solo servidor / ticketera con IP.
                     * En celulares (sin QZ Tray): false → comanda por servidor (misma PC que Laravel o ticketera con IP en LAN).
                     */
                    function kitchenComandaAllowClientQz() {
                        if (window.__qzKitchenComandaDisableClientOnTouch) {
                            try {
                                const ua = navigator.userAgent || '';
                                const coarseMobile = /Android|webOS|iPhone|iPod|BlackBerry|IEMobile|Opera Mini/i.test(ua);
                                const isPad = /iPad/i.test(ua) || (navigator.platform === 'MacIntel' && (navigator
                                    .maxTouchPoints || 0) > 1);
                                if (coarseMobile && !isPad) {
                                    return false;
                                }
                            } catch (e) {
                                /* ignore */
                            }
                        }
                        const d = String(window.__qzConfig?.defaultPrinterName || window.__qzConfig?.printerName || '').trim();
                        if (typeof window.__qzPrinterRequiresSecondaryCertFirst === 'function') {
                            return window.__qzPrinterRequiresSecondaryCertFirst(d);
                        }
                        const t = d.toLowerCase().replace(/\s+/g, '');
                        return t === 'barra2' || t.startsWith('barra2');
                    }

                    async function printKitchenTickets(items, table) {
                        const activeItems = Array.isArray(items) ? items : [];
                        const clientCancelled = Array.isArray(table?.cancellations) ? table.cancellations : [];
                        const mergedCancellations = [
                            ...clientCancelled,
                        ];
                        if (!activeItems.length && !mergedCancellations.length) return true;
                        const qzApi = window.qz;

                        function dedupeKitchenPrinterNameList(list) {
                            const out = [];
                            const seen = new Set();
                            (Array.isArray(list) ? list : []).forEach((raw) => {
                                const n = String(raw || '').trim();
                                const k = n.toLowerCase();
                                if (!k || seen.has(k)) return;
                                seen.add(k);
                                out.push(n);
                            });
                            return out;
                        }

                        function resolveAreaAllowedPrinterNames(areaId) {
                            const aid = parseInt(areaId, 10) || 0;
                            if (!aid) return [];
                            const raw = areaPrinterNames ? areaPrinterNames[String(aid)] ?? areaPrinterNames[aid] : null;
                            return dedupeKitchenPrinterNameList(raw);
                        }

                        function filterByAreaPrinters(list, areaAllowedNames) {
                            if (!Array.isArray(areaAllowedNames) || areaAllowedNames.length === 0) {
                                return list;
                            }
                            const allowed = new Set(areaAllowedNames.map((n) => String(n || '').trim().toLowerCase()));
                            return list.filter((n) => allowed.has(String(n || '').trim().toLowerCase()));
                        }
                        const areaAllowedPrinterNames = resolveAreaAllowedPrinterNames(table?.area_id);

                        function mergeKitchenBucketsSharedCanon(activeSrc, cancelSrc) {
                            const canon = Object.create(null);
                            const touch = (raw) => {
                                const t = String(raw || '').trim();
                                const low = t.toLowerCase();
                                if (!low) return;
                                if (!canon[low]) {
                                    canon[low] = t;
                                }
                            };
                            Object.keys(activeSrc || {}).forEach((k) => touch(k));
                            Object.keys(cancelSrc || {}).forEach((k) => touch(k));
                            const disp = (raw) => {
                                const low = String(raw || '').trim().toLowerCase();
                                return low ? canon[low] : '';
                            };
                            const byPrinterOut = {};
                            Object.keys(activeSrc || {}).forEach((k) => {
                                const d = disp(k);
                                if (!d) return;
                                if (!byPrinterOut[d]) byPrinterOut[d] = [];
                                (activeSrc[k] || []).forEach((row) => byPrinterOut[d].push(row));
                            });
                            const canceledByPrinterOut = {};
                            Object.keys(cancelSrc || {}).forEach((k) => {
                                const d = disp(k);
                                if (!d) return;
                                if (!canceledByPrinterOut[d]) canceledByPrinterOut[d] = [];
                                (cancelSrc[k] || []).forEach((row) => canceledByPrinterOut[d].push(row));
                            });
                            return {
                                byPrinter: byPrinterOut,
                                canceledByPrinter: canceledByPrinterOut
                            };
                        }

                        const byPrinterAcc = {};
                        activeItems.forEach((it) => {
                            const pId = parseInt(it.pId, 10) || 0;
                            if (!pId) return;
                            const pdefs = resolveQzPrinters(pId);
                            const pnamesRaw = pdefs.length ? pdefs.map(p => p.name) : resolveQzPrinterNames(pId);
                            const pnames = filterByAreaPrinters(dedupeKitchenPrinterNameList(pnamesRaw),
                                areaAllowedPrinterNames);
                            if (!pnames.length) return;
                            // Si un producto está asignado a varias impresoras (pivote), se imprime en todas.
                            pnames.forEach((pname) => {
                                if (!byPrinterAcc[pname]) byPrinterAcc[pname] = [];
                                byPrinterAcc[pname].push(it);
                            });
                        });
                        const canceledByPrinterAcc = {};
                        mergedCancellations.forEach((c) => {
                            const pId = parseInt(c?.pId ?? c?.product_id, 10) || 0;
                            const qty = parseFloat(c?.qtyCanceled ?? c?.quantity ?? 0) || 0;
                            if (!pId || qty <= 0) return;
                            const pdefs = resolveQzPrinters(pId);
                            const pnamesRaw = pdefs.length ? pdefs.map(p => p.name) : resolveQzPrinterNames(pId);
                            const pnames = filterByAreaPrinters(dedupeKitchenPrinterNameList(pnamesRaw),
                                areaAllowedPrinterNames);
                            if (!pnames.length) return;
                            pnames.forEach((pname) => {
                                if (!canceledByPrinterAcc[pname]) canceledByPrinterAcc[pname] = [];
                                canceledByPrinterAcc[pname].push({
                                    pId,
                                    name: String(c?.name ?? c?.description ?? 'Producto').trim(),
                                    qty,
                                    complements: normalizeComplements(c?.complements),
                                    reason: String(c?.cancel_reason ?? c?.comment ?? '').trim(),
                                });
                            });
                        });
                        const mergedBuckets = mergeKitchenBucketsSharedCanon(byPrinterAcc, canceledByPrinterAcc);

                        function mergePrinterBucketsByNameCase(mapByPrinter) {
                            const out = Object.create(null);
                            Object.keys(mapByPrinter || {}).forEach((k) => {
                                const t = String(k || '').trim();
                                if (!t) {
                                    return;
                                }
                                const list = mapByPrinter[k] || [];
                                const low = t.toLowerCase();
                                let canon = null;
                                for (const ek of Object.keys(out)) {
                                    if (String(ek).trim().toLowerCase() === low) {
                                        canon = ek;
                                        break;
                                    }
                                }
                                if (canon) {
                                    out[canon].push.apply(out[canon], list);
                                } else {
                                    out[t] = list.slice();
                                }
                            });
                            return out;
                        }
                        const byPrinter = mergePrinterBucketsByNameCase(mergedBuckets.byPrinter);
                        const canceledByPrinter = mergePrinterBucketsByNameCase(mergedBuckets.canceledByPrinter);
                        let names = (function() {
                            const m = new Map();
                            [...Object.keys(byPrinter), ...Object.keys(canceledByPrinter)].forEach((k) => {
                                const t = String(k || '').trim();
                                if (!t) {
                                    return;
                                }
                                const key = t.toLowerCase();
                                if (!m.has(key)) {
                                    m.set(key, t);
                                }
                            });
                            return Array.from(m.values());
                        })();
                        (function collapseBarraPairBuckets() {
                            const dedupeRows = (rows) => {
                                const out = [];
                                const seen = new Set();
                                (Array.isArray(rows) ? rows : []).forEach((row) => {
                                    const key = JSON.stringify({
                                        pId: parseInt(row?.pId ?? row?.product_id ?? 0, 10) || 0,
                                        name: String(row?.name || '').trim().toLowerCase(),
                                        qty: Number(parseFloat(row?.qty ?? 0) || 0),
                                        commandTime: String(row?.commandTime || '').trim(),
                                        note: String(row?.note || '').trim(),
                                        complements: normalizeComplements(row?.complements),
                                        status: String(row?.status || '').trim().toUpperCase(),
                                        reason: String(row?.reason || '').trim(),
                                    });
                                    if (seen.has(key)) {
                                        return;
                                    }
                                    seen.add(key);
                                    out.push(row);
                                });
                                return out;
                            };
                            const normalize = (raw) => String(raw || '').trim().toLowerCase().replace(/\s+/g, '');
                            const barraKey = names.find((n) => normalize(n) === 'barra');
                            const barra2Key = names.find((n) => {
                                const t = normalize(n);
                                return t === 'barra2' || t.startsWith('barra2');
                            });
                            if (!barraKey || !barra2Key) {
                                return;
                            }
                            const defaultPrinter = String(window.__qzConfig?.defaultPrinterName || window.__qzConfig
                                ?.printerName || '').trim();
                            const preferBarra2 = (typeof window.__qzPrinterRequiresSecondaryCertFirst === 'function') ?
                                window.__qzPrinterRequiresSecondaryCertFirst(defaultPrinter || barra2Key) :
                                normalize(defaultPrinter) === 'barra2';
                            const keep = preferBarra2 ? barra2Key : barraKey;
                            const drop = preferBarra2 ? barraKey : barra2Key;
                            if (drop !== keep) {
                                byPrinter[keep] = dedupeRows((byPrinter[keep] || []).concat(byPrinter[drop] || []));
                                canceledByPrinter[keep] = dedupeRows((canceledByPrinter[keep] || []).concat(
                                    canceledByPrinter[drop] || []));
                                delete byPrinter[drop];
                                delete canceledByPrinter[drop];
                                names = names.filter((n) => n !== drop);
                            }
                        })();
                        if (!names.length) {
                            if (typeof showNotification === 'function') {
                                showNotification('Comanda',
                                    'No hay ticketera asignada a los productos. Configure impresoras en el producto o sucursal.',
                                    'warning');
                            }
                            return false;
                        }
                        let printedDirectly = true;
                        const kitchenRecentPrints = (window.__kitchenRecentPrints = window.__kitchenRecentPrints ||
                        new Map());

                        function shouldSkipDuplicateKitchenTicket(printerName, ticketText) {
                            const pn = String(printerName || '').trim().toLowerCase();
                            const tt = String(ticketText || '');
                            if (!pn || !tt) return false;
                            const key = pn + '::' + tt;
                            const now = Date.now();
                            const prev = kitchenRecentPrints.get(key) || 0;
                            // Evita doble impresión por doble disparo del flujo JS en pocos segundos.
                            if (now - prev < 5000) {
                                return true;
                            }
                            kitchenRecentPrints.set(key, now);
                            if (kitchenRecentPrints.size > 200) {
                                for (const [k, ts] of kitchenRecentPrints.entries()) {
                                    if (now - ts > 60000) kitchenRecentPrints.delete(k);
                                }
                            }
                            return false;
                        }

                        async function sendKitchenTicketToServer(printerName, ticketText) {
                            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                                '';
                            const tr = await fetch(kitchenThermalPrintUrl, {
                                method: 'POST',
                                cache: 'no-store',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify({
                                    printer_name: printerName || null,
                                    ticket_text: ticketText,
                                }),
                            });
                            const td = tr.headers.get('content-type')?.includes('application/json') ? await tr.json() :
                                null;
                            if (tr.ok && td?.success) {
                                return td;
                            }
                            throw new Error(td?.message || ('No se pudo imprimir comanda en "' + (printerName ||
                                'Ticketera') + '".'));
                        }

                        const namesNeedingClientQz = names.filter((n) => !kitchenComandaPrinterUsesServerThermal(n));
                        const needsClientQz = namesNeedingClientQz.length > 0;
                        const QZ_MULTI_KITCHEN_HINT = '__MULTI_KITCHEN_SECONDARY_FIRST__';
                        const kitchenCertPrinterHint = namesNeedingClientQz.find((n) => {
                            if (typeof window.__qzPrinterRequiresSecondaryCertFirst === 'function') {
                                return window.__qzPrinterRequiresSecondaryCertFirst(n);
                            }
                            const t = String(n || '').trim().toLowerCase().replace(/\s+/g, '');
                            return t === 'barra2' || t.startsWith('barra2');
                        }) || null;
                        const multiTicketeraComanda = namesNeedingClientQz.length >= 2;
                        const defPn = String(window.__qzConfig?.defaultPrinterName || window.__qzConfig?.printerName || '')
                            .trim();
                        let qzKitchenCertHint = kitchenCertPrinterHint || (multiTicketeraComanda ? QZ_MULTI_KITCHEN_HINT :
                            undefined);
                        if (!qzKitchenCertHint && needsClientQz && defPn && typeof window
                            .__qzPrinterRequiresSecondaryCertFirst === 'function' && window
                            .__qzPrinterRequiresSecondaryCertFirst(defPn)) {
                            qzKitchenCertHint = defPn;
                        }
                        const allowKitchenClientQz = kitchenComandaAllowClientQz();
                        const qzAvailable = allowKitchenClientQz && needsClientQz && qzApi && await ensureQzTrayConnected(
                            qzApi, qzKitchenCertHint);
                        let canUseQz = !!qzAvailable;
                        if (!canUseQz && needsClientQz && allowKitchenClientQz && qzApi) {
                            if (typeof showNotification === 'function') {
                                showNotification('Impresión',
                                    'QZ Tray no disponible para comanda; se intentará impresión por servidor.',
                                    'warning');
                            }
                        }

                        function padEnd(str, length) {
                            const s = String(str ?? '');
                            if (s.length >= length) return s.slice(0, length);
                            return s + ' '.repeat(length - s.length);
                        }

                        function padCenter(str, length) {
                            const s = String(str ?? '');
                            if (s.length >= length) return s.slice(0, length);
                            return ' '.repeat(Math.floor((length - s.length) / 2)) + s + ' '.repeat(length - s.length - Math
                                .floor((length - s.length) / 2));
                        }

                        function padStart(str, length) {
                            const s = String(str ?? '');
                            if (s.length >= length) return s.slice(-length);
                            return ' '.repeat(length - s.length) + s;
                        }

                        const tableLabel = table?.name ?? table?.table_id ?? 'Mesa';
                        const areaLabel = (table?.original_area_name || '').trim();

                        for (let i = 0; i < names.length; i++) {
                            const pname = names[i];
                            const lines = byPrinter[pname] || [];
                            let body = '';
                            const paperWidth = resolvePrinterWidthByName(pname);
                            const LINE_WIDTH = paperWidth === 80 ? 48 : 24;
                            const COL_QTY = 4; // x99
                            const COL_TIME = 6; // "09:54"
                            const COL_NAME = LINE_WIDTH - COL_TIME - COL_QTY;
                            const separator = '='.repeat(LINE_WIDTH) + '\n';
                            const orderNumber = String(table?.order_movement_number ?? '').trim();
                            const orderDate = String(table?.order_movement_date ?? '').trim();
                            const comandaLabel = [`COMANDA: ${table?.order_movement_id}`, orderNumber].filter(Boolean).join(
                                ': ');
                            const comandaSub = String(pname || '').trim() || 'COCINA';
                            const clientLabel = String(table?.clientName || table?.client || '').trim();
                            const header = padCenter(comandaLabel, LINE_WIDTH) + '\n' +
                                padCenter(comandaSub, LINE_WIDTH) + '\n' +
                                (areaLabel ? areaLabel + '\n' : '') +
                                'Mesa ' + tableLabel + '\n' +
                                'Mozo: ' + (table?.waiter || '-') + '\n' +
                                (clientLabel ? 'Cliente: ' + clientLabel + '\n' : '') +
                                'Fecha: ' + new Date().toLocaleString() + '\n' +
                                separator +
                                padEnd('Producto', COL_NAME) + padCenter('Hora', COL_TIME) + padStart('Cant', COL_QTY) +
                                '\n' +
                                separator;
                            const canceledByProduct = {};
                            (canceledByPrinter[pname] || []).forEach((c) => {
                                const pid = parseInt(c?.pId, 10) || 0;
                                const qty = parseFloat(c?.qty ?? 0) || 0;
                                if (!pid || qty <= 0) return;
                                canceledByProduct[pid] = (canceledByProduct[pid] || 0) + qty;
                            });

                            lines.forEach((it) => {
                                const qty = it.qty ?? 1;
                                const nm = (it.name || 'Producto').trim();
                                const qtyCol = 'x' + qty;
                                const timeCol = (it.commandTime ? String(it.commandTime).trim() : '');
                                const complementsText = formatComplementsLabel(it?.complements);
                                const courtesyQty = Math.max(0, Math.min(parseFloat(qty) || 0, parseFloat(it
                                    ?.courtesyQty ?? 0) || 0));
                                const takeawayQty = Math.max(0, Math.min(parseFloat(qty) || 0, parseFloat(it
                                    ?.takeawayQty ?? 0) || 0));
                                body += padEnd(nm, COL_NAME) + padCenter(timeCol, COL_TIME) + padStart(qtyCol,
                                    COL_QTY) + '\n';
                                if (complementsText) {
                                    body += 'Detalle: ' + complementsText + '\n';
                                }
                                const unitK = parseFloat(it?.price);
                                if (!isNaN(unitK) && unitK >= 0) {
                                    body += 'P.unit: S/ ' + unitK.toFixed(2) + '\n';
                                }
                                if (courtesyQty > 0 || takeawayQty > 0) {
                                    const tags = [];
                                    if (courtesyQty > 0) tags.push('Cortesia: ' + courtesyQty);
                                    if (takeawayQty > 0) tags.push('Llevar: ' + takeawayQty);
                                    body += '  * ' + tags.join(' | ') + '\n';
                                }
                                if (it.note && String(it.note).trim()) {
                                    body += 'Nota: ' + String(it.note).trim() + '\n';
                                }
                                const status = String(it?.status || '').toUpperCase();
                                const isDelivered = !!it?.delivered || status === 'ENTREGADO' || status === 'E';
                                // Cancelado: desde el item (si existe) o desde la lista de cancelaciones de la mesa.
                                const pId = parseInt(it?.pId ?? it?.product_id, 10) || 0;
                                const canceledQty = canceledByProduct[pId] || 0;
                                const isCanceled = status === 'CANCELADO' || status === 'C' || canceledQty > 0;
                                const statusLabel = isCanceled ?
                                    ('CANCELADO' + (canceledQty > 0 ? ' x' + canceledQty : '')) :
                                    (isDelivered ? 'ENTREGADO' : 'PENDIENTE');
                                body += 'Estado: ' + statusLabel + '\n';
                                body += '\n';
                            });
                            const canceledItems = canceledByPrinter[pname] || [];
                            if (canceledItems.length) {
                                body += separator;
                                body += padCenter('ANULADO', LINE_WIDTH) + '\n';
                                body += separator;
                                canceledItems.forEach((c) => {
                                    const qtyCol = 'x' + (c.qty ?? 1);
                                    const complementsText = formatComplementsLabel(c?.complements);
                                    body += padEnd('ANULADO ' + (c.name || 'Producto'), COL_NAME) + padCenter('',
                                        COL_TIME) + padStart(qtyCol, COL_QTY) + '\n';
                                    if (complementsText) {
                                        body += 'Detalle: ' + complementsText + '\n';
                                    }
                                    if (c.reason) {
                                        body += 'Motivo: ' + c.reason + '\n';
                                    }
                                    body += '\n';
                                });
                            }
                            const data = header + body + '\n\n';
                            if (shouldSkipDuplicateKitchenTicket(pname, data)) {
                                console.warn('Comanda duplicada evitada en ' + pname);
                                continue;
                            }
                            try {
                                if (kitchenComandaPrinterUsesServerThermal(pname)) {
                                    await sendKitchenTicketToServer(pname, data);
                                } else if (canUseQz) {
                                    try {
                                        await qzApi.printers.find(pname);
                                        await printTicketWithQz(qzApi, pname, data);
                                    } catch (notFoundErr) {
                                        const msg = 'QZ no encontró la impresora "' + pname +
                                            '". Se intentará impresión por servidor.';
                                        console.warn(msg, notFoundErr);
                                        if (typeof showNotification === 'function') {
                                            showNotification('Impresión', msg, 'warning');
                                        }
                                        await sendKitchenTicketToServer(pname, data);
                                    }
                                } else {
                                    await sendKitchenTicketToServer(pname, data);
                                }
                            } catch (e) {
                                console.error('Impresi?n comanda: error al imprimir en ' + pname, e);
                                printedDirectly = false;
                                if (typeof showNotification === 'function') {
                                    showNotification('Comanda', 'No se pudo imprimir en "' + pname + '". ' + (e?.message ||
                                        ''), 'error');
                                }
                            }
                        }
                        return printedDirectly;
                    }

                    function getItemTaxRatePercent(item) {
                        const rate = parseFloat(item?.tax_rate);
                        return !isNaN(rate) && rate >= 0 ? rate : 10;
                    }

                    /** Cantidad para llevar por línea: 0 … qty (no aplica en DELIVERY en UI). */
                    function clampTakeawayQty(item) {
                        if (!item) return;
                        const q = parseFloat(item.qty) || 0;
                        let t = parseFloat(item.takeawayQty);
                        if (isNaN(t) || t < 0) t = 0;
                        if (t > q) t = q;
                        item.takeawayQty = t;
                    }

                    // Los precios del POS incluyen IGV.
                    function calculateTotalsFromItems(items) {
                        let subtotal = 0;
                        let tax = 0;
                        let total = 0;

                        (items || []).forEach(item => {
                            const qty = parseFloat(item.qty) || 0;
                            const price = parseFloat(item.price) || 0;
                            const courtesyQty = parseInt(item.courtesyQty) || 0;
                            const paidQty = Math.max(0, qty - courtesyQty);
                            const lineTotal = paidQty * price;
                            const rate = getItemTaxRatePercent(item) / 100;
                            const lineSubtotal = rate > 0 ? (lineTotal / (1 + rate)) : lineTotal;
                            const lineTax = lineTotal - lineSubtotal;
                            subtotal += lineSubtotal;
                            tax += lineTax;
                            total += lineTotal;
                        });

                        return {
                            subtotal: Math.round(subtotal * 100) / 100,
                            tax: Math.round(tax * 100) / 100,
                            total: Math.round(total * 100) / 100,
                        };
                    }

                    /** Costo delivery (solo servicio DELIVERY). */
                    function getDeliveryFeeAmount() {
                        if (!currentTable || currentTable.service_type !== 'DELIVERY') return 0;
                        const v = parseFloat(currentTable.delivery_amount);
                        return !isNaN(v) && v > 0 ? Math.round(v * 100) / 100 : 0;
                    }

                    function hasAnyTakeawayInCart() {
                        return (currentTable?.items || []).some(it => (parseFloat(it.takeawayQty) || 0) > 0);
                    }

                    /** Costo descartables para llevar (checkbox + monto). */
                    function getTakeawayDisposableFee() {
                        if (!currentTable || currentTable.service_type === 'DELIVERY') return 0;
                        if (!currentTable.takeaway_disposable_charge) return 0;
                        const ctx = currentTable.service_type === 'TAKE_AWAY' || hasAnyTakeawayInCart();
                        if (!ctx) return 0;
                        const v = parseFloat(currentTable.takeaway_disposable_amount);
                        return !isNaN(v) && v > 0 ? Math.round(v * 100) / 100 : 0;
                    }

                    /**
                     * Totales de productos + delivery + descartables (llevar) al total final.
                     */
                    function getTotalsWithDelivery(items) {
                        const base = calculateTotalsFromItems(items);
                        const fee = getDeliveryFeeAmount();
                        const disp = getTakeawayDisposableFee();
                        const grand = Math.round((base.total + fee + disp) * 100) / 100;
                        return {
                            subtotal: base.subtotal,
                            tax: base.tax,
                            productsTotal: base.total,
                            deliveryFee: fee,
                            takeawayDisposableFee: disp,
                            total: grand,
                        };
                    }

                    function syncTakeawayDisposablePanel() {
                        const el = document.getElementById('takeaway-disposable-panel');
                        if (!el || !currentTable) return;
                        const st = currentTable.service_type || 'IN_SITU';
                        const show = st !== 'DELIVERY' && (st === 'TAKE_AWAY' || hasAnyTakeawayInCart());
                        el.classList.toggle('hidden', !show);
                        const chk = document.getElementById('takeaway-disposable-charge');
                        const inp = document.getElementById('takeaway-disposable-amount');
                        if (chk) chk.checked = !!currentTable.takeaway_disposable_charge;
                        if (inp) {
                            const raw = parseFloat(currentTable.takeaway_disposable_amount);
                            inp.value = (!isNaN(raw) && raw > 0 ? raw : 0).toFixed(2);
                            inp.disabled = !chk || !chk.checked;
                        }
                    }

                    function updateTakeawayDisposableInfo(normalizeInput = false) {
                        if (!currentTable) return;
                        const chk = document.getElementById('takeaway-disposable-charge');
                        const inp = document.getElementById('takeaway-disposable-amount');
                        if (chk) currentTable.takeaway_disposable_charge = chk.checked;
                        if (inp) {
                            if (!chk || !chk.checked) {
                                inp.disabled = true;
                                currentTable.takeaway_disposable_amount = 0;
                            } else {
                                inp.disabled = false;
                                let n = parseFloat(inp.value);
                                if (isNaN(n) || n < 0) n = 0;
                                currentTable.takeaway_disposable_amount = Math.round(n * 100) / 100;
                                if (normalizeInput) {
                                    inp.value = currentTable.takeaway_disposable_amount.toFixed(2);
                                }
                            }
                        }
                        saveDB();
                        renderTicket();
                    }

                    // Actualizar precios del carrito con los precios actuales del servidor
                    function refreshCartPricesFromServer() {
                        if (!currentTable?.items || !serverProductBranches?.length) return;
                        let updated = false;
                        currentTable.items.forEach(item => {
                            if (item.priceManual === true) {
                                return;
                            }
                            const pId = parseInt(item.pId || item.product_id, 10);
                            const pb = findProductBranchByProductId(pId);
                            if (pb) {
                                const newPrice = parseFloat(pb.price);
                                if (!isNaN(newPrice) && newPrice >= 0 && newPrice !== parseFloat(item.price)) {
                                    item.price = newPrice;
                                    updated = true;
                                }
                                const newTaxRate = parseFloat(pb.tax_rate);
                                if (!isNaN(newTaxRate) && newTaxRate >= 0 && newTaxRate !== parseFloat(item.tax_rate)) {
                                    item.tax_rate = newTaxRate;
                                    updated = true;
                                }
                            }
                        });
                        if (updated) saveDB();
                    }

                    const CATEGORY_ALL_ID = '__all__';
                    const CATEGORY_FAVORITES_ID = '__favorites__';
                    let selectedCategoryId = CATEGORY_FAVORITES_ID;
                    let productSearchQuery = '';

                    function isProductFavorite(productId) {
                        const pb = serverProductBranches.find(p => Number(p.product_id) === Number(productId));
                        return pb && String(pb.favorite || 'N').toUpperCase() === 'S';
                    }

                    function ensureMobileQuickFilters() {
                        const grid = document.getElementById('categories-grid');
                        if (!grid) return;
                        const mobileOnlyQuickFilters = window.matchMedia('(max-width: 767.98px)').matches;
                        if (!mobileOnlyQuickFilters || grid.children.length >= 2) return;

                        grid.innerHTML = '';

                        const favBtn = document.createElement('button');
                        favBtn.type = 'button';
                        favBtn.className = [
                            'inline-flex items-center gap-2 px-2.5 py-1.5 rounded-full text-xs sm:text-sm font-semibold',
                            'border transition-all duration-150 whitespace-nowrap cursor-pointer shrink-0',
                            selectedCategoryId === CATEGORY_FAVORITES_ID ?
                            'bg-[#FF4622] text-white border-[#FF4622] shadow-sm' :
                            'bg-white dark:bg-slate-800 text-gray-700 border-gray-300 dark:border-slate-600 hover:border-[#FF4622] hover:text-[#FF4622] dark:hover:text-[#FF4622]'
                        ].join(' ');
                        favBtn.onclick = function(e) {
                            e.preventDefault();
                            selectedCategoryId = CATEGORY_FAVORITES_ID;
                            renderCategories();
                            renderProducts();
                        };
                        favBtn.innerHTML = `<i class="ri-star-fill text-lg"></i><span>Favoritos</span>`;
                        grid.appendChild(favBtn);

                        const allBtn = document.createElement('button');
                        allBtn.type = 'button';
                        allBtn.className = [
                            'inline-flex items-center gap-2 px-2.5 py-1.5 rounded-full text-xs sm:text-sm font-semibold',
                            'border transition-all duration-150 whitespace-nowrap cursor-pointer shrink-0',
                            selectedCategoryId === CATEGORY_ALL_ID ?
                            'bg-[#FF4622] text-white border-[#FF4622] shadow-sm' :
                            'bg-white dark:bg-slate-800 text-gray-700 border-gray-300 dark:border-slate-600 hover:border-[#FF4622] hover:text-[#FF4622] dark:hover:text-[#FF4622]'
                        ].join(' ');
                        allBtn.onclick = function(e) {
                            e.preventDefault();
                            selectedCategoryId = CATEGORY_ALL_ID;
                            renderCategories();
                            renderProducts();
                        };
                        allBtn.innerHTML = `<i class="ri-apps-line text-lg"></i><span>Todos</span>`;
                        grid.appendChild(allBtn);
                    }

                    function renderCategories() {
                        const grid = document.getElementById('categories-grid');
                        if (!grid) return;

                        grid.innerHTML = '';
                        const mobileOnlyQuickFilters = window.matchMedia('(max-width: 767.98px)').matches;
                        if (mobileOnlyQuickFilters && selectedCategoryId !== CATEGORY_ALL_ID && selectedCategoryId !==
                            CATEGORY_FAVORITES_ID) {
                            selectedCategoryId = CATEGORY_ALL_ID;
                        }

                        // Favoritos (predeterminado)
                        const favBtn = document.createElement('button');
                        favBtn.type = 'button';
                        const isFavActive = selectedCategoryId === CATEGORY_FAVORITES_ID;
                        favBtn.className = [
                            'inline-flex items-center gap-2 px-2.5 py-1.5 rounded-full text-xs sm:text-sm font-semibold',
                            'border transition-all duration-150 whitespace-nowrap cursor-pointer shrink-0',
                            isFavActive ?
                            'bg-[#FF4622] text-white border-[#FF4622] shadow-sm' :
                            'bg-white dark:bg-slate-800 text-gray-700 border-gray-300 dark:border-slate-600 hover:border-[#FF4622] hover:text-[#FF4622] dark:hover:text-[#FF4622]'
                        ].join(' ');
                        favBtn.onclick = function(e) {
                            e.preventDefault();
                            selectedCategoryId = CATEGORY_FAVORITES_ID;
                            renderCategories();
                            renderProducts();
                        };
                        favBtn.innerHTML = `<i class="ri-star-fill text-lg"></i><span>Favoritos</span>`;
                        grid.appendChild(favBtn);

                        // Botón "Todos" (lista todos los productos de la sucursal con categoría)
                        const allBtn = document.createElement('button');
                        allBtn.type = 'button';
                        const isAllActive = selectedCategoryId === CATEGORY_ALL_ID;
                        allBtn.className = [
                            'inline-flex items-center gap-2 px-2.5 py-1.5 rounded-full text-xs sm:text-sm font-semibold',
                            'border transition-all duration-150 whitespace-nowrap cursor-pointer shrink-0',
                            isAllActive ?
                            'bg-[#FF4622] text-white border-[#FF4622] shadow-sm' :
                            'bg-white dark:bg-slate-800 text-gray-700 border-gray-300 dark:border-slate-600 hover:border-[#FF4622] hover:text-[#FF4622] dark:hover:text-[#FF4622]'
                        ].join(' ');
                        allBtn.onclick = function(e) {
                            e.preventDefault();
                            selectedCategoryId = CATEGORY_ALL_ID;
                            renderCategories();
                            renderProducts();
                        };
                        allBtn.innerHTML = `<i class="ri-apps-line text-lg"></i><span>Todos</span>`;
                        grid.appendChild(allBtn);

                        if (mobileOnlyQuickFilters || !serverCategories || serverCategories.length === 0) {
                            ensureMobileQuickFilters();
                            renderProducts();
                            return;
                        }

                        serverCategories.forEach(cat => {
                            const el = document.createElement('button');
                            const categoryName = escapeHtml(cat.name || 'Sin nombre');
                            const imageUrl = getImageUrl(cat.img);
                            const isActive = selectedCategoryId === cat.id;

                            el.type = 'button';
                            el.className = [
                                'inline-flex items-center gap-2 px-2.5 py-1.5 rounded-full text-xs sm:text-sm font-semibold',
                                'border transition-all duration-150 whitespace-nowrap cursor-pointer shrink-0',
                                isActive ?
                                'bg-[#FF4622] text-white border-[#FF4622] shadow-sm' :
                                'bg-white dark:bg-slate-800 text-gray-700 dark:text-gray-200 border-gray-300 dark:border-slate-600 hover:border-[#FF4622] hover:text-[#FF4622] dark:hover:text-[#FF4622]'
                            ].join(' ');

                            el.onclick = function(e) {
                                e.preventDefault();
                                selectedCategoryId = cat.id;
                                renderCategories();
                                renderProducts();
                            };

                            el.innerHTML =
                                `
                                                                                                                                                                                                    <img src="${imageUrl}" alt="${categoryName}"
                                                                                                                                                                                                        class="w-6 h-6 rounded-full object-cover shrink-0 border ${isActive ? 'border-[#FF4622]/30' : 'border-gray-200 dark:border-slate-600'}"
                                                                                                                                                                                                        onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22200%22%3E%3Crect fill=%22%23e5e7eb%22 width=%22200%22 height=%22200%22/%3E%3C/svg%3E'">
                                                                                                                                                                                                    <span>${categoryName}</span>
                                                                                                                                                                                                `;
                            grid.appendChild(el);
                        });
                        ensureMobileQuickFilters();
                    }

                    function buildProductStockLabel(prod, productBranch) {
                        const stockVal = Number(productBranch?.stock);
                        const stockText = !isNaN(stockVal) ? stockVal.toFixed(2) : '0.00';
                        const availability = getRecipeAvailability(prod?.id);
                        if (availability && availability.limiting_ingredient) {
                            const ing = availability.limiting_ingredient;
                            const ingName = ing.name || 'Ingrediente';
                            const remaining = Math.max(0, parseFloat(ing.remaining) || 0);
                            const label = `Stock ${ingName}: ${remaining.toFixed(2)} | +${availability.max_additional} und`;
                            const colorClass = remaining <= 0 ?
                                'text-red-500 dark:text-red-400' :
                                (availability.max_additional <= 3 ? 'text-amber-500 dark:text-amber-400' :
                                    'text-gray-500 dark:text-gray-400');
                            return {
                                label,
                                colorClass
                            };
                        }

                        const colorClass = Number(stockText) <= 0 ?
                            'text-red-500 dark:text-red-400' :
                            (Number(stockText) < 5 ? 'text-amber-500 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400');
                        return {
                            label: `Stock: ${stockText}`,
                            colorClass
                        };
                    }

                    function refreshRecipeStockLabelsInProductGrid() {
                        document.querySelectorAll('[data-product-stock-label]').forEach((stockEl) => {
                            const productId = parseInt(stockEl.getAttribute('data-product-stock-label'), 10);
                            if (!productId) return;
                            const prod = serverProducts.find((p) => Number(p.id) === Number(productId));
                            const productBranch = findProductBranchByProductId(productId);
                            if (!prod || !productBranch) return;
                            const stockInfo = buildProductStockLabel(prod, productBranch);
                            stockEl.textContent = stockInfo.label;
                            stockEl.className = `mt-1 text-xs font-medium ${stockInfo.colorClass}`;
                        });
                    }

                    function tryAutoAddByExactInternalCode(qNormalized) {
                        if (!qNormalized || !Array.isArray(serverProducts)) {
                            return false;
                        }
                        const matches = [];
                        for (let i = 0; i < serverProducts.length; i++) {
                            const p = serverProducts[i];
                            const raw = p && p.code != null ? String(p.code).trim() : '';
                            if (!raw) {
                                continue;
                            }
                            if (raw.toLowerCase() === qNormalized) {
                                matches.push(p);
                            }
                        }
                        if (matches.length !== 1) {
                            return false;
                        }
                        const pb = findProductBranchByProductId(matches[0].id);
                        if (!pb) {
                            return false;
                        }
                        const inp = document.getElementById('search-products');
                        if (inp) {
                            inp.value = '';
                        }
                        productSearchQuery = '';
                        const btn = document.getElementById('search-products-clear');
                        if (btn) {
                            btn.classList.add('hidden');
                        }
                        addToCart(matches[0], pb);
                        return true;
                    }

                    function renderProducts() {
                        const grid = document.getElementById('products-grid');
                        if (!grid) return;
                        grid.innerHTML = '';

                        if (!serverProducts || serverProducts.length === 0) {
                            grid.innerHTML =
                                '<div class="col-span-full text-center text-gray-500 py-8">No hay productos disponibles</div>';
                            return;
                        }

                        // Leer búsqueda actual. Si hay texto, ignora la categoría seleccionada y busca en todo.
                        const searchInput = document.getElementById('search-products');
                        const q = (searchInput && searchInput.value !== undefined) ?
                            String(searchInput.value || '').trim().toLowerCase() :
                            String(productSearchQuery || '').trim().toLowerCase();

                        if (q.length > 0 && tryAutoAddByExactInternalCode(q)) {
                            renderProducts();
                            return;
                        }

                        let productsToShow = serverProducts;
                        if (q.length === 0) {
                            if (selectedCategoryId === CATEGORY_ALL_ID) {
                                productsToShow = serverProducts;
                            } else if (selectedCategoryId === CATEGORY_FAVORITES_ID) {
                                productsToShow = serverProducts.filter(p => isProductFavorite(p.id));
                            } else {
                                productsToShow = serverProducts.filter(p => p.category_id == selectedCategoryId);
                            }
                        }

                        if (q.length > 0) {
                            const searchWords = q.split(/\s+/).filter(word => word.length > 0);
                            productsToShow = productsToShow.filter(p => {
                                const name = String(p.name || '').toLowerCase();
                                const category = String(p.category || '').toLowerCase();
                                const code = String(p.code || '').toLowerCase();
                                const searchable = `${name} ${category} ${code}`;
                                return searchWords.every(word => searchable.includes(word));
                            });
                        }

                        let productsRendered = 0;
                        productsToShow.forEach(prod => {
                            const productBranch = findProductBranchByProductId(prod.id);
                            if (!productBranch) return;

                            const el = document.createElement('div');
                            el.className =
                                "group cursor-pointer transition-transform duration-200 hover:scale-105 h-full flex";

                            // Prevenir múltiples clics rápidos
                            let isAdding = false;
                            el.onclick = function(e) {
                                e.preventDefault();

                                isAdding = true;
                                addToCart(prod, productBranch);

                                // Permitir agregar de nuevo después de un breve delay
                                setTimeout(() => {
                                    isAdding = false;
                                }, 500);
                            };

                            const productName = escapeHtml(prod.name || 'Sin nombre');
                            const imageUrl = getImageUrl(prod.img);
                            const priceFormatted = 'S/ ' + parseFloat(productBranch.price).toFixed(2);
                            const hasImg = prod.img && String(prod.img).trim() !== '';
                            const stockInfo = buildProductStockLabel(prod, productBranch);

                            el.innerHTML =
                                `
                                                                                                                                                                                                    <div class="rounded-2xl overflow-hidden p-4 sm:p-5 bg-white dark:bg-slate-800/60 border-2 border-[#FF4622]/20 dark:border-[#FF4622]/40 hover:border-[#FF4622] dark:hover:border-[#FF4622] transition-all duration-200 hover:-translate-y-0.5 flex flex-col items-center text-center h-full w-full">
                                        <div class="hidden sm:flex w-20 h-20 rounded-full bg-[#FF4622] items-center justify-center shrink-0 overflow-hidden mb-3">
                                            ${hasImg
                                    ? `<img src="${imageUrl}" alt="${productName}" class="w-full h-full object-contain rounded-full object-cover object-center" loading="lazy" onerror="this.parentElement.innerHTML='<i class=\\'ri-restaurant-2-line text-2xl sm:text-3xl text-white\\'></i>'">`
                                    : `<i class="ri-restaurant-2-line text-2xl sm:text-3xl text-white"></i>`
                                }
                                        </div>
                                                                                                                                                                                                        <h4 class="font-semibold text-gray-900 dark:text-white text-sm sm:text-base line-clamp-2 leading-tight mb-1 min-h-[2.5rem]">
                                                                                                                                                                                                            ${productName}
                                                                                                                                                                                                        </h4>
                                                                                                                                                                                                        <span class="text-base sm:text-lg font-bold text-[#FF4622] dark:text-[#FF4622]">
                                                                                                                                                                                                            ${priceFormatted}
                                                                                                                                                                                                        </span>
                                                                                                                                                                                                        <span data-product-stock-label="${Number(prod.id)}" class="mt-1 text-xs font-medium ` +
                                stockInfo.colorClass + `">` + escapeHtml(stockInfo.label) +
                                `</span>
                                                                                                                                                                                                    </div>
                                                                                                                                                                                                `;
                            grid.appendChild(el);
                            productsRendered++;
                        });

                        if (productsRendered === 0) {
                            if (q.length > 0) {
                                grid.innerHTML =
                                    '<div class="col-span-full text-center text-gray-500 py-8">No se encontraron productos para "' +
                                    escapeHtml(productSearchQuery) + '"</div>';
                            } else if (selectedCategoryId === CATEGORY_FAVORITES_ID) {
                                grid.innerHTML =
                                    '<div class="col-span-full text-center text-gray-500 py-8">No hay productos favoritos en esta sucursal. Marca favoritos en el producto o elige la categoría Todos.</div>';
                            } else {
                                grid.innerHTML = selectedCategoryId === CATEGORY_ALL_ID ?
                                    '<div class="col-span-full text-center text-gray-500 py-8">No hay productos disponibles para esta sucursal</div>' :
                                    '<div class="col-span-full text-center text-gray-500 py-8">No hay productos en esta categoría</div>';
                            }
                        }

                    }

                    async function addToCart(prod, productBranch) {
                        if (!currentTable.items) currentTable.items = [];

                        if (!productBranch || !productBranch.price) {
                            showNotification('Precio no disponible', 'El producto no tiene un precio disponible.', 'error');
                            return;
                        }

                        const price = parseFloat(productBranch.price);
                        if (isNaN(price) || price <= 0) {
                            showNotification('Precio inválido', 'El precio del producto es inválido.', 'error');
                            return;
                        }

                        const stock = parseFloat(productBranch.stock ?? 0) || 0;
                        const allowZeroStockSales = @json($allowZeroStockSales ?? true);

                        // Asegurar que el ID del producto sea un número entero para la comparación
                        const productId = parseInt(prod.id, 10);
                        const hasRecipe = !!recipeStockData[String(productId)];
                        if (isNaN(productId) || productId <= 0) {

                            showNotification('ID de producto inválido', 'El ID del producto es inválido.', 'error');
                            return;
                        }

                        // Limpiar items inválidos antes de buscar
                        currentTable.items = currentTable.items.filter(i => {
                            const itemPId = parseInt(i.pId, 10);
                            return !isNaN(itemPId) && itemPId > 0;
                        });

                        const detailSelection = await promptProductDetailSelection(prod);
                        if (!detailSelection) {
                            return;
                        }
                        const selectedComplements = normalizeComplements(detailSelection.complements);
                        const qtyRequested = Math.max(1, parseInt(detailSelection.qty, 10) || 1);

                        // Buscar si el producto ya existe en el carrito con la misma configuración.
                        const existing = currentTable.items.find(i => {
                            const itemPId = parseInt(i.pId, 10);
                            return !isNaN(itemPId) && itemPId === productId &&
                                getComplementSignature(i.complements) === getComplementSignature(
                                    selectedComplements);
                        });

                        const productQtyInCart = getCurrentProductQtyInCart(productId);
                        const productVirtualQtyInCart = getCurrentProductVirtualQtyInCart(productId);
                        // Para productos con receta, no bloquear por el stock propio del producto
                        // (se controla vía ingredientes más abajo). Solo aplicar para productos sin receta.
                        if (!hasRecipe && !allowZeroStockSales && (productQtyInCart + qtyRequested) > stock) {
                            showNotification('Stock insuficiente', (prod.name || 'Producto') + ': solo hay ' + stock +
                                ' disponible(s).', 'error');
                            return;
                        }
                        // Validación de ingredientes para productos con receta (siempre obligatoria)
                        if (!checkRecipeStock(productId, productVirtualQtyInCart, qtyRequested)) return;

                        const st = currentTable.service_type || 'IN_SITU';
                        if (existing) {
                            // Si existe con la misma configuración, solo aumentar la cantidad
                            existing.qty += qtyRequested;
                            if (st === 'TAKE_AWAY') {
                                existing.takeawayQty = (parseFloat(existing.takeawayQty) || 0) + qtyRequested;
                                clampTakeawayQty(existing);
                            }
                            if (existing.tax_rate === undefined || existing.tax_rate === null) {
                                existing.tax_rate = parseFloat(productBranch.tax_rate ?? 10);
                            }
                            existing.complements = selectedComplements;
                        } else {
                            // Si no existe, agregarlo como nueva línea.
                            currentTable.items.push({
                                pId: productId,
                                name: prod.name || 'Sin nombre',
                                qty: qtyRequested,
                                price: price,
                                tax_rate: parseFloat(productBranch.tax_rate ?? 10),
                                note: "",
                                delivered: false,
                                courtesyQty: 0,
                                takeawayQty: st === 'TAKE_AWAY' ? qtyRequested : 0,
                                complements: selectedComplements
                            });
                        }
                        saveDB();
                        renderTicket();
                        showNotification('Producto agregado', 'El producto ' + (prod.name || 'Producto') +
                            ' ha sido agregado al carrito.', 'success');
                    }

                    async function updateQty(index, change) {
                        const item = currentTable.items[index];
                        const oldQty = item.qty;
                        let newQty = oldQty + change;

                        // Aumentar cantidad: no requiere razón
                        if (change > 0) {
                            const allowZeroStockSales = @json($allowZeroStockSales ?? true);
                            const prodId = parseInt(item.pId || item.product_id, 10);
                            const pb = serverProductBranches.find(p => parseInt(p.product_id, 10) === prodId);
                            const stock = parseFloat(pb?.stock ?? 0) || 0;
                            const currentOtherQty = getCurrentProductQtyInCart(prodId, index);
                            const currentOtherVirtualQty = getCurrentProductVirtualQtyInCart(prodId, index);
                            const prodHasRecipe = !!recipeStockData[String(prodId)];

                            // Para productos con receta, la validación es por ingredientes (más abajo)
                            if (!prodHasRecipe && !allowZeroStockSales && (currentOtherQty + newQty) > stock) {
                                showNotification('Stock insuficiente', (item.name || 'Producto') + ': solo hay ' + stock +
                                    ' disponible(s).', 'error');
                                return;
                            }
                            if (!checkRecipeStock(prodId, currentOtherVirtualQty, change)) return;

                            item.qty = newQty;
                            if ((currentTable.service_type || '') === 'TAKE_AWAY') {
                                item.takeawayQty = (parseFloat(item.takeawayQty) || 0) + change;
                                clampTakeawayQty(item);
                            }
                            saveDB();
                            renderTicket();
                            return;
                        }

                        // Disminuir cantidad (si pedido guardado, no bajar de savedQty)
                        const savedQty = Number.isFinite(parseFloat(item.savedQty)) ? parseFloat(item.savedQty) : 0;
                        if (isMozoProfile && newQty < 1 && lineLooksComandado(item)) {
                            if (typeof showNotification === 'function') {
                                showNotification('No permitido',
                                    'El perfil Mozo no puede eliminar productos ya enviados a cocina.', 'info');
                            } else if (window.Swal) {
                                window.Swal.fire({
                                    icon: 'info',
                                    title: 'No permitido',
                                    text: 'El perfil Mozo no puede eliminar productos ya enviados a cocina.'
                                });
                            }
                            return;
                        }
                        if (currentTable.order_movement_id && savedQty > 0) {
                            newQty = Math.max(savedQty, newQty);
                        }
                        item.qty = newQty;
                        const cq = parseFloat(item.courtesyQty) || 0;
                        if (cq > item.qty) item.courtesyQty = item.qty;
                        clampTakeawayQty(item);
                        if (item.qty <= 0) currentTable.items.splice(index, 1);
                        saveDB();
                        renderTicket();
                    }

                    async function setQtyFromInput(index, inputEl) {
                        const item = currentTable.items[index];
                        if (!item) return;
                        const raw = parseInt(inputEl.value, 10);
                        const newQty = isNaN(raw) || raw < 1 ? 1 : raw;
                        const oldQty = item.qty;
                        if (newQty === oldQty) {
                            inputEl.value = newQty;
                            return;
                        }
                        if (newQty > oldQty) {
                            const allowZeroStockSales = @json($allowZeroStockSales ?? true);
                            const prodId = parseInt(item.pId || item.product_id, 10);
                            const pb = serverProductBranches.find(p => parseInt(p.product_id, 10) === prodId);
                            const stock = parseFloat(pb?.stock ?? 0) || 0;
                            const currentOtherQty = getCurrentProductQtyInCart(prodId, index);
                            const currentOtherVirtualQty = getCurrentProductVirtualQtyInCart(prodId, index);
                            const prodHasRecipe = !!recipeStockData[String(prodId)];

                            // Para productos con receta, la validación es por ingredientes (más abajo)
                            if (!prodHasRecipe && !allowZeroStockSales && (currentOtherQty + newQty) > stock) {
                                showNotification('Stock insuficiente', (item.name || 'Producto') + ': solo hay ' + stock +
                                    ' disponible(s).', 'error');
                                inputEl.value = oldQty;
                                return;
                            }
                            if (!checkRecipeStock(prodId, currentOtherVirtualQty, newQty - oldQty)) {
                                inputEl.value = oldQty;
                                return;
                            }

                            item.qty = newQty;
                            if ((currentTable.service_type || '') === 'TAKE_AWAY') {
                                item.takeawayQty = (parseFloat(item.takeawayQty) || 0) + (newQty - oldQty);
                                clampTakeawayQty(item);
                            }
                            saveDB();
                            renderTicket();
                            return;
                        }
                        const savedQty = parseFloat(item.savedQty) ?? 0;
                        if (currentTable.order_movement_id && savedQty > 0 && newQty < savedQty) {
                            inputEl.value = savedQty;
                            showNotification('Cantidad no válida',
                                'La cantidad no puede ser menor que la cantidad guardada.', 'error');
                            item.qty = savedQty;
                            return;
                        }
                        await updateQty(index, newQty - oldQty);
                    }

                    function toggleNoteInput(index) {
                        if (!currentTable.items || !currentTable.items[index]) return;
                        const item = currentTable.items[index];
                        const nt = typeof item.note === 'string' ? item.note.trim() : '';
                        const hasN = nt !== '';
                        const shown = item.noteOpen === true || (item.noteOpen === undefined && hasN);
                        item.noteOpen = !shown;
                        saveDB();
                        renderTicket();
                    }

                    function toggleCourtesyInput(index) {
                        if (!currentTable.items || !currentTable.items[index]) return;
                        const item = currentTable.items[index];
                        const hasC = (parseFloat(item.courtesyQty) || 0) > 0;
                        const shown = item.courtesyOpen === true || (item.courtesyOpen === undefined && hasC);
                        item.courtesyOpen = !shown;
                        saveDB();
                        renderTicket();
                    }

                    function lineLooksComandado(item) {
                        if (!item) return false;
                        const rawSaved = item.savedQty != null && item.savedQty !== '' ? parseFloat(item.savedQty) : NaN;
                        const savedQtyItem = Number.isFinite(rawSaved) ? rawSaved : 0;
                        const omId = Number(currentTable?.order_movement_id || 0);
                        return savedQtyItem > 0 || (omId > 0 && !!item.commandTime);
                    }

                    async function confirmRemoveLine(index) {
                        if (!currentTable.items || index < 0 || index >= currentTable.items.length) return;
                        const item = currentTable.items[index];
                        if (isMozoProfile && lineLooksComandado(item)) {
                            if (typeof showNotification === 'function') {
                                showNotification('No permitido',
                                    'El perfil Mozo no puede eliminar productos ya enviados a cocina.', 'info');
                            } else if (window.Swal) {
                                window.Swal.fire({
                                    icon: 'info',
                                    title: 'No permitido',
                                    text: 'El perfil Mozo no puede eliminar productos ya enviados a cocina.'
                                });
                            }
                            return;
                        }
                        const qty = parseInt(item.qty, 10) || 1;
                        const prod = serverProducts.find(p => p.id === item.pId);
                        const name = (prod && prod.name) ? prod.name : 'este producto';
                        const msg = qty === 1 ?
                            `¿Desea eliminar 1 unidad de **${escapeHtml(name)}** del pedido?` :
                            `¿Desea eliminar **${qty}** unidades de **${escapeHtml(name)}** del pedido?`;
                        if (window.Swal) {
                            const result = await Swal.fire({
                                title: 'Eliminar del pedido',
                                html: msg.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>'),
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonText: 'Sí, eliminar',
                                cancelButtonText: 'Cancelar',
                                confirmButtonColor: '#dc2626'
                            });
                            if (!result.isConfirmed) return;
                        } else if (!window.confirm(msg.replace(/\*\*/g, ''))) {
                            return;
                        }
                        await removeFromCart(index);
                    }

                    async function removeFromCart(index) {
                        if (!currentTable.items || index < 0 || index >= currentTable.items.length) return;
                        const item = currentTable.items[index];
                        if (isMozoProfile && lineLooksComandado(item)) {
                            if (typeof showNotification === 'function') {
                                showNotification('No permitido',
                                    'El perfil Mozo no puede eliminar productos ya enviados a cocina.', 'info');
                            } else if (window.Swal) {
                                window.Swal.fire({
                                    icon: 'info',
                                    title: 'No permitido',
                                    text: 'El perfil Mozo no puede eliminar productos ya enviados a cocina.'
                                });
                            }
                            return;
                        }
                        const qty = parseInt(item.qty, 10) || 1;
                        await updateQty(index, -qty);
                    }

                    function openRemoveQuantityLineModal(index) {
                        if (!currentTable?.items || index < 0 || index >= currentTable.items.length) return;
                        const item = currentTable.items[index];
                        const maxQty = Math.max(1, parseInt(item.qty, 10) || 1);
                        const prod = serverProducts.find(p => p.id === item.pId);
                        const productName = (prod && prod.name) ? prod.name : (item.name || 'Producto');
                        const itemIsComandado = lineLooksComandado(item);
                        if (isMozoProfile && itemIsComandado) {
                            if (typeof showNotification === 'function') {
                                showNotification('No permitido',
                                    'El perfil Mozo no puede eliminar productos ya enviados a cocina.', 'info');
                            } else if (window.Swal) {
                                window.Swal.fire({
                                    icon: 'info',
                                    title: 'No permitido',
                                    text: 'El perfil Mozo no puede eliminar productos ya enviados a cocina.'
                                });
                            }
                            return;
                        }
                        if (!itemIsComandado) {
                            void removeFromCart(index);
                            return;
                        }
                        window.dispatchEvent(new CustomEvent('open-remove-quantity-modal', {
                            bubbles: true,
                            detail: {
                                index,
                                maxQty,
                                productName,
                                isComandado: true
                            },
                        }));
                    }

                    function applyRemoveQuantity(index, qtyToRemove, reason) {
                        if (!currentTable.items || index < 0 || index >= currentTable.items.length) return;
                        const item = currentTable.items[index];
                        if (isMozoProfile && lineLooksComandado(item)) {
                            if (typeof showNotification === 'function') {
                                showNotification('No permitido', 'El perfil Mozo no puede anular cantidades ya comandadas.',
                                    'info');
                            } else if (window.Swal) {
                                window.Swal.fire({
                                    icon: 'info',
                                    title: 'No permitido',
                                    text: 'El perfil Mozo no puede anular cantidades ya comandadas.'
                                });
                            }
                            return;
                        }
                        const prod = serverProducts.find(p => p.id === item.pId);
                        const qty = parseFloat(item.qty) || 0;
                        const toCancel = Math.min(qtyToRemove, qty);
                        if (toCancel <= 0) return;
                        // Siempre registrar la cancelación (no solo cuando hay order_movement_id)
                        currentTable.cancellations = currentTable.cancellations || [];
                        currentTable.cancellations.push({
                            pId: item.pId,
                            product_id: item.pId,
                            name: item.name || (prod && prod.name),
                            qtyCanceled: toCancel,
                            price: item.price,
                            note: item.note || null,
                            complements: normalizeComplements(item.complements),
                            cancel_reason: reason || null,
                            product_snapshot: prod ? {
                                ...prod
                            } : null
                        });
                        item.qty = qty - toCancel;
                        clampTakeawayQty(item);
                        if (item.qty <= 0) currentTable.items.splice(index, 1);
                        saveDB();
                        renderTicket();
                    }

                    function saveNote(index, val) {
                        currentTable.items[index].note = val;
                        saveDB();
                    }

                    function toggleDelivered(index) {
                        if (!currentTable.items || !currentTable.items[index]) return;
                        currentTable.items[index].delivered = !currentTable.items[index].delivered;
                        saveDB();
                        renderTicket();
                    }

                    function setCourtesyQty(index, inputEl) {
                        if (!currentTable.items || !currentTable.items[index]) return;
                        let val = parseFloat(inputEl.value);
                        if (isNaN(val) || val < 0) val = 0;
                        const maxQty = parseFloat(currentTable.items[index].qty) || 0;
                        if (val > maxQty) val = maxQty;
                        currentTable.items[index].courtesyQty = val;
                        inputEl.value = val;
                        clampTakeawayQty(currentTable.items[index]);
                        saveDB();
                        renderTicket();
                    }

                    function changeCourtesyQty(index, delta) {
                        if (!currentTable.items || !currentTable.items[index]) return;
                        const item = currentTable.items[index];
                        const maxQty = parseFloat(item.qty) || 0;
                        let v = (parseFloat(item.courtesyQty) || 0) + delta;
                        v = Math.max(0, Math.min(maxQty, v));
                        item.courtesyQty = v;
                        clampTakeawayQty(item);
                        saveDB();
                        renderTicket();
                    }
                    window.setCourtesyQty = setCourtesyQty;
                    window.changeCourtesyQty = changeCourtesyQty;

                    function setTakeawayQty(index, inputEl) {
                        if (!currentTable.items || !currentTable.items[index]) return;
                        let val = parseFloat(inputEl.value);
                        if (isNaN(val) || val < 0) val = 0;
                        const maxQty = parseFloat(currentTable.items[index].qty) || 0;
                        if (val > maxQty) val = maxQty;
                        currentTable.items[index].takeawayQty = val;
                        inputEl.value = val;
                        saveDB();
                        renderTicket();
                    }

                    function changeTakeawayQty(index, delta) {
                        if (!currentTable.items || !currentTable.items[index]) return;
                        const item = currentTable.items[index];
                        const maxQty = parseFloat(item.qty) || 0;
                        let v = (parseFloat(item.takeawayQty) || 0) + delta;
                        v = Math.max(0, Math.min(maxQty, v));
                        item.takeawayQty = v;
                        saveDB();
                        renderTicket();
                    }

                    function toggleTakeawayInput(index) {
                        if (!currentTable.items || !currentTable.items[index]) return;
                        const item = currentTable.items[index];
                        const hasT = (parseFloat(item.takeawayQty) || 0) > 0;
                        const shown = item.takeawayOpen === true || (item.takeawayOpen === undefined && hasT);
                        item.takeawayOpen = !shown;
                        saveDB();
                        renderTicket();
                    }
                    window.setTakeawayQty = setTakeawayQty;
                    window.changeTakeawayQty = changeTakeawayQty;
                    window.toggleTakeawayInput = toggleTakeawayInput;

                    function setItemUnitPrice(index, inputEl) {
                        if (!currentTable.items || !currentTable.items[index]) return;
                        let v = parseFloat(String(inputEl.value).replace(',', '.'));
                        if (isNaN(v) || v < 0) v = 0;
                        if (v > 999999.99) v = 999999.99;
                        v = Math.round(v * 100) / 100;
                        currentTable.items[index].price = v;
                        currentTable.items[index].priceManual = true;
                        inputEl.value = v.toFixed(2);
                        saveDB();
                        renderTicket();
                    }
                    window.setItemUnitPrice = setItemUnitPrice;

                    /** Lee inputs de P. unit. visibles y aplica al modelo (evita perder el cambio si se envía sin blur). */
                    function flushCartUnitPriceInputsFromDom() {
                        if (!currentTable?.items?.length) return;
                        let changed = false;
                        document.querySelectorAll('input[data-cart-unit-price-index]').forEach((el) => {
                            const idx = parseInt(el.getAttribute('data-cart-unit-price-index'), 10);
                            if (!Number.isFinite(idx) || !currentTable.items[idx]) return;
                            let v = parseFloat(String(el.value).replace(',', '.'));
                            if (isNaN(v) || v < 0) v = 0;
                            if (v > 999999.99) v = 999999.99;
                            v = Math.round(v * 100) / 100;
                            const prev = parseFloat(currentTable.items[idx].price);
                            if (!Number.isFinite(prev) || Math.abs(prev - v) > 0.0001) {
                                currentTable.items[idx].price = v;
                                currentTable.items[idx].priceManual = true;
                                changed = true;
                            }
                            el.value = v.toFixed(2);
                        });
                        if (changed) saveDB();
                    }

                    function renderTicket() {
                        const container = document.getElementById('cart-container');
                        if (!container) {
                            return;
                        }
                        container.innerHTML = '';
                        let subtotal = 0;

                        if (!currentTable.items || currentTable.items.length === 0) {
                            container.innerHTML =
                                `
                                                                                                                                                                                                <div class="flex flex-col items-center justify-center py-10 text-gray-400 opacity-60">
                                                                                                                                                                                                    <i class="ri-restaurant-line text-4xl mb-3"></i>
                                                                                                                                                                                                    <p class="font-medium text-sm">Sin productos en el pedido</p>
                                                                                                                                                                                                </div>`;
                        } else {
                            // Badge de tipo de servicio + Toggle para "Para Llevar"
                            const serviceHeader = document.createElement('div');
                            const st = currentTable.service_type || 'IN_SITU';
                            const isDelivery = st === 'DELIVERY';
                            const isTakeAway = st === 'TAKE_AWAY';

                            let serviceLabel = '🏠 En Mesa';
                            let serviceColor = 'bg-[#FF4622]/5 text-[#FF4622] border-[#FF4622]/20';
                            if (isTakeAway) {
                                serviceLabel = '🥡 Para Llevar';
                                serviceColor = 'bg-orange-100 text-orange-700 border-orange-200';
                            } else if (isDelivery) {
                                serviceLabel = '🛵 Delivery';
                                serviceColor = 'bg-purple-100 text-purple-700 border-purple-200';
                            }

                            serviceHeader.className =
                                `flex items-center justify-between px-3 py-2.5 mb-4 rounded-xl border ${serviceColor} shadow-sm`;
                            serviceHeader.innerHTML =
                                `
                                                                                                                                                                                                    <div class="flex flex-col">
                                                                                                                                                                                                        <span class="font-bold text-xs uppercase tracking-wider">${serviceLabel}</span>
                                                                                                                                                                                                        ${isTakeAway && currentTable.client_name_extra ? `<span class="text-[10px] font-normal lowercase italic text-orange-600">${escapeHtml(currentTable.client_name_extra)}</span>` : ''}
                                                                                                                                                                                                    </div>


                                                                                                                                                                                                `;
                            // Estado de servicio oculto por solicitud del usuario.

                            const isComandado = !!currentTable.order_movement_id;
                            currentTable.items.forEach((item, index) => {
                                const prod = serverProducts.find(p => p.id === item.pId);
                                if (!prod) return;
                                const itemPrice = parseFloat(item.price) || 0;
                                const itemQty = parseInt(item.qty) || 0;
                                const courtesyQty = Math.min(parseFloat(item.courtesyQty) || 0, itemQty);
                                const paidQty = Math.max(itemQty - courtesyQty, 0);
                                const lineBase = itemPrice * paidQty;
                                const lineTotal = lineBase;
                                // si quieres que el subtotal ignore las cortesías:
                                subtotal += lineBase;
                                const noteTime = item.commandTime || "";
                                const noteText = typeof item.note === "string" ? item.note.trim() : "";
                                const hasNote = noteText !== "";
                                const complementsLabel = formatComplementsLabel(item.complements);
                                const complementSummary = complementsLabel ?
                                    `<div class="mt-1 flex flex-wrap items-center gap-1.5"><span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"><i class="ri-checkbox-circle-line"></i> ${escapeHtml(complementsLabel)}</span></div>` :
                                    '';
                                const row = document.createElement('div');
                                row.className =
                                    "cart-item-row group relative mb-3 rounded-xl overflow-hidden border border-slate-200 bg-white text-slate-900 shadow-md dark:border-zinc-600/50 dark:bg-[#252526] dark:text-zinc-100 dark:shadow-lg dark:shadow-black/40";

                                const productName = escapeHtml(prod.name || 'Sin nombre');
                                const itemNote = escapeHtml(noteText || '');
                                const isDelivered = !!item.delivered;

                                const rawSaved = item.savedQty != null && item.savedQty !== '' ? parseFloat(item
                                    .savedQty) : NaN;
                                const savedQtyItem = Number.isFinite(rawSaved) ? rawSaved : 0;
                                const itemIsComandado = lineLooksComandado(item);
                                const commandedQty = Math.max(0, Math.min(itemQty, savedQtyItem > 0 ? savedQtyItem : (
                                    itemIsComandado ? itemQty : 0)));
                                const pendingQty = Math.max(0, itemQty - commandedQty);
                                const canReduce = !itemIsComandado || (parseFloat(item.qty) || 0) > savedQtyItem;

                                const statusLabel = isDelivered ? 'Entregado' : (itemIsComandado ? (pendingQty > 0 ?
                                    `Parcial ${commandedQty}/${itemQty}` : 'Comandado') : 'Pendiente');
                                const statusClass = isDelivered ?
                                    'bg-emerald-500/20 text-emerald-600 border border-emerald-500/35 dark:text-emerald-400 dark:border-emerald-500/40' :
                                    (itemIsComandado ? (pendingQty > 0 ?
                                            'bg-amber-500/15 text-amber-700 border border-amber-500/35 dark:text-amber-300 dark:border-amber-500/40' :
                                            'bg-[#FF4622]/15 text-[#C43B25] border border-[#FF4622]/35 dark:text-[#FF4622] dark:border-[#FF4622]/40'
                                            ) :
                                        'bg-zinc-200/90 text-zinc-600 border border-zinc-300 dark:bg-zinc-700/60 dark:text-zinc-300 dark:border-zinc-600'
                                        );
                                const commandSummary = itemIsComandado ?
                                    `<div class="mt-1 flex flex-wrap items-center gap-1.5">
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-[#FF4622]/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#C43B25] dark:bg-[#FF4622]/15 dark:text-[#FF4622]">
                                                        <i class="ri-printer-line"></i> Comandado x${commandedQty}
                                                    </span>
                                                    ${pendingQty > 0 ? `<span class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700 dark:bg-amber-500/15 dark:text-amber-300"><i class="ri-time-line"></i> Nuevo x${pendingQty}</span>` : ''}
                                                </div>` :
                                    '';

                                const qtyMinusDisabled = canReduce ? '' : ' disabled';
                                const qtyMinusClass = canReduce ?
                                    ' hover:bg-slate-100 dark:hover:bg-slate-700 font-bold' :
                                    ' opacity-30 cursor-not-allowed';
                                const qtyMinusOnclick = canReduce ? `onclick="updateQty(${index}, -1)"` : '';
                                const canRemoveLine = !isMozoProfile || !itemIsComandado;
                                const trashOnclick = canRemoveLine ?
                                    `onclick="openRemoveQuantityLineModal(${index})"` :
                                    '';
                                const trashHiddenMozo = canRemoveLine ? '' : ' hidden';
                                const hasCourtesy = (parseFloat(item.courtesyQty) || 0) > 0;
                                const showNoteBox = item.noteOpen === true || (item.noteOpen === undefined && hasNote);
                                const showCourtesyBox = item.courtesyOpen === true || (item.courtesyOpen ===
                                    undefined && hasCourtesy);
                                const noteBtnActive = hasNote || showNoteBox;
                                const courtesyBtnActive = hasCourtesy || showCourtesyBox;
                                const courtesyMinusDisabled = courtesyQty <= 0 ? ' disabled' : '';
                                const courtesyMinusClass = courtesyQty <= 0 ? ' opacity-30 cursor-not-allowed' :
                                    ' hover:bg-slate-100 dark:hover:bg-zinc-700 font-bold';
                                const courtesyMinusOnclick = courtesyQty > 0 ?
                                    `onclick="changeCourtesyQty(${index}, -1)"` : '';
                                const courtesyPlusDisabled = courtesyQty >= itemQty ? ' disabled' : '';
                                const courtesyPlusClass = courtesyQty >= itemQty ? ' opacity-30 cursor-not-allowed' :
                                    ' hover:bg-slate-100 dark:hover:bg-zinc-700 font-bold';
                                const courtesyPlusOnclick = courtesyQty < itemQty ?
                                    `onclick="changeCourtesyQty(${index}, 1)"` : '';

                                const takeawayQty = Math.min(Math.max(parseFloat(item.takeawayQty) || 0, 0), itemQty);
                                const hasTakeaway = takeawayQty > 0;
                                const showTakeawayBox = item.takeawayOpen === true || (item.takeawayOpen ===
                                    undefined && hasTakeaway);
                                const takeawayBtnActive = hasTakeaway || showTakeawayBox;
                                const takeawayMinusDisabled = !isDelivery && takeawayQty <= 0 ? ' disabled' : '';
                                const takeawayMinusClass = !isDelivery && takeawayQty <= 0 ?
                                    ' opacity-30 cursor-not-allowed' :
                                    ' hover:bg-slate-100 dark:hover:bg-zinc-700 font-bold';
                                const takeawayMinusOnclick = !isDelivery && takeawayQty > 0 ?
                                    `onclick="changeTakeawayQty(${index}, -1)"` : '';
                                const takeawayPlusDisabled = !isDelivery && takeawayQty >= itemQty ? ' disabled' : '';
                                const takeawayPlusClass = !isDelivery && takeawayQty >= itemQty ?
                                    ' opacity-30 cursor-not-allowed' :
                                    ' hover:bg-slate-100 dark:hover:bg-zinc-700 font-bold';
                                const takeawayPlusOnclick = !isDelivery && takeawayQty < itemQty ?
                                    `onclick="changeTakeawayQty(${index}, 1)"` : '';
                                const takeawayBadge = !isDelivery && hasTakeaway ?
                                    `<span class="mt-1 inline-flex items-center gap-1 rounded-full bg-orange-500/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-orange-700 dark:text-orange-300"><i class="ri-shopping-bag-3-line"></i> ${takeawayQty} p. llevar</span>` :
                                    '';

                                // Badge de advertencia de stock de ingredientes (receta)
                                const recipeMaxCheck = getRecipeAvailability(item.pId);
                                let stockWarningBadge = '';
                                if (recipeMaxCheck !== null) {
                                    const remaining = Math.max(0, parseInt(recipeMaxCheck.max_additional, 10) || 0);
                                    if (remaining <= 0) {
                                        stockWarningBadge =
                                            `<span class="mt-1 inline-flex items-center gap-1 rounded-full bg-red-500/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-red-700 dark:bg-red-500/15 dark:text-red-400"><i class="ri-error-warning-line"></i> Stock de insumos agotado</span>`;
                                    } else if (remaining <= 3) {
                                        stockWarningBadge =
                                            `<span class="mt-1 inline-flex items-center gap-1 rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700 dark:bg-amber-500/15 dark:text-amber-300"><i class="ri-alert-line"></i> Puedes agregar ${remaining} unidad(es) más</span>`;
                                    }
                                }

                                row.innerHTML =
                                    `
                                                                                                                                                                                                    <div class="flex flex-col gap-3 p-3.5 sm:p-4">
                                                                                                                                                                                                            <div class="flex items-start justify-between gap-2">
                                                                                                                                                                                                                <div class="min-w-0 flex-1">
                                                                                                                                                                                                                    <h3 class="font-bold text-[15px] sm:text-base leading-snug tracking-tight text-slate-900 dark:text-white">${productName}</h3>
                                                                                                                                                                                                                    ${stockWarningBadge}
                                                                                                                                                                                                                    ${takeawayBadge}
                                                                                                                                                                                                                    ${complementSummary}
                                                                                                                                                                                                                    ${commandSummary}
                                                                                                                                                                                                                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] sm:text-xs">
                                                                                                                                                                                                                        ${noteTime ? `<span class="text-slate-500 dark:text-zinc-400 font-medium tabular-nums">${noteTime}</span>` : ''}
                                                                                                                                                                                                                        <label class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-1.5 py-0.5 dark:border-zinc-600 dark:bg-zinc-800/80">
                                                                                                                                                                                                                            <span class="text-slate-500 dark:text-zinc-500 font-medium">P. unit.</span>
                                                                                                                                                                                                                            <span class="text-slate-600 dark:text-zinc-400 font-semibold">S/</span>
                                                                                                                                                                                                                            <input type="number" inputmode="decimal" step="0.01" min="0" value="${itemPrice.toFixed(2)}"
                                                                                                                                                                                                                                data-cart-unit-price-index="${index}"
                                                                                                                                                                                                                                onchange="setItemUnitPrice(${index}, this)"
                                                                                                                                                                                                                                onblur="setItemUnitPrice(${index}, this)"
                                                                                                                                                                                                                                class="w-[4.75rem] min-w-0 bg-transparent text-right text-xs font-bold tabular-nums text-slate-900 dark:text-white border-none p-0 focus:ring-0 focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                                                                                                                                                                                                                        </label>
                                                                                                                                                                                                                    </div>
                                                                                                                                                                                                                </div>
                                                                                                                                                                                                                <span class="shrink-0 px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-wide ${statusClass}">${statusLabel}</span>
                                                                                                                                                                                                            </div>

                                                                                                                                                                                                            <div class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 border border-slate-200 px-2.5 py-2.5 dark:bg-zinc-900/50 dark:border-zinc-600/50">
                                                                                                                                                                                                                <div class="flex justify-center items-center gap-0.5 rounded-lg bg-white px-0.5 py-0.5 border border-slate-200 dark:bg-zinc-800/80 dark:border-zinc-600/60">
                                                                                                                                                                                                                    <button type="button" ${qtyMinusOnclick} class="w-9 h-9 flex items-center justify-center rounded-md transition-all text-slate-600 dark:text-zinc-300 ${qtyMinusClass}"${qtyMinusDisabled}>
                                                                                                                                                                                                                        <i class="ri-subtract-line text-base"></i>
                                                                                                                                                                                                                    </button>
                                                                                                                                                                                                                    <input type="number" value="${item.qty}" min="1" onchange="setQtyFromInput(${index}, this)" class="w-11 h-9 text-center text-sm font-bold bg-transparent border-none focus:ring-0 focus:outline-none tabular-nums text-slate-900 dark:text-white p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" ${canReduce ? '' : 'readonly'}>
                                                                                                                                                                                                                    <button type="button" onclick="updateQty(${index}, 1)" class="w-9 h-9 flex items-center justify-center rounded-md hover:bg-slate-100 dark:hover:bg-zinc-700 text-slate-600 dark:text-zinc-300 transition-all font-bold">
                                                                                                                                                                                                                        <i class="ri-add-line text-base"></i>
                                                                                                                                                                                                                    </button>
                                                                                                                                                                                                                </div>
                                                                                                                                                                                                                <div class="text-right flex flex-col justify-center shrink-0">
                                                                                                                                                                                                                    <span class="text-[10px] text-slate-500 dark:text-zinc-500 uppercase font-bold tracking-wider leading-none mb-0.5">Subtotal</span>
                                                                                                                                                                                                                    <span class="text-lg font-bold tabular-nums leading-none text-slate-900 dark:text-white">S/ ${lineTotal.toFixed(2)}</span>
                                                                                                                                                                                                                </div>
                                                                                                                                                                                                            </div>

                                                                                                                                                                                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5 border-t border-slate-200 pt-2.5 dark:border-zinc-700/60">
                                                                                                                                                                                                                <button type="button" onclick="toggleDelivered(${index})" class="inline-flex shrink-0 items-center gap-1.5 text-sm font-medium text-[#FF4622] hover:text-[#C43B25] dark:text-[#FF4622] dark:hover:text-[#FF4622]/80 transition-colors">
                                                                                                                                                                                                                    <i class="${isDelivered ? 'ri-check-double-line' : 'ri-checkbox-blank-circle-line'}"></i>
                                                                                                                                                                                                                    ${isDelivered ? 'Entregado' : 'Pendiente'}
                                                                                                                                                                                                                </button>
                                                                                                                                                                                                                <button type="button" onclick="toggleNoteInput(${index})" class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium transition-colors ${noteBtnActive ? 'bg-[#FF4622]/10 text-[#C43B25] dark:bg-[#FF4622]/15 dark:text-[#FF4622]' : 'text-slate-500 hover:bg-slate-100 hover:text-[#FF4622] dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-[#FF4622]'}">
                                                                                                                                                                                                                    <i class="${hasNote ? 'ri-chat-1-fill' : 'ri-chat-1-line'}"></i> ${hasNote ? 'Editar nota' : 'Nota'}
                                                                                                                                                                                                                </button>
                                                                                                                                                                                                                <button type="button" onclick="toggleCourtesyInput(${index})" class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium transition-colors ${courtesyBtnActive ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' : 'text-slate-500 hover:bg-slate-100 hover:text-emerald-600 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-emerald-400'}">
                                                                                                                                                                                                                    <i class="${courtesyBtnActive ? 'ri-star-fill' : 'ri-star-line'}"></i> Cortesía
                                                                                                                                                                                                                </button>
                                                                                                                                                                                                                <button type="button" ${trashOnclick} class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-zinc-400 transition-colors hover:bg-red-500/10 hover:text-red-500 dark:text-zinc-500 dark:hover:text-red-400${trashHiddenMozo}" title="Quitar o anular cantidad">
                                                                                                                                                                                                                    <i class="ri-delete-bin-line text-lg"></i>
                                                                                                                                                                                                                </button>
                                                                                                                                                                                                            </div>

                                                                                                                                                                                                            <div id="note-box-${index}" class="${showNoteBox ? '' : 'hidden'}">
                                                                                                                                                                                                                <textarea rows="2" onblur="saveNote(${index}, this.value)" placeholder="Ej: Sin cebolla, término medio..." class="w-full min-h-[3.25rem] resize-y rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 placeholder:text-slate-400 focus:border-[#FF4622] focus:outline-none focus:ring-2 focus:ring-[#FF4622]/20 dark:border-zinc-600 dark:bg-zinc-900/80 dark:text-zinc-100 dark:placeholder:text-zinc-500 dark:focus:border-[#FF4622]">${itemNote}</textarea>
                                                                                                                                                                                                            </div>

                                                                                                                                                                                                            <div id="courtesy-box-${index}" class="${showCourtesyBox ? '' : 'hidden'}">
                                                                                                                                                                                                                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 dark:border-zinc-600/50 dark:bg-zinc-900/40">
                                                                                                                                                                                                                    <span class="text-[11px] font-semibold text-slate-700 dark:text-zinc-300">Cortesía <span class="font-normal text-slate-500 dark:text-zinc-500">(sin cargo)</span></span>
                                                                                                                                                                                                                    <div class="flex items-center gap-0.5 rounded-lg bg-white px-0.5 py-0.5 border border-slate-200 dark:bg-zinc-800/90 dark:border-zinc-600/60">
                                                                                                                                                                                                                        <button type="button" ${courtesyMinusOnclick} class="w-8 h-8 flex items-center justify-center rounded-md transition-all text-slate-700 dark:text-zinc-200${courtesyMinusClass}"${courtesyMinusDisabled}>
                                                                                                                                                                                                                            <i class="ri-subtract-line text-sm"></i>
                                                                                                                                                                                                                        </button>
                                                                                                                                                                                                                        <input type="number" min="0" max="${itemQty}" value="${courtesyQty}" onchange="setCourtesyQty(${index}, this)" class="w-10 h-8 text-center text-sm font-bold bg-transparent border-none focus:ring-0 focus:outline-none tabular-nums text-slate-900 dark:text-white p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                                                                                                                                                                                                        <button type="button" ${courtesyPlusOnclick} class="w-8 h-8 flex items-center justify-center rounded-md transition-all text-slate-700 dark:text-zinc-200${courtesyPlusClass}"${courtesyPlusDisabled}>
                                                                                                                                                                                                                            <i class="ri-add-line text-sm"></i>
                                                                                                                                                                                                                        </button>
                                                                                                                                                                                                                    </div>
                                                                                                                                                                                                                </div>
                                                                                                                                                                                                            </div>

                                                                                                                                                                                                            ${isDelivery ? '' : `
                                                                                                                                                                                                                        <div id="takeaway-box-${index}" class="${showTakeawayBox ? '' : 'hidden'}">
                                                                                                                                                                                                                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-orange-200 bg-orange-50/80 px-3 py-2.5 dark:border-orange-700/50 dark:bg-orange-950/30">
                                                                                                                                                                                                                                <span class="text-[11px] font-semibold text-slate-800 dark:text-zinc-200">Para llevar <span class="font-normal text-slate-600 dark:text-zinc-300">(de ${itemQty} u.)</span></span>
                                                                                                                                                                                                                                <div class="flex items-center gap-0.5 rounded-lg bg-white px-0.5 py-0.5 border border-orange-200 dark:bg-zinc-800/90 dark:border-orange-700/50">
                                                                                                                                                                                                                                    <button type="button" ${takeawayMinusOnclick} class="w-8 h-8 flex items-center justify-center rounded-md transition-all text-slate-700 dark:text-zinc-200${takeawayMinusClass}"${takeawayMinusDisabled}>
                                                                                                                                                                                                                                        <i class="ri-subtract-line text-sm"></i>
                                                                                                                                                                                                                                    </button>
                                                                                                                                                                                                                                    <input type="number" min="0" max="${itemQty}" value="${takeawayQty}" onchange="setTakeawayQty(${index}, this)" class="w-10 h-8 text-center text-sm font-bold bg-transparent border-none focus:ring-0 focus:outline-none tabular-nums text-slate-900 dark:text-white p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                                                                                                                                                                                                                    <button type="button" ${takeawayPlusOnclick} class="w-8 h-8 flex items-center justify-center rounded-md transition-all text-slate-700 dark:text-zinc-200${takeawayPlusClass}"${takeawayPlusDisabled}>
                                                                                                                                                                                                                                        <i class="ri-add-line text-sm"></i>
                                                                                                                                                                                                                                    </button>
                                                                                                                                                                                                                                </div>
                                                                                                                                                                                                                            </div>
                                                                                                                                                                                                                        </div>`}
                                                                                                                                                                                                    </div>
                                                                                                                                                                                                `;
                                container.appendChild(row);
                            });
                        }
                        const totals = getTicketTotalsConsideringSplit();
                        const tax = totals.tax;
                        const total = totals.total;
                        subtotal = totals.subtotal;
                        const deliveryFee = totals.deliveryFee;
                        const takeawayDispFee = totals.takeawayDisposableFee ?? 0;

                        const subtotalEl = document.getElementById('ticket-subtotal');
                        const taxEl = document.getElementById('ticket-tax');
                        const totalEl = document.getElementById('ticket-total');
                        const deliveryRow = document.getElementById('ticket-delivery-row');
                        const deliveryEl = document.getElementById('ticket-delivery');
                        const takeawayDispRow = document.getElementById('ticket-takeaway-disposable-row');
                        const takeawayDispEl = document.getElementById('ticket-takeaway-disposable');
                        const ticketTotLabel = document.getElementById('ticket-total-label');
                        const splitRemHint = document.getElementById('ticket-split-remaining-hint');

                        if (subtotalEl) subtotalEl.innerText = `S/ ${subtotal.toFixed(2)}`;
                        if (taxEl) taxEl.innerText = `S/ ${tax.toFixed(2)}`;
                        if (deliveryRow && deliveryEl) {
                            if (currentTable?.service_type === 'DELIVERY' && deliveryFee > 0) {
                                deliveryRow.classList.remove('hidden');
                                deliveryEl.innerText = `S/ ${deliveryFee.toFixed(2)}`;
                            } else {
                                deliveryRow.classList.add('hidden');
                            }
                        }
                        if (takeawayDispRow && takeawayDispEl) {
                            if (takeawayDispFee > 0) {
                                takeawayDispRow.classList.remove('hidden');
                                takeawayDispEl.innerText = `S/ ${takeawayDispFee.toFixed(2)}`;
                            } else {
                                takeawayDispRow.classList.add('hidden');
                            }
                        }
                        if (totalEl) totalEl.innerText = `S/ ${total.toFixed(2)}`;
                        if (ticketTotLabel) {
                            ticketTotLabel.textContent = totals.showSplitHint ? 'Saldo pendiente' : 'Total a Pagar';
                        }
                        if (splitRemHint) {
                            if (totals.showSplitHint && totals.splitPart > 0) {
                                splitRemHint.classList.remove('hidden');
                                splitRemHint.textContent = 'Parte a cobrar en este movimiento: S/ ' + totals.splitPart.toFixed(
                                    2) + '. El importe en rojo es el saldo que quedará en el pedido.';
                            } else {
                                splitRemHint.classList.add('hidden');
                                splitRemHint.textContent = '';
                            }
                        }

                        syncTicketCreditSummary();
                        refreshRecipeStockLabelsInProductGrid();
                        syncTakeawayDisposablePanel();
                        syncCobroAmountsWithCart(total);
                        renderCancelledSection();
                        syncCobroTabState();
                        if (typeof updateMobileSummary === 'function') updateMobileSummary();
                    }

                    function syncCobroAmountsWithCart(orderTotal) {
                        const list = document.getElementById('cobro-payment-methods-list');
                        if (!list) return;
                        const cb = document.getElementById('split-dividir-cuenta');
                        if (cb && cb.checked && window.__splitAccount && window.__splitAccount.enabled) {
                            syncCobroPaymentAmountsToSplitPartInner();
                            if (typeof updateCobroTotalPaid === 'function') updateCobroTotalPaid();
                            return;
                        }
                        const rows = list.querySelectorAll('.cobro-pm-row');
                        if (rows.length === 0) return;
                        if (rows.length === 1) {
                            const input = rows[0].querySelector('.cobro-pm-amount');
                            if (input) input.value = orderTotal.toFixed(2);
                        } else {
                            const totalPaid = Array.from(rows).slice(1).reduce((s, row) => {
                                const inp = row.querySelector('.cobro-pm-amount');
                                return s + (parseFloat(inp?.value || 0) || 0);
                            }, 0);
                            const firstInput = rows[0].querySelector('.cobro-pm-amount');
                            if (firstInput) firstInput.value = Math.max(0, orderTotal - totalPaid).toFixed(2);
                        }
                        if (typeof updateCobroTotalPaid === 'function') updateCobroTotalPaid();
                    }

                    function renderCancelledSection() {
                        const container = document.getElementById('cancelled-platos-container');
                        const listEl = document.getElementById('cancelled-platos-list');
                        if (!container || !listEl) return;

                        const hasActiveServerOrder = !!serverOrderMovementId && !window.tableIsFree;
                        const hasSavedOrder = hasActiveServerOrder && !!currentTable.order_movement_id;
                        const isCurrentPendingOrder = hasSavedOrder && (currentTable.order_movement_id ===
                            serverOrderMovementId);
                        const serverCancelled = (isCurrentPendingOrder && serverPendingCancelledDetails &&
                            serverPendingCancelledDetails.length) ? serverPendingCancelledDetails : [];
                        const clientCancelled = isCurrentPendingOrder ? (currentTable.cancellations || []) : [];
                        const hasAny = serverCancelled.length > 0 || clientCancelled.length > 0;

                        if (!hasSavedOrder || !hasAny) {
                            container.classList.add('hidden');
                            listEl.innerHTML = '';
                            return;
                        }

                        container.classList.remove('hidden');
                        let html = '';
                        serverCancelled.forEach(function(d) {
                            const desc = escapeHtml(d.description || 'Producto');
                            const qty = d.quantity != null ? d.quantity : 1;
                            const reason = escapeHtml((d.comment || '').trim() || '—');
                            html +=
                                `<div class="rounded border border-amber-200 dark:border-amber-700/50 p-1.5 bg-white/60 dark:bg-gray-800/60"><span class="font-medium text-slate-700 dark:text-slate-200">${desc}</span> <span class="text-amber-700 dark:text-amber-300">×${qty}</span><br><span class="text-amber-800 dark:text-amber-200 italic">${reason}</span></div>`;
                        });
                        clientCancelled.forEach(function(c) {
                            const name = escapeHtml(c.name || 'Producto');
                            const qty = c.qtyCanceled != null ? c.qtyCanceled : 1;
                            const reason = escapeHtml((c.cancel_reason || '').trim() || '—');
                            html +=
                                `<div class="rounded border border-amber-200 dark:border-amber-700/50 p-1.5 bg-white/60 dark:bg-gray-800/60"><span class="font-medium text-slate-700 dark:text-slate-200">${name}</span> <span class="text-amber-700 dark:text-amber-300">×${qty}</span><br><span class="text-amber-800 dark:text-amber-200 italic">${reason}</span></div>`;
                        });
                        listEl.innerHTML = html;
                    }

                    /** Devuelve los ítems del pedido agrupados por producto (mismo pId → un ítem con qty sumada) para enviar al servidor. */
                    function getItemsGroupedByProduct() {
                        const items = currentTable.items || [];
                        const byPid = {};
                        items.forEach((it) => {
                            const id = parseInt(it.pId, 10) || 0;
                            if (!id) return;
                            const key = getItemGroupingKey(it);
                            const lineQty = parseFloat(it.qty) || 0;
                            const lineCourtesy = Math.max(0, Math.min(parseFloat(it.courtesyQty) || 0, lineQty));
                            const linePaid = Math.max(0, lineQty - lineCourtesy);
                            const linePrice = parseFloat(it.price) || 0;
                            if (!byPid[key]) {
                                byPid[key] = {
                                    pId: it.pId,
                                    name: it.name,
                                    price: linePrice,
                                    tax_rate: it.tax_rate,
                                    note: it.note || '',
                                    commandTime: it.commandTime || null,
                                    delivered: !!it.delivered,
                                    complements: normalizeComplements(it.complements),
                                    courtesyQty: 0,
                                    savedCourtesyQty: 0,
                                    takeawayQty: 0,
                                    savedTakeawayQty: 0,
                                    savedQty: 0,
                                    qty: 0,
                                    _priceSum: 0,
                                    _paidQtySum: 0,
                                };
                            }
                            byPid[key].qty = (byPid[key].qty || 0) + lineQty;
                            byPid[key].courtesyQty = (byPid[key].courtesyQty || 0) + lineCourtesy;
                            byPid[key].takeawayQty = (byPid[key].takeawayQty || 0) + (parseFloat(it.takeawayQty) || 0);
                            byPid[key].savedQty = (byPid[key].savedQty || 0) + (parseFloat(it.savedQty) || 0);
                            byPid[key].savedCourtesyQty = (byPid[key].savedCourtesyQty || 0) + (parseFloat(it
                                .savedCourtesyQty) || 0);
                            byPid[key].savedTakeawayQty = (byPid[key].savedTakeawayQty || 0) + (parseFloat(it
                                .savedTakeawayQty) || 0);
                            if (it.delivered) byPid[key].delivered = true;
                            byPid[key]._priceSum += linePrice * linePaid;
                            byPid[key]._paidQtySum += linePaid;
                        });
                        const st = currentTable?.service_type || 'IN_SITU';
                        const vals = Object.values(byPid);
                        vals.forEach((v) => {
                            const paidSum = parseFloat(v._paidQtySum) || 0;
                            if (paidSum > 0) {
                                v.price = Math.round((parseFloat(v._priceSum) / paidSum) * 100) / 100;
                            }
                            delete v._priceSum;
                            delete v._paidQtySum;
                            const q = parseFloat(v.qty) || 0;
                            let t = parseFloat(v.takeawayQty) || 0;
                            if (t > q) t = q;
                            if (t < 0) t = 0;
                            v.takeawayQty = st === 'DELIVERY' ? q : t;
                        });
                        return vals;
                    }

                    function getKitchenDeltaItems(items, commandTime) {
                        return (Array.isArray(items) ? items : []).map((it) => {
                            const qty = Math.max(0, parseFloat(it?.qty) || 0);
                            const savedQty = Math.max(0, parseFloat(it?.savedQty) || 0);
                            const deltaQty = Math.max(0, qty - savedQty);
                            if (deltaQty <= 0) return null;

                            const courtesyQty = Math.max(0, parseFloat(it?.courtesyQty) || 0);
                            const savedCourtesyQty = Math.max(0, parseFloat(it?.savedCourtesyQty) || 0);
                            const takeawayQty = Math.max(0, parseFloat(it?.takeawayQty) || 0);
                            const savedTakeawayQty = Math.max(0, parseFloat(it?.savedTakeawayQty) || 0);

                            return {
                                ...it,
                                qty: deltaQty,
                                courtesyQty: Math.max(0, Math.min(deltaQty, courtesyQty - savedCourtesyQty)),
                                takeawayQty: Math.max(0, Math.min(deltaQty, takeawayQty - savedTakeawayQty)),
                                commandTime: commandTime || it?.commandTime || null,
                            };
                        }).filter(Boolean);
                    }

                    function markCurrentItemsAsCommanded(commandTime) {
                        (currentTable.items || []).forEach((item) => {
                            const qty = Math.max(0, parseFloat(item?.qty) || 0);
                            item.savedQty = qty;
                            item.savedCourtesyQty = Math.max(0, Math.min(qty, parseFloat(item?.courtesyQty) || 0));
                            item.savedTakeawayQty = Math.max(0, Math.min(qty, parseFloat(item?.takeawayQty) || 0));
                            if (commandTime) item.commandTime = commandTime;
                        });
                    }

                    function saveDB() {
                        if (db && currentTable) {
                            // Agregar timestamp para saber cuándo se guardó
                            currentTable.lastUpdated = new Date().toISOString();
                            currentTable.isActive = true;
                            db[activeKey] = currentTable;
                            localStorage.setItem('restaurantDB', JSON.stringify(db));
                            const hasItems = currentTable.items && currentTable.items.length > 0;
                            const hasCancels = currentTable.cancellations && currentTable.cancellations.length > 0;
                            if (hasItems || hasCancels) {
                                // scheduleAutoSave(); // Deshabilitado auto-guardado automático al servidor
                            }
                        }
                    }

                    function focusProductSearchInput() {
                        const input = document.getElementById('search-products');
                        if (!input) return;
                        const isHidden = input.offsetParent === null;
                        if (isHidden || input.disabled || input.readOnly) return;
                        try {
                            input.focus();
                            input.select();
                        } catch (e) {}
                    }

                    function goToIndexWithTurbo() {
                        const u = new URL(afterPaymentIndexUrl, window.location.href);
                        u.searchParams.set('_', String(Date.now()));
                        const params = new URLSearchParams(window.location.search);
                        const viewId = params.get('view_id');
                        if (viewId) u.searchParams.set('view_id', viewId);
                        let url = u.toString();
                        const win = (typeof window.top !== 'undefined' ? window.top : window);
                        try {
                            win.location.replace(url);
                        } catch (e1) {
                            try {
                                win.location.href = url;
                            } catch (e2) {
                                const a = document.createElement('a');
                                a.href = url;
                                a.target = '_top';
                                document.body.appendChild(a);
                                a.click();
                                document.body.removeChild(a);
                            }
                        }
                    }

                    function isMesaYaCobradaMessage(msg) {
                        if (!msg || typeof msg !== 'string') return false;
                        const m = msg.toLowerCase();
                        return m.indexOf('ya fue cobrada') !== -1 || m.indexOf('ya fue cobrado') !== -1;
                    }

                    function isNoActiveShiftMessage(msg) {
                        if (!msg || typeof msg !== 'string') return false;
                        const m = msg.toLowerCase();
                        return m.indexOf('no hay un turno activo') !== -1 ||
                            m.indexOf('apertura de caja') !== -1 ||
                            m.indexOf('apertura de caja primero') !== -1;
                    }

                    // Limpiar auto-guardado al navegar con Turbo para no dispararlo en otra página
                    document.addEventListener('turbo:before-visit', function() {
                        if (autoSaveTimer) {
                            clearTimeout(autoSaveTimer);
                            autoSaveTimer = null;
                        }
                    });

                    function scheduleAutoSave() {
                        if (autoSaveTimer) clearTimeout(autoSaveTimer);
                        autoSaveTimer = setTimeout(autoSaveToServer, 1500);
                    }

                    function autoSaveToServer() {
                        autoSaveTimer = null;
                        if (counterPosMode) {
                            return;
                        }
                        const items = currentTable.items || [];
                        if (items.length === 0 && (!currentTable.cancellations || currentTable.cancellations.length === 0))
                            return;
                        // No auto-guardar si hay cancelaciones pendientes de razón (se pide al hacer Guardar / Cobrar)
                        const cancels = currentTable.cancellations || [];
                        if (cancels.some(c => !(c.cancel_reason && String(c.cancel_reason).trim()))) return;
                        flushCartUnitPriceInputsFromDom();
                        renderTicket();
                        const itemsToSend = getItemsGroupedByProduct();
                        const totals = calculateTotalsFromItems(itemsToSend);
                        const order = {
                            items: itemsToSend,
                            table_id: counterPosMode ? null : (currentTable.table_id ?? currentTable.id),
                            area_id: currentTable.area_id ?? null,
                            subtotal: totals.subtotal,
                            tax: totals.tax,
                            total: totals.total,
                            people_count: currentTable.people_count ?? 0,
                            client_id: currentTable.person_id ?? null,
                            client_name: (currentTable.service_type === 'TAKE_AWAY' && currentTable.client_name_extra) ?
                                currentTable.client_name_extra : (currentTable.clientName || null),
                            contact_phone: currentTable.contact_phone ?? null,
                            delivery_address: currentTable.delivery_address ?? null,
                            delivery_time: currentTable.delivery_time ?? null,
                            delivery_amount: currentTable.delivery_amount ?? 0,
                            takeaway_disposable_charge: !!currentTable.takeaway_disposable_charge,
                            takeaway_disposable_amount: parseFloat(currentTable.takeaway_disposable_amount) || 0,
                            service_type: currentTable.service_type ?? 'IN_SITU',
                            order_movement_id: currentTable.order_movement_id ?? null,
                            cancellations: currentTable.cancellations || [],
                        };
                        fetch('{{ route('orders.process') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute(
                                        'content') || '',
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify(order)
                            })
                            .then(res => res.headers.get('content-type')?.includes('application/json') ? res.json() : Promise
                                .reject(new Error('Respuesta inválida')))
                            .then(data => {
                                if (data && data.success) {
                                    currentTable.cancellations = [];
                                    saveDB();
                                } else if (data && isMesaYaCobradaMessage(data.message)) {
                                    if (typeof showNotification === 'function') {
                                        showNotification('Aviso', data.message || 'Esta mesa ya fue cobrada.', 'info');
                                    }
                                }
                            })
                            .catch(() => {});
                    }

                    /** Pide una sola razón de anulación y la asigna a todas las cancelaciones que no la tengan. Devuelve false si el usuario cancela. */
                    async function ensureCancellationReasons() {
                        const cancels = currentTable.cancellations || [];
                        const pending = cancels.filter(c => !(c.cancel_reason && String(c.cancel_reason).trim()));
                        if (pending.length === 0) return true;

                        let reason = null;
                        if (window.Swal) {
                            const result = await Swal.fire({
                                title: 'Razón de anulación',
                                html: `Hay <strong>${pending.length}</strong> ítem(s) anulados. Indica la razón (aplica a todos).`,
                                input: 'textarea',
                                inputPlaceholder: 'Escribe la razón de la anulación...',
                                showCancelButton: true,
                                confirmButtonText: 'Continuar',
                                cancelButtonText: 'Cancelar',
                                inputValidator: (value) => {
                                    if (!value || !value.trim()) return 'Debes ingresar una razón';
                                    return null;
                                }
                            });
                            if (!result.isConfirmed || !result.value) return false;
                            reason = result.value.trim();
                        } else {
                            const p = window.prompt('Razón de anulación (aplica a todos los ítems anulados):');
                            if (!p || !p.trim()) return false;
                            reason = p.trim();
                        }

                        cancels.forEach(c => {
                            if (!(c.cancel_reason && String(c.cancel_reason).trim())) c.cancel_reason = reason;
                        });
                        return true;
                    }

                    function resetBtnGuardarLabel() {
                        const btnGuardar = document.getElementById('btn-guardar');
                        if (!btnGuardar) {
                            return;
                        }
                        if (counterPosMode) {
                            btnGuardar.innerHTML = '<i class="ri-save-line text-base"></i><span>Guardar</span>';
                        } else {
                            btnGuardar.innerHTML = '<i class="ri-send-plane-2-line text-base"></i><span>Enviar</span>';
                        }
                    }

                    async function processCounterSaveDraft() {
                        flushCartUnitPriceInputsFromDom();
                        const items = getItemsGroupedByProduct();
                        if (!items.length) {
                            if (typeof showNotification === 'function') {
                                showNotification('Nueva venta', 'Agrega al menos un producto.', 'warning');
                            } else {
                                alert('Agrega al menos un producto.');
                            }
                            return;
                        }
                        const payloadItems = items.filter((it) => (parseFloat(it.qty) || 0) > 0).map((it) => {
                            const q = parseFloat(it.qty) || 0;
                            let cq = Math.max(0, parseFloat(it.courtesyQty) || 0);
                            if (cq > q) {
                                cq = q;
                            }
                            const n = (it.note != null && String(it.note).trim() !== '') ? String(it.note).trim() :
                                null;
                            return {
                                pId: parseInt(it.pId, 10),
                                qty: q,
                                price: parseFloat(it.price) || 0,
                                courtesyQty: cq,
                                note: n,
                            };
                        });
                        if (!payloadItems.length) {
                            if (typeof showNotification === 'function') {
                                showNotification('Nueva venta', 'No hay líneas válidas para guardar.', 'warning');
                            } else {
                                alert('No hay líneas válidas para guardar.');
                            }
                            return;
                        }
                        const docTypeEl = document.getElementById('cobro-document-type');
                        const body = {
                            items: payloadItems
                        };
                        if (docTypeEl && docTypeEl.value) {
                            const dti = parseInt(docTypeEl.value, 10);
                            if (Number.isFinite(dti) && dti > 0) {
                                body.document_type_id = dti;
                            }
                        }
                        const btnGuardar = document.getElementById('btn-guardar');
                        if (btnGuardar) {
                            btnGuardar.disabled = true;
                            btnGuardar.innerHTML =
                                '<i class="ri-loader-4-line text-base animate-spin"></i><span>Guardando...</span>';
                        }
                        try {
                            const response = await fetch(salesDraftUrl, {
                                method: 'POST',
                                cache: 'no-store',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute(
                                        'content') || '',
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify(body),
                            });
                            const data = response.headers.get('content-type')?.includes('application/json') ?
                                await response.json() :
                                null;
                            if (response.status === 419) {
                                if (typeof showNotification === 'function') {
                                    showNotification('Sesión', 'Sesión expirada. Recarga la página.', 'error');
                                } else {
                                    alert('Sesión expirada. Recarga la página.');
                                }
                                return;
                            }
                            if (!response.ok || !data || !data.success) {
                                const msg = (data && data.message) ||
                                    (data && data.errors && JSON.stringify(data.errors)) ||
                                    'No se pudo guardar el borrador.';
                                if (typeof showNotification === 'function') {
                                    showNotification('Nueva venta', msg, 'error');
                                } else {
                                    alert(msg);
                                }
                                return;
                            }
                            const mid = data.data && data.data.movement_id != null ?
                                parseInt(String(data.data.movement_id), 10) :
                                null;
                            if (!mid) {
                                if (typeof showNotification === 'function') {
                                    showNotification('Nueva venta', 'El servidor no devolvió el movimiento de venta.',
                                        'error');
                                } else {
                                    alert('El servidor no devolvió el movimiento de venta.');
                                }
                                return;
                            }
                            if (db && activeKey) {
                                if (db[activeKey]) {
                                    delete db[activeKey];
                                }
                                localStorage.setItem('restaurantDB', JSON.stringify(db));
                            }
                            const u = new URL(salesChargeUrl, window.location.href);
                            u.searchParams.set('movement_id', String(mid));
                            if (salesViewIdParam) {
                                u.searchParams.set('view_id', String(salesViewIdParam));
                            }
                            window.location.href = u.toString();
                        } catch (e) {
                            console.error('processCounterSaveDraft', e);
                            if (typeof showNotification === 'function') {
                                showNotification('Nueva venta', 'Error de red al guardar la venta.', 'error');
                            } else {
                                alert('Error de red al guardar la venta.');
                            }
                        } finally {
                            if (btnGuardar) {
                                btnGuardar.disabled = false;
                            }
                            resetBtnGuardarLabel();
                        }
                    }

                    async function processOrder() {
                        if (window.__processOrderInFlight) {
                            if (typeof showNotification === 'function') {
                                showNotification('Pedido', 'Ya se está procesando el envío. Espera un momento.', 'info');
                            }
                            return;
                        }
                        window.__processOrderInFlight = true;
                        const releaseProcessOrder = () => {
                            window.__processOrderInFlight = false;
                        };
                        if (waiterPinEnabled && !isMozoProfile) {
                            const ok = await ensureWaiterPin();
                            if (!ok) {
                                releaseProcessOrder();
                                return;
                            }
                        }
                        const okReason = await ensureCancellationReasons();
                        if (!okReason) {
                            releaseProcessOrder();
                            return;
                        }
                        if (counterPosMode) {
                            if (typeof switchAsideTab === 'function') {
                                switchAsideTab('cobro');
                            }
                            releaseProcessOrder();
                            return;
                        }
                        flushCartUnitPriceInputsFromDom();
                        renderTicket();
                        const btnGuardar = document.getElementById('btn-guardar');
                        if (btnGuardar) {
                            btnGuardar.disabled = true;
                            btnGuardar.innerHTML =
                                '<i class="ri-loader-4-line text-base animate-spin"></i><span>Procesando...</span>';
                        }
                        let items = getItemsGroupedByProduct();

                        // Hora de comanda: solo se fija la primera vez; la nota va siempre solo como texto.
                        const now = new Date();
                        const timeString = now.toLocaleTimeString([], {
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: false
                        });
                        items.forEach((it) => {
                            if (!it) return;
                            if (!it.commandTime) it.commandTime = timeString;
                        });
                        const kitchenDeltaItems = getKitchenDeltaItems(items, timeString);

                        const totals = calculateTotalsFromItems(items);
                        const subtotal = totals.subtotal;
                        const tax = totals.tax;
                        const total = totals.total;

                        const order = {
                            items: items,
                            table_id: counterPosMode ? null : (currentTable.table_id ?? currentTable.id),
                            area_id: currentTable.area_id ?? null,
                            subtotal: subtotal,
                            tax: tax,
                            total: total,
                            people_count: currentTable.people_count ?? 0,
                            waiter_id: currentTable.waiter_id ?? null,
                            client_id: currentTable.person_id ?? null,
                            client_name: (currentTable.service_type === 'TAKE_AWAY' && currentTable.client_name_extra) ?
                                currentTable.client_name_extra : (currentTable.clientName || null),
                            contact_phone: currentTable.contact_phone ?? null,
                            delivery_address: currentTable.delivery_address ?? null,
                            delivery_time: currentTable.delivery_time ?? null,
                            delivery_amount: currentTable.delivery_amount ?? 0,
                            takeaway_disposable_charge: !!currentTable.takeaway_disposable_charge,
                            takeaway_disposable_amount: parseFloat(currentTable.takeaway_disposable_amount) || 0,
                            service_type: currentTable.service_type ?? 'IN_SITU',
                            order_movement_id: currentTable.order_movement_id ?? null,
                            cancellations: currentTable.cancellations || [],
                        };
                        fetch('{{ route('orders.process') }}', {
                                method: 'POST',
                                cache: 'no-store',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute(
                                            'content') ||
                                        '',
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: JSON.stringify(order)
                            })
                            .then(async (response) => {
                                const ct = response.headers.get('content-type');
                                if (ct && ct.includes('application/json')) {
                                    return response.json();
                                }
                                throw new Error(response.status === 419 ? 'Sesión expirada. Recarga la página.' : (
                                    response.status === 401 ? 'Debes iniciar sesión.' :
                                    'Error del servidor. Intenta de nuevo.'));
                            })
                            .then(async data => {
                                if (data && data.success) {
                                    currentTable.order_movement_id = data.order_movement_id ?? currentTable
                                        .order_movement_id ?? null;
                                    currentTable.movement_id = data.movement_id ?? currentTable.movement_id ?? null;
                                    currentTable.person_id = data.client_person_id ?? currentTable.person_id ??
                                    null;
                                    currentTable.clientName = data.client_name ?? currentTable.clientName ?? '';
                                    const hasKitchenOutput = kitchenDeltaItems.length > 0 || (currentTable
                                        .cancellations || []).length > 0;
                                    let kitchenPrintedOk = true;
                                    try {
                                        if (hasKitchenOutput) {
                                            kitchenPrintedOk = await printKitchenTickets(kitchenDeltaItems,
                                                currentTable);
                                        }
                                    } catch (pzErr) {
                                        console.error('QZ Tray:', pzErr);
                                        kitchenPrintedOk = false;
                                    }
                                    markCurrentItemsAsCommanded(timeString);
                                    // Limpiar cancelaciones ya persistidas
                                    currentTable.cancellations = [];
                                    saveDB();
                                    renderTicket();
                                    syncPreAccountVisibility();
                                    syncCobroTabState();
                                    if (!kitchenPrintedOk && hasKitchenOutput && typeof showNotification ===
                                        'function') {
                                        showNotification('Pedido guardado',
                                            'El pedido se guardó, pero la comanda salió por PDF de respaldo.',
                                            'warning');
                                    }
                                    sessionStorage.setItem('flash_success_message', data.message);
                                    goToIndexWithTurbo();
                                } else if (data && isMesaYaCobradaMessage(data.message)) {
                                    if (typeof showNotification === 'function') {
                                        showNotification('Aviso', data.message || 'Esta mesa ya fue cobrada.',
                                            'info');
                                    } else {
                                        alert(data.message || 'Esta mesa ya fue cobrada.');
                                    }
                                } else if (data && data.message && String(data.message).indexOf('PIN') !== -1) {
                                    sessionStorage.removeItem(`waiterPin:${waiterPinBranchId}`);
                                    if (typeof showNotification === 'function') {
                                        showNotification('PIN requerido', data.message ||
                                            'Ingrese el PIN del mozo e intente guardar de nuevo.', 'error');
                                    } else {
                                        sessionStorage.setItem('flash_error_message', data.message ||
                                            'Ingrese el PIN del mozo e intente guardar de nuevo.');
                                    }
                                } else if (data && isNoActiveShiftMessage(data.message)) {
                                    if (typeof showNotification === 'function') {
                                        showNotification('Caja cerrada', data.message ||
                                            'No hay un turno activo. Realice una Apertura de Caja primero.',
                                            'error');
                                    } else {
                                        sessionStorage.setItem('flash_error_message', data.message ||
                                            'No hay un turno activo. Realice una Apertura de Caja primero.');
                                    }
                                } else {
                                    console.error('Error al guardar:', data);
                                    sessionStorage.setItem('flash_error_message', data?.message ||
                                        'Error al guardar.');
                                }
                                if (btnGuardar) {
                                    btnGuardar.disabled = false;
                                    btnGuardar.innerHTML =
                                        '<i class="ri-send-plane-2-line text-base"></i><span>Enviar</span>';
                                }
                                releaseProcessOrder();
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                sessionStorage.setItem('flash_error_message',
                                    'Error al guardar el pedido. Revisa la consola.');
                                if (btnGuardar) {
                                    btnGuardar.disabled = false;
                                    btnGuardar.innerHTML =
                                        '<i class="ri-send-plane-2-line text-base"></i><span>Enviar</span>';
                                }
                                releaseProcessOrder();
                            });

                    }

                    function getCobroPaymentMethodsFromForm() {
                        const list = document.getElementById('cobro-payment-methods-list');
                        if (!list) return [];
                        const result = [];
                        const rows = list.querySelectorAll('.cobro-pm-row');
                        rows.forEach(row => {
                            const methodSelect = row.querySelector('.cobro-pm-method');
                            const input = row.querySelector('.cobro-pm-amount');
                            if (!methodSelect || !input) return;
                            const pmId = parseInt(methodSelect.value, 10);
                            const amount = parseFloat(String(input.value || 0).replace(',', '.')) || 0;
                            if (!pmId || amount <= 0) return;
                            const methodName = String(methodSelect.options[methodSelect.selectedIndex]?.text || '')
                                .trim();
                            const obj = {
                                payment_method_id: pmId,
                                amount,
                                payment_method_name: methodName
                            };
                            const desc = methodName.toLowerCase();
                            const isCard = (desc.includes('tarjeta') || desc.includes('card')) && !desc.includes(
                                'billetera');
                            const isWallet = desc.includes('billetera');
                            const isTransfer = desc.includes('transferencia') || desc.includes('transfer') || desc
                                .includes('deposito') || desc.includes('depósito');
                            if (isCard) {
                                const gw = row.querySelector('.cobro-pm-gateway');
                                const card = row.querySelector('.cobro-pm-card');
                                if (gw?.value) obj.payment_gateway_id = parseInt(gw.value, 10);
                                if (card?.value) obj.card_id = parseInt(card.value, 10);
                            }
                            if (isWallet) {
                                const wallet = row.querySelector('.cobro-pm-wallet');
                                if (wallet?.value) obj.digital_wallet_id = parseInt(wallet.value, 10);
                            }
                            if (isTransfer) {
                                const bank = row.querySelector('.cobro-pm-bank');
                                if (bank?.value) obj.bank_id = parseInt(bank.value, 10);
                            }
                            result.push(obj);
                        });
                        return result;
                    }

                    function getCobroTotalPaid() {
                        const inputs = document.querySelectorAll('.cobro-pm-amount');
                        let total = 0;
                        inputs.forEach(inp => {
                            total += parseFloat(String(inp.value || 0).replace(',', '.')) || 0;
                        });
                        return total;
                    }

                    async function sendThermalTicketAfterSale(movementId, saleResponse) {
                        if (!movementId) return;
                        const qzApi = window.qz;
                        const sel = document.getElementById('cobro-thermal-printer');
                        const printerId = sel && sel.value ? parseInt(sel.value, 10) : null;
                        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                        const printerName = resolvePreAccountPrinterName();
                        const strictLocalQz = requiresStrictLocalQz(printerName);
                        const body = {
                            movement_id: movementId,
                            printer_name: printerName || null
                        };
                        if (printerId) body.printer_id = printerId;

                        let qzFailed = false;
                        if (qzApi && await ensureQzTrayConnected(qzApi, printerName)) {
                            try {
                                const tr = await fetch(salesThermalPrintUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': csrf,
                                        'Accept': 'application/json'
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({
                                        ...body,
                                        mode: 'qz'
                                    })
                                });
                                const td = tr.headers.get('content-type')?.includes('application/json') ? await tr.json() :
                                    null;
                                if (!tr.ok || !td?.success || (!td?.ticket_pdf_b64 && !td?.payload_b64)) {
                                    throw new Error(td?.message || 'No se pudo obtener el ticket del servidor.');
                                }
                                let currentPrinterName = printerName || td.printer_name || '';
                                if (!currentPrinterName) currentPrinterName = await qzApi.printers.getDefault();
                                if (!currentPrinterName) {
                                    openSaleTicketPdfTab(movementId);
                                    return;
                                }
                                const paperMm = (parseInt(td.paper_width) || 58) === 80 ? 80 : 58;
                                const sizeOpts = {
                                    units: 'mm',
                                    size: {
                                        width: paperMm,
                                        height: 200
                                    }
                                };
                                const configPdf = qzApi.configs.create(currentPrinterName, {
                                    ...sizeOpts,
                                    scaleContent: true
                                });
                                const configRaw = qzApi.configs.create(currentPrinterName, {
                                    ...sizeOpts,
                                    scaleContent: false
                                });
                                if (td.ticket_pdf_b64 && td.qz_print_format === 'pdf') {
                                    try {
                                        await qzApi.print(configPdf, [{
                                            type: 'pixel',
                                            format: 'pdf',
                                            flavor: 'base64',
                                            data: td.ticket_pdf_b64
                                        }]);
                                    } catch (pdfErr) {
                                        console.warn('QZ Tray: PDF ticket, reintento RAW', pdfErr);
                                        await qzApi.print(configRaw, [{
                                            type: 'raw',
                                            format: 'base64',
                                            data: td.payload_b64
                                        }]);
                                    }
                                } else {
                                    await qzApi.print(configRaw, [{
                                        type: 'raw',
                                        format: 'base64',
                                        data: td.payload_b64
                                    }]);
                                }
                                if (typeof showNotification === 'function')
                                    showNotification('Impresión', 'Comprobante enviado a "' + currentPrinterName + '".',
                                        'success');
                                return;
                            } catch (e) {
                                qzFailed = true;
                                console.warn('QZ Ticket:', e);
                                if (strictLocalQz) {
                                    openSaleTicketPdfTab(movementId);
                                    return;
                                }
                                openSaleTicketPdfTab(movementId);
                                return;
                            }
                        }

                        // Fallback: impresión TCP por red (requiere red local e IP en impresora)
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
                                    'Accept': 'application/json'
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify(body)
                            });
                            const td = tr.headers.get('content-type')?.includes('application/json') ? await tr.json() :
                            null;
                            if (tr.ok && td?.success) {
                                if (typeof showNotification === 'function')
                                    showNotification('Impresión', td.message || 'Comprobante enviado a la ticketera.',
                                        'success');
                            } else {
                                openSaleTicketPdfTab(movementId);
                            }
                        } catch (e) {
                            console.warn('Ticketera red:', e);
                            openSaleTicketPdfTab(movementId);
                        }
                    }

                    function toggleCobroDetailGlosa() {
                        const modeEl = document.getElementById('cobro-detail-mode');
                        const glosaWrap = document.getElementById('cobro-detail-glosa-wrapper');
                        const glosaInput = document.getElementById('cobro-detail-glosa');
                        const mode = (modeEl?.value || 'DETALLADO').toUpperCase();

                        if (glosaWrap) {
                            glosaWrap.classList.toggle('hidden', mode !== 'GLOSA');
                        }
                        if (glosaInput && mode !== 'GLOSA') {
                            glosaInput.value = '';
                        }
                    }

                    function formatTicketDueDateDisplay(ymd) {
                        const s = String(ymd || '').trim();
                        if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) return '—';
                        const [y, m, d] = s.split('-');
                        return d + '/' + m + '/' + y;
                    }

                    function syncTicketCreditSummary() {
                        const saleType = document.getElementById('cobro-sale-type')?.value;
                        const isCredit = saleType === 'CREDITO';
                        const rowDays = document.getElementById('ticket-credit-days-row');
                        const rowDue = document.getElementById('ticket-due-date-row');
                        const dispDays = document.getElementById('ticket-credit-days-display');
                        const dispDue = document.getElementById('ticket-due-date-display');
                        const daysInput = document.getElementById('cobro-credit-days');
                        const dueInput = document.getElementById('cobro-due-date');
                        if (rowDays) rowDays.classList.toggle('hidden', !isCredit);
                        if (rowDue) rowDue.classList.toggle('hidden', !isCredit);
                        if (!isCredit) return;
                        const n = parseInt(daysInput?.value || '0', 10);
                        if (dispDays) dispDays.textContent = String(Number.isFinite(n) ? n : 0);
                        if (dispDue) dispDue.textContent = formatTicketDueDateDisplay(dueInput?.value);
                    }

                    function toggleCobroCreditFields() {
                        const saleType = document.getElementById('cobro-sale-type')?.value;
                        const creditFields = document.getElementById('cobro-credit-fields');
                        const paymentListWrap = document.getElementById('cobro-payment-methods-list')?.closest('div');
                        
                        const isCredit = (saleType === 'CREDITO');
                        
                        if (creditFields) {
                            creditFields.classList.toggle('hidden', !isCredit);
                        }
                        
                        // En ventas a crédito, usualmente no se registran pagos inmediatos,
                        // o se registran como abonos iniciales. Por ahora, permitimos ambos 
                        // pero mostramos los campos de crédito.
                        if (isCredit) {
                            calculateCobroDueDate();
                            // Evitar enviar importes de "contado" al cerrar en crédito (saldo 0 / CANCELADO en cuentas por cobrar).
                            document.querySelectorAll('#cobro-payment-methods-list .cobro-pm-amount').forEach((inp) => {
                                inp.value = '';
                            });
                        }
                        syncTicketCreditSummary();
                    }

                    function calculateCobroDueDate() {
                        const daysInput = document.getElementById('cobro-credit-days');
                        const dueDateInput = document.getElementById('cobro-due-date');
                        if (daysInput && dueDateInput) {
                            const days = parseInt(daysInput.value || 0);
                            const date = new Date();
                            date.setDate(date.getDate() + days);
                            dueDateInput.value = date.toISOString().split('T')[0];
                        }
                        syncTicketCreditSummary();
                    }

                    function getCurrentCobroClientData() {
                        const opts = window.__orderClientOptions || [];
                        const selected = opts.find(o => String(o.id) === String(currentTable?.person_id ?? ''));
                        return {
                            id: selected?.id || null,
                            document_number: String(selected?.document_number || '').trim(),
                            client_name: selected?.client_name || selected?.description || (currentTable?.clientName || '')
                        };
                    }

                    function getCurrentCobroDocumentMeta() {
                        const docTypeEl = document.getElementById('cobro-document-type');
                        const label = String(docTypeEl?.selectedOptions?.[0]?.textContent || '').trim().toLowerCase();
                        return {
                            id: docTypeEl?.value ? parseInt(docTypeEl.value, 10) : null,
                            isFactura: label.includes('factura'),
                            isBoleta: label.includes('boleta'),
                            label
                        };
                    }

                    function getOrderTotalForSplit() {
                        const totals = getTotalsWithDelivery(currentTable.items || []);
                        return totals.total;
                    }

                    function getSplitRemainingDisplayed() {
                        const cfg = window.__splitAccount || {};
                        if (cfg.enabled && cfg.remainingTotal !== null && cfg.remainingTotal !== undefined) {
                            const x = parseFloat(cfg.remainingTotal);
                            if (!isNaN(x)) return x;
                        }
                        return getOrderTotalForSplit();
                    }

                    function isSplitDivisionActiveForTicket() {
                        const cb = document.getElementById('split-dividir-cuenta');
                        return !!(cb && cb.checked && window.__splitAccount && window.__splitAccount.enabled);
                    }

                    /**
                     * Con división de cuenta aplicada: el ticket muestra el saldo pendiente del pedido
                     * después de descontar la parte que se va a cobrar en este movimiento (no el total del carrito).
                     */
                    function getTicketTotalsConsideringSplit() {
                        const full = getTotalsWithDelivery(currentTable.items || []);
                        if (!isSplitDivisionActiveForTicket()) {
                            return {
                                ...full,
                                showSplitHint: false,
                                splitPart: 0
                            };
                        }
                        let part = 0;
                        try {
                            const p = buildSplitPayloadForPayment();
                            if (p) part = computeSplitPartTotal(p);
                        } catch (e) {
                            return {
                                ...full,
                                showSplitHint: false,
                                splitPart: 0
                            };
                        }
                        const pending = getSplitRemainingDisplayed();
                        const after = Math.max(0, Math.round((pending - part) * 100) / 100);
                        if (full.total <= 0) {
                            return {
                                subtotal: 0,
                                tax: 0,
                                total: after,
                                deliveryFee: full.deliveryFee,
                                takeawayDisposableFee: full.takeawayDisposableFee,
                                productsTotal: 0,
                                showSplitHint: true,
                                splitPart: part,
                                splitPending: pending,
                            };
                        }
                        const r = Math.min(1, Math.max(0, after / full.total));
                        return {
                            subtotal: Math.round(full.subtotal * r * 100) / 100,
                            tax: Math.round(full.tax * r * 100) / 100,
                            total: after,
                            deliveryFee: Math.round(full.deliveryFee * r * 100) / 100,
                            takeawayDisposableFee: Math.round((full.takeawayDisposableFee || 0) * r * 100) / 100,
                            productsTotal: Math.round((full.productsTotal || 0) * r * 100) / 100,
                            showSplitHint: true,
                            splitPart: part,
                            splitPending: pending,
                        };
                    }

                    function escapeSplitHtml(s) {
                        const d = document.createElement('div');
                        d.textContent = s == null ? '' : String(s);
                        return d.innerHTML;
                    }

                    function splitQtyStepForMax(max) {
                        const m = parseFloat(max) || 0;
                        if (m > 0 && m % 1 < 0.000001) return 1;
                        return 0.01;
                    }

                    function syncSplitQtyRow(tr) {
                        const hid = tr.querySelector('.split-qty-input');
                        const disp = tr.querySelector('.split-qty-display');
                        if (!hid || !disp) return;
                        let v = parseFloat(hid.value) || 0;
                        const max = parseFloat(hid.getAttribute('data-max')) || 0;
                        v = Math.max(0, Math.min(max, v));
                        hid.value = String(v);
                        const showDec = (max % 1 > 0.000001) || (v % 1 > 0.000001);
                        disp.textContent = showDec ? v.toFixed(2) : String(Math.round(v));
                    }

                    function adjustSplitLineQty(tr, direction) {
                        const hid = tr.querySelector('.split-qty-input');
                        if (!hid) return;
                        const max = parseFloat(hid.getAttribute('data-max')) || 0;
                        const step = splitQtyStepForMax(max);
                        let v = parseFloat(hid.value) || 0;
                        if (direction > 0) {
                            v = Math.min(max, v + step);
                        } else {
                            v = Math.max(0, v - step);
                        }
                        hid.value = String(Math.round(v * 10000) / 10000);
                        syncSplitQtyRow(tr);
                        const cbSplit = document.getElementById('split-dividir-cuenta');
                        if (cbSplit && cbSplit.checked && typeof renderTicket === 'function') renderTicket();
                    }

                    function renderSplitProductsTbody() {
                        const tbody = document.getElementById('split-products-tbody');
                        const cfg = window.__splitAccount || {};
                        if (!tbody || !Array.isArray(cfg.lines)) return;
                        tbody.innerHTML = '';
                        const snap = window.__splitAppliedSnapshot;
                        cfg.lines.forEach(function(line) {
                            const tr = document.createElement('tr');
                            tr.className = 'border-b border-gray-100 dark:border-gray-700/80';
                            tr.setAttribute('data-split-detail-id', String(line.detail_id));
                            const rq = parseFloat(line.remaining_qty) || 0;
                            let initial = 0;
                            if (snap && snap.mode === 'products' && Array.isArray(snap.items)) {
                                const found = snap.items.find(function(it) {
                                    return parseInt(it.detail_id, 10) === parseInt(line.detail_id, 10);
                                });
                                if (found) initial = parseFloat(found.quantity) || 0;
                            }
                            initial = Math.max(0, Math.min(rq, initial));
                            const dec = (rq % 1 > 0.000001) || (initial % 1 > 0.000001);
                            const dispVal = dec ? initial.toFixed(2) : String(Math.round(initial));
                            tr.innerHTML = '<td class="py-2 px-2 align-middle">' + escapeSplitHtml(line.description ||
                                    '') + '</td>' +
                                '<td class="py-2 px-1 text-center tabular-nums align-middle">' + rq.toFixed(2) +
                                '</td>' +
                                '<td class="py-2 px-2 align-middle">' +
                                '<div class="flex items-center justify-end gap-1">' +
                                '<button type="button" class="split-qty-minus flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-lg font-bold text-slate-700 hover:bg-slate-50 dark:border-gray-600 dark:bg-gray-800 dark:text-slate-200 dark:hover:bg-gray-700 leading-none" aria-label="Menos">−</button>' +
                                '<span class="split-qty-display w-11 text-center text-sm font-bold tabular-nums text-slate-800 dark:text-slate-100">' +
                                dispVal + '</span>' +
                                '<input type="hidden" class="split-qty-input" value="' + initial + '" data-max="' + rq +
                                '" />' +
                                '<button type="button" class="split-qty-plus flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-lg font-bold text-slate-700 hover:bg-slate-50 dark:border-gray-600 dark:bg-gray-800 dark:text-slate-200 dark:hover:bg-gray-700 leading-none" aria-label="Más">+</button>' +
                                '</div></td>';
                            tbody.appendChild(tr);
                            syncSplitQtyRow(tr);
                            tr.querySelector('.split-qty-minus')?.addEventListener('click', function() {
                                adjustSplitLineQty(tr, -1);
                            });
                            tr.querySelector('.split-qty-plus')?.addEventListener('click', function() {
                                adjustSplitLineQty(tr, 1);
                            });
                        });
                    }

                    function onSplitModeChange() {
                        const mode = (document.getElementById('split-mode')?.value || 'products');
                        const wrapP = document.getElementById('split-products-wrap');
                        const wrapA = document.getElementById('split-amount-wrap');
                        if (wrapP) wrapP.classList.toggle('hidden', mode !== 'products');
                        if (wrapA) wrapA.classList.toggle('hidden', mode !== 'amount');
                    }

                    function setSplitModeTab(mode) {
                        const cfg = window.__splitAccount || {};
                        if (cfg.lockedToAmount && mode === 'products') {
                            mode = 'amount';
                        }
                        const sel = document.getElementById('split-mode');
                        if (sel) sel.value = mode;
                        const tp = document.getElementById('split-mode-tab-products');
                        const ta = document.getElementById('split-mode-tab-amount');
                        const active =
                            'bg-white dark:bg-gray-700 text-[#FF4622] shadow-sm ring-1 ring-slate-200 dark:ring-slate-600';
                        const inactive = 'text-slate-600 dark:text-slate-300 hover:bg-white/70 dark:hover:bg-gray-700/50';
                        if (tp) {
                            tp.className =
                                'split-mode-tab flex-1 py-2.5 px-3 rounded-lg text-sm font-semibold transition-colors ' + (
                                    mode === 'products' ? active : inactive);
                            tp.disabled = !!cfg.lockedToAmount;
                            tp.classList.toggle('opacity-40', !!cfg.lockedToAmount);
                            tp.classList.toggle('cursor-not-allowed', !!cfg.lockedToAmount);
                        }
                        if (ta) {
                            ta.className =
                                'split-mode-tab flex-1 py-2.5 px-3 rounded-lg text-sm font-semibold transition-colors ' + (
                                    mode === 'amount' ? active : inactive);
                        }
                        onSplitModeChange();
                    }

                    function openSplitAccountModal() {
                        const modal = document.getElementById('split-account-modal');
                        if (!modal) return;
                        initSplitPanel();
                        modal.classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                        window.__splitModalOnKey = function(e) {
                            if (e.key === 'Escape') closeSplitAccountModal();
                        };
                        document.addEventListener('keydown', window.__splitModalOnKey);
                    }

                    function closeSplitAccountModal() {
                        const modal = document.getElementById('split-account-modal');
                        if (modal) modal.classList.add('hidden');
                        document.body.style.overflow = '';
                        if (window.__splitModalOnKey) {
                            document.removeEventListener('keydown', window.__splitModalOnKey);
                            window.__splitModalOnKey = null;
                        }
                    }

                    function collectSplitSnapshotFromDom() {
                        const mode = document.getElementById('split-mode')?.value || 'products';
                        if (mode === 'amount') {
                            const amt = parseFloat(document.getElementById('split-amount-input')?.value || '0');
                            return {
                                mode: 'amount',
                                amount: amt
                            };
                        }
                        const items = [];
                        document.querySelectorAll('#split-products-tbody tr[data-split-detail-id]').forEach(function(tr) {
                            const id = parseInt(tr.getAttribute('data-split-detail-id'), 10);
                            const hid = tr.querySelector('.split-qty-input');
                            const q = parseFloat(hid?.value || '0');
                            if (q > 0) items.push({
                                detail_id: id,
                                quantity: q
                            });
                        });
                        return {
                            mode: 'products',
                            items: items
                        };
                    }

                    function applySplitAccountModal() {
                        const cfg = window.__splitAccount || {};
                        if (!cfg.enabled) return;
                        const rem = getSplitRemainingDisplayed();
                        const mode = document.getElementById('split-mode')?.value || 'products';
                        if (mode === 'amount') {
                            const amt = parseFloat(document.getElementById('split-amount-input')?.value || '0');
                            if (!(amt > 0)) {
                                if (typeof showNotification === 'function') showNotification('División',
                                    'Ingrese un monto mayor a cero.', 'error');
                                else alert('Ingrese un monto mayor a cero.');
                                return;
                            }
                            if (amt > rem + 0.02) {
                                if (typeof showNotification === 'function') showNotification('División',
                                    'El monto excede lo pendiente del pedido.', 'error');
                                else alert('El monto excede lo pendiente del pedido.');
                                return;
                            }
                        } else {
                            let any = false;
                            document.querySelectorAll('#split-products-tbody tr[data-split-detail-id]').forEach(function(tr) {
                                const hid = tr.querySelector('.split-qty-input');
                                if (hid && parseFloat(hid.value) > 0) any = true;
                            });
                            if (!any) {
                                if (typeof showNotification === 'function') showNotification('División',
                                    'Indique cantidades a cobrar con + / −.', 'error');
                                else alert('Indique cantidades a cobrar con + / −.');
                                return;
                            }
                        }
                        window.__splitAppliedSnapshot = collectSplitSnapshotFromDom();
                        const cb = document.getElementById('split-dividir-cuenta');
                        if (cb) cb.checked = true;
                        const dm = document.getElementById('cobro-detail-mode');
                        if (dm) {
                            dm.value = 'DETALLADO';
                            dm.disabled = true;
                            toggleCobroDetailGlosa();
                        }
                        updateSplitInlineStatus();
                        syncCobroPaymentAmountsToSplitPart();
                        closeSplitAccountModal();
                        if (typeof renderTicket === 'function') renderTicket();
                    }

                    function syncCobroPaymentAmountsToSplitPartInner() {
                        const cb = document.getElementById('split-dividir-cuenta');
                        if (!cb || !cb.checked) return;
                        let part = 0;
                        try {
                            const p = buildSplitPayloadForPayment();
                            if (p) part = computeSplitPartTotal(p);
                        } catch (e) {
                            return;
                        }
                        if (!(part > 0)) return;
                        const list = document.getElementById('cobro-payment-methods-list');
                        if (!list) return;
                        while (list.querySelectorAll('.cobro-pm-row').length > 1) {
                            list.querySelector('.cobro-pm-row:last-child')?.remove();
                        }
                        let inputs = document.querySelectorAll('.cobro-pm-amount');
                        if (!inputs.length) {
                            addCobroPaymentMethod();
                            inputs = document.querySelectorAll('.cobro-pm-amount');
                        }
                        inputs.forEach(function(inp, i) {
                            inp.value = i === 0 ? part.toFixed(2) : '0.00';
                        });
                    }

                    function syncCobroPaymentAmountsToSplitPart() {
                        window.__splitCobroSyncing = true;
                        try {
                            syncCobroPaymentAmountsToSplitPartInner();
                        } finally {
                            window.__splitCobroSyncing = false;
                        }
                        updateCobroTotalPaid();
                    }

                    function clearSplitDivision() {
                        window.__splitAppliedSnapshot = null;
                        const cb = document.getElementById('split-dividir-cuenta');
                        if (cb) cb.checked = false;
                        const dm = document.getElementById('cobro-detail-mode');
                        if (dm) dm.disabled = false;
                        const amt = document.getElementById('split-amount-input');
                        if (amt) amt.value = '';
                        const st = document.getElementById('split-inline-status');
                        if (st) st.textContent = '';
                        document.querySelectorAll('#split-products-tbody tr[data-split-detail-id]').forEach(function(tr) {
                            const hid = tr.querySelector('.split-qty-input');
                            if (hid) hid.value = '0';
                            syncSplitQtyRow(tr);
                        });
                        closeSplitAccountModal();
                        const listCobro = document.getElementById('cobro-payment-methods-list');
                        if (listCobro) {
                            while (listCobro.querySelectorAll('.cobro-pm-row').length > 1) {
                                listCobro.querySelector('.cobro-pm-row:last-child')?.remove();
                            }
                            const fullT = getTotalsWithDelivery(currentTable?.items || []).total || 0;
                            const inp = listCobro.querySelector('.cobro-pm-amount');
                            if (inp) inp.value = fullT.toFixed(2);
                        }
                        if (typeof updateCobroTotalPaid === 'function') updateCobroTotalPaid();
                        if (typeof renderTicket === 'function') renderTicket();
                    }

                    function updateSplitInlineStatus() {
                        const el = document.getElementById('split-inline-status');
                        const cb = document.getElementById('split-dividir-cuenta');
                        if (!el || !cb || !cb.checked) {
                            if (el && (!cb || !cb.checked)) el.textContent = '';
                            return;
                        }
                        try {
                            const p = buildSplitPayloadForPayment();
                            if (!p) {
                                el.textContent = '';
                                return;
                            }
                            const t = computeSplitPartTotal(p);
                            el.textContent = 'Parte: S/ ' + t.toFixed(2);
                        } catch (e) {
                            el.textContent = 'División activa';
                        }
                    }

                    function initSplitPanel() {
                        const modal = document.getElementById('split-account-modal');
                        if (!modal) return;
                        const cfg = window.__splitAccount || {};
                        const badge = document.getElementById('split-remaining-badge');
                        const r = getSplitRemainingDisplayed();
                        if (badge) badge.textContent = 'Pendiente: S/ ' + r.toFixed(2);
                        const modeSel = document.getElementById('split-mode');
                        const hint = document.getElementById('split-hint-locked');
                        let startMode = 'products';
                        if (cfg.lockedToAmount) {
                            if (hint) hint.classList.remove('hidden');
                            startMode = 'amount';
                        } else {
                            if (hint) hint.classList.add('hidden');
                        }
                        if (window.__splitAppliedSnapshot && window.__splitAppliedSnapshot.mode) {
                            startMode = window.__splitAppliedSnapshot.mode;
                        }
                        if (modeSel) modeSel.value = startMode;
                        const amtIn = document.getElementById('split-amount-input');
                        if (amtIn) {
                            if (window.__splitAppliedSnapshot && window.__splitAppliedSnapshot.mode === 'amount' && window
                                .__splitAppliedSnapshot.amount != null) {
                                amtIn.value = String(window.__splitAppliedSnapshot.amount);
                            } else if (!amtIn.value) {
                                amtIn.placeholder = r.toFixed(2);
                            }
                            if (!amtIn.dataset.splitAmountBound) {
                                amtIn.dataset.splitAmountBound = '1';
                                amtIn.addEventListener('input', function() {
                                    const cb = document.getElementById('split-dividir-cuenta');
                                    if (cb && cb.checked && typeof renderTicket === 'function') renderTicket();
                                });
                            }
                        }
                        setSplitModeTab(startMode);
                        renderSplitProductsTbody();
                        updateSplitInlineStatus();
                    }

                    function buildSplitPayloadForPayment() {
                        const cfg = window.__splitAccount || {};
                        const cb = document.getElementById('split-dividir-cuenta');
                        if (!cfg.enabled || !cb || !cb.checked) return null;
                        const mode = (document.getElementById('split-mode')?.value || 'products');
                        const rem = getSplitRemainingDisplayed();
                        if (mode === 'amount') {
                            const amt = parseFloat(document.getElementById('split-amount-input')?.value || '0');
                            if (!(amt > 0)) {
                                throw new Error('Ingrese un monto a cobrar mayor a cero.');
                            }
                            if (amt > rem + 0.02) {
                                throw new Error('El monto excede lo pendiente por cobrar en el pedido.');
                            }
                            return {
                                mode: 'amount',
                                amount: Math.round(amt * 100) / 100
                            };
                        }
                        const items = [];
                        document.querySelectorAll('#split-products-tbody tr[data-split-detail-id]').forEach(function(tr) {
                            const id = parseInt(tr.getAttribute('data-split-detail-id'), 10);
                            const inp = tr.querySelector('.split-qty-input');
                            const max = parseFloat(inp?.getAttribute('data-max') || '0');
                            let qty = parseFloat(inp?.value || '0');
                            if (isNaN(qty) || qty <= 0) return;
                            if (qty > max + 0.000001) {
                                throw new Error('Cantidad a cobrar mayor a la pendiente en una línea.');
                            }
                            items.push({
                                detail_id: id,
                                quantity: qty
                            });
                        });
                        if (items.length === 0) {
                            throw new Error('Seleccione al menos un producto con cantidad para dividir la cuenta.');
                        }
                        return {
                            mode: 'products',
                            items: items
                        };
                    }

                    function computeSplitPartTotal(splitPayload) {
                        if (!splitPayload) return getOrderTotalForSplit();
                        if (splitPayload.mode === 'amount') return splitPayload.amount;
                        const cfg = window.__splitAccount || {};
                        let sum = 0;
                        (splitPayload.items || []).forEach(function(it) {
                            const line = (cfg.lines || []).find(function(l) {
                                return parseInt(l.detail_id, 10) === parseInt(it.detail_id, 10);
                            });
                            if (line) {
                                sum += (parseFloat(line.unit_amount) || 0) * (parseFloat(it.quantity) || 0);
                            }
                        });
                        return Math.round(sum * 100) / 100;
                    }

                    async function processOrderPayment() {
                        if (waiterPinEnabled && !isMozoProfile) {
                            const ok = await ensureWaiterPin();
                            if (!ok) return;
                        }
                        const cbSplitPay = document.getElementById('split-dividir-cuenta');
                        if (cbSplitPay && cbSplitPay.checked && window.__splitAccount && window.__splitAccount.enabled) {
                            syncCobroPaymentAmountsToSplitPartInner();
                        }
                        flushCartUnitPriceInputsFromDom();
                        renderTicket();
                        const items = currentTable.items || [];
                        if (items.length === 0) {
                            if (typeof showNotification === 'function') {
                                showNotification('Error', 'Agrega productos a la orden antes de cobrar.', 'error');
                            } else {
                                sessionStorage.setItem('flash_error_message',
                                    'Agrega productos a la orden antes de cobrar.');
                            }
                            return;
                        }
                        const totals = getTotalsWithDelivery(items);
                        const total = totals.total;
                        let cobroTotal = total;
                        let splitPayload = null;
                        try {
                            splitPayload = buildSplitPayloadForPayment();
                            if (splitPayload) {
                                cobroTotal = computeSplitPartTotal(splitPayload);
                            }
                        } catch (e) {
                            if (typeof showNotification === 'function') {
                                showNotification('Error', e.message || 'Error en división de cuenta.', 'error');
                            } else {
                                alert(e.message || 'Error en división de cuenta.');
                            }
                            return;
                        }

                        const paymentMethodsData = getCobroPaymentMethodsFromForm();
                        const totalPaid = paymentMethodsData.reduce((s, p) => s + (parseFloat(p.amount) || 0), 0);
                        const saleType = document.getElementById('cobro-sale-type')?.value || 'CONTADO';

                        if (saleType === 'CONTADO') {
                            if (paymentMethodsData.length === 0) {
                                if (typeof showNotification === 'function') {
                                    showNotification('Error', 'Agrega al menos un método de pago.', 'error');
                                } else {
                                    alert('Agrega al menos un método de pago.');
                                }
                                return;
                            }
                            if (Math.abs(totalPaid - cobroTotal) > 0.01) {
                                if (typeof showNotification === 'function') {
                                    showNotification('Error', 'La suma de los métodos de pago debe ser igual al total (S/ ' +
                                        cobroTotal.toFixed(2) + ').', 'error');
                                } else {
                                    alert('La suma de los métodos de pago debe ser igual al total (S/ ' + cobroTotal.toFixed(
                                        2) + ').');
                                }
                                return;
                            }
                        } else {
                            // CREDITO
                            if (!currentTable.person_id) {
                                const msg = 'Para una venta al crédito debes seleccionar un cliente.';
                                if (typeof showNotification === 'function') showNotification('Error', msg, 'error');
                                else alert(msg);
                                return;
                            }
                            if (totalPaid > cobroTotal + 0.01) {
                                const msg = 'El pago inicial no puede ser mayor al total de la venta.';
                                if (typeof showNotification === 'function') showNotification('Error', msg, 'error');
                                else alert(msg);
                                return;
                            }
                        }

                        const selectedDoc = getCurrentCobroDocumentMeta();
                        const selectedClient = getCurrentCobroClientData();
                        const cleanDocument = String(selectedClient.document_number || '').replace(/\D+/g, '');
                        const detailModeEl = document.getElementById('cobro-detail-mode');
                        const detailGlosaEl = document.getElementById('cobro-detail-glosa');
                        const detailMode = String(detailModeEl?.value || 'DETALLADO').toUpperCase();
                        const detailGlosa = String(detailGlosaEl?.value || '').trim();

                        if (selectedDoc.isFactura && cleanDocument.length !== 11) {
                            const msg = 'La factura de venta requiere un cliente con RUC valido de 11 digitos.';
                            if (typeof showNotification === 'function') showNotification('Error', msg, 'error');
                            else alert(msg);
                            return;
                        }
                        if (selectedDoc.isBoleta && cobroTotal > 700 && cleanDocument.length !== 8 && cleanDocument
                            .length !== 11) {
                            const msg =
                                'Para emitir boleta mayor a S/ 700.00 debes seleccionar un cliente con DNI o RUC valido.';
                            if (typeof showNotification === 'function') showNotification('Error', msg, 'error');
                            else alert(msg);
                            return;
                        }
                        if (detailMode === 'GLOSA' && !detailGlosa && !splitPayload) {
                            const msg = 'Debes escribir la glosa que saldra en el comprobante.';
                            if (typeof showNotification === 'function') showNotification('Error', msg, 'error');
                            else alert(msg);
                            return;
                        }

                        if (window.Swal) {
                            const confirmPayment = await Swal.fire({
                                title: 'Confirmar Cobro',
                                text: `Total a cobrar: S/ ${cobroTotal.toFixed(2)}. ¿Deseas proceder?`,
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonColor: '#FF4622',
                                cancelButtonColor: '#d33',
                                confirmButtonText: 'Sí, cobrar',
                                cancelButtonText: 'Cancelar'
                            });
                            if (!confirmPayment.isConfirmed) return;
                        }

                        const okReason = await ensureCancellationReasons();
                        if (!okReason) return;
                        if (autoSaveTimer) {
                            clearTimeout(autoSaveTimer);
                            autoSaveTimer = null;
                        }

                        const itemsToSend = getItemsGroupedByProduct();
                        const productTotals = calculateTotalsFromItems(itemsToSend);
                        const processPayload = {
                            items: itemsToSend,
                            table_id: counterPosMode ? null : (currentTable.table_id ?? currentTable.id),
                            area_id: currentTable.area_id ?? null,
                            subtotal: productTotals.subtotal,
                            tax: productTotals.tax,
                            total: productTotals.total,
                            people_count: currentTable.people_count ?? 0,
                            waiter_id: currentTable.waiter_id ?? null,
                            client_id: currentTable.person_id ?? null,
                            client_name: (currentTable.service_type === 'TAKE_AWAY' && currentTable.client_name_extra) ?
                                currentTable.client_name_extra : (currentTable.clientName || null),
                            contact_phone: currentTable.contact_phone ?? null,
                            delivery_time: currentTable.delivery_time ?? null,
                            delivery_amount: currentTable.delivery_amount ?? 0,
                            takeaway_disposable_charge: !!currentTable.takeaway_disposable_charge,
                            takeaway_disposable_amount: parseFloat(currentTable.takeaway_disposable_amount) || 0,
                            service_type: currentTable.service_type ?? 'IN_SITU',
                            order_movement_id: currentTable.order_movement_id ?? null,
                            cancellations: currentTable.cancellations || [],
                        };

                        let btn = document.querySelector('button[onclick*="processOrderPayment"]');
                        if (btn) {
                            btn.disabled = true;
                            btn.innerHTML =
                                '<i class="ri-loader-4-line animate-spin text-base"></i><span>Procesando...</span>';
                        }

                        try {
                            let movementId = currentTable.movement_id;

                            if (!movementId) {
                                const processRes = await fetch('{{ route('orders.process') }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                            ?.getAttribute('content') || '',
                                        'Accept': 'application/json'
                                    },
                                    body: JSON.stringify(processPayload)
                                });
                                const ct = processRes.headers.get('content-type');
                                let processData = null;
                                if (ct && ct.includes('application/json')) {
                                    processData = await processRes.json();
                                } else {
                                    throw new Error(processRes.status === 419 ? 'Sesión expirada. Recarga la página.' : (
                                        processRes.status === 401 ? 'Debes iniciar sesión.' : (processRes.status ===
                                            500 ? 'Error al procesar. Intenta de nuevo.' : 'Error del servidor.')));
                                }
                                if (!processData || !processData.success || !processData.movement_id) {
                                    throw new Error(processData?.message ||
                                        'No se pudo guardar el pedido. Intenta de nuevo.');
                                }
                                movementId = processData.movement_id;
                                currentTable.cancellations = [];
                                currentTable.order_movement_id = processData.order_movement_id;
                                currentTable.movement_id = movementId;
                                saveDB();
                            }

                            const docTypeEl = document.getElementById('cobro-document-type');
                            const cashRegEl = document.getElementById('cobro-cash-register');
                            const saleTypeEl = document.getElementById('cobro-sale-type');
                            const creditDaysEl = document.getElementById('cobro-credit-days');
                            const dueDateEl = document.getElementById('cobro-due-date');

                            const paymentPayload = {
                                movement_id: movementId,
                                table_id: counterPosMode ? null : (currentTable.table_id ?? currentTable.id),
                                document_type_id: docTypeEl?.value ? parseInt(docTypeEl.value, 10) : null,
                                cash_register_id: cashRegEl?.value ? parseInt(cashRegEl.value, 10) : null,
                                client_id: currentTable.person_id ?? null,
                                client_name: currentTable.clientName || null,
                                detail_mode: detailMode,
                                detail_glosa: detailGlosa,
                                payment_methods: paymentMethodsData,
                                sale_type: saleTypeEl?.value || 'CONTADO',
                                credit_days: creditDaysEl?.value || 0,
                                due_date: dueDateEl?.value || null,
                                notes: '',
                            };
                            if (splitPayload) {
                                paymentPayload.split = splitPayload;
                            }

                            const payRes = await fetch('{{ route('orders.processOrderPayment') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute(
                                        'content') || '',
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify(paymentPayload)
                            });

                            const payData = payRes.headers.get('content-type')?.includes('application/json') ? await payRes
                                .json() : null;
                            if (!payRes.ok) {
                                throw new Error(payData?.message || payData?.error || 'Error al procesar el cobro.');
                            }
                            if (!payData?.success) {
                                throw new Error(payData?.message || payData?.error || 'Error al procesar el cobro.');
                            }

                            if (payData.split_remaining_total !== undefined && payData.order_closed === false) {
                                const splitSaleMovId = payData?.split_sale_movement_id || payData?.movement_id;
                                if (!counterPosMode) {
                                    await sendThermalTicketAfterSale(splitSaleMovId, payData);
                                }
                                sessionStorage.setItem('flash_success_message', payData.message ||
                                    'Cobro parcial registrado.');
                                window.location.reload();
                                return;
                            }

                            const payMovementId = payData?.split_sale_movement_id || payData?.movement_id;
                            if (!counterPosMode) {
                                await sendThermalTicketAfterSale(payMovementId, payData);
                            }

                            if (db && activeKey && db[activeKey]) {
                                delete db[activeKey];
                                localStorage.setItem('restaurantDB', JSON.stringify(db));
                            }
                            sessionStorage.setItem('flash_success_message', payData.message ||
                                'Cobro de pedido procesado correctamente');
                            const indexUrl = afterPaymentIndexUrl;
                            setTimeout(() => {
                                if (window.Turbo && typeof window.Turbo.visit === 'function') {
                                    window.Turbo.visit(indexUrl, {
                                        action: 'replace'
                                    });
                                } else {
                                    window.location.href = indexUrl;
                                }
                            }, 600);
                        } catch (error) {
                            console.error('Error:', error);
                            if (String(error?.message || '').indexOf('PIN') !== -1) {
                                sessionStorage.removeItem(`waiterPin:${waiterPinBranchId}`);
                            }
                            if (typeof showNotification === 'function') {
                                showNotification('Error', error?.message || 'Error al procesar.', 'error');
                            } else {
                                alert(error?.message || 'Error al procesar.');
                            }
                        } finally {
                            if (btn) {
                                btn.disabled = false;
                                btn.innerHTML = '<i class="ri-bank-card-line text-base"></i><span>Cobrar</span>';
                            }
                        }
                    }

                    function releaseTableAndGoBack() {
                        if (counterPosMode) {
                            const indexUrl = afterPaymentIndexUrl;
                            if (window.Turbo && typeof window.Turbo.visit === 'function') {
                                window.Turbo.visit(indexUrl, {
                                    action: 'replace'
                                });
                            } else {
                                window.location.href = indexUrl;
                            }
                            return;
                        }
                        const tableId = currentTable?.table_id ?? currentTable?.id ?? {{ $table->id }};
                        const url = "{{ route('orders.cancelOrder') }}";
                        const indexUrl = "{{ route('orders.index') }}";
                        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                        fetch(url, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify({
                                    table_id: tableId
                                }),
                            })
                            .then(async (r) => {
                                if (r.headers.get('content-type')?.includes('application/json')) {
                                    return r.json();
                                }
                                return null;
                            })
                            .then((data) => {
                                // Limpiar borrador local de esta mesa para que no reaparezca como guardada
                                if (db && activeKey && db[activeKey]) {
                                    delete db[activeKey];
                                    localStorage.setItem('restaurantDB', JSON.stringify(db));
                                }
                                if (currentTable) {
                                    currentTable.items = [];
                                    currentTable.order_movement_id = null;
                                    currentTable.movement_id = null;
                                    currentTable.isActive = false;
                                }
                            })
                            .catch(() => {
                                /* seguir igual, redirigir */
                            })
                            .finally(() => {
                                if (window.Turbo && typeof window.Turbo.visit === 'function') {
                                    window.Turbo.visit(indexUrl, {
                                        action: 'replace'
                                    });
                                } else {
                                    window.location.href = indexUrl;
                                }
                            });
                    }

                    function goBack() {
                        if (window.Turbo && typeof window.Turbo.visit === 'function') {
                            window.Turbo.visit(afterPaymentIndexUrl, {
                                action: 'replace'
                            });
                        }
                    }

                    // Inicializar cuando el DOM esté listo
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', init);
                    } else {
                        init();
                    }

                    function showAddToCartNotification(productName) {
                        const notification = document.getElementById('add-to-cart-notification');
                        const productNameEl = document.getElementById('notification-product-name');
                        if (!notification || !productNameEl) return;
                        productNameEl.textContent = productName;
                        notification.classList.add('notification-show');
                        setTimeout(() => notification.classList.remove('notification-show'), 1200);
                    }

                    function showNotification(title, message, type = 'info') {
                        const notification = document.getElementById('notification');
                        if (!notification) return;
                        const isError = type === 'error';
                        notification.innerHTML =
                            `
                                                                                                                                                                                                <div class="rounded-xl border p-4 shadow-lg ${isError ? 'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800' : 'bg-green-50 border-green-200 dark:bg-green-900/20 dark:border-green-800'}">
                                                                                                                                                                                                    <div class="flex items-start gap-3">
                                                                                                                                                                                                        <div class="${isError ? 'text-red-500' : 'text-green-500'}"><i class="fas fa-${isError ? 'exclamation-circle' : 'check-circle'} text-xl"></i></div>
                                                                                                                                                                                                        <div>
                                                                                                                                                                                                            <h3 class="font-semibold ${isError ? 'text-red-800 dark:text-red-200' : 'text-green-800 dark:text-green-200'}">${title}</h3>
                                                                                                                                                                                                            <p class="text-sm mt-1 ${isError ? 'text-red-700 dark:text-red-300' : 'text-green-700 dark:text-green-300'}">${message}</p>
                                                                                                                                                                                                        </div>
                                                                                                                                                                                                    </div>
                                                                                                                                                                                                </div>
                                                                                                                                                                                            `;
                        notification.classList.remove('opacity-0', 'pointer-events-none');
                        setTimeout(() => {
                            notification.classList.add('opacity-0', 'pointer-events-none');
                        }, 3500);
                    }

                    function updateDiners(delta) {
                        const input = document.getElementById('diners-input');
                        if (!input) return;
                        let value = delta === 0 ?
                            parseInt(input.value, 10) :
                            parseInt(input.value, 10) + delta;
                        if (isNaN(value) || value < 1) value = 1;
                        input.value = value;
                        if (currentTable) {
                            currentTable.people_count = value;
                            if (db && activeKey) {
                                db[activeKey] = currentTable;
                                localStorage.setItem('restaurantDB', JSON.stringify(db));
                            }
                        }
                    }

                    function safeSetAlpineDataProperty(root, property, value) {
                        if (!root || !window.Alpine || typeof Alpine.$data !== 'function') return;

                        try {
                            const data = Alpine.$data(root);
                            if (data && Object.prototype.hasOwnProperty.call(data, property)) {
                                data[property] = value;
                            }
                        } catch (error) {
                            console.warn('No se pudo sincronizar Alpine:', error);
                        }
                    }

                    function ensureCounterSaleDefaultClientSelected() {
                        if (!counterPosMode || !currentTable) return;
                        if (currentTable.person_id) return;
                        const opts = Array.isArray(window.__orderClientOptions) ? window.__orderClientOptions : [];
                        const defaultOpt = opts.find((o) => {
                            const desc = String(o?.description || '').toUpperCase();
                            const name = String(o?.client_name || '').toUpperCase();
                            return desc.includes('CLIENTES VARIOS') || name.includes('CLIENTES VARIOS');
                        }) || null;
                        if (!defaultOpt || !defaultOpt.id) return;
                        currentTable.person_id = parseInt(defaultOpt.id, 10);
                        currentTable.clientName = defaultOpt.client_name || defaultOpt.description || 'CLIENTES VARIOS';
                        currentTable.clientLabel = defaultOpt.description || currentTable.clientName;
                        saveDB();
                        const picker = document.getElementById('order-client-picker');
                        safeSetAlpineDataProperty(picker, 'clientId', currentTable.person_id);
                        const cobroInput = document.getElementById('cobro-client-input');
                        if (cobroInput) cobroInput.value = currentTable.clientLabel || 'CLIENTES VARIOS';
                    }

                    function ensureCounterCobroAmountsReady() {
                        if (!counterPosMode) return;
                        const list = document.getElementById('cobro-payment-methods-list');
                        if (!list) return;
                        let rows = list.querySelectorAll('.cobro-pm-row');
                        if (!rows.length && typeof addCobroPaymentMethod === 'function') {
                            addCobroPaymentMethod();
                            rows = list.querySelectorAll('.cobro-pm-row');
                        }
                        const total = getCobroOrderTotal();
                        if (typeof syncCobroAmountsWithCart === 'function') {
                            syncCobroAmountsWithCart(total);
                        } else if (rows.length) {
                            const firstInput = rows[0].querySelector('.cobro-pm-amount');
                            if (firstInput) firstInput.value = total.toFixed(2);
                        }
                        if (typeof updateCobroTotalPaid === 'function') {
                            updateCobroTotalPaid();
                        }
                    }

                    function switchAsideTab(tab) {
                        const resumen = document.getElementById('aside-resumen');
                        const cobro = document.getElementById('aside-cobro');
                        const btnResumen = document.getElementById('tab-resumen');
                        const btnCobro = document.getElementById('tab-cobro');
                        const footerResumen = document.getElementById('footer-resumen');
                        const footerCobro = document.getElementById('footer-cobro');
                        const productsGrid = document.getElementById('products-grid');
                        const categoriesGrid = document.getElementById('categories-grid');
                        const searchInput = document.getElementById('search-products');
                        if (tab === 'cobro' && !canAccessCobroTab()) {
                            return;
                        }
                        if (tab === 'cobro') {
                            ensureCounterSaleDefaultClientSelected();
                            resumen?.classList.add('hidden');
                            cobro?.classList.remove('hidden');
                            cobro?.classList.add('flex');
                            footerResumen?.classList.add('hidden');
                            footerCobro?.classList.remove('hidden');
                            btnResumen?.classList.remove('bg-[#FF4622]', 'text-white', 'border-[#FF4622]');
                            btnResumen?.classList.add('bg-white', 'dark:bg-gray-900', 'text-gray-500', 'dark:text-gray-400',
                                'border-gray-200', 'dark:border-gray-700');
                            btnCobro?.classList.remove('bg-white', 'dark:bg-gray-900', 'text-gray-500', 'dark:text-gray-400',
                                'border-gray-200', 'dark:border-gray-700');
                            btnCobro?.classList.add('bg-[#FF4622]', 'text-white', 'border-[#FF4622]');
                            // Deshabilitar agregar/modificar productos mientras se está en Cobro
                            if (productsGrid) {
                                productsGrid.classList.add('pointer-events-none', 'opacity-60');
                            }
                            if (categoriesGrid) {
                                categoriesGrid.classList.add('pointer-events-none', 'opacity-60');
                            }
                            if (searchInput) {
                                searchInput.setAttribute('disabled', 'disabled');
                                searchInput.classList.add('bg-gray-100', 'cursor-not-allowed');
                            }
                            if (typeof initSplitPanel === 'function') initSplitPanel();
                            const cbSplit = document.getElementById('split-dividir-cuenta');
                            if (cbSplit && cbSplit.checked && typeof syncCobroPaymentAmountsToSplitPart === 'function') {
                                syncCobroPaymentAmountsToSplitPart();
                            }
                            ensureCounterCobroAmountsReady();
                        } else {
                            cobro?.classList.add('hidden');
                            cobro?.classList.remove('flex');
                            resumen?.classList.remove('hidden');
                            footerCobro?.classList.add('hidden');
                            footerResumen?.classList.remove('hidden');
                            btnCobro?.classList.remove('bg-[#FF4622]', 'text-white', 'border-[#FF4622]');
                            btnCobro?.classList.add('bg-white', 'dark:bg-gray-900', 'text-gray-500', 'dark:text-gray-400',
                                'border-gray-200', 'dark:border-gray-700');
                            btnResumen?.classList.remove('bg-white', 'dark:bg-gray-900', 'text-gray-500', 'dark:text-gray-400',
                                'border-gray-200', 'dark:border-gray-700');
                            btnResumen?.classList.add('bg-[#FF4622]', 'text-white', 'border-[#FF4622]');
                            // Volver a habilitar productos al regresar a Resumen
                            if (productsGrid) {
                                productsGrid.classList.remove('pointer-events-none', 'opacity-60');
                            }
                            if (categoriesGrid) {
                                categoriesGrid.classList.remove('pointer-events-none', 'opacity-60');
                                ensureMobileQuickFilters();
                            }
                            if (searchInput) {
                                searchInput.removeAttribute('disabled');
                                searchInput.classList.remove('bg-gray-100', 'cursor-not-allowed');
                            }
                        }
                    }

                    function clearCobroClient() {
                        const input = document.getElementById('cobro-client-input');
                        if (input) input.value = 'CLIENTES VARIOS';
                        if (currentTable) {
                            currentTable.clientName = 'CLIENTES VARIOS';
                            currentTable.clientLabel = 'CLIENTES VARIOS';
                            currentTable.person_id = null;
                            saveDB();
                        }
                        const headerInput = document.getElementById('header-client-name');
                        if (headerInput) headerInput.value = '';
                        const picker = document.getElementById('order-client-picker');
                        safeSetAlpineDataProperty(picker, 'clientId', null);
                        window.dispatchEvent(new CustomEvent('clear-combobox', {
                            detail: {
                                name: 'header_client_id'
                            }
                        }));
                    }

                    function clearHeaderClientName() {
                        const input = document.getElementById('header-client-name');
                        if (input) {
                            input.value = '';
                            input.focus();
                        }
                        if (!currentTable) return;
                        currentTable.clientName = '';
                        currentTable.person_id = null;
                        saveDB();
                        const cobroInput = document.getElementById('cobro-client-input');
                        if (cobroInput) cobroInput.value = '';
                    }

                    function updateHeaderClientName(value) {
                        if (!currentTable) return;
                        const name = String(value || '').trim();
                        currentTable.clientName = name;
                        currentTable.person_id = null;
                        saveDB();
                        const cobroInput = document.getElementById('cobro-client-input');
                        if (cobroInput) cobroInput.value = name || 'CLIENTES VARIOS';
                    }

                    function getCobroOrderTotal() {
                        const cb = document.getElementById('split-dividir-cuenta');
                        if (cb && cb.checked && window.__splitAccount && window.__splitAccount.enabled) {
                            try {
                                const p = buildSplitPayloadForPayment();
                                if (p) {
                                    return computeSplitPartTotal(p);
                                }
                            } catch (e) {
                                /* ignore */
                            }
                        }
                        const totals = getTotalsWithDelivery(currentTable?.items || []);
                        return totals.total || 0;
                    }

                    function getCobroRemainingAmount(excludeInput) {
                        const orderTotal = getCobroOrderTotal();
                        const inputs = document.querySelectorAll('.cobro-pm-amount');
                        let paid = 0;
                        inputs.forEach(inp => {
                            if (inp !== excludeInput) {
                                paid += parseFloat(inp.value || 0) || 0;
                            }
                        });
                        return Math.max(0, orderTotal - paid);
                    }

                    function autocompleteCobroAmount(inputEl) {
                        if (!inputEl) return;
                        if (inputEl.readOnly) return;
                        const val = parseFloat(inputEl.value || 0) || 0;
                        if (val > 0) {
                            inputEl.select();
                            return;
                        }
                        const remaining = getCobroRemainingAmount(inputEl);
                        if (remaining > 0) {
                            inputEl.value = remaining.toFixed(2);
                            inputEl.select();
                            updateCobroTotalPaid();
                        }
                    }

                    function isCobroMethodCard(desc) {
                        const d = (desc || '').toLowerCase();
                        return (d.includes('tarjeta') || d.includes('card')) && !d.includes('billetera');
                    }

                    function isCobroMethodWallet(desc) {
                        return ('' + (desc || '')).toLowerCase().includes('billetera');
                    }

                    function isCobroMethodTransfer(desc) {
                        const d = ('' + (desc || '')).toLowerCase();
                        return d.includes('transferencia') || d.includes('transfer') || d.includes('deposito') || d.includes(
                            'depósito');
                    }

                    function buildCobroGatewayOptions() {
                        const gws = cobroPaymentGateways || [];
                        const opts = gws.map(g => `<option value="${g.id}">${escapeHtml(g.description || '')}</option>`).join(
                            '');
                        return opts ? '<option value="">Seleccionar pasarela</option>' + opts :
                            '<option value="">Sin pasarelas</option>';
                    }

                    function buildCobroCardOptions() {
                        const cards = cobroCards || [];
                        const credit = cards.filter(c => (c.type || '').toUpperCase() === 'C');
                        const debit = cards.filter(c => (c.type || '').toUpperCase() === 'D');
                        let html = '<option value="">Seleccionar tarjeta</option>';
                        if (credit.length) {
                            html += '<optgroup label="Crédito">' + credit.map(c =>
                                    `<option value="${c.id}">${escapeHtml(c.description || '')}</option>`).join('') +
                                '</optgroup>';
                        }
                        if (debit.length) {
                            html += '<optgroup label="Débito">' + debit.map(c =>
                                    `<option value="${c.id}">${escapeHtml(c.description || '')}</option>`).join('') +
                                '</optgroup>';
                        }
                        return html || '<option value="">Sin tarjetas</option>';
                    }

                    function buildCobroWalletOptions() {
                        const wls = cobroDigitalWallets || [];
                        const opts = wls.map(w => `<option value="${w.id}">${escapeHtml(w.description || '')}</option>`).join(
                            '');
                        return opts ? '<option value="">Seleccionar billetera</option>' + opts :
                            '<option value="">Sin billeteras</option>';
                    }

                    function buildCobroBankOptions() {
                        const banks = cobroBanks || [];
                        const opts = banks.map(b => `<option value="${b.id}">${escapeHtml(b.description || '')}</option>`).join(
                            '');
                        return opts ? '<option value="">Seleccionar banco</option>' + opts :
                            '<option value="">Sin bancos</option>';
                    }

                    function toggleCobroExtraFields(row) {
                        const methodSelect = row.querySelector('.cobro-pm-method');
                        const cardGroup = row.querySelector('.cobro-pm-card-group');
                        const walletGroup = row.querySelector('.cobro-pm-wallet-group');
                        const bankGroup = row.querySelector('.cobro-pm-bank-group');
                        if (!methodSelect || !cardGroup || !walletGroup) return;
                        const sel = methodSelect.options[methodSelect.selectedIndex];
                        const desc = sel ? sel.text : '';
                        const isCard = isCobroMethodCard(desc);
                        const isWallet = isCobroMethodWallet(desc);
                        const isTransfer = isCobroMethodTransfer(desc);
                        cardGroup.classList.toggle('hidden', !isCard);
                        walletGroup.classList.toggle('hidden', !isWallet);
                        const gw = row.querySelector('.cobro-pm-gateway');
                        const card = row.querySelector('.cobro-pm-card');
                        const wallet = row.querySelector('.cobro-pm-wallet');
                        if (!isCard && gw) gw.value = '';
                        if (!isCard && card) card.value = '';
                        if (!isWallet && wallet) wallet.value = '';
                        if (bankGroup) {
                            if (isTransfer) {
                                bankGroup.classList.remove('hidden');
                                bankGroup.classList.add('flex');
                            } else {
                                bankGroup.classList.add('hidden');
                                bankGroup.classList.remove('flex');
                                const bank = row.querySelector('.cobro-pm-bank');
                                if (bank) bank.value = '';
                            }
                        }
                    }

                    function removeCobroPaymentRow(btnEl) {
                        const cb = document.getElementById('split-dividir-cuenta');
                        if (cb && cb.checked && window.__splitAccount && window.__splitAccount.enabled) {
                            return;
                        }
                        btnEl.closest('.cobro-pm-row')?.remove();
                        updateCobroTotalPaid();
                    }

                    function addCobroPaymentMethod() {
                        const list = document.getElementById('cobro-payment-methods-list');
                        if (!list) return;
                        const cb = document.getElementById('split-dividir-cuenta');
                        if (cb && cb.checked && window.__splitAccount && window.__splitAccount.enabled) {
                            if (list.querySelectorAll('.cobro-pm-row').length > 0) {
                                return;
                            }
                        }
                        const methods = cobroPaymentMethods || [];
                        const opts = methods.map(pm =>
                            `<option value="${pm.id}">${escapeHtml(pm.description || '')}</option>`
                        ).join('');
                        const autoAmount = getCobroRemainingAmount();
                        const row = document.createElement('div');
                        row.className =
                            'cobro-pm-row rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50 p-3 space-y-2';
                        row.innerHTML =
                            `
                                                                                                                                                                                                <div class="flex gap-2 items-end flex-wrap">
                                                                                                                                                                                                    <div class="flex-1 min-w-[120px]">
                                                                                                                                                                                                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Método</label>
                                                                                                                                                                                                        <select class="cobro-pm-method w-full py-2 px-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm focus:ring-2 focus:ring-orange-400 focus:border-orange-400" onchange="toggleCobroExtraFields(this.closest('.cobro-pm-row'))">
                                                                                                                                                                                                            ${opts}
                                                                                                                                                                                                        </select>
                                                                                                                                                                                                    </div>
                                                                                                                                                                                                    <div class="w-24 shrink-0">
                                                                                                                                                                                                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Monto</label>
                                                                                                                                                                                                        <input type="number" step="0.01" min="0" value="${autoAmount > 0 ? autoAmount.toFixed(2) : '0.00'}" placeholder="0.00"
                                                                                                                                                                                                            class="w-full py-2 px-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm tabular-nums cobro-pm-amount"
                                                                                                                                                                                                            oninput="updateCobroTotalPaid()" onfocus="autocompleteCobroAmount(this)">
                                                                                                                                                                                                    </div>
                                                                                                                                                                                                    <button type="button" onclick="removeCobroPaymentRow(this)" class="cobro-pm-delete-btn p-2 h-9 flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors shrink-0" title="Eliminar">
                                                                                                                                                                                                        <i class="ri-delete-bin-line text-lg"></i>
                                                                                                                                                                                                    </button>
                                                                                                                                                                                                </div>
                                                                                                                                                                                                <div class="cobro-pm-card-group hidden flex gap-2 items-end flex-wrap">
                                                                                                                                                                                                    <div class="flex-1 min-w-[100px]">
                                                                                                                                                                                                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Pasarela</label>
                                                                                                                                                                                                        <select class="cobro-pm-gateway w-full py-2 px-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm">
                                                                                                                                                                                                            ${buildCobroGatewayOptions()}
                                                                                                                                                                                                        </select>
                                                                                                                                                                                                    </div>
                                                                                                                                                                                                    <div class="flex-1 min-w-[100px]">
                                                                                                                                                                                                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Tarjeta</label>
                                                                                                                                                                                                        <select class="cobro-pm-card w-full py-2 px-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm">
                                                                                                                                                                                                            ${buildCobroCardOptions()}
                                                                                                                                                                                                        </select>
                                                                                                                                                                                                    </div>
                                                                                                                                                                                                </div>
                                                                                                                                                                                                <div class="cobro-pm-wallet-group hidden flex gap-2 items-end flex-wrap">
                                                                                                                                                                                                    <div class="flex-1 min-w-[120px]">
                                                                                                                                                                                                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Billetera (Yape, Plin...)</label>
                                                                                                                                                                                                        <select class="cobro-pm-wallet w-full py-2 px-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm">
                                                                                                                                                                                                            ${buildCobroWalletOptions()}
                                                                                                                                                                                                        </select>
                                                                                                                                                                                                    </div>
                                                                                                                                                                                                </div>
                                                                                                                                                                                                <div class="cobro-pm-bank-group hidden flex gap-2 items-end flex-wrap">
                                                                                                                                                                                                    <div class="flex-1 min-w-[120px]">
                                                                                                                                                                                                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Banco destino</label>
                                                                                                                                                                                                        <select class="cobro-pm-bank w-full py-2 px-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm">
                                                                                                                                                                                                            ${buildCobroBankOptions()}
                                                                                                                                                                                                        </select>
                                                                                                                                                                                                    </div>
                                                                                                                                                                                                </div>
                                                                                                                                                                                            `;
                        list.appendChild(row);
                        toggleCobroExtraFields(row);
                        if (!window.__splitCobroSyncing) {
                            updateCobroTotalPaid();
                        }
                    }

                    function applyCobroPaymentFieldsLocked(splitOn) {
                        const addBtn = document.getElementById('cobro-btn-add-payment-method');
                        if (addBtn) addBtn.classList.toggle('hidden', !!splitOn);
                        document.querySelectorAll('.cobro-pm-amount').forEach(inp => {
                            inp.readOnly = !!splitOn;
                            inp.classList.toggle('bg-gray-100', !!splitOn);
                            inp.classList.toggle('dark:bg-gray-700', !!splitOn);
                            inp.classList.toggle('cursor-not-allowed', !!splitOn);
                        });
                        document.querySelectorAll('.cobro-pm-delete-btn').forEach(btn => {
                            btn.classList.toggle('hidden', !!splitOn);
                        });
                    }

                    function updateCobroTotalPaid() {
                        const cb = document.getElementById('split-dividir-cuenta');
                        const splitOn = !!(cb && cb.checked && window.__splitAccount && window.__splitAccount.enabled);
                        if (splitOn && !window.__splitCobroSyncing) {
                            window.__splitCobroSyncing = true;
                            try {
                                syncCobroPaymentAmountsToSplitPartInner();
                            } finally {
                                window.__splitCobroSyncing = false;
                            }
                        }
                        applyCobroPaymentFieldsLocked(splitOn);
                        const inputs = document.querySelectorAll('.cobro-pm-amount');
                        let total = 0;
                        inputs.forEach(inp => {
                            total += parseFloat(inp.value || 0) || 0;
                        });
                        const el = document.getElementById('cobro-total-paid');
                        if (el) {
                            if (splitOn) {
                                try {
                                    const p = buildSplitPayloadForPayment();
                                    if (p) el.textContent = 'S/ ' + computeSplitPartTotal(p).toFixed(2);
                                    else el.textContent = 'S/ ' + total.toFixed(2);
                                } catch (e) {
                                    el.textContent = 'S/ ' + total.toFixed(2);
                                }
                            } else {
                                el.textContent = 'S/ ' + total.toFixed(2);
                            }
                        }
                        const lbl = document.getElementById('cobro-total-label');
                        const row = document.getElementById('cobro-order-total-row');
                        const orderDisp = document.getElementById('cobro-order-total-display');
                        const foot = document.getElementById('cobro-split-footnote');
                        if (lbl) {
                            lbl.textContent = splitOn ? 'Total dividido' : 'Total pagado';
                        }
                        if (row && orderDisp) {
                            if (splitOn) {
                                row.classList.remove('hidden');
                                const orderTot = getTotalsWithDelivery(currentTable?.items || []).total || 0;
                                orderDisp.textContent = 'S/ ' + orderTot.toFixed(2);
                            } else {
                                row.classList.add('hidden');
                            }
                        }
                        if (foot) {
                            foot.classList.toggle('hidden', !splitOn);
                        }
                        const btnLbl = document.getElementById('footer-cobro-btn-label');
                        if (btnLbl) {
                            btnLbl.textContent = splitOn ? 'Cobrar parte' : 'Cobrar';
                        }
                        const fh = document.getElementById('cobro-split-footer-hint');
                        if (fh) {
                            fh.classList.toggle('hidden', !splitOn);
                        }
                    }

                    function changeClient(selectEl) {
                        if (!selectEl || !currentTable) return;
                        const personId = selectEl.value ? parseInt(selectEl.value, 10) : null;
                        const opts = window.__orderClientOptions || [];
                        const selected = opts.find(o => String(o.id) === String(personId));
                        const clientName = selected ? (selected.client_name || selected.description || 'CLIENTES VARIOS') :
                            'CLIENTES VARIOS';
                        const clientLabel = selected ? (selected.description || clientName) : 'CLIENTES VARIOS';
                        currentTable.clientName = clientName;
                        currentTable.clientLabel = clientLabel;
                        currentTable.person_id = personId;
                        saveDB();
                        const headerInput = document.getElementById('header-client-name');
                        if (headerInput) headerInput.value = personId ? clientName : '';
                        const cobroInput = document.getElementById('cobro-client-input');
                        if (cobroInput) cobroInput.value = clientLabel;
                    }

                    function changeWaiter(selectEl) {
                        if (!selectEl || !currentTable) return;
                        const name = selectEl.options[selectEl.selectedIndex]?.text || 'Sin asignar';
                        currentTable.waiter = name;
                        saveDB();
                    }

                    function buildClientOption(person) {
                        if (!person || !person.person_id) return null;
                        return {
                            id: person.person_id,
                            description: person.description || person.name || 'Cliente',
                            client_name: person.name || 'Cliente',
                            document_number: person.document_number || ''
                        };
                    }

                    function setSelectedCobroClient(person) {
                        const option = buildClientOption(person);
                        if (!option) return;

                        const currentOptions = Array.isArray(window.__orderClientOptions) ? window.__orderClientOptions
                        .slice() : [];
                        const filtered = currentOptions.filter(item => String(item.id) !== String(option.id));
                        filtered.unshift(option);
                        window.__orderClientOptions = filtered;

                        window.dispatchEvent(new CustomEvent('update-combobox-options', {
                            detail: {
                                name: 'header_client_id',
                                options: filtered
                            }
                        }));

                        if (currentTable) {
                            currentTable.person_id = option.id;
                            currentTable.clientName = option.client_name;
                            currentTable.clientLabel = option.description;
                            saveDB();
                        }

                        const picker = document.getElementById('order-client-picker');
                        safeSetAlpineDataProperty(picker, 'clientId', option.id);

                        const headerInput = document.getElementById('header-client-name');
                        if (headerInput) {
                            headerInput.value = option.client_name || '';
                        }

                        if (typeof switchAsideTab === 'function') {
                            switchAsideTab('cobro');
                        }
                    }

                    async function submitQuickClientForm(event) {
                        event.preventDefault();
                        const form = event.target;
                        if (!form) return;

                        const submitButton = form.querySelector('button[type="submit"]');
                        const originalContent = submitButton ? submitButton.innerHTML : '';
                        if (submitButton) {
                            submitButton.disabled = true;
                            submitButton.innerHTML = '<i class="ri-loader-4-line animate-spin mr-1"></i> Guardando...';
                        }

                        try {
                            const formData = new FormData(form);
                            const response = await fetch(form.action, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: formData,
                            });

                            const data = await response.json().catch(() => ({}));

                            if (!response.ok) {
                                const errors = data.errors || {};
                                const firstError = Object.values(errors).flat()[0] || data.message ||
                                    'No se pudo guardar el cliente.';
                                showNotification('Cliente', firstError, 'error');
                                return;
                            }

                            setSelectedCobroClient(data);
                            window.dispatchEvent(new CustomEvent('close-person-modal'));
                            form.reset();
                            showNotification('Cliente', data.message || 'Cliente creado correctamente.', 'success');
                        } catch (error) {
                            showNotification('Cliente', 'No se pudo guardar el cliente en este momento.', 'error');
                        } finally {
                            if (submitButton) {
                                submitButton.disabled = false;
                                submitButton.innerHTML = originalContent;
                            }
                        }
                    }

                    function toggleOrderTakeAway() {
                        if (!currentTable) return;
                        const newType = currentTable.service_type === 'TAKE_AWAY' ? 'IN_SITU' : 'TAKE_AWAY';
                        changeServiceType(newType);
                    }

                    function changeServiceType(valOrEl) {
                        if (!currentTable) return;
                        const val = (valOrEl && typeof valOrEl === 'object') ? valOrEl.value : valOrEl;
                        currentTable.service_type = val;

                        // Mostrar/ocultar paneles
                        const deliveryContainer = document.getElementById('delivery-info-container');
                        const takeawayContainer = document.getElementById('takeaway-info-container');
                        const dinersSection = document.getElementById('diners-section');

                        if (deliveryContainer) deliveryContainer.classList.toggle('hidden', val !== 'DELIVERY');
                        if (takeawayContainer) takeawayContainer.classList.toggle('hidden', val !== 'TAKE_AWAY');
                        if (dinersSection) dinersSection.classList.toggle('hidden', val !== 'IN_SITU');

                        // Sincronizar input oculto si existe
                        const hiddenInput = document.getElementById('header-service-type-val');
                        if (hiddenInput) {
                            hiddenInput.value = val;
                        }

                        saveDB();
                        renderTicket();
                    }

                    function updateTakeAwayInfo() {
                        if (!currentTable) return;
                        const nameInp = document.getElementById('takeaway-client-name');
                        const timeInp = document.getElementById('takeaway-time');

                        if (nameInp) currentTable.client_name_extra = nameInp.value;
                        if (timeInp) currentTable.delivery_time = timeInp.value;

                        saveDB();
                        renderTicket();
                    }

                    function updateDeliveryInfo() {
                        if (!currentTable) return;
                        const addr = document.getElementById('delivery-address');
                        const phone = document.getElementById('delivery-phone');
                        const amount = document.getElementById('delivery-amount');

                        if (addr) currentTable.delivery_address = addr.value;
                        if (phone) currentTable.contact_phone = phone.value;
                        if (amount) currentTable.delivery_amount = parseFloat(amount.value) || 0;

                        saveDB();
                        renderTicket();
                    }

                    // Exponer funciones usadas desde onclick en el HTML (mismo ámbito tras re-render)
                    window.toggleCourtesyInput = toggleCourtesyInput;
                    window.toggleNoteInput = toggleNoteInput;
                    window.updateQty = updateQty;
                    window.setQtyFromInput = setQtyFromInput;
                    window.confirmRemoveLine = confirmRemoveLine;
                    window.removeFromCart = removeFromCart;
                    window.openRemoveQuantityLineModal = openRemoveQuantityLineModal;
                    window.applyRemoveQuantity = applyRemoveQuantity;
                    window.saveNote = saveNote;
                    window.toggleDelivered = toggleDelivered;
                    window.getImageUrl = getImageUrl;
                    window.goBack = goBack;
                    window.processOrder = processOrder;
                    window.processOrderPayment = processOrderPayment;
                    window.updateDiners = updateDiners;
                    window.switchAsideTab = switchAsideTab;
                    window.clearCobroClient = clearCobroClient;
                    window.addCobroPaymentMethod = addCobroPaymentMethod;
                    window.removeCobroPaymentRow = removeCobroPaymentRow;
                    window.updateCobroTotalPaid = updateCobroTotalPaid;
                    window.autocompleteCobroAmount = autocompleteCobroAmount;
                    window.toggleCobroExtraFields = toggleCobroExtraFields;
                    window.clearHeaderClientName = clearHeaderClientName;
                    window.updateHeaderClientName = updateHeaderClientName;
                    window.changeClient = changeClient;
                    window.changeWaiter = changeWaiter;
                    window.changeServiceType = changeServiceType;
                    window.toggleOrderTakeAway = toggleOrderTakeAway;
                    window.updateDeliveryInfo = updateDeliveryInfo;
                    window.updateTakeAwayInfo = updateTakeAwayInfo;
                    window.updateTakeawayDisposableInfo = updateTakeawayDisposableInfo;
                    window.toggleCobroDetailGlosa = toggleCobroDetailGlosa;
                    window.toggleCobroCreditFields = toggleCobroCreditFields;
                    window.calculateCobroDueDate = calculateCobroDueDate;
                    window.syncTicketCreditSummary = syncTicketCreditSummary;
                    window.openSplitAccountModal = openSplitAccountModal;
                    window.closeSplitAccountModal = closeSplitAccountModal;
                    window.applySplitAccountModal = applySplitAccountModal;
                    window.clearSplitDivision = clearSplitDivision;
                    window.setSplitModeTab = setSplitModeTab;
                    window.onSplitModeChange = onSplitModeChange;
                    window.initSplitPanel = initSplitPanel;
                    window.printPreAccountTicket = printPreAccountTicket;
                    window.openPreAccountPdfTab = openPreAccountPdfTab;
                    window.ensureWaiterPin = ensureWaiterPin;

                    // Fix scroll on page load
                    const initPosScroll = () => {
                        document.body.style.removeProperty('overflow');
                        document.body.style.removeProperty('overflow-y');
                        document.body.style.removeProperty('overflow-x');
                        document.documentElement.style.removeProperty('overflow');
                        document.documentElement.style.removeProperty('overflow-y');
                        document.documentElement.style.removeProperty('overflow-x');
                        setTimeout(fixScrollLayout, 50);
                        setTimeout(focusProductSearchInput, 80);
                    };
                    initPosScroll();
                    // Just in case it's a first page load
                    document.addEventListener('turbo:load', initPosScroll, { once: true });

                    const quickClientForm = document.getElementById('quick-client-form');
                    if (quickClientForm) {
                        quickClientForm.addEventListener('submit', submitQuickClientForm);
                    }
                    toggleCobroDetailGlosa();
                    toggleCobroCreditFields();
                })();
            </script>

        @endsection
