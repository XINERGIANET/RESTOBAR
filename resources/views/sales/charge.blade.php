@extends('layouts.app')

@section('content')
    @php
        $viewId = request('view_id');
        $backUrl = route('sales.create', $viewId ? ['view_id' => $viewId] : []);

        $peopleCollection = $people ?? collect();

        $clientOptions = $peopleCollection->map(function ($p) {
            $name = trim(($p->first_name ?? '') . ' ' . ($p->last_name ?? ''));
            if ($name === '' && !empty($p->document_number)) {
                $name = $p->document_number;
            }

            return [
                'id' => $p->id,
                'description' => $name,
            ];
        })->values()->all();

        $defaultClientId = $defaultClientId ?? $peopleCollection->first()?->id ?? null;
    @endphp

    <div class="min-h-screen bg-white dark:bg-gray-900 py-4 sm:py-5">
        <div class="mx-auto max-w-7xl px-3 sm:px-5 lg:px-7">
            <div class="rounded-2xl border border-slate-200/90 bg-white dark:border-gray-700 dark:bg-gray-800 shadow-xl shadow-slate-300/30 dark:shadow-black/30 overflow-hidden min-h-[calc(100vh-120px)] flex flex-col">
                <header class="flex items-center justify-between px-4 sm:px-6 py-3 bg-white dark:bg-[#151C2C] border-b border-gray-200 dark:border-gray-800 shadow-sm z-30 shrink-0 h-16 gap-4 relative">            
                    <div class="flex items-center gap-3 sm:gap-5 shrink-0">
                        <a href="{{ $backUrl }}" id="back-to-sales-link" 
                        class="flex items-center justify-center w-9 h-9 rounded-full bg-gray-50 border border-gray-200 hover:bg-gray-100 text-gray-600 transition-all dark:bg-gray-800 dark:border-gray-700 dark:hover:bg-gray-700 dark:text-gray-300 shadow-sm">
                            <i class="ri-arrow-left-s-line text-xl"></i>
                        </a>
                        <div class="hidden sm:block h-6 w-px bg-gray-300 dark:bg-gray-700"></div>
                        <div>
                            <h1 class="text-lg font-bold text-gray-900 dark:text-white leading-none tracking-tight">Cobrar Venta</h1>
                            <div class="flex items-center gap-1.5 mt-1.5">
                                <span class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Confirmación de pago</span>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 flex-1 p-4 sm:p-6 min-h-0 overflow-hidden bg-white dark:bg-transparent">
                    <div class="lg:col-span-2 flex flex-col gap-3 overflow-hidden">
                        <div class="flex items-center gap-3 rounded-xl border border-blue-100 bg-blue-50/60 px-4 py-2.5 dark:border-gray-600 dark:bg-gray-800 shrink-0 shadow-sm">
                            <div class="flex-1 min-w-0" x-data="{ selectedClientId: {{ $defaultClientId ?? 'null' }} }">
                                <div class="flex justify-between items-center mb-0.5">
                                    <p class="text-[10px] uppercase tracking-wider font-medium text-gray-400 dark:text-gray-500 leading-none">Cliente</p>
                                </div>
                                
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 min-w-0">
                                        <x-form.select.combobox 
                                            :options="$clientOptions"
                                            x-model="selectedClientId"
                                            name="client_id"
                                            placeholder="Buscar cliente..."
                                            icon=""
                                            class="!h-auto !py-0 !text-sm !font-semibold !text-gray-900 dark:!text-white !bg-transparent !border-0 !p-0 !shadow-none focus:!ring-0 w-full placeholder-gray-400"
                                        />
                                    </div>

                                    <button type="button" 
                                        x-data 
                                        @click="$dispatch('open-person-modal')" 
                                        title="Nuevo Cliente"
                                        class="shrink-0 flex items-center justify-center h-9 w-9 rounded-lg bg-blue-200/50 text-blue-700 hover:bg-blue-600 hover:text-white dark:bg-blue-900/50 dark:text-blue-300 dark:hover:bg-blue-600 dark:hover:text-white transition-all duration-200 shadow-sm">
                                        <i class="ri-user-add-line text-lg"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="h-8 w-px bg-gray-300/50 dark:bg-gray-600 shrink-0"></div>

                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] uppercase tracking-wider font-medium text-gray-400 dark:text-gray-500 leading-none mb-0.5">Caja</p>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate" id="cash-register-display">-</p>
                                <input type="hidden" id="cash-register-id" value="">
                            </div>
                        </div>

                        <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 dark:border-gray-600 dark:bg-gray-800 flex-1 flex flex-col min-h-0 overflow-hidden shadow-sm">
                            <div class="px-4 py-3 border-b border-indigo-100 dark:border-gray-700 flex items-center justify-between shrink-0 bg-indigo-100/50 dark:bg-gray-800/80">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-shopping-bag text-sm text-blue-600 dark:text-blue-400"></i>
                                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white">Detalle de la venta</h2>
                                </div>
                                <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-bold text-blue-700 dark:bg-blue-900/50 dark:text-blue-300" id="items-count">0 ítems</span>
                            </div>
                            <div id="items-list" class="flex-1 overflow-y-auto custom-scrollbar px-3 py-2 space-y-1.5 bg-white/80 dark:bg-gray-900/40">
                                <div class="flex flex-col items-center justify-center py-10 text-center">
                                    <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-200 dark:bg-gray-700">
                                        <i class="fas fa-shopping-cart text-xl text-slate-400 dark:text-gray-500"></i>
                                    </div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No hay productos en la venta</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200/90 bg-gradient-to-br from-slate-50 to-white p-3.5 dark:border-gray-600 dark:from-gray-800/90 dark:to-gray-900/80 shrink-0 shadow-sm">
                            <p class="mb-2.5 flex items-center gap-2 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <i class="fas fa-receipt text-[11px] text-blue-600 dark:text-blue-400"></i>
                                Totales (subtotal + IGV)
                            </p>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between items-center gap-3">
                                    <span class="text-gray-600 dark:text-gray-400">Subtotal</span>
                                    <span class="font-semibold tabular-nums text-gray-900 dark:text-white" id="subtotal">S/0.00</span>
                                </div>
                                <div class="flex justify-between items-center gap-3">
                                    <span class="text-gray-600 dark:text-gray-400">IGV</span>
                                    <span class="font-semibold tabular-nums text-gray-900 dark:text-white" id="tax">S/0.00</span>
                                </div>
                                <div class="mt-2 flex justify-between items-center gap-3 border-t border-slate-200 pt-2.5 dark:border-gray-600">
                                    <span class="text-sm font-bold text-gray-900 dark:text-white">Total a pagar</span>
                                    <span class="text-xl font-extrabold tabular-nums text-blue-600 dark:text-blue-400" id="total">S/0.00</span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-emerald-100 bg-emerald-50/35 p-3 dark:border-gray-600 dark:bg-gray-800 shadow-sm">
                            <label for="sale-notes" class="mb-1.5 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <i class="fas fa-sticky-note text-[11px]"></i> Nota <span class="normal-case font-normal text-gray-400">(Opcional)</span>
                            </label>
                            <textarea id="sale-notes" rows="2" placeholder="Ej: Cliente pagó con billete de 50..."
                                class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-gray-900 placeholder-gray-400 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-500 dark:focus:border-blue-400 dark:focus:bg-gray-600"></textarea>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 lg:sticky lg:top-4  min-h-0 lg:border-l lg:border-slate-200/80 lg:pl-3 dark:lg:border-gray-700">
                        {{-- Sin overflow-y: el menú nativo del <select> no se recorta --}}
                        <div class="shrink-0 space-y-3">
                            <div class="rounded-xl border border-violet-100 bg-violet-50/40 p-3 dark:border-gray-600 dark:bg-gray-800 shadow-sm relative z-10">
                                <label for="payment_type" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Tipo de pago</label>
                                <select name="payment_type" id="payment_type" class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-3 pr-10 text-sm font-medium text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-blue-400">
                                    <option value="CONTADO">Contado</option>
                                    <option value="CREDITO">Crédito / Deuda</option>
                                </select>
                                <div id="charge-credit-fields" class="hidden mt-3 space-y-3 border-t border-violet-200/80 pt-3 dark:border-gray-600">
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div>
                                            <label for="charge-credit-days"
                                                class="mb-1 block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Días
                                                de crédito</label>
                                            <input type="number" id="charge-credit-days" min="0" step="1" value="0"
                                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm tabular-nums text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        </div>
                                        <div>
                                            <label for="charge-due-date"
                                                class="mb-1 block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Fecha
                                                de vencimiento</label>
                                            <input type="date" id="charge-due-date"
                                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm tabular-nums text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        </div>
                                    </div>
                            </div>
                        </div>

                        <div class="flex-1 min-h-0 overflow-y-auto space-y-3 custom-scrollbar pr-0.5">
                            <div class="rounded-xl border border-violet-100 bg-violet-50/40 p-3 dark:border-gray-600 dark:bg-gray-800 shadow-sm">
                                <p class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Comprobante</p>
                                
                                {{-- Contenedor de botones --}}
                                <div class="flex flex-wrap gap-2" id="document-type-container">
                                    @foreach ($documentTypes as $index => $documentType)
                                        <button type="button"
                                            {{-- Evento Click --}}
                                            onclick="selectDocument(this, '{{ $documentType->id }}')"
                                            {{-- Clases Dinámicas --}}
                                            class="doc-type-btn inline-flex items-center gap-1 rounded-lg border-2 px-3 py-1.5 text-xs font-semibold transition-all duration-200
                                            {{ (int) ($defaultDocumentTypeId ?? ($documentTypes->first()?->id ?? 0)) === (int) $documentType->id
                                                ? 'doc-selected border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 dark:border-blue-600' 
                                                : 'border-slate-200 bg-slate-50 text-gray-600 hover:border-blue-400 hover:bg-blue-50/80 hover:text-blue-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:border-blue-500 dark:hover:bg-blue-900/30' 
                                            }}"
                                            data-doc-type="{{ strtolower($documentType->name) }}">
                                            
                                            <i class="fas fa-file-alt text-[11px]"></i>
                                            {{-- Tu lógica de nombre limpio --}}
                                            {{ trim(str_ireplace('de venta', '', $documentType->name)) }}
                                        </button>
                                    @endforeach
                                </div>

                                {{-- Input Oculto que guarda el ID --}}
                                <input type="hidden" id="document-type-id" name="document_type_id" value="{{ $defaultDocumentTypeId ?? ($documentTypes->first()?->id ?? '') }}">
                            </div>

                            <div class="rounded-xl border border-cyan-100 bg-cyan-50/35 p-3 dark:border-gray-600 dark:bg-gray-800 shadow-sm">
                                <div class="mb-3 flex items-center justify-between">
                                    <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Método de pago</p>
                                    <button type="button" id="add-payment-method-btn"
                                        class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-blue-700 active:scale-95 dark:bg-blue-700 dark:hover:bg-blue-600">
                                        <i class="fas fa-plus text-[10px]"></i> Agregar
                                    </button>
                                </div>
                                <div id="payment-methods-list" class="space-y-2"></div>
                                <div id="payment-summary" class="mt-3 rounded-lg border border-slate-200 bg-slate-100/80 px-3 py-2.5 dark:bg-gray-700/50 dark:border-gray-600">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">Total pagado</span>
                                        <span class="text-base font-bold text-gray-900 dark:text-white tabular-nums" id="total-paid">S/0.00</span>
                                    </div>
                                    <div id="payment-remaining" class="hidden mt-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-1.5 dark:bg-amber-900/20 dark:border-amber-800/50">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-semibold text-amber-700 dark:text-amber-400"><i class="fas fa-exclamation-circle mr-1 text-[10px]"></i>Falta pagar</span>
                                            <span class="text-sm font-bold text-amber-700 dark:text-amber-400 tabular-nums" id="remaining-amount">S/0.00</span>
                                        </div>
                                    </div>
                                    <div id="payment-excess" class="hidden mt-2 rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-1.5 dark:bg-emerald-900/20 dark:border-emerald-800/50">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400"><i class="fas fa-check-circle mr-1 text-[10px]"></i>Vuelto</span>
                                            <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400 tabular-nums" id="excess-amount">S/0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="button" id="confirm-btn"
                            class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-blue-500/30 transition hover:bg-blue-700 active:scale-[.98] dark:bg-blue-700 dark:shadow-blue-900/40 dark:hover:bg-blue-600 shrink-0">
                            <i class="fas fa-check-circle mr-2"></i>
                            Confirmar y Cobrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="payment-method-selection-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
        <div class="flex h-max max-h-[85vh] w-full max-w-md flex-col overflow-hidden rounded-2xl bg-white shadow-2xl shadow-slate-300/50 dark:bg-gray-800 dark:shadow-black/30 border border-slate-200/80 dark:border-gray-700">
            <div class="flex shrink-0 items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-gray-700">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/40">
                        <i class="fas fa-wallet text-blue-600 dark:text-blue-400 text-sm"></i>
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Método de pago</h3>
                </div>
                <button type="button" id="close-payment-method-modal" class="flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>
            <div class="min-h-0 max-h-[50vh] overflow-y-auto px-5 py-4 custom-scrollbar">
                <div class="grid grid-cols-2 gap-2.5">
                    @foreach ($paymentMethods as $paymentMethod)
                        @php
                            $desc = strtolower($paymentMethod->description);
                            $isCard = (str_contains($desc, 'tarjeta') || str_contains($desc, 'card')) && !str_contains($desc, 'billetera');
                            $isWallet = str_contains($desc, 'billetera');
                            $icon = $isCard ? 'fa-credit-card' : (str_contains($desc, 'efectivo') || str_contains($desc, 'cash') ? 'fa-money-bill-wave' : (str_contains($desc, 'yape') || str_contains($desc, 'plin') ? 'fa-mobile-alt' : ((str_contains($desc, 'transfer') || str_contains($desc, 'deposito') || str_contains($desc, 'depósito')) ? 'fa-exchange-alt' : 'fa-wallet')));
                        @endphp
                        <button type="button"
                            class="pm-selection-btn group flex items-center gap-3 rounded-xl border-2 border-gray-200 bg-gray-50 p-3.5 text-left transition hover:border-blue-500 hover:bg-blue-50 dark:border-gray-600 dark:bg-gray-700/60 dark:hover:border-blue-500 dark:hover:bg-blue-900/20"
                            data-method-id="{{ $paymentMethod->id }}"
                            data-method-name="{{ $paymentMethod->description }}"
                            data-is-card="{{ $isCard ? '1' : '0' }}"
                            data-is-wallet="{{ $isWallet ? '1' : '0' }}">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white shadow-sm dark:bg-gray-700 group-hover:bg-blue-100 dark:group-hover:bg-blue-900/40 transition">
                                <i class="fas {{ $icon }} text-gray-500 dark:text-gray-400 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition"></i>
                            </div>
                            <span class="flex-1 text-sm font-semibold text-gray-800 dark:text-white">{{ $paymentMethod->description }}</span>
                            <i class="fas fa-check-circle hidden text-blue-600 dark:text-blue-400 text-sm"></i>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div id="card-selection-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
        <div class="flex h-max max-h-[85vh] w-full max-w-md flex-col rounded-2xl bg-white shadow-2xl shadow-slate-300/50 dark:bg-gray-800 dark:shadow-black/30 border border-slate-200/80 dark:border-gray-700 overflow-hidden">
            <div class="flex shrink-0 items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-gray-700">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/40">
                        <i class="fas fa-credit-card text-blue-600 dark:text-blue-400 text-sm"></i>
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Pasarela y Tarjeta</h3>
                </div>
                <button type="button" id="close-card-modal" class="flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>
            <div class="min-h-0 max-h-[50vh] overflow-y-auto px-5 py-4 space-y-5 custom-scrollbar">
                <div>
                    <p class="mb-2.5 text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Pasarela de pago</p>
                    <div class="flex gap-2 overflow-x-auto custom-scrollbar pb-1">
                        @foreach ($paymentGateways as $gateway)
                            <button type="button"
                                class="gateway-btn inline-flex shrink-0 items-center gap-2 rounded-xl border-2 border-gray-200 bg-gray-50 px-3.5 py-2.5 text-left transition hover:border-blue-500 hover:bg-blue-50 dark:border-gray-600 dark:bg-gray-700/60 dark:hover:border-blue-500 dark:hover:bg-blue-900/20"
                                data-gateway-id="{{ $gateway->id }}" data-gateway-name="{{ $gateway->description }}">
                                <i class="fas fa-building-columns text-sm text-gray-500 dark:text-gray-400"></i>
                                <span class="text-sm font-semibold text-gray-800 dark:text-white whitespace-nowrap">{{ $gateway->description }}</span>
                                <i class="fas fa-check-circle hidden text-blue-600 dark:text-blue-400 text-xs"></i>
                            </button>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="mb-2.5 text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Tipo de tarjeta</p>
                    @if($cards->where('type', 'C')->count())
                        <p class="mb-1.5 text-xs font-medium text-gray-400 dark:text-gray-500">Crédito</p>
                        <div class="flex gap-2 overflow-x-auto custom-scrollbar pb-2 mb-3">
                            @foreach ($cards->where('type', 'C') as $card)
                                <button type="button"
                                    class="card-btn inline-flex shrink-0 items-center gap-2 rounded-xl border-2 border-gray-200 bg-gray-50 px-3.5 py-2.5 transition hover:border-blue-500 hover:bg-blue-50 dark:border-gray-600 dark:bg-gray-700/60 dark:hover:border-blue-500 dark:hover:bg-blue-900/20"
                                    data-card-id="{{ $card->id }}" data-card-name="{{ $card->description }}">
                                    <i class="{{ $card->icon ?: 'fas fa-credit-card' }} text-sm text-gray-500 dark:text-gray-400"></i>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-white whitespace-nowrap">{{ $card->description }}</span>
                                    <i class="fas fa-check-circle hidden text-blue-600 dark:text-blue-400 text-xs"></i>
                                </button>
                            @endforeach
                        </div>
                    @endif
                    @if($cards->where('type', 'D')->count())
                        <p class="mb-1.5 text-xs font-medium text-gray-400 dark:text-gray-500">Débito</p>
                        <div class="flex gap-2 overflow-x-auto custom-scrollbar pb-1">
                            @foreach ($cards->where('type', 'D') as $card)
                                <button type="button"
                                    class="card-btn inline-flex shrink-0 items-center gap-2 rounded-xl border-2 border-gray-200 bg-gray-50 px-3.5 py-2.5 transition hover:border-blue-500 hover:bg-blue-50 dark:border-gray-600 dark:bg-gray-700/60 dark:hover:border-blue-500 dark:hover:bg-blue-900/20"
                                    data-card-id="{{ $card->id }}" data-card-name="{{ $card->description }}">
                                    <i class="{{ $card->icon ?: 'fas fa-credit-card' }} text-sm text-gray-500 dark:text-gray-400"></i>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-white whitespace-nowrap">{{ $card->description }}</span>
                                    <i class="fas fa-check-circle hidden text-blue-600 dark:text-blue-400 text-xs"></i>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
            <div class="flex shrink-0 justify-end gap-2 border-t border-slate-100 px-5 py-3 dark:border-gray-700">
                <button type="button" id="cancel-card-selection" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">Cancelar</button>
                <button type="button" id="confirm-card-selection" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-blue-700 dark:hover:bg-blue-600" disabled>Confirmar</button>
            </div>
        </div>
    </div>

    <div id="wallet-selection-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
        <div class="flex h-max max-h-[85vh] w-full max-w-md flex-col overflow-hidden rounded-2xl bg-white shadow-2xl shadow-slate-300/50 dark:bg-gray-800 dark:shadow-black/30 border border-slate-200/80 dark:border-gray-700">
            <div class="flex shrink-0 items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-gray-700">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/40">
                        <i class="fas fa-mobile-alt text-emerald-600 dark:text-emerald-400 text-sm"></i>
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Elegir billetera</h3>
                </div>
                <button type="button" id="close-wallet-modal" class="flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>
            <div class="min-h-0 max-h-[50vh] overflow-y-auto px-5 py-4 custom-scrollbar">
                <p class="mb-2.5 text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Billetera digital</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($digitalWallets ?? [] as $wallet)
                        <button type="button"
                            class="wallet-btn inline-flex shrink-0 items-center gap-2 rounded-xl border-2 border-gray-200 bg-gray-50 px-3.5 py-2.5 transition hover:border-emerald-500 hover:bg-emerald-50 dark:border-gray-600 dark:bg-gray-700/60 dark:hover:border-emerald-500 dark:hover:bg-emerald-900/20"
                            data-wallet-id="{{ $wallet->id }}" data-wallet-name="{{ $wallet->description }}">
                            <i class="fas fa-mobile-alt text-sm text-gray-500 dark:text-gray-400"></i>
                            <span class="text-sm font-semibold text-gray-800 dark:text-white whitespace-nowrap">{{ $wallet->description }}</span>
                            <i class="fas fa-check-circle hidden text-emerald-600 dark:text-emerald-400 text-xs"></i>
                        </button>
                    @endforeach
                </div>
            </div>
            <div class="flex shrink-0 justify-end gap-2 border-t border-slate-100 px-5 py-3 dark:border-gray-700">
                <button type="button" id="cancel-wallet-selection" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">Cancelar</button>
                <button type="button" id="confirm-wallet-selection" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-blue-700 dark:hover:bg-blue-600" disabled>Confirmar</button>
            </div>
        </div>
    </div>

    <div id="payment-notification" class="fixed top-24 right-6 z-50 transform transition-all duration-400 translate-x-[150%] opacity-0">
        <div id="notification-content" class="flex min-w-[300px] items-center gap-3 rounded-2xl border border-blue-400/20 bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3.5 text-white shadow-2xl shadow-blue-500/20 backdrop-blur-sm">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/20">
                <i id="notification-icon" class="fas fa-info-circle text-lg"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p id="notification-title" class="text-sm font-bold leading-tight">Notificación</p>
                <p id="notification-message" class="mt-0.5 text-xs text-blue-100 leading-tight truncate">Mensaje</p>
            </div>
            <button onclick="hidePaymentNotification()" class="ml-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-white/70 transition hover:bg-white/20 hover:text-white">
                <i class="fas fa-times text-xs"></i>
            </button>
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
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white/90">Registrar / Editar Cliente</h3>
                            <p class="mt-1 text-sm text-gray-500">Ingresa DNI y nombre de la persona.</p>
                        </div>
                    </div>
                    <button type="button" @click="open = false"
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>

                <form method="POST" data-quick-client-form data-client-combobox-name="client_id"
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

    <style>
        .doc-type-btn.doc-active { border-color: #3b82f6; background: linear-gradient(135deg, #eff6ff, #fff); color: #1d4ed8; }
        .dark .doc-type-btn.doc-active { background: linear-gradient(135deg, rgba(30,58,138,.4), rgba(15,23,42,1)); color: #93c5fd; }
        .pm-btn.pm-active { border-color: #3b82f6; background: linear-gradient(135deg, #eff6ff, #fff); }
        .dark .pm-btn.pm-active { background: linear-gradient(135deg, rgba(30,58,138,.4), rgba(15,23,42,1)); }
        .gateway-btn.border-blue-500, .card-btn.border-blue-500 { border-color: #3b82f6; background: linear-gradient(135deg, #eff6ff, #fff); }
        .dark .gateway-btn.border-blue-500, .dark .card-btn.border-blue-500 { background: linear-gradient(135deg, rgba(30,58,138,.4), rgba(15,23,42,1)); }
        .payment-method-item { border-color: #cbd5e1 !important; transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease; }
        .payment-method-item:hover { border-color: #93c5fd !important; box-shadow: 0 10px 20px rgba(30, 64, 175, .10) !important; transform: translateY(-1px); }
        .payment-method-item:focus-within { border-color: #3b82f6 !important; box-shadow: 0 0 0 3px rgba(59, 130, 246, .15), 0 10px 22px rgba(30, 64, 175, .12) !important; }
        .pm-selection-btn, .gateway-btn, .card-btn, .wallet-btn { transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease, background-color .18s ease; }
        .pm-selection-btn:hover, .gateway-btn:hover, .card-btn:hover, .wallet-btn:hover { box-shadow: 0 8px 18px rgba(37, 99, 235, .12); transform: translateY(-1px); }
        .wallet-btn.border-emerald-500 { border-color: #10b981; background: linear-gradient(135deg, #ecfdf5, #fff); }
        .dark .wallet-btn.border-emerald-500 { background: linear-gradient(135deg, rgba(5,150,105,.3), rgba(15,23,42,1)); }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 999px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #64748b; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .notification-show { transform: translateX(0) !important; opacity: 1 !important; }
    </style>

    <script>
        function selectDocument(btn, id) {
            document.getElementById('document-type-id').value = id;
            const buttons = document.querySelectorAll('.doc-type-btn');
            const activeClasses = ['doc-selected', 'border-blue-500', 'bg-blue-50', 'text-blue-700', 'dark:bg-blue-900/40', 'dark:text-blue-300', 'dark:border-blue-600'];
            const inactiveClasses = ['border-slate-200', 'bg-slate-50', 'text-gray-600', 'hover:border-blue-400', 'hover:bg-blue-50/80', 'hover:text-blue-700', 'dark:border-gray-600', 'dark:bg-gray-700', 'dark:text-gray-300', 'dark:hover:border-blue-500', 'dark:hover:bg-blue-900/30'];
            
            buttons.forEach(b => {
                b.classList.remove(...activeClasses);
                b.classList.add(...inactiveClasses);
            });
            btn.classList.remove(...inactiveClasses);
            btn.classList.add(...activeClasses);
        }

        function chargeTodayLocalYmd() {
            const d = new Date();
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        }

        function calculateChargeDueDateFromDays() {
            const daysInput = document.getElementById('charge-credit-days');
            const dueDateInput = document.getElementById('charge-due-date');
            if (!daysInput || !dueDateInput) return;
            const days = Math.max(0, parseInt(String(daysInput.value || '0'), 10) || 0);
            const base = new Date();
            base.setHours(0, 0, 0, 0);
            base.setDate(base.getDate() + days);
            const y = base.getFullYear();
            const m = String(base.getMonth() + 1).padStart(2, '0');
            const da = String(base.getDate()).padStart(2, '0');
            dueDateInput.value = `${y}-${m}-${da}`;
            dueDateInput.min = chargeTodayLocalYmd();
        }

        function calculateChargeDaysFromDueDate() {
            const daysInput = document.getElementById('charge-credit-days');
            const dueDateInput = document.getElementById('charge-due-date');
            if (!daysInput || !dueDateInput?.value) return;
            const raw = String(dueDateInput.value).trim();
            const parts = raw.split('-').map((x) => parseInt(x, 10));
            if (parts.length !== 3 || parts.some((n) => Number.isNaN(n))) return;
            const due = new Date(parts[0], parts[1] - 1, parts[2]);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            due.setHours(0, 0, 0, 0);
            const diff = Math.round((due.getTime() - today.getTime()) / 86400000);
            daysInput.value = String(Math.max(0, diff));
        }

        /** Deshabilita el select de tipo de pago (p. ej. borrador desde servidor). */
        function syncPaymentTypeButtonsDisabled(disabled) {
            const sel = document.getElementById('payment_type');
            if (!sel) return;
            sel.disabled = !!disabled;
            sel.classList.toggle('opacity-60', !!disabled);
            sel.classList.toggle('cursor-not-allowed', !!disabled);
            sel.classList.toggle('pointer-events-none', !!disabled);
            sel.classList.toggle('bg-slate-100', !!disabled);
            sel.classList.toggle('dark:bg-gray-800/80', !!disabled);
            [document.getElementById('charge-credit-days'), document.getElementById('charge-due-date')].forEach((el) => {
                if (!el) return;
                el.disabled = !!disabled;
                el.classList.toggle('opacity-60', !!disabled);
                el.classList.toggle('cursor-not-allowed', !!disabled);
            });
        }

        function toggleChargeSaleMode() {
            const pt = document.getElementById('payment_type');
            const credit = pt?.value === 'CREDITO';
            const wrap = document.getElementById('charge-credit-fields');
            if (wrap) wrap.classList.toggle('hidden', !credit);
            if (credit) {
                const dueEl = document.getElementById('charge-due-date');
                if (dueEl) dueEl.min = chargeTodayLocalYmd();
                calculateChargeDueDateFromDays();
            }
        }

        function syncPaymentTypeSegment() {
            /* Con <select> nativo no hay segmentos; reservado por si se reutiliza. */
        }

        function setPaymentType(mode) {
            const el = document.getElementById('payment_type');
            if (!el || el.disabled) return;
            el.value = mode === 'CREDITO' ? 'CREDITO' : 'CONTADO';
            toggleChargeSaleMode();
        }

        window.toggleChargeSaleMode = toggleChargeSaleMode;
        window.syncPaymentTypeSegment = syncPaymentTypeSegment;
        window.syncPaymentTypeButtonsDisabled = syncPaymentTypeButtonsDisabled;
        window.setPaymentType = setPaymentType;
        window.calculateChargeDueDateFromDays = calculateChargeDueDateFromDays;
        window.calculateChargeDaysFromDueDate = calculateChargeDaysFromDueDate;

        document.addEventListener('DOMContentLoaded', function() {
            const documentTypes = @json($documentTypes ?? []);
            const paymentMethods = @json($paymentMethods ?? []);
            const paymentGateways = @json($paymentGateways ?? []);
            const cards = @json($cards ?? []);
            const digitalWallets = @json($digitalWallets ?? []);
            const banks = @json($banks ?? []);
            const clientOptions = @json($clientOptions ?? []);
            const defaultClientId = @json($defaultClientId ?? 4);
            const productsMap = @json($products ?? []);
            const productBranches = @json($productBranches ?? []);
            const cashRegisters = @json($cashRegisters ?? []);
            const taxRateByProductId = new Map();
            const defaultTaxPct = 18;
            productBranches.forEach((pb) => {
                const pid = Number(pb.product_id);
                if (!Number.isNaN(pid)) {
                    taxRateByProductId.set(pid, pb.tax_rate != null ? Number(pb.tax_rate) : defaultTaxPct);
                }
            });
            
            const docButtons = document.querySelectorAll('.doc-type-btn');
            const totalElement = document.getElementById('total');
            const documentTypeInput = document.getElementById('document-type-id');
            const clientInput = document.querySelector('input[name="client_id"]'); 
            const cashRegisterInput = document.getElementById('cash-register-id');
            const cashRegisterDisplay = document.getElementById('cash-register-display');

            function ensureDefaultClientSelected() {
                if (!clientInput) return;
                const current = String(clientInput.value || '').trim();
                if (current !== '') return;
                if (!defaultClientId) return;
                clientInput.value = String(defaultClientId);
                clientInput.dispatchEvent(new Event('change', { bubbles: true }));
            }

            document.getElementById('payment_type')?.addEventListener('change', toggleChargeSaleMode);
            document.getElementById('charge-credit-days')?.addEventListener('input', calculateChargeDueDateFromDays);
            document.getElementById('charge-due-date')?.addEventListener('change', calculateChargeDaysFromDueDate);

            function setupQuickClientCreate() {
                const form = document.querySelector('form[data-quick-client-form]');
                if (!form) return;
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalHtml = submitBtn ? submitBtn.innerHTML : '';
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="ri-loader-4-line animate-spin mr-1"></i> Guardando...';
                    }
                    try {
                        const res = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            body: new FormData(form)
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data?.success || !data?.id) {
                            const validation = data?.errors && typeof data.errors === 'object'
                                ? Object.values(data.errors).flat().join(', ')
                                : '';
                            throw new Error(validation || data?.message || 'No se pudo crear el cliente.');
                        }

                        const label = [data.name, data.document_number].filter(Boolean).join(' - ');
                        const normalizedOptions = Array.isArray(clientOptions) ? [...clientOptions] : [];
                        const exists = normalizedOptions.some((c) => String(c.id) === String(data.id));
                        if (!exists) {
                            normalizedOptions.push({ id: data.id, description: label || 'Cliente' });
                        }
                        clientOptions.length = 0;
                        clientOptions.push(...normalizedOptions);
                        window.dispatchEvent(new CustomEvent('update-combobox-options', {
                            detail: { name: 'client_id', options: normalizedOptions }
                        }));
                        if (clientInput) {
                            clientInput.value = String(data.id);
                            clientInput.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        showNotification('Cliente', data.message || 'Cliente creado y seleccionado.', 'success');
                        window.dispatchEvent(new CustomEvent('close-person-modal'));
                    } catch (err) {
                        showNotification('Error', err?.message || 'Error al crear cliente.', 'error');
                    } finally {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalHtml;
                        }
                    }
                });
            }
            
            fetch('/api/session/cash-register', {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.cash_register_id) {
                    const crId = data.cash_register_id;
                    const cashRegister = cashRegisters.find(cr => cr.id == crId);
                    
                    if (cashRegister) {
                        cashRegisterInput.value = crId;
                        cashRegisterDisplay.textContent = cashRegister.number + (cashRegister.status === 'A' ? ' (Activa)' : '');
                    }
                }
            })
            .catch(err => {
                console.error('Error al obtener caja de sesión:', err);
            });

            setupQuickClientCreate();
            ensureDefaultClientSelected();

            const paymentMethodsList = document.getElementById('payment-methods-list');
            const addPaymentMethodBtn = document.getElementById('add-payment-method-btn');
            const paymentMethodSelectionModal = document.getElementById('payment-method-selection-modal');
            const closePaymentMethodModalBtn = document.getElementById('close-payment-method-modal');
            const cardSelectionModal = document.getElementById('card-selection-modal');
            const closeCardModal = document.getElementById('close-card-modal');
            const cancelCardSelection = document.getElementById('cancel-card-selection');
            const confirmCardSelection = document.getElementById('confirm-card-selection');
            const walletSelectionModal = document.getElementById('wallet-selection-modal');
            const closeWalletModalBtn = document.getElementById('close-wallet-modal');
            const cancelWalletSelection = document.getElementById('cancel-wallet-selection');
            const confirmWalletSelection = document.getElementById('confirm-wallet-selection');
            let gatewayButtons = document.querySelectorAll('.gateway-btn');
            let cardButtons = document.querySelectorAll('.card-btn');
            const pmSelectionButtons = document.querySelectorAll('.pm-selection-btn');

            let paymentMethodsData = []; 
            let currentEditingIndex = -1; 
            let selectedGatewayId = null;
            let selectedCardId = null;
            let selectedWalletId = null;
            let cardModalListenersSetup = false;
            let walletModalListenersSetup = false;

            function fmtMoney(n) {
                return 'S/' + (Number(n) || 0).toFixed(2);
            }

            function calculateTotalPaid() {
                return paymentMethodsData.reduce((sum, pm) => sum + (parseFloat(pm.amount) || 0), 0);
            }

            function updatePaymentSummary() {
                const total = parseFloat((totalElement?.textContent || 'S/0.00').replace('S/', '').replace(',', '').trim()) || 0;
                const totalPaid = calculateTotalPaid();
                const remaining = total - totalPaid;
                const excess = totalPaid - total;

                document.getElementById('total-paid').textContent = fmtMoney(totalPaid);
                
                const remainingDiv = document.getElementById('payment-remaining');
                const excessDiv = document.getElementById('payment-excess');
                
                if (remaining > 0.01) {
                    remainingDiv.classList.remove('hidden');
                    document.getElementById('remaining-amount').textContent = fmtMoney(remaining);
                } else {
                    remainingDiv.classList.add('hidden');
                }
                
                if (excess > 0.01) {
                    excessDiv.classList.remove('hidden');
                    document.getElementById('excess-amount').textContent = fmtMoney(excess);
                } else {
                    excessDiv.classList.add('hidden');
                }
            }

            function renderPaymentMethod(index, paymentMethod) {
                const isCard = paymentMethod.isCard || false;
                const isWallet = paymentMethod.isWallet || false;
                const methodName = paymentMethod.methodName || '';
                const desc = (methodName || '').toLowerCase();
                const isTransfer = desc.includes('transferencia') || desc.includes('transfer') || desc.includes('deposito') || desc.includes('depósito');
                const amount = paymentMethod.amount || 0;
                const methodId = paymentMethod.methodId || null;
                const gatewayId = paymentMethod.gatewayId || null;
                const cardId = paymentMethod.cardId || null;
                const gatewayName = paymentMethod.gatewayName || '';
                const cardName = paymentMethod.cardName || '';
                const walletId = paymentMethod.walletId || null;
                const walletName = paymentMethod.walletName || '';
                const bankId = paymentMethod.bankId || null;
                const bankName = paymentMethod.bankName || '';

                const getMethodIcon = (methodDesc) => {
                    const desc = (methodDesc || '').toLowerCase();
                    if (desc.includes('tarjeta') || desc.includes('card')) return 'fa-credit-card';
                    if (desc.includes('efectivo') || desc.includes('cash')) return 'fa-money-bill-wave';
                    if (desc.includes('yape') || desc.includes('plin') || desc.includes('billetera')) return 'fa-mobile-alt';
                    if (desc.includes('transferencia') || desc.includes('transfer') || desc.includes('deposito') || desc.includes('depósito')) return 'fa-exchange-alt';
                    return 'fa-wallet';
                };

                const methodIcon = getMethodIcon(methodName);
                const hasCardInfo = isCard && gatewayName && cardName;
                const hasWalletInfo = isWallet && walletName;
                const hasBankInfo = isTransfer && bankId && bankName;

                const bankInfo = isTransfer ? `
                    <div class="mb-2 rounded-lg border-2 ${hasBankInfo ? 'border-indigo-200 bg-indigo-50' : 'border-orange-200 bg-orange-50'} p-2 dark:${hasBankInfo ? 'border-indigo-800 bg-indigo-900/20' : 'border-orange-800 bg-orange-900/20'}">
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Banco destino</label>
                        <select class="bank-select w-full rounded-lg border border-gray-200 py-2 px-3 text-sm font-medium focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" data-index="${index}">
                            <option value="">Seleccionar banco</option>
                            ${(banks || []).map(b => `<option value="${b.id}" ${bankId == b.id ? 'selected' : ''}>${b.description || ''}</option>`).join('')}
                        </select>
                    </div>
                ` : '';

                const walletInfo = isWallet ? `
                    <div class="mb-2 rounded-lg border-2 ${hasWalletInfo ? 'border-emerald-200 bg-emerald-50' : 'border-orange-200 bg-orange-50'} p-2 dark:${hasWalletInfo ? 'border-emerald-800 bg-emerald-900/20' : 'border-orange-800 bg-orange-900/20'}">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-bold ${hasWalletInfo ? 'text-emerald-700 dark:text-emerald-400' : 'text-orange-700 dark:text-orange-400'} wallet-name-${index}">${walletName || 'Elegir billetera (Yape, Plin...)'}</p>
                            <button type="button" class="select-wallet-btn ml-2 rounded-lg ${hasWalletInfo ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-orange-600 hover:bg-orange-700'} px-3 py-1.5 text-xs font-semibold text-white transition" data-index="${index}">
                                <i class="fas fa-${hasWalletInfo ? 'edit' : 'plus'}"></i>
                            </button>
                        </div>
                    </div>
                ` : '';

                const cardInfo = isCard ? `
                    <div class="mb-2 rounded-lg border-2 ${hasCardInfo ? 'border-green-200 bg-green-50' : 'border-orange-200 bg-orange-50'} p-2 dark:${hasCardInfo ? 'border-green-800 bg-green-900/20' : 'border-orange-800 bg-orange-900/20'}">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">Pasarela:</p>
                                <p class="text-sm font-bold ${hasCardInfo ? 'text-green-700 dark:text-green-400' : 'text-orange-700 dark:text-orange-400'} gateway-name-${index}">${gatewayName || 'No seleccionada'}</p>
                            </div>
                            <div class="flex-1">
                                <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">Tarjeta:</p>
                                <p class="text-sm font-bold ${hasCardInfo ? 'text-green-700 dark:text-green-400' : 'text-orange-700 dark:text-orange-400'} card-name-${index}">${cardName || 'No seleccionada'}</p>
                            </div>
                            <button type="button" class="select-card-btn ml-2 rounded-lg ${hasCardInfo ? 'bg-green-600 hover:bg-green-700' : 'bg-orange-600 hover:bg-orange-700'} px-3 py-1.5 text-xs font-semibold text-white transition" data-index="${index}">
                                <i class="fas fa-${hasCardInfo ? 'edit' : 'plus'}"></i>
                            </button>
                        </div>
                    </div>
                ` : '';

                return `
                    <div class="payment-method-item rounded-lg border-2 border-gray-300 bg-white p-3 dark:border-gray-600 dark:bg-gray-800 shadow-sm hover:shadow-md transition-shadow" data-index="${index}">
                        <div class="flex items-center justify-between mb-3">
                            <button type="button" class="payment-method-btn flex-1 rounded-lg border-2 ${isCard ? 'border-blue-500 bg-blue-50' : 'border-gray-300'} p-2.5 text-left transition hover:${isCard ? 'bg-blue-100' : 'bg-gray-100'} dark:${isCard ? 'border-blue-600 bg-blue-900/20' : 'border-gray-600'} dark:hover:${isCard ? 'bg-blue-900/30' : 'bg-gray-600'}" data-index="${index}">
                                <div class="flex items-center gap-2">
                                    <i class="fas ${methodIcon} text-lg ${isCard ? 'text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400'}"></i>
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">${methodName || 'Seleccionar método'}</p>
                                        ${isCard && !hasCardInfo ? '<p class="text-xs text-orange-600 dark:text-orange-400 mt-0.5">Configurar pasarela y tarjeta</p>' : ''}
                                        ${isWallet && !hasWalletInfo ? '<p class="text-xs text-orange-600 dark:text-orange-400 mt-0.5">Elegir billetera (Yape, Plin...)</p>' : ''}
                                        ${isTransfer && !hasBankInfo ? '<p class="text-xs text-orange-600 dark:text-orange-400 mt-0.5">Seleccionar banco</p>' : ''}
                                    </div>
                                    <i class="fas fa-chevron-down text-xs "></i>
                                </div>
                            </button>
                            <button type="button" class="remove-payment-method ml-2 rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700 transition" data-index="${index}" title="Eliminar método">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </div>
                        ${walletInfo}
                        ${cardInfo}
                        ${bankInfo}
                        <div class="flex items-center gap-2">
                            <div class="relative flex-1">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-bold text-gray-600 dark:text-gray-400">S/</span>
                                <input type="number" step="0.01" min="0" class="payment-amount-input w-full text-right rounded-lg border-2 border-gray-300 bg-white pl-8 pr-3 py-2.5 text-base font-bold text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-blue-400" 
                                    value="${amount > 0 ? amount.toFixed(2) : '0.00'}" data-index="${index}" placeholder="0.00">
                            </div>
                            <button type="button" class="fill-remaining-btn rounded-lg bg-blue-100 px-3 py-2.5 text-sm font-semibold text-blue-700 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50" data-index="${index}" title="Completar con lo que falta">
                                <i class="ri-money-dollar-circle-line"></i>
                            </button>
                        </div>
                        <input type="hidden" class="payment-method-id" value="${methodId || ''}" data-index="${index}">
                        <input type="hidden" class="payment-gateway-id" value="${gatewayId || ''}" data-index="${index}">
                        <input type="hidden" class="payment-card-id" value="${cardId || ''}" data-index="${index}">
                        <input type="hidden" class="payment-bank-id" value="${bankId || ''}" data-index="${index}">
                    </div>
                `;
            }

            function getRemainingAmount() {
                const total = parseFloat((totalElement?.textContent || 'S/0.00').replace('S/', '').replace(',', '').trim()) || 0;
                const totalPaid = calculateTotalPaid();
                return Math.max(0, total - totalPaid);
            }

            function fillRemainingAmount(index) {
                const remaining = getRemainingAmount();
                if (remaining > 0 && paymentMethodsData[index]) {
                    paymentMethodsData[index].amount = remaining;
                    updatePaymentMethodsList();
                    updatePaymentSummary();
                }
            }

            function addPaymentMethod() {
                if (!paymentMethods || paymentMethods.length === 0) {
                    console.error('No hay métodos de pago disponibles');
                    if (typeof showNotification === 'function') {
                        showNotification('Error', 'No hay métodos de pago disponibles', 'error');
                    }
                    return;
                }
                
                const total = parseFloat((totalElement?.textContent || 'S/0.00').replace('S/', '').replace(',', '').trim()) || 0;
                const totalPaid = calculateTotalPaid();
                const remaining = total - totalPaid;
                
                const defaultMethod = paymentMethods.find(pm => {
                    const desc = (pm.description || '').toLowerCase();
                    return !desc.includes('tarjeta') && !desc.includes('card');
                }) || paymentMethods[0];
                
                const isCard = defaultMethod && (defaultMethod.description.toLowerCase().includes('tarjeta') || defaultMethod.description.toLowerCase().includes('card'));
                
                const initialAmount = paymentMethodsData.length === 0 ? total : (remaining > 0 ? remaining : 0);
                
                const descDef = (defaultMethod?.description || '').toLowerCase();
                const isWallet = descDef.includes('billetera');
                const isTransferDef = descDef.includes('transferencia') || descDef.includes('transfer') || descDef.includes('deposito') || descDef.includes('depósito');
                const newPaymentMethod = {
                    methodId: defaultMethod?.id || null,
                    methodName: defaultMethod?.description || 'Seleccionar método',
                    isCard: isCard,
                    isWallet: isWallet,
                    isTransfer: isTransferDef,
                    amount: initialAmount,
                    gatewayId: null,
                    cardId: null,
                    gatewayName: '',
                    cardName: '',
                    walletId: null,
                    walletName: '',
                    bankId: null,
                    bankName: ''
                };
                
                paymentMethodsData.push(newPaymentMethod);
                updatePaymentMethodsList();
                updatePaymentSummary();
            }

            function removePaymentMethod(index) {
                paymentMethodsData.splice(index, 1);
                updatePaymentMethodsList();
                updatePaymentSummary();
            }

            function openPaymentMethodModal(index) {
                currentEditingIndex = index;
                paymentMethodSelectionModal.classList.remove('hidden');
                paymentMethodSelectionModal.classList.add('flex');
                
                if (paymentMethodsData[index]) {
                    const currentMethodId = paymentMethodsData[index].methodId;
                    pmSelectionButtons.forEach(btn => {
                        if (btn.dataset.methodId == currentMethodId) {
                            btn.classList.remove('border-gray-300', 'bg-gray-50');
                            btn.classList.add('border-blue-500', 'bg-blue-50');
                            const checkIcon = btn.querySelector('.fa-check-circle');
                            if (checkIcon) checkIcon.classList.remove('hidden');
                        } else {
                            btn.classList.remove('border-blue-500', 'bg-blue-50');
                            btn.classList.add('border-gray-300', 'bg-gray-50');
                            const checkIcon = btn.querySelector('.fa-check-circle');
                        if (checkIcon) checkIcon.classList.add('hidden');
                        }
                    });
                }
            }

            function closePaymentMethodModal(resetIndex = true) {
                paymentMethodSelectionModal.classList.add('hidden');
                paymentMethodSelectionModal.classList.remove('flex');
                if (resetIndex) {
                    currentEditingIndex = -1;
                }
            }

pmSelectionButtons.forEach(btn => {
                    btn.addEventListener('click', function() {
                        const methodId = parseInt(this.dataset.methodId);
                        const methodName = this.dataset.methodName;
                        const isCard = this.dataset.isCard === '1';
                        const isWallet = this.dataset.isWallet === '1';
                        const desc = (methodName || '').toLowerCase();
                        const isTransfer = desc.includes('transferencia') || desc.includes('transfer') || desc.includes('deposito') || desc.includes('depósito');
                        
                        if (currentEditingIndex >= 0 && paymentMethodsData[currentEditingIndex]) {
                            paymentMethodsData[currentEditingIndex].methodId = methodId;
                            paymentMethodsData[currentEditingIndex].methodName = methodName;
                            paymentMethodsData[currentEditingIndex].isCard = isCard;
                            paymentMethodsData[currentEditingIndex].isWallet = isWallet;
                            paymentMethodsData[currentEditingIndex].isTransfer = isTransfer;
                            
                        if (isCard) {
                            paymentMethodsData[currentEditingIndex].walletId = null;
                            paymentMethodsData[currentEditingIndex].walletName = '';
                            paymentMethodsData[currentEditingIndex].bankId = null;
                            paymentMethodsData[currentEditingIndex].bankName = '';
                            const savedIndex = currentEditingIndex;
                            closePaymentMethodModal(false);
                            currentEditingIndex = savedIndex;
                            setTimeout(() => openCardModal(), 200);
                        } else if (isWallet) {
                            paymentMethodsData[currentEditingIndex].gatewayId = null;
                            paymentMethodsData[currentEditingIndex].cardId = null;
                            paymentMethodsData[currentEditingIndex].gatewayName = '';
                            paymentMethodsData[currentEditingIndex].cardName = '';
                            paymentMethodsData[currentEditingIndex].walletId = null;
                            paymentMethodsData[currentEditingIndex].bankId = null;
                            paymentMethodsData[currentEditingIndex].bankName = '';
                            paymentMethodsData[currentEditingIndex].walletName = '';
                            const savedIndex = currentEditingIndex;
                            closePaymentMethodModal(false);
                            currentEditingIndex = savedIndex;
                            setTimeout(() => openWalletModal(), 200);
                        } else {
                            paymentMethodsData[currentEditingIndex].gatewayId = null;
                            paymentMethodsData[currentEditingIndex].cardId = null;
                            paymentMethodsData[currentEditingIndex].gatewayName = '';
                            paymentMethodsData[currentEditingIndex].cardName = '';
                            paymentMethodsData[currentEditingIndex].walletId = null;
                            paymentMethodsData[currentEditingIndex].walletName = '';
                            paymentMethodsData[currentEditingIndex].bankId = null;
                            paymentMethodsData[currentEditingIndex].bankName = '';
                            updatePaymentMethodsList();
                            closePaymentMethodModal();
                        }
                    }
                });
            });

            paymentMethodSelectionModal?.addEventListener('click', function(e) {
                if (e.target === paymentMethodSelectionModal) {
                    closePaymentMethodModal();
                }
            });

            closePaymentMethodModalBtn?.addEventListener('click', closePaymentMethodModal);

            function updatePaymentMethodsList() {
                paymentMethodsList.innerHTML = paymentMethodsData.map((pm, index) => renderPaymentMethod(index, pm)).join('');
                
                paymentMethodsList.querySelectorAll('.payment-method-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const index = parseInt(this.dataset.index);
                        openPaymentMethodModal(index);
                    });
                });
                
                paymentMethodsList.querySelectorAll('.payment-amount-input').forEach(input => {
                    input.addEventListener('input', function() {
                        const index = parseInt(this.dataset.index);
                        paymentMethodsData[index].amount = parseFloat(this.value) || 0;
                        updatePaymentSummary();
                    });
                });
                
                paymentMethodsList.querySelectorAll('.remove-payment-method').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const index = parseInt(this.dataset.index);
                        removePaymentMethod(index);
                    });
                });
                
                paymentMethodsList.querySelectorAll('.bank-select').forEach(sel => {
                    sel.addEventListener('change', function() {
                        const index = parseInt(this.dataset.index);
                        const id = this.value ? parseInt(this.value) : null;
                        const bank = banks.find(b => b.id == id);
                        if (paymentMethodsData[index]) {
                            paymentMethodsData[index].bankId = id;
                            paymentMethodsData[index].bankName = bank ? bank.description : '';
                        }
                        updatePaymentMethodsList();
                    });
                });
                
                paymentMethodsList.querySelectorAll('.select-card-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const index = parseInt(this.dataset.index);
                        currentEditingIndex = index;
                        openCardModal();
                    });
                });
                
                paymentMethodsList.querySelectorAll('.select-wallet-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const index = parseInt(this.dataset.index);
                        currentEditingIndex = index;
                        openWalletModal();
                    });
                });
                
                paymentMethodsList.querySelectorAll('.fill-remaining-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const index = parseInt(this.dataset.index);
                        fillRemainingAmount(index);
                    });
                });
            }

            addPaymentMethodBtn?.addEventListener('click', addPaymentMethod);

            function openCardModal() {
                gatewayButtons = document.querySelectorAll('.gateway-btn');
                cardButtons = document.querySelectorAll('.card-btn');
                
                cardSelectionModal.classList.remove('hidden');
                cardSelectionModal.classList.add('flex');
                
                if (currentEditingIndex >= 0 && paymentMethodsData[currentEditingIndex]) {
                    const pm = paymentMethodsData[currentEditingIndex];
                    selectedGatewayId = pm.gatewayId ? String(pm.gatewayId) : null;
                    selectedCardId = pm.cardId ? String(pm.cardId) : null;
                    console.log('Restaurando valores del método:', pm, { selectedGatewayId, selectedCardId });
                } else {
                    selectedGatewayId = null;
                    selectedCardId = null;
                    console.warn('currentEditingIndex es inválido o no hay método de pago:', currentEditingIndex);
                }
                
                gatewayButtons.forEach(b => {
                    if (b.dataset.gatewayId && b.dataset.gatewayId == selectedGatewayId) {
                        b.classList.remove('border-gray-300', 'bg-gray-50');
                        b.classList.add('border-blue-500', 'bg-blue-50');
                        const checkIcon = b.querySelector('.fa-check-circle');
                        if (checkIcon) checkIcon.classList.remove('hidden');
                    } else {
                        b.classList.remove('border-blue-500', 'bg-blue-50');
                        b.classList.add('border-gray-300', 'bg-gray-50');
                        const checkIcon = b.querySelector('.fa-check-circle');
                        if (checkIcon) checkIcon.classList.add('hidden');
                    }
                });
                
                cardButtons.forEach(b => {
                    if (b.dataset.cardId && b.dataset.cardId == selectedCardId) {
                        b.classList.remove('border-gray-300', 'bg-gray-50');
                        b.classList.add('border-blue-500', 'bg-blue-50');
                        const checkIcon = b.querySelector('.fa-check-circle');
                        if (checkIcon) checkIcon.classList.remove('hidden');
                    } else {
                        b.classList.remove('border-blue-500', 'bg-blue-50');
                        b.classList.add('border-gray-300', 'bg-gray-50');
                        const checkIcon = b.querySelector('.fa-check-circle');
                        if (checkIcon) checkIcon.classList.add('hidden');
                    }
                });
                
                setupCardModalListeners();
                
                updateConfirmButton();
            }
            
            function setupCardModalListeners() {
                if (cardModalListenersSetup) return;
                cardModalListenersSetup = true;
                
                cardSelectionModal.addEventListener('click', function(e) {
                    if (e.target.closest('#confirm-card-selection') || e.target.closest('#cancel-card-selection') || e.target.closest('#close-card-modal')) {
                        return;
                    }
                    
                    const gatewayBtn = e.target.closest('.gateway-btn');
                    if (gatewayBtn && gatewayBtn.dataset.gatewayId) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        gatewayButtons = document.querySelectorAll('.gateway-btn');
                        
                        gatewayButtons.forEach(b => {
                            if (!b.dataset.gatewayId) return;
                            b.classList.remove('border-blue-500', 'bg-blue-50');
                            b.classList.add('border-gray-300', 'bg-gray-50');
                            const checkIcon = b.querySelector('.fa-check-circle');
                            if (checkIcon) checkIcon.classList.add('hidden');
                        });
                        
                        gatewayBtn.classList.remove('border-gray-300', 'bg-gray-50');
                        gatewayBtn.classList.add('border-blue-500', 'bg-blue-50');
                        const checkIcon = gatewayBtn.querySelector('.fa-check-circle');
                        if (checkIcon) checkIcon.classList.remove('hidden');
                        selectedGatewayId = gatewayBtn.dataset.gatewayId;
                        console.log('Pasarela seleccionada:', selectedGatewayId, gatewayBtn.dataset.gatewayName);
                        updateConfirmButton();
                        return;
                    }
                    
                    const cardBtn = e.target.closest('.card-btn');
                    if (cardBtn && cardBtn.dataset.cardId) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        cardButtons = document.querySelectorAll('.card-btn');
                        
                        cardButtons.forEach(b => {
                            if (!b.dataset.cardId) return;
                            b.classList.remove('border-blue-500', 'bg-blue-50');
                            b.classList.add('border-gray-300', 'bg-gray-50');
                            const checkIcon = b.querySelector('.fa-check-circle');
                            if (checkIcon) checkIcon.classList.add('hidden');
                        });
                        
                        cardBtn.classList.remove('border-gray-300', 'bg-gray-50');
                        cardBtn.classList.add('border-blue-500', 'bg-blue-50');
                        const checkIcon = cardBtn.querySelector('.fa-check-circle');
                        if (checkIcon) checkIcon.classList.remove('hidden');
                        selectedCardId = cardBtn.dataset.cardId;
                        console.log('Tarjeta seleccionada:', selectedCardId, cardBtn.dataset.cardName);
                        updateConfirmButton();
                        return;
                    }
                });
            }

            function closeModal() {
                cardSelectionModal.classList.add('hidden');
                cardSelectionModal.classList.remove('flex');
            }

            closeCardModal?.addEventListener('click', closeModal);
            cancelCardSelection?.addEventListener('click', function() {
                closeModal();
                currentEditingIndex = -1;
            });

            cardSelectionModal?.addEventListener('click', function(e) {
                if (e.target === cardSelectionModal) {
                    closeModal();
                }
            });

            function updateConfirmButton() {
                if (selectedGatewayId && selectedCardId) {
                    confirmCardSelection.disabled = false;
                    confirmCardSelection.classList.remove('opacity-50', 'cursor-not-allowed');
                } else {
                    confirmCardSelection.disabled = true;
                    confirmCardSelection.classList.add('opacity-50', 'cursor-not-allowed');
                }
            }

            confirmCardSelection?.addEventListener('click', function() {
                
                if (selectedGatewayId && selectedCardId && currentEditingIndex >= 0) {
                    gatewayButtons = document.querySelectorAll('.gateway-btn');
                    cardButtons = document.querySelectorAll('.card-btn');
                    
                    const pm = paymentMethodsData[currentEditingIndex];
                    pm.gatewayId = selectedGatewayId;
                    pm.cardId = selectedCardId;
                    
                    const gatewayBtn = Array.from(gatewayButtons).find(b => b.dataset.gatewayId == selectedGatewayId);
                    const cardBtn = Array.from(cardButtons).find(b => b.dataset.cardId == selectedCardId);

                    if (gatewayBtn) {
                        pm.gatewayName = gatewayBtn.dataset.gatewayName || '';
                    }
                    if (cardBtn) {
                        pm.cardName = cardBtn.dataset.cardName || '';
                    }

                    updatePaymentMethodsList();
                    closeModal();
                    currentEditingIndex = -1;
                    
                    selectedGatewayId = null;
                    selectedCardId = null;
                } else {
                }
            });

            function openWalletModal() {
                walletSelectionModal.classList.remove('hidden');
                walletSelectionModal.classList.add('flex');
                if (currentEditingIndex >= 0 && paymentMethodsData[currentEditingIndex]) {
                    const pm = paymentMethodsData[currentEditingIndex];
                    selectedWalletId = pm.walletId ? String(pm.walletId) : null;
                } else {
                    selectedWalletId = null;
                }
                const walletButtons = document.querySelectorAll('.wallet-btn');
                walletButtons.forEach(b => {
                    if (b.dataset.walletId && b.dataset.walletId == selectedWalletId) {
                        b.classList.remove('border-gray-300', 'bg-gray-50');
                        b.classList.add('border-emerald-500', 'bg-emerald-50');
                        const checkIcon = b.querySelector('.fa-check-circle');
                        if (checkIcon) checkIcon.classList.remove('hidden');
                    } else {
                        b.classList.remove('border-emerald-500', 'bg-emerald-50');
                        b.classList.add('border-gray-300', 'bg-gray-50');
                        const checkIcon = b.querySelector('.fa-check-circle');
                        if (checkIcon) checkIcon.classList.add('hidden');
                    }
                });
                setupWalletModalListeners();
                updateWalletConfirmButton();
            }

            function closeWalletModal() {
                walletSelectionModal.classList.add('hidden');
                walletSelectionModal.classList.remove('flex');
            }

            function setupWalletModalListeners() {
                if (walletModalListenersSetup) return;
                walletModalListenersSetup = true;
                walletSelectionModal.addEventListener('click', function(e) {
                    if (e.target.closest('#confirm-wallet-selection') || e.target.closest('#cancel-wallet-selection') || e.target.closest('#close-wallet-modal')) return;
                    const walletBtn = e.target.closest('.wallet-btn');
                    if (walletBtn && walletBtn.dataset.walletId) {
                        e.preventDefault();
                        e.stopPropagation();
                        const walletButtons = document.querySelectorAll('.wallet-btn');
                        walletButtons.forEach(b => {
                            b.classList.remove('border-emerald-500', 'bg-emerald-50');
                            b.classList.add('border-gray-300', 'bg-gray-50');
                            const checkIcon = b.querySelector('.fa-check-circle');
                            if (checkIcon) checkIcon.classList.add('hidden');
                        });
                        walletBtn.classList.remove('border-gray-300', 'bg-gray-50');
                        walletBtn.classList.add('border-emerald-500', 'bg-emerald-50');
                        const checkIcon = walletBtn.querySelector('.fa-check-circle');
                        if (checkIcon) checkIcon.classList.remove('hidden');
                        selectedWalletId = walletBtn.dataset.walletId;
                        updateWalletConfirmButton();
                    }
                });
            }

            function updateWalletConfirmButton() {
                if (confirmWalletSelection) {
                    confirmWalletSelection.disabled = !selectedWalletId;
                    if (selectedWalletId) {
                        confirmWalletSelection.classList.remove('opacity-50', 'cursor-not-allowed');
                    } else {
                        confirmWalletSelection.classList.add('opacity-50', 'cursor-not-allowed');
                    }
                }
            }

            closeWalletModalBtn?.addEventListener('click', closeWalletModal);
            cancelWalletSelection?.addEventListener('click', function() {
                closeWalletModal();
                currentEditingIndex = -1;
            });
            walletSelectionModal?.addEventListener('click', function(e) {
                if (e.target === walletSelectionModal) closeWalletModal();
            });
            confirmWalletSelection?.addEventListener('click', function() {
                if (!selectedWalletId || currentEditingIndex < 0) return;
                const pm = paymentMethodsData[currentEditingIndex];
                const walletButtons = document.querySelectorAll('.wallet-btn');
                const walletBtn = Array.from(walletButtons).find(b => b.dataset.walletId == selectedWalletId);
                pm.walletId = selectedWalletId;
                pm.walletName = walletBtn ? (walletBtn.dataset.walletName || '') : '';
                updatePaymentMethodsList();
                closeWalletModal();
                currentEditingIndex = -1;
                selectedWalletId = null;
            });

            // Tipo de documento
            docButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    docButtons.forEach(b => {
                        b.classList.remove('doc-active');
                        b.classList.add('border-gray-300', 'bg-gray-50');
                        b.classList.remove('border-blue-500', 'bg-blue-50');
                        b.querySelector('.fa-file-alt').classList.remove('text-blue-600',
                            'dark:text-blue-400');
                        b.querySelector('.fa-file-alt').classList.add('text-gray-600',
                            'dark:text-gray-400');
                        const checkIcon = b.querySelector('.fa-check-circle');
                        if (checkIcon) checkIcon.classList.add('hidden');
                    });
                    this.classList.add('doc-active');
                    this.classList.remove('border-gray-300', 'bg-gray-50');
                    this.classList.add('border-blue-500', 'bg-blue-50');
                    this.querySelector('.fa-file-alt').classList.remove('text-gray-600',
                        'dark:text-gray-400');
                    this.querySelector('.fa-file-alt').classList.add('text-blue-600',
                        'dark:text-blue-400');
                    const checkIcon = this.querySelector('.fa-check-circle');
                    if (checkIcon) checkIcon.classList.remove('hidden');

                    // Obtener el ID directamente del atributo data-doc-id
                    const docId = this.dataset.docId;
                    if (docId && documentTypeInput) {
                        documentTypeInput.value = docId;
                    }
                });
            });


            // Cargar orden desde localStorage o desde el servidor (si es borrador)
            const ACTIVE_SALE_KEY_STORAGE = 'restaurantActiveSaleKey';
            const draftSaleFromServer = @json($draftSale ?? null);
            
            let sale = null;
            
            // Filtrar ítems válidos: pId y qty > 0, y que el producto exista en la BD (productsMap)
            function validItems(items) {
                if (!Array.isArray(items)) return [];
                return items.filter(it => {
                    const id = it.pId ?? it.id;
                    const qty = Number(it.qty) || 0;
                    if (id == null || id === '' || Number.isNaN(Number(id)) || qty <= 0) return false;
                    // Excluir productos que no existen en la BD (evita "Producto #5" fantasma)
                    const idStr = String(id);
                    const exists = productsMap && (Object.prototype.hasOwnProperty.call(productsMap, idStr) || Object.prototype.hasOwnProperty.call(productsMap, Number(id)));
                    return !!exists;
                });
            }

            // Cargar venta solo si: (1) viene borrador del servidor (movement_id), o (2) viene desde Create (botón Cobrar)
            if (draftSaleFromServer && draftSaleFromServer.items && draftSaleFromServer.items.length > 0) {
                sale = {
                    id: draftSaleFromServer.id,
                    number: draftSaleFromServer.number,
                    clientId: draftSaleFromServer.clientId != null ? draftSaleFromServer.clientId : null,
                    clientName: draftSaleFromServer.clientName || 'Público General',
                    items: validItems(draftSaleFromServer.items),
                    status: 'draft',
                    notes: draftSaleFromServer.notes || '',
                    pendingAmount: draftSaleFromServer.pendingAmount || 0,
                    sale_payment_mode: draftSaleFromServer.sale_payment_mode || null,
                    credit_days: draftSaleFromServer.credit_days != null ? draftSaleFromServer.credit_days : null,
                };
            } else if (sessionStorage.getItem('sales_charge_from_create') === '1') {
                sessionStorage.removeItem('sales_charge_from_create');
                const db = JSON.parse(localStorage.getItem('restaurantDB') || '{}');
                const activeKey = localStorage.getItem(ACTIVE_SALE_KEY_STORAGE);
                const fromStorage = activeKey ? db[activeKey] : null;
                if (fromStorage && Array.isArray(fromStorage.items)) {
                    sale = { ...fromStorage, items: validItems(fromStorage.items) };
                    if (sale.items.length !== fromStorage.items.length && activeKey) {
                        db[activeKey] = sale;
                        localStorage.setItem('restaurantDB', JSON.stringify(db));
                    }
                } else {
                    sale = null;
                }
            } else {
                sale = null;
                localStorage.removeItem(ACTIVE_SALE_KEY_STORAGE);
            }

            function fmtMoney(n) {
                return 'S/' + (Number(n) || 0).toFixed(2);
            }

            // Hacer fmtMoney disponible globalmente
            window.fmtMoney = fmtMoney;
            function hydratePaymentMethodsFromSale(total) {
                if (!sale || !Array.isArray(sale.payment_methods) || sale.payment_methods.length === 0) {
                    return false;
                }

                const normalized = sale.payment_methods
                    .map((pm) => {
                        const methodId = Number(pm.payment_method_id ?? pm.methodId);
                        if (!methodId) return null;
                        const catalogMethod = paymentMethods.find((m) => Number(m.id) === methodId);
                        if (!catalogMethod) return null;

                        const methodName = catalogMethod.description || '';
                        const mDesc = methodName.toLowerCase();
                        const isCard = mDesc.includes('tarjeta') || mDesc.includes('card');
                        const isWallet = mDesc.includes('billetera');
                        const isTransfer = mDesc.includes('transferencia') || mDesc.includes('transfer') || mDesc.includes('deposito') || mDesc.includes('depósito');
                        const bankId = pm.bank_id ? Number(pm.bank_id) : null;
                        const bank = bankId ? banks.find(b => b.id == bankId) : null;

                        return {
                            methodId,
                            methodName,
                            isCard,
                            isWallet,
                            isTransfer,
                            amount: Number(pm.amount ?? 0),
                            gatewayId: pm.payment_gateway_id ? Number(pm.payment_gateway_id) : null,
                            cardId: pm.card_id ? Number(pm.card_id) : null,
                            gatewayName: '',
                            cardName: '',
                            walletId: pm.digital_wallet_id ? Number(pm.digital_wallet_id) : null,
                            walletName: '',
                            bankId,
                            bankName: bank ? bank.description : '',
                        };
                    })
                    .filter(Boolean);

                if (normalized.length === 0) {
                    return false;
                }

                const sum = normalized.reduce((acc, item) => acc + (Number(item.amount) || 0), 0);
                if (sum <= 0) {
                    normalized[0].amount = Number(total || 0);
                }

                paymentMethodsData = normalized;
                updatePaymentMethodsList();
                return true;
            }

            function renderSale() {
                if (!sale || !Array.isArray(sale.items) || sale.items.length === 0) {
                    window.location.href = "{{ route('sales.create') }}";
                    return;
                }

                if (clientInput) {
                    clientInput.value = sale.clientId ? String(sale.clientId) : String(defaultClientId);
                }

                const totalItems = sale.items.reduce((sum, it) => sum + (Number(it.qty) || 0), 0);
                document.getElementById('items-count').textContent = `${totalItems} items`;

                let subtotal = 0;
                const rows = sale.items.map((it) => {
                    const qty = Number(it.qty) || 0;
                    // Buscar el nombre del producto: primero en it.name, luego en productsMap, luego usar ID
                    const description = it.name || productsMap[it.pId] || `Producto #${it.pId}`;
                    const price = Number(it.price) || 0;
                    const lineTotal = qty * price;
                    subtotal += lineTotal;
                    const safeNote = (it.note ?? it.comment ?? '') || '';
                    return `
                <div class="flex items-center justify-between rounded-lg border border-gray-200 p-2 dark:border-gray-700 dark:bg-gray-700">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">${description}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">${qty} x ${fmtMoney(price)}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">${safeNote}</p>
                    </div>
                    <p class="ml-2 text-sm font-bold text-gray-900 dark:text-white whitespace-nowrap">${fmtMoney(lineTotal)}</p>
                </div>
            `;
                }).join('');

                document.getElementById('items-list').innerHTML = rows;

                // Calcular subtotal e IGV por producto según su tasa (del sistema o del ítem si es borrador).
                let subtotalBase = 0;
                let tax = 0;
                sale.items.forEach((it) => {
                    const itemTotal = (Number(it.qty) || 0) * (Number(it.price) || 0);
                    const taxPct = it.tax_rate != null ? Number(it.tax_rate) : (taxRateByProductId.get(Number(it.pId)) ?? defaultTaxPct);
                    const taxVal = taxPct / 100;
                    const itemSubtotal = taxVal > 0 ? itemTotal / (1 + taxVal) : itemTotal;
                    subtotalBase += itemSubtotal;
                    tax += itemTotal - itemSubtotal;
                });
                const total = subtotalBase + tax;

                document.getElementById('subtotal').textContent = fmtMoney(subtotalBase);
                document.getElementById('tax').textContent = fmtMoney(tax);
                document.getElementById('total').textContent = fmtMoney(total);

                // Inicializar el primer método de pago con el total
                const preloaded = hydratePaymentMethodsFromSale(total);
                if (!preloaded && paymentMethodsData.length === 0) {
                    addPaymentMethod();
                }
                updatePaymentSummary();

                // Si es un borrador, establecer las notas
                if (sale.notes && document.getElementById('sale-notes')) {
                    const notesText = sale.notes.replace(' [BORRADOR]', '').trim();
                    document.getElementById('sale-notes').value = notesText;
                }

                const paymentTypeEl = document.getElementById('payment_type');
                if (paymentTypeEl) {
                    syncPaymentTypeButtonsDisabled(false);
                    paymentTypeEl.disabled = false;
                    paymentTypeEl.classList.remove('opacity-60', 'cursor-not-allowed', 'pointer-events-none');
                    paymentTypeEl.classList.remove('bg-slate-100', 'dark:bg-gray-800/80');
                    paymentTypeEl.classList.add('bg-white', 'dark:bg-gray-700');
                    const fromSale = sale.sale_payment_mode === 'CREDITO' || sale.sale_payment_mode === 'CONTADO'
                        ? sale.sale_payment_mode
                        : null;
                    if (fromSale) {
                        paymentTypeEl.value = fromSale;
                    } else if (sale.status !== 'draft' && !paymentTypeEl.value) {
                        paymentTypeEl.value = 'CONTADO';
                    }
                    const cdEl = document.getElementById('charge-credit-days');
                    if (cdEl && sale.credit_days != null && sale.credit_days !== '') {
                        cdEl.value = String(Math.max(0, parseInt(sale.credit_days, 10) || 0));
                    }
                    if (typeof calculateChargeDueDateFromDays === 'function') {
                        calculateChargeDueDateFromDays();
                    }
                    toggleChargeSaleMode();
                }
            }

            renderSale();

            // El tipo de documento ya está establecido en el HTML con el primer valor
            // No es necesario hacer clic automático

            // Funciones de notificación
            let notificationTimeout;
            
            function showNotification(title, message, type = 'info') {
                const notification = document.getElementById('payment-notification');
                const content = document.getElementById('notification-content');
                const icon = document.getElementById('notification-icon');
                const titleEl = document.getElementById('notification-title');
                const messageEl = document.getElementById('notification-message');
                
                if (!notification || !content || !icon || !titleEl || !messageEl) return;
                
                // Limpiar timeout anterior
                if (notificationTimeout) {
                    clearTimeout(notificationTimeout);
                }
                
                // Configurar colores según el tipo
                const colors = {
                    success: 'from-green-500 to-emerald-600 border-green-400/30',
                    error: 'from-red-500 to-red-600 border-red-400/30',
                    warning: 'from-amber-500 to-orange-600 border-amber-400/30',
                    info: 'from-blue-500 to-blue-600 border-blue-400/30'
                };
                
                const icons = {
                    success: 'fa-check-circle',
                    error: 'fa-exclamation-circle',
                    warning: 'fa-exclamation-triangle',
                    info: 'fa-info-circle'
                };
                
                content.className = `bg-gradient-to-r ${colors[type]} text-white px-6 py-4 rounded-xl shadow-2xl border backdrop-blur-sm flex items-center gap-4 min-w-[320px]`;
                icon.className = `fas ${icons[type]} text-2xl`;
                titleEl.textContent = title;
                messageEl.textContent = message;
                
                notification.classList.add('notification-show');
                
                notificationTimeout = setTimeout(() => {
                    hidePaymentNotification();
                }, 5000);
            }
            
            function hidePaymentNotification() {
                const notification = document.getElementById('payment-notification');
                if (notification) {
                    notification.classList.remove('notification-show');
                }
            }

            function showErrorModal(message, title = 'No se pudo completar la venta') {
                if (window.Swal && typeof window.Swal.fire === 'function') {
                    window.Swal.fire({
                        icon: 'error',
                        title,
                        text: message,
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#2563eb'
                    });
                    return;
                }
                showNotification('Error', message, 'error');
            }

            // Confirmar pago
            document.getElementById('confirm-btn')?.addEventListener('click', function() {
                const docTypeId = documentTypeInput?.value;
                if (!docTypeId) {
                    showNotification('Error', 'Selecciona un tipo de documento', 'error');
                    return;
                }
                const cashRegisterId = cashRegisterInput?.value;
                if (!cashRegisterId) {
                    showNotification('Error', 'Selecciona una caja', 'error');
                    return;
                }

                const totalText = (totalElement?.textContent || 'S/0.00').replace('S/', '').replace(',', '').trim();
                const total = parseFloat(totalText) || 0;
                const totalPaid = calculateTotalPaid();

                const saleModeEl = document.getElementById('payment_type');
                let saleMode = 'CONTADO';
                if (saleModeEl && saleModeEl.value) {
                    saleMode = String(saleModeEl.value).trim().toUpperCase();
                }
                if (saleMode !== 'CREDITO' && saleMode !== 'CONTADO') {
                    saleMode = 'CONTADO';
                }
                const creditDays = Math.max(0, parseInt(document.getElementById('charge-credit-days')?.value || '0', 10) || 0);
                const isCredit = saleMode === 'CREDITO';

                if (isCredit) {
                    const pid = clientInput?.value ? parseInt(clientInput.value, 10) : null;
                    if (!pid || pid <= 0) {
                        showNotification('Error', 'Para venta a crédito seleccione un cliente identificado.', 'error');
                        return;
                    }
                    if (totalPaid > total + 0.01) {
                        showNotification('Error', 'El abono no puede superar el total de la venta.', 'error');
                        return;
                    }
                } else {
                    if (paymentMethodsData.length === 0) {
                        showNotification('Error', 'Agrega al menos un método de pago', 'error');
                        return;
                    }
                    if (Math.abs(totalPaid - total) > 0.01) {
                        showNotification('Error', `La suma de los métodos de pago (${fmtMoney(totalPaid)}) debe ser igual al total (${fmtMoney(total)})`, 'error');
                        return;
                    }
                }

                for (let i = 0; i < paymentMethodsData.length; i++) {
                    const pm = paymentMethodsData[i];
                    const amt = parseFloat(pm.amount) || 0;
                    if (amt <= 0.005) continue;
                    if (pm.isCard) {
                        if (!pm.gatewayId || !pm.cardId) {
                            showNotification('Error', `El método de pago "${pm.methodName}" requiere seleccionar pasarela y tarjeta`, 'error');
                            currentEditingIndex = i;
                            openCardModal();
                            return;
                        }
                    }
                    if (pm.isWallet) {
                        if (!pm.walletId || !pm.walletName) {
                            showNotification('Error', `El método de pago "${pm.methodName}" requiere elegir billetera (Yape, Plin, etc.)`, 'error');
                            currentEditingIndex = i;
                            openWalletModal();
                            return;
                        }
                    }
                }

                if (!sale || !Array.isArray(sale.items) || sale.items.length === 0) {
                    showNotification('Error', 'No hay una orden activa', 'error');
                    setTimeout(() => {
                        window.location.href = "{{ route('sales.index') }}";
                    }, 2000);
                    return;
                }

                const payload = {
                    items: sale.items.map(it => ({
                        pId: it.pId ?? it.id,
                        name: it.name,
                        qty: Number(it.qty) || 0,
                        price: Number(it.price) || 0,
                        note: String(it.note ?? it.comment ?? '').trim(),
                    })),
                    document_type_id: parseInt(docTypeId),
                    cash_register_id: parseInt(cashRegisterId),
                    person_id: clientInput?.value ? parseInt(clientInput.value) : null,
                    payment_methods: paymentMethodsData.map(pm => ({
                        payment_method_id: pm.methodId,
                        amount: parseFloat(pm.amount) || 0,
                        payment_gateway_id: pm.gatewayId ? parseInt(pm.gatewayId) : null,
                        card_id: pm.cardId ? parseInt(pm.cardId) : null,
                        digital_wallet_id: pm.walletId ? parseInt(pm.walletId) : null,
                        bank_id: pm.bankId ? parseInt(pm.bankId) : null,
                    })),
                    notes: document.getElementById('sale-notes')?.value || '',
                    sale_payment_mode: saleMode,
                    credit_days: creditDays,
                };
                
                // Si es un borrador, agregar el movement_id para actualizar en lugar de crear
                if (sale.id && sale.status === 'draft') {
                    payload.movement_id = sale.id;
                }

                this.disabled = true;
                const originalText = this.textContent;
                this.textContent = 'Procesando...';

                fetch('{{ route('sales.process') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') || '',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    })
                    .then(async r => {
                        const contentType = r.headers.get('content-type') || '';

                        if (contentType.includes('application/json')) {
                            const data = await r.json();
                            if (!r.ok) {
                                let errorMessage = data.message || data.error || 'Error al procesar la venta';
                                if (data.errors && typeof data.errors === 'object') {
                                    const validationErrors = Object.values(data.errors).flat().join(', ');
                                    errorMessage = validationErrors || errorMessage;
                                }
                                if (r.status >= 500 && (!errorMessage || errorMessage === 'Error al procesar la venta')) {
                                    errorMessage = 'Ocurri un error interno al procesar la venta. Por favor, intntalo nuevamente en unos minutos.';
                                }
                                throw new Error(errorMessage);
                            }
                            return data;
                        }

                        await r.text();
                        if (!r.ok) {
                            throw new Error('Ocurri un error interno al procesar la venta. Por favor, intntalo nuevamente en unos minutos.');
                        }
                        throw new Error('Respuesta inesperada del servidor.');
                    })
                    .then(data => {
                        if (!data.success) {
                            const errorMessage = data.message || data.error || 'Error al procesar la venta';
                            throw new Error(errorMessage);
                        }

                        // Limpiar venta activa
                        const db2 = JSON.parse(localStorage.getItem('restaurantDB') || '{}');
                        const k = localStorage.getItem(ACTIVE_SALE_KEY_STORAGE);
                        if (k && db2[k]) {
                            db2[k].status = 'completed';
                            db2[k].items = [];
                            localStorage.setItem('restaurantDB', JSON.stringify(db2));
                        }
                        localStorage.removeItem(ACTIVE_SALE_KEY_STORAGE);

                        sessionStorage.setItem('flash_success_message', data.message || 'Venta cobrada correctamente');
                        const viewId = new URLSearchParams(window.location.search).get('view_id');
                        let url = "{{ route('sales.index') }}";
                        if (viewId) url += (url.includes('?') ? '&' : '?') + 'view_id=' + encodeURIComponent(viewId);
                        window.location.href = url;
                    })
                    .catch(err => {    
                        const errorMessage = err.message || 'Error al procesar la venta';
                        showErrorModal(errorMessage);
                        this.disabled = false;
                        this.textContent = originalText;
                    });
            });
        });
    </script>
@endsection
