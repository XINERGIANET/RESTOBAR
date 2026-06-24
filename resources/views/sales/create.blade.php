@extends('layouts.app')

@section('title', 'Punto de Venta')

@section('content')
    <div class="px-4 md:px-6 pt-4 pb-2">

        <x-common.page-breadcrumb :crumbs="[['label' => 'Ventas', 'url' => route('sales.index')], ['label' => 'Nueva venta', 'url' => route('sales.create')]]" pageTitle="Nueva Venta" />

        @php
            $viewId = request('view_id');
            $salesIndexUrl = route('sales.index', $viewId ? ['view_id' => $viewId] : []);
            $personName = $person
                ? trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? ''))
                : 'Público General';
            if ($personName === '') {
                $personName = 'Público General';
            }
            $peopleCollection = $people ?? collect();
            $clientOptions = $peopleCollection
                ->map(function ($p) {
                    $name = trim(($p->first_name ?? '') . ' ' . ($p->last_name ?? ''));
                    if ($name === '' && !empty($p->document_number)) {
                        $name = $p->document_number;
                    }

                    return ['id' => $p->id, 'description' => $name];
                })
                ->values()
                ->all();
        @endphp
        <script>
            window.__salesClientOptions = @json($clientOptions);
        </script>

        <div class="mx-4 md:mx-6 flex flex-col dark:bg-gray-950 overflow-hidden rounded-t-2xl">
            {{-- Misma estructura visual que orders/create (sin mesa, mozo, personas, delivery/llevar) --}}
            <div data-turbo-cache="false"
                class="flex flex-col flex-1 min-h-0 h-[calc(100vh-theme(spacing.52))] lg:h-[calc(100vh-theme(spacing.40))]">
                {{-- lg:items-start: el aside NO se estira a toda la altura del panel productos (evita hueco enorme abajo)
                --}}
                <div class="flex-1 flex flex-col lg:flex-row min-h-0 bg-gray-50/50 dark:bg-gray-950/50 gap-3 p-3">
                    <div
                        class="flex-1 min-w-0 w-full lg:min-h-0 lg:overflow-hidden p-3 sm:p-4 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-800 flex flex-col min-h-0">
                        <div class="flex flex-col flex-1 min-h-0 min-w-0 overflow-hidden">
                            <div class="shrink-0 border-gray-300 px-2 sm:px-4 pt-3 pb-4">
                                <div id="categories-grid"
                                    class="flex flex-row flex-wrap gap-1.5 sm:gap-2 overflow-x-auto pb-3 overscroll-x-contain">
                                </div>
                            </div>

                            <!---Buscador de productos-->
                            <div class="px-2 sm:px-4 md:px-5 pt-3 pb-4">
                                <div class="relative">
                                    <i class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 fas fa-search"></i>
                                    <input type="text" id="product-search"
                                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#124731]/30 focus:border-[#124731] dark:bg-gray-800 dark:border-gray-700 dark:text-white"
                                        placeholder="Buscar producto">
                                </div>
                            </div>
                            <div class="flex-1 overflow-y-auto pt-2 sm:pt-3 min-h-0">
                                <div id="products-grid"
                                    class="px-2 sm:px-4 md:px-5 p-3 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2 sm:gap-4 content-start pb-6">
                                </div>
                            </div>
                        </div>
                    </div>
                    <aside
                        class="lg:w-[450px] w-full md:w-[350px] lg:shrink-0 mx-auto lg:mx-0 flex-none bg-white dark:bg-gray-900 border-t lg:border-t-0 lg:border-l border-gray-200 dark:border-gray-800 flex flex-col min-h-[450px] lg:min-h-0 overflow-x-hidden rounded-2xl shadow-sm"
es                        style="max-height: 80vh;">
                        <div class="flex w-full shrink-0 border-b border-gray-200 dark:border-gray-700">
                            <button type="button" id="tab-resumen" onclick="window.switchAsideTab?.('resumen')"
                                class="flex-1 py-3 px-4 text-sm font-bold transition-colors rounded-tl-2xl bg-[#124731] text-white">
                                Resumen
                            </button>
                            <button type="button" id="tab-cobro" onclick="window.switchAsideTab?.('cobro')"
                                class="flex-1 py-3 px-4 text-sm font-bold transition-colors bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:bg-[#124731]/10 dark:hover:bg-[#124731]/20 hover:text-[#124731] dark:hover:text-[#124731]">
                                Cobro
                            </button>
                        </div>

                        {{-- Fuera de aside-cobro / overflow-y-auto: el menú nativo del select no se recorta --}}
                        <div id="pos-sale-condition"
                            class="shrink-0 relative z-30 px-4 sm:px-5 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50/90 dark:bg-gray-900/90">
                            <label for="cobro-sale-type"
                                class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Tipo
                                de venta</label>
                            <select id="cobro-sale-type"
                                class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm transition focus:border-[#124731] focus:outline-none focus:ring-2 focus:ring-[#124731]/20 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-[#124731]"
                                onchange="toggleCobroSaleMode()">
                                <option value="CONTADO">Contado</option>
                                <option value="CREDITO">Crédito / Deuda</option>
                            </select>
                            <div id="cobro-credit-fields" class="mt-3 hidden space-y-2">
                                <div>
                                    <label for="cobro-credit-days"
                                        class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Días
                                        de crédito</label>
                                    <input type="number" id="cobro-credit-days" min="0" step="1" value="0"
                                        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm tabular-nums text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                </div>
                                <p class="text-[11px] text-amber-700 dark:text-amber-300/90">Sin anticipo o con abono parcial
                                    en métodos de pago.</p>
                            </div>
                        </div>

                        <div id="aside-resumen" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                            <div id="cart-container"
                                class="flex-1 overflow-y-auto min-h-[300px] lg:min-h-0 overflow-x-hidden p-3 sm:p-5 space-y-2 sm:space-y-3 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700"
                                style="max-height: 300px">
                            </div>
                            <div
                                class="shrink-0 w-full min-w-0 p-4 sm:p-5 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                                <div class="w-full min-w-0 space-y-2 sm:space-y-3 text-xs sm:text-sm">
                                    <div
                                        class="flex w-full min-w-0 items-baseline justify-between gap-3 text-gray-500 font-medium">
                                        <span class="shrink-0">Subtotal</span>
                                        <span
                                            class="shrink-0 text-right tabular-nums text-slate-700 dark:text-slate-300 whitespace-nowrap"
                                            id="ticket-subtotal">S/ 0.00</span>
                                    </div>
                                    <div
                                        class="flex w-full min-w-0 items-baseline justify-between gap-3 text-gray-500 font-medium">
                                        <span class="shrink-0">Impuestos</span>
                                        <span
                                            class="shrink-0 text-right tabular-nums text-slate-700 dark:text-slate-300 whitespace-nowrap"
                                            id="ticket-tax">S/ 0.00</span>
                                    </div>
                                    <div class="border-t border-dashed border-gray-300 dark:border-gray-600 my-2"></div>
                                    <div class="flex w-full min-w-0 items-center justify-between gap-3">
                                        <span
                                            class="min-w-0 text-base sm:text-lg font-bold text-slate-800 dark:text-white leading-tight">Total
                                            a pagar</span>
                                        <span
                                            class="shrink-0 text-right text-xl sm:text-2xl font-black tabular-nums text-[#124731] dark:text-[#124731] whitespace-nowrap"
                                            id="ticket-total">S/ 0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="aside-cobro" class="hidden flex-col flex-1 min-h-0 overflow-y-auto p-4 sm:p-5">
                            <div class="space-y-4">
                                <div>
                                    <label
                                        class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Cliente</label>
                                    <div class="relative">
                                        <div
                                            class="order-2 w-full flex shrink-0 items-center justify-center gap-1.5 overflow-visible sm:order-3 sm:w-auto sm:pl-2 sm:ml-0">
                                            <div class="relative z-[70] flex items-center gap-1 min-w-[220px] sm:min-w-0 overflow-visible"
                                                id="sales-client-picker"
                                                x-data="{
                                                                                                                                                                                                        person_id: {{ $person?->id ? (int) $person->id : 'null' }},
                                                                                                                                                                                                        init() {
                                                                                                                                                                                                            this.$nextTick(() => {
                                                                                                                                                                                                                if (typeof currentSale !== 'undefined' && currentSale.person_id) {
                                                                                                                                                                                                                    this.person_id = currentSale.person_id;
                                                                                                                                                                                                                }
                                                                                                                                                                                                            });
                                                                                                                                                                                                            this.$watch('person_id', () => {
                                                                                                                                                                                                                const opts = window.__salesClientOptions || [];
                                                                                                                                                                                                                const selected = opts.find(o => String(o.id) === String(this.person_id));
                                                                                                                                                                                                                const name = selected ? selected.description : 'Público General';
                                                                                                                                                                                                                if (typeof currentSale !== 'undefined') {
                                                                                                                                                                                                                    currentSale.person_id = this.person_id ? parseInt(this.person_id, 10) : null;
                                                                                                                                                                                                                    currentSale.clientName = name;
                                                                                                                                                                                                                    if (typeof saveDB === 'function') {
                                                                                                                                                                                                                        saveDB();
                                                                                                                                                                                                                    }
                                                                                                                                                                                                                }
                                                                                                                                                                                                                const cobroInput = document.getElementById('cobro-client-input');
                                                                                                                                                                                                                if (cobroInput) {
                                                                                                                                                                                                                    cobroInput.value = name;
                                                                                                                                                                                                                }
                                                                                                                                                                                                            });
                                                                                                                                                                                                        }
                                                                                                                                                                                                    }">
                                                <x-form.select.combobox :options="$clientOptions" x-model="person_id"
                                                    name="header_client_id" placeholder="Buscar cliente..." :compact="true"
                                                    class="w-full" />
                                                @if ($branch ?? null)
                                                    <button type="button"
                                                        class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-white border border-gray-200 text-gray-500 hover:bg-[#124731]/10 hover:text-[#124731] hover:border-[#124731]/30 shadow-sm transition-colors dark:bg-gray-900 dark:border-gray-700"
                                                        onclick="window.dispatchEvent(new CustomEvent('open-person-modal'))">
                                                        <i class="ri-user-add-line text-sm sm:text-base"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                        <button type="button" onclick="clearCobroClient()"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">

                                        </button>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label
                                            class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Documento</label>
                                        <select id="cobro-document-type"
                                            class="w-full py-2.5 px-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-slate-700 dark:text-slate-200 text-sm">
                                            @forelse(($documentTypes ?? []) as $dt)
                                                <option value="{{ $dt?->id }}" @selected((int) ($defaultDocumentTypeId ?? 0) === (int) ($dt?->id ?? 0))>{{ $dt?->name ?? '' }}</option>
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
                                                <option value="{{ $cr?->id }}" @selected((int) session('cash_register_id') === (int) $cr?->id)>
                                                    {{ $cr?->number ?? 'Caja ' . ($cr?->id ?? '') }}
                                                </option>
                                            @empty
                                                <option value="">Sin cajas</option>
                                            @endforelse
                                        </select>
                                    </div>
                                </div>
                                @if (!empty($clientOnLocalNetwork) && ($thermalPrinters ?? collect())->isNotEmpty())
                                    <div
                                        class="rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50/90 dark:bg-emerald-900/20 px-3 py-2.5">
                                        <p class="text-[11px] font-semibold text-emerald-900 dark:text-emerald-100 mb-1.5">
                                            <i class="ri-wifi-line align-middle"></i> WiFi del local: al cobrar se envía
                                            copia a la ticketera en red.
                                        </p>
                                        @if (($thermalPrinters ?? collect())->count() > 1)
                                            <label
                                                class="block text-[10px] font-bold uppercase tracking-wider text-emerald-800/80 dark:text-emerald-200/90 mb-1">Ticketera</label>
                                            <select id="cobro-thermal-printer"
                                                class="w-full py-2 px-3 rounded-lg border border-emerald-200 dark:border-emerald-700 bg-white dark:bg-gray-800 text-slate-700 dark:text-slate-200 text-xs">
                                                <option value="">Predeterminada (primera en lista)</option>
                                                @foreach ($thermalPrinters as $tp)
                                                    <option value="{{ $tp->id }}">{{ $tp->name }} —
                                                        {{ $tp->ip }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @endif
                                    </div>
                                @endif
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <label
                                            class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Métodos
                                            de pago</label>
                                        <button type="button" onclick="addCobroPaymentMethod()"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-[#124731] px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-[#0A2E1F] active:scale-95 transition-colors shrink-0">
                                            <i class="ri-add-line text-sm"></i> Agregar
                                        </button>
                                    </div>
                                    <div id="cobro-payment-methods-list" class="space-y-3 max-h-48 overflow-y-auto pr-1">
                                    </div>
                                    <div
                                        class="mt-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-100 dark:bg-gray-800/80 px-3 py-2.5">
                                        <div class="flex justify-between items-center">
                                            <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">Total
                                                pagado</span>
                                            <span class="text-base font-bold text-slate-800 dark:text-white tabular-nums"
                                                id="cobro-total-paid">S/ 0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="shrink-0 p-4 sm:p-5 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                            <div id="footer-resumen" class="flex justify-end">
                                <x-ui.button type="button" variant="secondary" size="sm" onclick="clearCart()"
                                    class="!gap-1.5 !max-w-full" title="Vaciar orden">
                                    <i class="ri-delete-bin-line text-base shrink-0"></i>
                                    <span class="sm:hidden">Vaciar</span>
                                    <span class="hidden sm:inline">Vaciar orden</span>
                                </x-ui.button>
                            </div>
                            <div id="footer-cobro" class="hidden flex justify-end">
                                <button type="button" id="checkout-button" onclick="processSale()"
                                    class="py-2.5 px-4 rounded-xl bg-[#124731] text-white font-bold text-xs sm:text-sm shadow-lg hover:bg-[#0A2E1F] active:scale-95 transition-all flex justify-center items-center gap-2">
                                    <i class="ri-bank-card-line text-base"></i>
                                    <span>Cobrar</span>
                                </button>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>

        @if ($branch ?? null)
            <x-ui.modal x-data="{ open: false }" @open-person-modal.window="open = true"
                @close-person-modal.window="open = false" :isOpen="false" :showCloseButton="false" class="max-w-4xl z-[100]">
                <div class="p-6 sm:p-8 bg-white dark:bg-gray-800">
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#124731]/10 text-[#124731] dark:bg-[#124731]/20 dark:text-[#124731]">
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

                    <form method="POST" data-quick-client-form data-client-combobox-name="header_client_id"
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
                                class="px-5 py-2.5 rounded-xl bg-[#124731] text-white font-semibold hover:bg-[#0A2E1F] shadow-lg shadow-[#124731]/30 transition-all">
                                <i class="ri-save-line mr-1"></i> Guardar Cliente
                            </button>
                        </div>
                    </form>
                </div>
            </x-ui.modal>
        @endif

        <div id="notification"
            class="fixed top-24 right-8 z-50 max-w-sm opacity-0 pointer-events-none transition-opacity duration-300"
            aria-live="polite"></div>

        {{-- NOTIFICACIONES TOAST --}}
        <div id="toast-container"
            class="fixed top-20 left-1/2 -translate-x-1/2 z-50 pointer-events-none flex flex-col gap-2 w-auto max-w-sm">
            {{-- Stock Error --}}
            <div id="stock-error-notification"
                class="transform transition-all duration-300 -translate-y-10 opacity-0 pointer-events-none bg-white dark:bg-gray-800 border-l-4 border-red-500 shadow-2xl rounded-r-lg p-4 flex items-center gap-3 min-w-[300px]">
                <div class="text-red-500"><i class="ri-error-warning-fill text-xl"></i></div>
                <div>
                    <p class="text-xs font-bold text-gray-900 dark:text-white uppercase">Stock Insuficiente</p>
                    <p id="stock-error-message" class="text-sm text-gray-600 dark:text-gray-300">Mensaje de error</p>
                </div>
            </div>

            {{-- Success Add --}}
            <div id="add-to-cart-notification"
                class="transform transition-all duration-300 -translate-y-10 opacity-0 pointer-events-none bg-slate-800 text-white shadow-2xl rounded-full px-6 py-3 flex items-center gap-3 min-w-[200px]">
                <i class="ri-check-line text-green-400 text-xl"></i>
                <div>
                    <p class="text-[10px] uppercase font-bold text-gray-400">Agregado</p>
                    <p id="notification-product-name" class="text-sm font-bold text-white truncate max-w-[180px]">Producto
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- ESTILOS CSS --}}


    {{-- LÓGICA JS --}}
    <script>
        (function () {
            // --- 1. CARGA DE DATOS (DATA LOGIC) ---
            const productsRaw = @json($products ?? []);
            const productBranchesRaw = @json($productBranches ?? ($productsBranches ?? []));
            const cashRegisters = @json($cashRegisters ?? []);

            // AQUÍ ESTABA EL PROBLEMA: Necesitamos pasar las categorías con imagen desde PHP
            const categoriesDB = @json($categories ?? []);

            const productBranches = Array.isArray(productBranchesRaw) ? productBranchesRaw : Object.values(
                productBranchesRaw || {});
            const serverCategories = @json($categories ?? []);
            const categoryIdsInBranch = (serverCategories || []).map(c => Number(c.id));
            // Solo productos con productBranch en sucursal Y categoría en category_branch
            const productsRawArr = Array.isArray(productsRaw) ? productsRaw : Object.values(productsRaw || {});
            const products = (productsRawArr || []).filter(p =>
                productBranches.some(pb => Number(pb.product_id) === Number(p.id)) &&
                categoryIdsInBranch.includes(Number(p.category_id))
            );

            const priceByProductId = new Map();
            const taxRateByProductId = new Map();
            const stockByProductId = new Map();
            const defaultTaxPct = 18;
            const allowZeroStockSales = @json($allowZeroStockSales ?? true);

            // Mapeo de precios y stock
            productBranches.forEach((pb) => {
                const pid = Number(pb.product_id ?? pb.id);
                if (!Number.isNaN(pid)) {
                    priceByProductId.set(pid, Number(pb.price ?? 0));
                    taxRateByProductId.set(pid, pb.tax_rate != null ? Number(pb.tax_rate) : defaultTaxPct);
                    stockByProductId.set(pid, Number(pb.stock ?? 0) || 0);
                }
            });

            const CATEGORY_ALL_ID = '__all__';
            const CATEGORY_FAVORITES_ID = '__favorites__';
            let selectedCategoryId = CATEGORY_FAVORITES_ID;
            let searchQuery = '';

            function isProductFavoriteSales(productId) {
                const pb = productBranches.find(p => Number(p.product_id) === Number(productId));
                return pb && String(pb.favorite || 'N').toUpperCase() === 'S';
            }
            const cobroPaymentMethods = @json($paymentMethods ?? []);
            const cobroPaymentGateways = @json($paymentGateways ?? []);
            const cobroCards = @json($cards ?? []);
            const cobroDigitalWallets = @json($digitalWallets ?? []);
            const cobroBanks = @json($banks ?? []);
            const salesProcessUrl = @json(route('sales.process'));
            const salesIndexUrl = @json($salesIndexUrl ?? route('sales.index'));
            const salesThermalPrintUrl = @json(route('sales.print.ticket.thermal'));
            const salesTicketPrintBaseUrl = @json(route('admin.sales.print.ticket', ['sale' => '__SALE__']));

            function openSaleTicketPdfTab(movementId) {
                if (!movementId) return;
                let ticketUrl = salesTicketPrintBaseUrl.replace('__SALE__', movementId);
                const currentUrl = new URL(window.location.href);
                const viewId = currentUrl.searchParams.get('view_id');
                if (viewId) {
                    ticketUrl += (ticketUrl.includes('?') ? '&' : '?') + 'view_id=' + encodeURIComponent(viewId);
                }
                window.open(ticketUrl, '_blank', 'noopener,noreferrer');
            }

            function escapeHtml(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function calculateTotalsFromItems(items) {
                let subtotal = 0,
                    tax = 0,
                    total = 0;
                (items || []).forEach(item => {
                    const qty = parseFloat(item.qty) || 0;
                    const courtesyQty = Math.min(parseFloat(item.courtesyQty) || 0, qty);
                    const paidQty = Math.max(0, qty - courtesyQty);
                    const price = parseFloat(item.price) || 0;
                    const lineTotal = paidQty * price;
                    const rate = (taxRateByProductId.get(Number(item.pId)) ?? defaultTaxPct) / 100;
                    const lineSubtotal = rate > 0 ? (lineTotal / (1 + rate)) : lineTotal;
                    subtotal += lineSubtotal;
                    tax += lineTotal - lineSubtotal;
                    total += lineTotal;
                });
                return {
                    subtotal: Math.round(subtotal * 100) / 100,
                    tax: Math.round(tax * 100) / 100,
                    total: Math.round(total * 100) / 100
                };
            }

            // --- FUNCIONES AUXILIARES ---

            function getImageUrl(imgUrl) {
                if (imgUrl && String(imgUrl).trim() !== '') return imgUrl;
                return 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y5ZmFmYiIvPjwvc3ZnPg==';
            }

            function setSaleType(value) {
                const sel = document.getElementById('header-sale-type');
                if (!sel) return;
                sel.value = value;
                sel.dispatchEvent(new Event('change', {
                    bubbles: true
                }));
            }

            // --- STORAGE ---
            // - Recargar pestaña (F5): se mantiene el carrito (localStorage + misma clave).
            // - Navegar a otra pantalla del sistema (Turbo) y volver: se limpia en turbo:before-visit.
            const ACTIVE_SALE_KEY_STORAGE = 'restaurantActiveSaleKey';

            function normalizePathForSales(pathname) {
                return String(pathname || '').replace(/\/+$/, '') || '';
            }

            function isVentasCreatePath(pathname) {
                return normalizePathForSales(pathname).endsWith('/ventas/create');
            }

            function clearIncompleteSalesCartStorage() {
                try {
                    const dbClean = JSON.parse(localStorage.getItem('restaurantDB') || '{}');
                    const key = localStorage.getItem(ACTIVE_SALE_KEY_STORAGE);
                    if (key && dbClean[key] && dbClean[key].status !== 'completed') {
                        delete dbClean[key];
                        localStorage.setItem('restaurantDB', JSON.stringify(dbClean));
                    }
                    localStorage.removeItem(ACTIVE_SALE_KEY_STORAGE);
                } catch (_) { }
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

            /** Turbo 8 puede enviar detail.url como string o como URL */
            function getTurboBeforeVisitUrl(e) {
                const u = e.detail?.url;
                if (u == null) {
                    return null;
                }
                if (typeof u === 'string') {
                    return u;
                }
                if (typeof u.href === 'string') {
                    return u.href;
                }
                return null;
            }

            if (!window.__salesCreateTurboLeaveBound) {
                window.__salesCreateTurboLeaveBound = true;
                document.addEventListener('turbo:before-visit', function (e) {
                    if (!isVentasCreatePath(window.location.pathname)) {
                        return;
                    }
                    const rawUrl = getTurboBeforeVisitUrl(e);
                    if (!rawUrl) {
                        return;
                    }
                    let destPath;
                    try {
                        destPath = new URL(rawUrl, window.location.href).pathname;
                    } catch (_) {
                        return;
                    }
                    if (normalizePathForSales(destPath) === normalizePathForSales(window.location.pathname)) {
                        return;
                    }
                    clearIncompleteSalesCartStorage();
                });

                /**
                 * Botón "atrás" del navegador: puede restaurar /ventas/create desde bfcache
                 * con el carrito en memoria aunque ya se hubiera limpiado localStorage.
                 */
                window.addEventListener('pageshow', function (event) {
                    if (!event.persisted || !isVentasCreatePath(window.location.pathname)) {
                        return;
                    }
                    const key = localStorage.getItem(ACTIVE_SALE_KEY_STORAGE);
                    const dbSync = JSON.parse(localStorage.getItem('restaurantDB') || '{}');
                    // Sin borrador en storage pero la página viene de bfcache: el JS en memoria puede estar viejo
                    if (!key || !dbSync[key]) {
                        window.location.reload();
                    }
                });

                /**
                 * Atrás del navegador / cerrar pestaña: descarga real de la página (Turbo no siempre dispara).
                 * No limpiar si esta descarga es por RECARGAR (F5): el tipo de navegación suele ser "reload".
                 * Nota: si el usuario recargó y luego sale, el tipo puede seguir "reload" y no se limpia (caso raro).
                 */
                window.addEventListener('pagehide', function () {
                    if (!isVentasCreatePath(window.location.pathname)) {
                        return;
                    }
                    try {
                        const nav = performance.getEntriesByType('navigation')[0];
                        if (nav && nav.type === 'reload') {
                            return;
                        }
                    } catch (_) { }
                    clearIncompleteSalesCartStorage();
                });
            }

            let db = JSON.parse(localStorage.getItem('restaurantDB') || '{}');
            let activeKey = localStorage.getItem(ACTIVE_SALE_KEY_STORAGE);

            if (!activeKey || !db[activeKey] || db[activeKey]?.status === 'completed') {
                activeKey = `sale-${Date.now()}`;
                localStorage.setItem(ACTIVE_SALE_KEY_STORAGE, activeKey);
            }

            let currentSale = db[activeKey] || {
                id: Date.now(),
                clientName: 'Publico General',
                status: 'in_progress',
                items: [],
            };

            db[activeKey] = currentSale;
            localStorage.setItem('restaurantDB', JSON.stringify(db));

            // Enlace normal: Turbo dispara turbo:before-visit y limpia el carrito antes de salir.
            // Evitar preventDefault + location.href: antes no corría before-visit y el flujo fallaba al volver.

            function saveDB() {
                db[activeKey] = currentSale;
                localStorage.setItem('restaurantDB', JSON.stringify(db));
            }

            // --- 2. RENDER PRODUCTS ---
            function renderProducts() {
                const grid = document.getElementById('products-grid');
                if (!grid) return;
                grid.innerHTML = '';

                let rendered = 0;

                let productsToShow = products;
                if (selectedCategoryId === CATEGORY_ALL_ID) {
                    productsToShow = products;
                } else if (selectedCategoryId === CATEGORY_FAVORITES_ID) {
                    productsToShow = products.filter(p => isProductFavoriteSales(p.id));
                } else {
                    productsToShow = products.filter(p => p.category_id == selectedCategoryId);
                }

                const q = String(searchQuery || '').trim().toLowerCase();
                if (q.length > 0) {
                    const queryWords = q.split(/\s+/).filter(word => word.length > 0);
                    productsToShow = productsToShow.filter(p => {
                        const name = String(p.name || '').toLowerCase();
                        const categoryName = String(p.category || '').toLowerCase();
                        const combined = name + " " + categoryName;
                        // Debe contener TODAS las palabras ingresadas (AND search)
                        return queryWords.every(word => combined.includes(word));
                    });
                }

                productsToShow.forEach((prod) => {
                    const productId = Number(prod.id);
                    const price = priceByProductId.get(productId);

                    if (typeof price === 'undefined') return;

                    const safeName = (prod.name || 'Sin nombre').replace(/</g, '&lt;');
                    const safePrice = Number(price).toFixed(2);
                    const stockVal = stockByProductId.get(productId) ?? 0;
                    const stockText = !isNaN(stockVal) ? Number(stockVal).toFixed(2) : '0.00';
                    const hasImg = prod.img && String(prod.img).trim() !== '';
                    const imageUrl = getImageUrl(prod.img);

                    const el = document.createElement('div');
                    el.className =
                        'group cursor-pointer transition-transform duration-200 hover:scale-105 h-full flex';
                    el.addEventListener('click', function () {
                        addToCart(prod, price);
                    });

                    el.innerHTML =
                        `
                                <div class="rounded-2xl overflow-hidden p-4 sm:p-5 bg-white dark:bg-slate-800/60 border-2 border-[#124731]/20 dark:border-[#124731]/40 hover:border-[#124731] dark:hover:border-[#124731] transition-all duration-200 hover:-translate-y-0.5 flex flex-col items-center text-center h-full w-full">
                                <div class="hidden sm:flex w-20 h-20 rounded-full bg-[#124731] items-center justify-center shrink-0 overflow-hidden mb-3">
                                ${hasImg
                            ? `<img src="${imageUrl}" alt="${safeName}" class="w-full h-full object-contain rounded-full object-cover object-center" loading="lazy" onerror="this.parentElement.innerHTML='<i class=\\'ri-restaurant-2-line text-2xl sm:text-3xl text-white\\'></i>'">`
                            : `<i class="ri-restaurant-2-line text-2xl sm:text-3xl text-white"></i>`
                        }
                                </div>
                                                                                                                                                                                                <h4 class="font-semibold text-gray-900 dark:text-white text-sm sm:text-base line-clamp-2 leading-tight mb-1 min-h-[2.5rem]">${safeName}</h4>
                                                                                                                                                                                                <span class="text-base sm:text-lg font-bold text-[#124731] dark:text-[#124731]">S/ ${safePrice}</span>
                                                                                                                                                                                                <span class="mt-1 text-xs font-medium text-gray-500 dark:text-gray-400">Stock: ` +
                        stockText + `</span>
                                                                                                                                                                                            </div>
                                                                                                                                                                                        `;

                    grid.appendChild(el);
                    rendered++;
                });

                if (rendered === 0) {
                    const emptyMsg = (q.length > 0) ?
                        'No se encontraron productos' :
                        (selectedCategoryId === CATEGORY_FAVORITES_ID ?
                            'No hay productos favoritos en esta sucursal. Marca favoritos en el producto o elige Todos.' :
                            'No se encontraron productos');
                    grid.innerHTML = `
                                                                                                                                                                                            <div class="col-span-full flex flex-col items-center justify-center py-20 text-gray-400 px-4 text-center">
                                                                                                                                                                                                <div class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mb-3">
                                                                                                                                                                                                    <i class="ri-search-line text-2xl opacity-50"></i>
                                                                                                                                                                                                </div>
                                                                                                                                                                                                <p class="text-sm font-medium">${emptyMsg}</p>
                                                                                                                                                                                            </div>
                                                                                                                                                                                        `;
                }
            }

            // --- 3. RENDER CATEGORIES (CON IMAGEN) ---
            function renderCategoryFilters() {
                const container = document.getElementById('categories-grid');
                if (!container) return;
                container.innerHTML = '';
                const mobileOnlyQuickFilters = window.matchMedia('(max-width: 767.98px)').matches;
                if (mobileOnlyQuickFilters && selectedCategoryId !== CATEGORY_ALL_ID && selectedCategoryId !== CATEGORY_FAVORITES_ID) {
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
                        'bg-[#124731] text-white border-[#124731] shadow-sm' :
                        'bg-white dark:bg-slate-800 text-gray-700 dark:text-gray-200 border-gray-300 dark:border-slate-600 hover:border-[#124731] hover:text-[#124731] dark:hover:text-[#124731]'
                ].join(' ');
                favBtn.onclick = function () {
                    selectedCategoryId = CATEGORY_FAVORITES_ID;
                    renderCategoryFilters();
                    renderProducts();
                };
                favBtn.innerHTML = `<i class="ri-star-fill text-lg"></i><span>Favoritos</span>`;
                container.appendChild(favBtn);

                // Botón "Todos"
                const allBtn = document.createElement('button');
                allBtn.type = 'button';
                const isAllActive = selectedCategoryId === CATEGORY_ALL_ID;
                allBtn.className = [
                    'inline-flex items-center gap-2 px-2.5 py-1.5 rounded-full text-xs sm:text-sm font-semibold',
                    'border transition-all duration-150 whitespace-nowrap cursor-pointer shrink-0',
                    isAllActive ?
                        'bg-[#124731] text-white border-[#124731] shadow-sm' :
                        'bg-white dark:bg-slate-800 text-gray-700 dark:text-gray-200 border-gray-300 dark:border-slate-600 hover:border-[#124731] hover:text-[#124731] dark:hover:text-[#124731]'
                ].join(' ');
                allBtn.onclick = function () {
                    selectedCategoryId = CATEGORY_ALL_ID;
                    renderCategoryFilters();
                    renderProducts();
                };
                allBtn.innerHTML = `<i class="ri-apps-line text-lg"></i><span>Todos</span>`;
                container.appendChild(allBtn);

                if (mobileOnlyQuickFilters || !serverCategories || serverCategories.length === 0) {
                    renderProducts();
                    return;
                }

                serverCategories.forEach(cat => {
                    const el = document.createElement('button');
                    const categoryName = (cat.name || 'Sin nombre').replace(/</g, '&lt;');
                    const imageUrl = getImageUrl(cat.img);
                    const isActive = selectedCategoryId === cat.id;
                    el.type = 'button';
                    el.className = [
                        'inline-flex items-center gap-2 px-2.5 py-1.5 rounded-full text-xs sm:text-sm font-semibold',
                        'border transition-all duration-150 whitespace-nowrap cursor-pointer shrink-0',
                        isActive ?
                            'bg-[#124731] text-white border-[#124731] shadow-sm' :
                            'bg-white dark:bg-slate-800 text-gray-700 dark:text-gray-200 border-gray-300 dark:border-slate-600 hover:border-[#124731] hover:text-[#124731] dark:hover:text-[#124731]'
                    ].join(' ');
                    el.onclick = function () {
                        selectedCategoryId = cat.id;
                        renderCategoryFilters();
                        renderProducts();
                    };
                    el.innerHTML = `
                                                                                                                                                                                            <img src="${imageUrl}" alt="${categoryName}"
                                                                                                                                                                                                class="w-6 h-6 rounded-full object-cover shrink-0 border ${isActive ? 'border-[#124731]/30' : 'border-gray-200 dark:border-slate-600'}"
                                                                                                                                                                                                onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22200%22%3E%3Crect fill=%22%23e5e7eb%22 width=%22200%22 height=%22200%22/%3E%3C/svg%3E'">
                                                                                                                                                                                            <span>${categoryName}</span>
                                                                                                                                                                                        `;
                    container.appendChild(el);
                });
            }

            // --- 4. RENDER TICKET ---
            function renderTicket() {
                const container = document.getElementById('cart-container');
                if (!container) return;

                container.innerHTML = '';
                let subtotal = 0;

                if (!currentSale.items || currentSale.items.length === 0) {
                    container.innerHTML = `
                                                                                                                                                                                            <div class="flex flex-col items-center justify-center text-gray-300 opacity-60">
                                                                                                                                                                                                <i class="fas fa-utensils text-3xl mb-2"></i>
                                                                                                                                                                                                <p class="font-medium text-sm">Sin productos</p>
                                                                                                                                                                                            </div>`;
                } else {
                    const ticketSavedTime = sessionStorage.getItem('last_saved_time') || '';
                    currentSale.items.forEach((item, index) => {
                        const prod = products.find((p) => Number(p.id) === Number(item.pId));
                        if (!prod) return;

                        const itemPrice = Number(item.price) || 0;
                        const itemQty = Number(item.qty) || 0;
                        const courtesyQty = Math.min(Number(item.courtesyQty) || 0, itemQty);
                        const paidQty = Math.max(0, itemQty - courtesyQty);
                        const itemTotal = itemPrice * paidQty;
                        const noteText = typeof item.note === 'string' ? item.note.trim() : '';
                        const hasNote = noteText !== '';
                        const productName = escapeHtml(prod.name || 'Sin nombre');
                        const itemNote = escapeHtml(noteText || '');

                        const statusLabel = 'Venta';
                        const statusClass =
                            'bg-[#124731]/15 text-[#124731] border border-[#124731]/35 dark:text-[#124731]/80 dark:border-[#124731]/40';

                        const qtyMinusOnclick = `onclick="updateQty(${index}, -1)"`;
                        const qtyMinusClass = ' hover:bg-slate-100 dark:hover:bg-slate-700 font-bold';

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

                        const row = document.createElement('div');
                        row.className =
                            'cart-item-row group relative mb-3 rounded-xl overflow-hidden border border-slate-200 bg-white text-slate-900 shadow-md dark:border-zinc-600/50 dark:bg-[#252526] dark:text-zinc-100 dark:shadow-lg dark:shadow-black/40';

                        row.innerHTML = `
                                                                                                                                                                                        <div class="flex flex-col gap-3 p-4 sm:p-4">
                                                                                                                                                                                                <div class="flex items-start justify-between gap-2">
                                                                                                                                                                                                    <div class="min-w-0 flex-1">
                                                                                                                                                                                                        <h3 class="font-bold text-[15px] sm:text-base leading-snug tracking-tight text-slate-900 dark:text-white">${productName}</h3>
                                                                                                                                                                                                        <p class="mt-1 text-[11px] sm:text-xs text-slate-500 dark:text-zinc-400 font-medium tabular-nums">${ticketSavedTime ? ticketSavedTime + ' · ' : ''}S/ ${itemPrice.toFixed(2)} <span class="text-slate-400 dark:text-zinc-500 font-normal">c/u</span></p>
                                                                                                                                                                                                    </div>
                                                                                                                                                                                                    <span class="shrink-0 px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-wide ${statusClass}">${statusLabel}</span>
                                                                                                                                                                                                </div>

                                                                                                                                                                                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-lg bg-slate-50 border border-slate-200 px-2.5 py-2.5 dark:bg-zinc-900/50 dark:border-zinc-600/50">
                                                                                                                                                                                                    <div class="flex justify-center items-center gap-0.5 rounded-lg bg-white px-0.5 py-0.5 border border-slate-200 dark:bg-zinc-800/80 dark:border-zinc-600/60">
                                                                                                                                                                                                        <button type="button" ${qtyMinusOnclick} class="w-9 h-9 flex items-center justify-center rounded-md transition-all text-slate-600 dark:text-zinc-300 ${qtyMinusClass}">
                                                                                                                                                                                                            <i class="ri-subtract-line text-base"></i>
                                                                                                                                                                                                        </button>
                                                                                                                                                                                                        <input type="number" value="${item.qty}" min="1" onchange="setQtyFromInput(${index}, this)" class="w-11 h-9 text-center text-sm font-bold bg-transparent border-none focus:ring-0 focus:outline-none tabular-nums text-slate-900 dark:text-white p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                                                                                                                                                                                        <button type="button" onclick="updateQty(${index}, 1)" class="w-9 h-9 flex items-center justify-center rounded-md hover:bg-slate-100 dark:hover:bg-zinc-700 text-slate-600 dark:text-zinc-300 transition-all font-bold">
                                                                                                                                                                                                            <i class="ri-add-line text-base"></i>
                                                                                                                                                                                                        </button>
                                                                                                                                                                                                    </div>
                                                                                                                                                                                                    <div class="text-center sm:text-right flex flex-col justify-center">
                                                                                                                                                                                                        <span class="text-[10px] text-slate-500 dark:text-zinc-500 uppercase font-bold tracking-wider leading-none mb-0.5">Subtotal</span>
                                                                                                                                                                                                        <span class="text-lg font-bold tabular-nums leading-none text-slate-900 dark:text-white">S/ ${itemTotal.toFixed(2)}</span>
                                                                                                                                                                                                    </div>
                                                                                                                                                                                                </div>

                                                                                                                                                                                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5 border-t border-slate-200 pt-2.5 dark:border-zinc-700/60">
                                                                                                                                                                                                    <button type="button" onclick="toggleNoteInput(${index})" class="flex h-7 items-center justify-center rounded-lg px-2 py-1 text-xs font-medium transition-colors ${noteBtnActive ? 'bg-[#124731]/10 text-[#124731] dark:bg-[#124731]/20 dark:text-[#124731]' : 'text-slate-500 hover:bg-slate-100 hover:text-[#124731] dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-[#124731]'}">
                                                                                                                                                                                                        <i class="${hasNote ? 'ri-chat-1-fill' : 'ri-chat-1-line'}"></i> ${hasNote ? 'Editar nota' : 'Nota'}
                                                                                                                                                                                                    </button>
                                                                                                                                                                                                    <button type="button" onclick="toggleCourtesyInput(${index})" class="flex h-7 items-center justify-center rounded-lg px-2 py-1 text-xs font-medium transition-colors ${courtesyBtnActive ? 'bg-[#124731]/10 text-[#124731] dark:bg-[#124731]/20 dark:text-[#124731]' : 'text-slate-500 hover:bg-slate-100 hover:text-[#124731] dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-[#124731]'}">
                                                                                                                                                                                                        <i class="${courtesyBtnActive ? 'ri-star-fill' : 'ri-star-line'}"></i> Cortesía
                                                                                                                                                                                                    </button>
                                                                                                                                                                                                    <button type="button" onclick="removeFromCart(${index})" class="ml-auto flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-zinc-400 transition-colors hover:bg-red-500/10 hover:text-red-500 dark:text-zinc-500 dark:hover:text-red-400" title="Eliminar">
                                                                                                                                                                                                        <i class="ri-delete-bin-line text-lg"></i>
                                                                                                                                                                                                    </button>
                                                                                                                                                                                                </div>

                                                                                                                                                                                                <div id="note-box-${index}" class="${showNoteBox ? '' : 'hidden'}">
                                                                                                                                                                                                    <textarea rows="2" onblur="saveNote(${index}, this.value)" placeholder="Ej: Sin cebolla, término medio..." class="w-full min-h-[3.25rem] resize-y rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 placeholder:text-slate-400 focus:border-[#124731] focus:outline-none focus:ring-2 focus:ring-[#124731]/20 dark:border-zinc-600 dark:bg-zinc-900/80 dark:text-zinc-100 dark:placeholder:text-zinc-500 dark:focus:border-[#124731]">${itemNote}</textarea>
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
                                                                                                                                                                                        </div>
                                                                                                                                                                                    `;
                        container.appendChild(row);
                    });
                }

                let subtotalBase = 0;
                let tax = 0;
                (currentSale.items || []).forEach((item) => {
                    const qty = Number(item.qty) || 0;
                    const courtesyQty = Math.min(Number(item.courtesyQty) || 0, qty);
                    const paidQty = Math.max(0, qty - courtesyQty);
                    const itemTotal = (Number(item.price) || 0) * paidQty;
                    const taxPct = taxRateByProductId.get(Number(item.pId)) ?? defaultTaxPct;
                    const taxVal = taxPct / 100;
                    const itemSubtotal = taxVal > 0 ? itemTotal / (1 + taxVal) : itemTotal;
                    subtotalBase += itemSubtotal;
                    tax += itemTotal - itemSubtotal;
                });
                const total = subtotalBase + tax;

                document.getElementById('ticket-subtotal').innerText = 'S/ ' + subtotalBase.toFixed(2);
                document.getElementById('ticket-tax').innerText = 'S/ ' + tax.toFixed(2);
                document.getElementById('ticket-total').innerText = 'S/ ' + total.toFixed(2);

                // Actualizar montos de Cobro dinámicamente cuando cambia el carrito
                syncCobroAmountsWithCart(total);
            }

            function syncCobroAmountsWithCart(orderTotal) {
                const list = document.getElementById('cobro-payment-methods-list');
                if (!list) return;
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
                updateCobroTotalPaid();
            }

            // --- HANDLERS (Expuestos globalmente) ---
            function addToCart(prod, price) {
                const productId = Number(prod.id);
                if (Number.isNaN(productId)) return;
                if (!Array.isArray(currentSale.items)) currentSale.items = [];

                const stock = stockByProductId.get(productId) ?? 0;
                const existing = currentSale.items.find((i) => Number(i.pId) === productId);
                const qtyToAdd = existing ? existing.qty + 1 : 1;

                if (!allowZeroStockSales && qtyToAdd > stock) {
                    showStockError(prod.name || 'Producto', stock);
                    return;
                }

                if (existing) {
                    existing.qty += 1;
                } else {
                    currentSale.items.push({
                        pId: productId,
                        name: prod.name || '',
                        qty: 1,
                        price: Number(price) || 0,
                        note: '',
                        courtesyQty: 0,
                    });
                }
                saveDB();
                renderTicket();
                showNotification(prod.name || 'Producto');
            }

            function updateQty(index, delta) {
                if (!currentSale.items[index]) return;
                const item = currentSale.items[index];
                if (!allowZeroStockSales && delta > 0) {
                    const productId = Number(item.pId);
                    const stock = stockByProductId.get(productId) ?? 0;
                    if (item.qty + delta > stock) {
                        showStockError(item.name || 'Producto', stock);
                        return;
                    }
                }
                item.qty += delta;
                if (item.qty <= 0) {
                    currentSale.items.splice(index, 1);
                } else {
                    const cq = parseFloat(item.courtesyQty) || 0;
                    if (cq > item.qty) item.courtesyQty = item.qty;
                }
                saveDB();
                renderTicket();
            }

            function setQtyFromInput(index, inputEl) {
                if (!currentSale.items || !currentSale.items[index]) return;
                const item = currentSale.items[index];
                const raw = parseInt(inputEl.value, 10);
                const newQty = isNaN(raw) || raw < 1 ? 1 : raw;
                const productId = Number(item.pId);
                const stock = stockByProductId.get(productId) ?? 0;
                if (!allowZeroStockSales && newQty > stock) {
                    showStockError(item.name || 'Producto', stock);
                    inputEl.value = item.qty;
                    return;
                }
                const oldQty = Number(item.qty) || 0;
                if (newQty === oldQty) {
                    inputEl.value = newQty;
                    return;
                }
                item.qty = newQty;
                const cq = parseFloat(item.courtesyQty) || 0;
                if (cq > newQty) item.courtesyQty = newQty;
                saveDB();
                renderTicket();
            }

            // NUEVA FUNCIÓN: Para eliminar el ítem completo de una vez
            function removeItem(index) {
                if (!currentSale.items[index]) return;
                currentSale.items.splice(index, 1);
                saveDB();
                renderTicket();
            }

            function removeFromCart(index) {
                if (!currentSale.items || index < 0 || index >= currentSale.items.length) return;
                const item = currentSale.items[index];
                const qty = parseInt(item.qty, 10) || 1;
                updateQty(index, -qty);
            }

            function setCourtesyQty(index, inputEl) {
                if (!currentSale.items || !currentSale.items[index]) return;
                let val = parseFloat(inputEl.value);
                if (isNaN(val) || val < 0) val = 0;
                const item = currentSale.items[index];
                const maxQty = Number(item.qty) || 0;
                if (val > maxQty) val = maxQty;
                item.courtesyQty = val;
                inputEl.value = val;
                saveDB();
                renderTicket();
            }

            function toggleCourtesyInput(index) {
                if (!currentSale.items || !currentSale.items[index]) return;
                const item = currentSale.items[index];
                const hasC = (parseFloat(item.courtesyQty) || 0) > 0;
                const shown = item.courtesyOpen === true || (item.courtesyOpen === undefined && hasC);
                item.courtesyOpen = !shown;
                saveDB();
                renderTicket();
            }

            function changeCourtesyQty(index, delta) {
                if (!currentSale.items || !currentSale.items[index]) return;
                const item = currentSale.items[index];
                const maxQty = parseFloat(item.qty) || 0;
                let v = (parseFloat(item.courtesyQty) || 0) + delta;
                v = Math.max(0, Math.min(maxQty, v));
                item.courtesyQty = v;
                saveDB();
                renderTicket();
            }

            function setupQuickClientCreate() {
                const form = document.querySelector('form[data-quick-client-form]');
                if (!form) return;
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

                        const comboName = form.dataset.clientComboboxName || 'header_client_id';
                        const label = [data.name, data.document_number].filter(Boolean).join(' - ') ||
                            'Cliente';
                        const newOpts = [...(window.__salesClientOptions || [])];
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
                        window.__salesClientOptions = newOpts;
                        window.dispatchEvent(new CustomEvent('update-combobox-options', {
                            detail: {
                                name: comboName,
                                options: newOpts
                            }
                        }));
                        const root = document.getElementById('sales-client-picker');
                        safeSetAlpineDataProperty(root, 'person_id', data.id);
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

            function switchAsideTab(tab) {
                const resumen = document.getElementById('aside-resumen');
                const cobro = document.getElementById('aside-cobro');
                const btnResumen = document.getElementById('tab-resumen');
                const btnCobro = document.getElementById('tab-cobro');
                const footerResumen = document.getElementById('footer-resumen');
                const footerCobro = document.getElementById('footer-cobro');
                const productsGrid = document.getElementById('products-grid');
                const categoriesGrid = document.getElementById('categories-grid');
                const searchInput = document.getElementById('product-search') || document.getElementById('search-products');
                const searchClearBtn = document.getElementById('search-products-clear');
                if (tab === 'cobro') {
                    resumen?.classList.add('hidden');
                    cobro?.classList.remove('hidden');
                    cobro?.classList.add('flex');
                    footerResumen?.classList.add('hidden');
                    footerCobro?.classList.remove('hidden');
                    btnResumen?.classList.remove('bg-[#124731]', 'text-white');
                    btnResumen?.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
                    btnCobro?.classList.remove('bg-gray-100', 'dark:bg-gray-800', 'text-gray-500',
                        'dark:text-gray-400');
                    btnCobro?.classList.add('bg-[#124731]', 'text-white');
                    if (productsGrid) {
                        productsGrid.classList.add('pointer-events-none', 'opacity-60');
                    }
                    if (categoriesGrid) {
                        categoriesGrid.classList.add('pointer-events-none', 'opacity-60');
                    }
                    if (searchInput) {
                        searchInput.setAttribute('disabled', 'disabled');
                        searchInput.classList.add('bg-gray-100', 'cursor-not-allowed', 'dark:bg-gray-800');
                    }
                    if (searchClearBtn) {
                        searchClearBtn.setAttribute('disabled', 'disabled');
                    }
                } else {
                    cobro?.classList.add('hidden');
                    cobro?.classList.remove('flex');
                    resumen?.classList.remove('hidden');
                    footerCobro?.classList.add('hidden');
                    footerCobro?.classList.remove('flex');
                    footerResumen?.classList.remove('hidden');
                    btnCobro?.classList.remove('bg-[#124731]', 'text-white');
                    btnCobro?.classList.add('bg-gray-100', 'dark:bg-gray-800', 'text-gray-500', 'dark:text-gray-400');
                    btnResumen?.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700',
                        'dark:text-gray-300');
                    btnResumen?.classList.add('bg-[#124731]', 'text-white');
                    if (productsGrid) {
                        productsGrid.classList.remove('pointer-events-none', 'opacity-60');
                    }
                    if (categoriesGrid) {
                        categoriesGrid.classList.remove('pointer-events-none', 'opacity-60');
                    }
                    if (searchInput) {
                        searchInput.removeAttribute('disabled');
                        searchInput.classList.remove('bg-gray-100', 'cursor-not-allowed', 'dark:bg-gray-800');
                    }
                    if (searchClearBtn) {
                        searchClearBtn.removeAttribute('disabled');
                    }
                }
                if (tab === 'cobro' && typeof toggleCobroSaleMode === 'function') {
                    toggleCobroSaleMode();
                }
            }

            function toggleCobroSaleMode() {
                const el = document.getElementById('cobro-sale-type');
                const credit = el?.value === 'CREDITO';
                const wrap = document.getElementById('cobro-credit-fields');
                if (wrap) wrap.classList.toggle('hidden', !credit);
            }

            function clearProductSearch() {
                searchQuery = '';
                const el = document.getElementById('product-search') || document.getElementById('search-products');
                if (el) el.value = '';
                renderProducts();
            }

            function clearCobroClient() {
                const input = document.getElementById('cobro-client-input');
                if (input) input.value = 'Público General';
                if (currentSale) {
                    currentSale.clientName = 'Público General';
                    currentSale.person_id = null;
                    saveDB();
                }
                const root = document.getElementById('sales-client-picker');
                safeSetAlpineDataProperty(root, 'person_id', null);
                window.dispatchEvent(new CustomEvent('clear-combobox', {
                    detail: {
                        name: 'header_client_id'
                    }
                }));
            }

            function getCobroOrderTotal() {
                return calculateTotalsFromItems(currentSale?.items || []).total || 0;
            }

            function getCobroRemainingAmount(excludeInput) {
                const orderTotal = getCobroOrderTotal();
                const inputs = document.querySelectorAll('.cobro-pm-amount');
                let paid = 0;
                inputs.forEach(inp => {
                    if (inp !== excludeInput) paid += parseFloat(String(inp.value || 0).replace(',', '.')) || 0;
                });
                return Math.max(0, orderTotal - paid);
            }

            function autocompleteCobroAmount(inputEl) {
                if (!inputEl) return;
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
                if (credit.length) html += '<optgroup label="Crédito">' + credit.map(c =>
                    `<option value="${c.id}">${escapeHtml(c.description || '')}</option>`).join('') + '</optgroup>';
                if (debit.length) html += '<optgroup label="Débito">' + debit.map(c =>
                    `<option value="${c.id}">${escapeHtml(c.description || '')}</option>`).join('') + '</optgroup>';
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
                const desc = (methodSelect.options[methodSelect.selectedIndex]?.text || '').toLowerCase();
                const isCard = isCobroMethodCard(desc);
                const isWallet = isCobroMethodWallet(desc);
                const isTransfer = desc.includes('transferencia') || desc.includes('transfer') || desc.includes(
                    'deposito') || desc.includes('depósito');
                cardGroup.classList.toggle('hidden', !isCard);
                walletGroup.classList.toggle('hidden', !isWallet);
                if (bankGroup) {
                    bankGroup.classList.toggle('hidden', !isTransfer);
                    bankGroup.classList.toggle('flex', isTransfer);
                    if (!isTransfer) {
                        const bank = row.querySelector('.cobro-pm-bank');
                        if (bank) bank.value = '';
                    }
                }
                const gw = row.querySelector('.cobro-pm-gateway');
                const card = row.querySelector('.cobro-pm-card');
                const wallet = row.querySelector('.cobro-pm-wallet');
                if (!isCard && gw) gw.value = '';
                if (!isCard && card) card.value = '';
                if (!isWallet && wallet) wallet.value = '';
            }

            function addCobroPaymentMethod() {
                const list = document.getElementById('cobro-payment-methods-list');
                if (!list) return;
                const methods = cobroPaymentMethods || [];
                const opts = methods.map(pm => `<option value="${pm.id}">${escapeHtml(pm.description || '')}</option>`)
                    .join('');
                const autoAmount = getCobroRemainingAmount();
                const row = document.createElement('div');
                row.className =
                    'cobro-pm-row rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50 p-3 space-y-2';
                row.innerHTML = `
                                                                                                                                                                                        <div class="flex gap-2 items-end flex-wrap">
                                                                                                                                                                                            <div class="flex-1 min-w-[120px]">
                                                                                                                                                                                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Método</label>
                                                                                                                                                                                                <select class="cobro-pm-method w-full py-2 px-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#124731]/30 focus:border-[#124731] outline-none dark:border-gray-600 bg-white dark:bg-gray-800 text-sm" onchange="toggleCobroExtraFields(this.closest('.cobro-pm-row'))">${opts}</select>
                                                                                                                                                                                            </div>
                                                                                                                                                                                            <div class="w-24 shrink-0">
                                                                                                                                                                                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Monto</label>
                                                                                                                                                                                                <input type="number" step="0.01" min="0" value="${autoAmount > 0 ? autoAmount.toFixed(2) : '0.00'}" placeholder="0.00"
                                                                                                                                                                                                    class="w-full py-2 px-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#124731]/30 focus:border-[#124731] outline-none dark:border-gray-600 bg-white dark:bg-gray-800 text-sm tabular-nums cobro-pm-amount"
                                                                                                                                                                                                    oninput="updateCobroTotalPaid()" onfocus="autocompleteCobroAmount(this)">
                                                                                                                                                                                            </div>
                                                                                                                                                                                            <button type="button" onclick="this.closest('.cobro-pm-row').remove(); updateCobroTotalPaid();" class="p-2 h-9 flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors shrink-0" title="Eliminar">
                                                                                                                                                                                                <i class="ri-delete-bin-line text-lg"></i>
                                                                                                                                                                                            </button>
                                                                                                                                                                                        </div>
                                                                                                                                                                                        <div class="cobro-pm-card-group hidden flex gap-2 items-end flex-wrap">
                                                                                                                                                                                            <div class="flex-1 min-w-[100px]">
                                                                                                                                                                                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Pasarela</label>
                                                                                                                                                                                                <select class="cobro-pm-gateway w-full py-2 px-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#124731]/30 focus:border-[#124731] outline-none dark:border-gray-600 bg-white dark:bg-gray-800 text-sm">${buildCobroGatewayOptions()}</select>
                                                                                                                                                                                            </div>
                                                                                                                                                                                            <div class="flex-1 min-w-[100px]">
                                                                                                                                                                                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Tarjeta</label>
                                                                                                                                                                                                <select class="cobro-pm-card w-full py-2 px-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#124731]/30 focus:border-[#124731] outline-none dark:border-gray-600 bg-white dark:bg-gray-800 text-sm">${buildCobroCardOptions()}</select>
                                                                                                                                                                                            </div>
                                                                                                                                                                                        </div>
                                                                                                                                                                                        <div class="cobro-pm-wallet-group hidden flex gap-2 items-end flex-wrap">
                                                                                                                                                                                            <div class="flex-1 min-w-[120px]">
                                                                                                                                                                                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Billetera</label>
                                                                                                                                                                                                <select class="cobro-pm-wallet w-full py-2 px-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#124731]/30 focus:border-[#124731] outline-none dark:border-gray-600 bg-white dark:bg-gray-800 text-sm">${buildCobroWalletOptions()}</select>
                                                                                                                                                                                            </div>
                                                                                                                                                                                        </div>
                                                                                                                                                                                        <div class="cobro-pm-bank-group hidden flex gap-2 items-end flex-wrap">
                                                                                                                                                                                            <div class="flex-1 min-w-[120px]">
                                                                                                                                                                                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Banco destino</label>
                                                                                                                                                                                                <select class="cobro-pm-bank w-full py-2 px-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#124731]/30 focus:border-[#124731] outline-none dark:border-gray-600 bg-white dark:bg-gray-800 text-sm">${buildCobroBankOptions()}</select>
                                                                                                                                                                                            </div>
                                                                                                                                                                                        </div>
                                                                                                                                                                                    `;
                list.appendChild(row);
                toggleCobroExtraFields(row);
                updateCobroTotalPaid();
            }

            function updateCobroTotalPaid() {
                const inputs = document.querySelectorAll('.cobro-pm-amount');
                let total = 0;
                inputs.forEach(inp => {
                    total += parseFloat(String(inp.value || 0).replace(',', '.')) || 0;
                });
                const el = document.getElementById('cobro-total-paid');
                if (el) el.textContent = 'S/ ' + total.toFixed(2);
            }

            function getCobroPaymentMethodsFromForm() {
                const list = document.getElementById('cobro-payment-methods-list');
                if (!list) return [];
                const result = [];
                list.querySelectorAll('.cobro-pm-row').forEach(row => {
                    const methodSelect = row.querySelector('.cobro-pm-method');
                    const input = row.querySelector('.cobro-pm-amount');
                    if (!methodSelect || !input) return;
                    const pmId = parseInt(methodSelect.value, 10);
                    const amount = parseFloat(String(input.value || 0).replace(',', '.')) || 0;
                    if (!pmId || amount <= 0) return;
                    const obj = {
                        payment_method_id: pmId,
                        amount
                    };
                    const desc = (methodSelect.options[methodSelect.selectedIndex]?.text || '').toLowerCase();
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

            function showCobroNotification(title, message, type) {
                const notification = document.getElementById('notification');
                if (!notification) return;
                const isError = type === 'error';
                notification.innerHTML = `
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
                setTimeout(() => notification.classList.add('opacity-0', 'pointer-events-none'), 3500);
            }

            function isQzTrayAvailable() {
                try {
                    if (window.sessionStorage?.getItem('qzTrayUnavailable') === '1') {
                        return false;
                    }
                } catch (e) {
                    // Ignorar restricciones de sessionStorage.
                }
                return typeof window.qz !== 'undefined' && window.qz !== null;
            }

            function markQzTrayUnavailable() {
                try {
                    window.sessionStorage?.setItem('qzTrayUnavailable', '1');
                } catch (e) {
                    // Ignorar restricciones de sessionStorage.
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

            async function sendThermalTicketAfterSale(movementId, saleResponse) {
                if (!movementId) return;
                const qzApi = window.qz;
                const sel = document.getElementById('cobro-thermal-printer');
                const printerId = sel && sel.value ? parseInt(sel.value, 10) : null;
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const preferredPrinterName = resolveStrictLocalPrinterName();
                const strictLocalQz = requiresStrictLocalQz(preferredPrinterName);
                const body = {
                    movement_id: movementId,
                    printer_name: preferredPrinterName || null
                };
                if (printerId) body.printer_id = printerId;

                // Si QZ Tray está activo y conectado, obtener el payload del servidor e imprimir por QZ (USB o red)
                if (qzApi && await ensureQzTrayConnected(qzApi, preferredPrinterName)) {
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
                            openSaleTicketPdfTab(movementId);
                            return;
                        }
                        let printerName = preferredPrinterName || td.printer_name || '';
                        if (!printerName) printerName = await qzApi.printers.getDefault();
                        if (!printerName) {
                            openSaleTicketPdfTab(movementId);
                            return;
                        }
                        const paperMm = (parseInt(td.paper_width) || 58) === 80 ? 80 : 58;
                        const sizeOpts = { units: 'mm', size: { width: paperMm, height: 200 } };
                        const configPdf = qzApi.configs.create(printerName, { ...sizeOpts, scaleContent: true });
                        const configRaw = qzApi.configs.create(printerName, { ...sizeOpts, scaleContent: false });
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
                        showCobroNotification('Impresión', 'Ticket enviado a "' + printerName + '".', 'success');
                    } catch (e) {
                        markQzTrayUnavailable();
                        console.warn('QZ Ticket:', e);
                        if (strictLocalQz) {
                            openSaleTicketPdfTab(movementId);
                            return;
                        }
                        openSaleTicketPdfTab(movementId);
                    }
                    return;
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
                        showCobroNotification('Impresión', td.message || 'Ticket enviado a la ticketera.',
                            'success');
                    } else {
                        openSaleTicketPdfTab(movementId);
                    }
                } catch (e) {
                    console.warn('Ticketera red:', e);
                    openSaleTicketPdfTab(movementId);
                }
            }

            async function processSale() {
                const items = currentSale?.items || [];
                if (items.length === 0) {
                    showCobroNotification('Error', 'Agrega productos antes de cobrar.', 'error');
                    return;
                }
                // Guardar hora de guardado (solo visual, no afecta montos)
                const now = new Date();
                const timeString = now.toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                sessionStorage.setItem('last_saved_time', timeString);
                const totals = calculateTotalsFromItems(items);
                const total = totals.total;
                const paymentMethodsData = getCobroPaymentMethodsFromForm();
                const totalPaid = paymentMethodsData.reduce((s, p) => s + (parseFloat(p.amount) || 0), 0);

                const saleMode = document.getElementById('cobro-sale-type')?.value || 'CONTADO';
                const creditDays = Math.max(0, parseInt(document.getElementById('cobro-credit-days')?.value || '0', 10) || 0);

                const docTypeEl = document.getElementById('cobro-document-type');
                const cashRegEl = document.getElementById('cobro-cash-register');
                const headerClientHidden = document.querySelector('input[name="header_client_id"]');
                const personId = currentSale.person_id || (headerClientHidden?.value ?
                    parseInt(headerClientHidden.value, 10) : null);

                if (saleMode === 'CREDITO') {
                    if (!personId) {
                        showCobroNotification('Error', 'Para venta a crédito seleccione un cliente identificado.', 'error');
                        return;
                    }
                    if (totalPaid > total + 0.01) {
                        showCobroNotification('Error', 'El abono no puede ser mayor al total (S/ ' + total.toFixed(2) + ').', 'error');
                        return;
                    }
                } else {
                    if (paymentMethodsData.length === 0) {
                        showCobroNotification('Error', 'Agrega al menos un método de pago.', 'error');
                        return;
                    }
                    if (Math.abs(totalPaid - total) > 0.01) {
                        showCobroNotification('Error', 'La suma de los métodos de pago debe ser igual al total (S/ ' +
                            total.toFixed(2) + ').', 'error');
                        return;
                    }
                }

                const payload = {
                    items: items.map(it => ({
                        pId: it.pId ?? it.id,
                        name: it.name,
                        qty: Number(it.qty) || 0,
                        price: Number(it.price) || 0,
                        courtesyQty: Number(it.courtesyQty) || 0,
                        note: String(it.note ?? '').trim(),
                    })),
                    document_type_id: parseInt(docTypeEl?.value || 0),
                    cash_register_id: parseInt(cashRegEl?.value || 0),
                    person_id: personId,
                    sale_payment_mode: saleMode,
                    credit_days: creditDays,
                    payment_methods: paymentMethodsData.map(pm => ({
                        payment_method_id: pm.payment_method_id,
                        amount: parseFloat(pm.amount) || 0,
                        payment_gateway_id: pm.payment_gateway_id || null,
                        card_id: pm.card_id || null,
                        digital_wallet_id: pm.digital_wallet_id || null,
                        bank_id: pm.bank_id || null,
                    })),
                    notes: '',
                };
                const selectedDocName = (docTypeEl?.selectedOptions?.[0]?.textContent || '').trim() || 'Documento no definido';
                const creditHint = saleMode === 'CREDITO' ?
                    `<br>Condición: <b>Crédito / Deuda</b> · Plazo: <b>${creditDays}</b> día(s)` : '';
                if (window.Swal && typeof window.Swal.fire === 'function') {
                    const confirmPayment = await Swal.fire({
                        title: 'Confirmar cobro',
                        html: `Se emitira: <b>${selectedDocName}</b><br>Total: <b>S/ ${total.toFixed(2)}</b>${creditHint}`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#124731',
                        cancelButtonColor: '#6b7280',
                        confirmButtonText: 'Si, cobrar',
                        cancelButtonText: 'Revisar'
                    });
                    if (!confirmPayment.isConfirmed) return;
                } else if (!window.confirm('Se emitira: ' + selectedDocName + '\nTotal: S/ ' + total.toFixed(2) + '\n\n¿Deseas continuar con el cobro?')) {
                    return;
                }

                const btn = document.getElementById('checkout-button');
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Procesando...';
                }

                try {
                    const r = await fetch(salesProcessUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute(
                                'content') || '',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });
                    const data = r.headers.get('content-type')?.includes('application/json') ? await r.json() :
                        null;
                    if (!r.ok) {
                        const msg = data?.message || data?.error || 'Error al procesar la venta';
                        throw new Error(msg);
                    }
                    if (!data?.success) {
                        throw new Error(data?.message || data?.error || 'Error al procesar la venta');
                    }
                    if (activeKey && db[activeKey]) {
                        db[activeKey].status = 'completed';
                        db[activeKey].items = [];
                        localStorage.setItem('restaurantDB', JSON.stringify(db));
                    }
                    localStorage.removeItem(ACTIVE_SALE_KEY_STORAGE);
                    sessionStorage.setItem('flash_success_message', data.message || 'Venta cobrada correctamente');
                    const movementId = data?.data?.movement_id;
                    await sendThermalTicketAfterSale(movementId, data);
                    setTimeout(() => {
                        window.location.href = salesIndexUrl;
                    }, 600);
                } catch (err) {
                    showCobroNotification('Error', err.message || 'Error al procesar la venta.', 'error');
                } finally {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="ri-bank-card-line text-base"></i><span>Cobrar</span>';
                    }
                }
            }

            function clearCart() {
                if (currentSale.items.length > 0 && confirm('¿Vaciar toda la orden?')) {
                    currentSale.items = [];
                    saveDB();
                    renderTicket();
                }
            }

            function toggleNoteInput(index) {
                if (!currentSale.items || !currentSale.items[index]) return;
                const item = currentSale.items[index];
                const nt = typeof item.note === 'string' ? item.note.trim() : '';
                const hasN = nt !== '';
                const shown = item.noteOpen === true || (item.noteOpen === undefined && hasN);
                item.noteOpen = !shown;
                saveDB();
                renderTicket();
            }

            function saveNote(index, value) {
                if (!currentSale.items[index]) return;
                currentSale.items[index].note = value;
                saveDB();
            }

            function showStockError(productName, stock) {
                const notification = document.getElementById('stock-error-notification');
                const msgEl = document.getElementById('stock-error-message');
                if (!notification || !msgEl) return;
                msgEl.textContent = `Solo quedan ${stock} unidades.`;
                notification.classList.add('notification-show');
                setTimeout(hideStockError, 3000);
            }

            function hideStockError() {
                document.getElementById('stock-error-notification')?.classList.remove('notification-show');
            }

            function showNotification(productName) {
                const notification = document.getElementById('add-to-cart-notification');
                const productNameEl = document.getElementById('notification-product-name');
                if (!notification || !productNameEl) return;
                productNameEl.textContent = productName;
                notification.classList.add('notification-show');
                setTimeout(hideNotification, 1200);
            }

            function hideNotification() {
                document.getElementById('add-to-cart-notification')?.classList.remove('notification-show');
            }

            // --- INICIALIZACIÓN ---
            function init() {
                renderCategoryFilters();
                renderProducts();
                renderTicket();
                setupQuickClientCreate();
                const cobroInput = document.getElementById('cobro-client-input');
                if (cobroInput) cobroInput.value = currentSale?.clientName || '{{ $personName }}';
                if (document.getElementById('cobro-payment-methods-list')?.children.length === 0) {
                    addCobroPaymentMethod();
                }
                toggleCobroSaleMode();
                const searchEl = document.getElementById('product-search');
                if (searchEl) {
                    searchEl.addEventListener('input', function (e) {
                        searchQuery = (e.target.value || '').toLowerCase();
                        renderProducts();
                    });
                }
            }

            // Exponer funciones globales para los onclick="" del HTML
            window.getImageUrl = getImageUrl;
            window.updateQty = updateQty;
            window.setQtyFromInput = setQtyFromInput;
            window.setCourtesyQty = setCourtesyQty;
            window.changeCourtesyQty = changeCourtesyQty;
            window.toggleCourtesyInput = toggleCourtesyInput;
            window.removeFromCart = removeFromCart;
            window.clearCart = clearCart;
            window.toggleNoteInput = toggleNoteInput;
            window.saveNote = saveNote;
            window.switchAsideTab = switchAsideTab;
            window.clearCobroClient = clearCobroClient;
            window.addCobroPaymentMethod = addCobroPaymentMethod;
            window.updateCobroTotalPaid = updateCobroTotalPaid;
            window.autocompleteCobroAmount = autocompleteCobroAmount;
            window.toggleCobroExtraFields = toggleCobroExtraFields;
            window.toggleCobroSaleMode = toggleCobroSaleMode;
            window.processSale = processSale;
            window.hideStockError = hideStockError;
            window.hideNotification = hideNotification;
            window.clearProductSearch = clearProductSearch;

            // Arrancar
            init();

        })();
    </script>
@endsection
