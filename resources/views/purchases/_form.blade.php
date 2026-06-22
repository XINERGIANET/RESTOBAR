@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Nueva Compra" />

    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-600">
            <div class="font-bold flex items-center gap-2 mb-2">
                <i class="ri-error-warning-line text-lg"></i> Por favor corrige los siguientes errores:
            </div>
            <ul class="list-disc pl-6 space-y-1">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $purchaseMovement = $purchaseMovement ?? optional($purchase ?? null)->purchaseMovement ?? null;
        $productsForForm = $products ?? collect();
        $existingItems = collect($purchaseMovement?->details ?? [])->map(function ($detail) use ($productsForForm) {
            $product = $productsForForm->first(fn($p) => ($p->id ?? $p['id'] ?? 0) == ($detail->producto_id ?? $detail->product_id ?? 0));
            $imageUrl = $product && isset($product->image_url) ? $product->image_url : null;
            return [
                'product_id' => (int) ($detail->producto_id ?? $detail->product_id ?? 0),
                'unit_id' => (int) ($detail->unidad_id ?? $detail->unit_id ?? 0),
                'description' => (string) ($detail->descripcion ?? $detail->description ?? ''),
                'quantity' => (float) ($detail->cantidad ?? $detail->quantity ?? 1),
                'amount' => (float) ($detail->monto ?? $detail->amount ?? 0),
                'comment' => (string) ($detail->comentario ?? $detail->comment ?? ''),
                'image_url' => $imageUrl,
            ];
        })->values();
    @endphp

    <form class="space-y-4 w-full max-w-full" method="POST"
        action="{{ isset($purchaseMovement) ? route('purchase.update', $purchaseMovement) : route('purchase.store', !empty($viewId) ? ['view_id' => $viewId] : []) }}"
        enctype="multipart/form-data" x-data="purchaseFormCatalog({
                products: @js(($products ?? collect())->map(fn($p) => [
                    'id' => (int) $p->id,
                    'code' => (string) ($p->code ?? ''),
                    'name' => (string) ($p->description ?? ''),
                    'unit_id' => (int) ($p->unit_sale ?? 0),
                    'unit_name' => (string) ($p->unit_name ?? ''),
                    'cost' => (float) ($p->price ?? 0),
                    'stock' => (float) ($p->stock ?? 0),
                    'category_id' => $p->category_id ?? null,
                    'category' => (string) ($p->category ?? 'General'),
                    'image_url' => $p->image_url ?? null,
                ])->values()),
                categories: @js(($categories ?? collect())->map(fn($c) => ['id' => $c->id, 'name' => $c->description ?? ''])->values()->all()),
                units: @js($units),
                initialProviderId: @js((int) old('person_id', $purchase?->person_id ?? 0)),
                initialItems: @js(old('items', $existingItems->all())),
                taxRate: @js((float) old('tax_rate_percent', $defaultTaxRate ?? 18)),
                includesTax: @js((string) old('includes_tax', $purchaseMovement?->incluye_igv ?? 'S')),
                initialCurrency: @js((string) old('currency', $purchaseMovement?->moneda ?? 'PEN')),
                initialBranchId: @js((int) old('branch_id', $purchaseMovement?->branch_id ?? $branchId ?? 0)),
                initialPaymentType: @js((string) old('payment_type', $purchaseMovement?->tipo_pago ?? 'CONTADO')),
                defaultExchangeRate: @js((float) old('exchange_rate', $purchaseMovement?->tipocambio ?? 3.5)),
                initialAffectsKardex: @js((string) old('affects_kardex', $purchaseMovement?->afecta_kardex ?? 'S')),
                paymentMethods: @js(($paymentMethods ?? collect())->map(fn($pm) => ['id' => $pm->id, 'description' => $pm->description ?? ''])->values()->all()),
                cards: @js(($cards ?? collect())->map(fn($c) => ['id' => $c->id, 'description' => $c->description ?? '', 'type' => $c->type ?? ''])->values()->all()),
                paymentGateways: @js(($paymentGateways ?? collect())->map(fn($pg) => ['id' => $pg->id, 'description' => $pg->description ?? ''])->values()->all()),
                digitalWallets: @js(($digitalWallets ?? collect())->map(fn($dw) => ['id' => $dw->id, 'description' => $dw->description ?? ''])->values()->all()),
                banks: @js(($banks ?? collect())->map(fn($b) => ['id' => $b->id, 'description' => $b->description ?? ''])->values()->all()),
                initialProviderOptions: @js(($people ?? collect())->map(fn($person) => ['id' => $person->id, 'description' => trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? '')) . ' - ' . ($person->document_number ?? '')])->values()->all()),
                initialCreditDays: @js((int) old('credit_days', 0)),
                initialDueDate: @js((string) old('due_date', '')),
            })" x-init="initForm()" @submit.prevent="
                if (!canSubmit) {
                    alert(submitErrorMessage);
                    return;
                }
                let firstInvalid = null;
                const elements = $el.querySelectorAll('input[name]:not([type=hidden]), select[name], textarea[name]');
                for (let el of elements) {
                    if (!el.checkValidity()) {
                        firstInvalid = el;
                        break;
                    }
                }
                if (firstInvalid) {
                    firstInvalid.reportValidity();
                } else {
                    isSubmitting = true;
                    $el.submit();
                }
            ">
        @csrf
        @if(isset($purchaseMovement))
            @method('PUT')
        @endif

        <div class="rounded-xl bg-white border border-gray-200 shadow-sm p-6">
            <div class="flex flex-col xl:flex-row gap-6 w-full">
                {{-- Panel izquierdo: Productos y categorías (2/3 del ancho) --}}
                <div class="flex-1 min-w-0 space-y-4">
                    {{-- Fecha de compra --}}
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <label class="mb-2 block text-xs font-semibold uppercase text-gray-500 tracking-wider">Fecha de
                            compra</label>
                        <div class="relative">
                            <input type="text" name="moved_at" x-ref="movedAtInput"
                                value="{{ old('moved_at', optional($purchase)->moved_at?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i')) }}"
                                placeholder="dd/mm/yyyy hh:mm"
                                class="h-10 w-full rounded-lg border border-gray-300 px-3 pr-10 text-sm focus:border-[#FF4622] focus:outline-none focus:ring-1 focus:ring-[#FF4622] bg-white"
                                required />
                            <i
                                class="ri-calendar-line absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg pointer-events-none"></i>
                        </div>
                    </div>

                    {{-- Documento y Número --}}
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-semibold uppercase text-gray-500 tracking-wider">DOCUMENTO</label>
                                <select name="document_type_id"
                                    class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-[#FF4622] focus:outline-none focus:ring-1 focus:ring-[#FF4622] bg-white"
                                    required>
                                    @foreach($documentTypes as $documentType)
                                        <option value="{{ $documentType->id }}" @selected((int) old('document_type_id', $purchase?->document_type_id ?? 0) === (int) $documentType->id)>
                                            {{ $documentType->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-semibold uppercase text-gray-500 tracking-wider">SERIE</label>
                                <input type="text" name="series"
                                    class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-[#FF4622] focus:outline-none focus:ring-1 focus:ring-[#FF4622]"
                                    value="{{ old('series', $purchaseMovement?->serie ?? '') }}" placeholder="Ej: F001"
                                    required>
                            </div>
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-semibold uppercase text-gray-500 tracking-wider">NUMERO</label>
                                <input type="text" name="number"
                                    class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-[#FF4622] focus:outline-none focus:ring-1 focus:ring-[#FF4622]"
                                    value="{{ old('number', $purchaseMovement?->numero ?? '') }}" placeholder="Ej: 00123"
                                    required>
                            </div>
                        </div>
                        <div class="mt-4">
                            <label
                                class="mb-1.5 block text-xs font-semibold uppercase text-gray-500 tracking-wider">Proveedor</label>
                            <div class="flex gap-2">
                                 <x-form.select.combobox label="" name="person_id" x-model="selectedProviderId"
                                :options="$people->map(fn($person) => [ 
            'id' => $person->id,
            'description' => trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? '')) . ' - ' . ($person->document_number ?? '')
        ])->values()->all()" :required="true"   
                                placeholder="Buscar proveedor..." icon="ri-user-shared-line"
                                iconClickEvent="open-modal-proveedor" />
                            <button type="button" @click="openModalCreateProveedor()"
                                class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-[#FF4622] text-white hover:bg-[#C43B25]">
                                <i class="ri-add-line"></i> Crear proveedor</button>
                            </div>
                        </div>
                    </div>

                    {{-- Modal Crear proveedor (mejorado) --}}
                    <div x-show="showModalProveedor" x-cloak
                        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm"
                        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                        @keydown.escape.window="closeModalProveedor()">
                        <div class="w-full max-w-2xl rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900"
                            @click.stop x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">

                            {{-- Header --}}
                            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700 bg-gradient-to-r from-[#FF4622]/5 to-transparent rounded-t-2xl">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#FF4622]/10">
                                        <i class="ri-user-add-line text-[#FF4622] text-lg"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-base font-bold text-gray-800 dark:text-white">Nuevo Proveedor</h3>
                                        <p class="text-xs text-gray-500">Busca por DNI o RUC para autocompletar los datos</p>
                                    </div>
                                </div>
                                <button type="button" @click="closeModalProveedor()"
                                    class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 transition-colors">
                                    <i class="ri-close-line text-xl"></i>
                                </button>
                            </div>

                            {{-- Body --}}
                            <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">

                                {{-- Alerta de error --}}
                                <p x-show="providerError" x-text="providerError" x-cloak
                                    class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-600 flex items-center gap-2">
                                </p>

                                @php $iC = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 placeholder:text-gray-400 focus:border-[#FF4622] focus:ring-2 focus:ring-[#FF4622]/20 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-white'; @endphp

                                {{-- Tipo + Documento + Botón Buscar (una sola fila) --}}
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-[#2752FF] uppercase tracking-wider">
                                        <i class="ri-search-eye-line mr-1"></i>Tipo y Documento
                                        <span class="text-[10px] font-normal text-gray-400 normal-case">(Enter o clic en Buscar)</span>
                                    </label>
                                    <div class="flex gap-2">
                                        <select x-model="providerForm.person_type" @change="onSupplierTypeChange()"
                                            class="h-11 w-32 shrink-0 rounded-xl border border-gray-300 bg-white px-3 text-sm text-gray-800 focus:border-[#FF4622] focus:ring-2 focus:ring-[#FF4622]/20 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                            <option value="DNI">DNI</option>
                                            <option value="RUC">RUC</option>
                                            <option value="CARNET DE EXTRANGERIA">Carné ext.</option>
                                            <option value="PASAPORTE">Pasaporte</option>
                                        </select>
                                        <input type="text" x-model.trim="providerForm.document_number"
                                            @keydown.enter.prevent="searchSupplierApi()"
                                            :maxlength="providerForm.person_type === 'RUC' ? 11 : 8"
                                            placeholder="Ej: 20123456789"
                                            class="{{ $iC }} flex-1" />
                                        <button type="button" @click="searchSupplierApi()" :disabled="supplierSearchLoading"
                                            class="inline-flex h-11 shrink-0 items-center gap-2 rounded-xl bg-[#2752FF] px-4 text-sm font-semibold text-white hover:bg-[#1f45dc] disabled:opacity-60 transition-colors">
                                            <i :class="supplierSearchLoading ? 'ri-loader-4-line animate-spin' : 'ri-search-line'"></i>
                                            <span class="hidden sm:inline" x-text="supplierSearchLoading ? 'Buscando...' : 'Buscar'"></span>
                                        </button>
                                    </div>
                                    <p x-show="supplierApiError" x-text="supplierApiError" class="mt-1 text-xs text-red-500"></p>
                                </div>

                                {{-- Nombre / Razón Social --}}
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div :class="providerForm.person_type === 'RUC' ? 'sm:col-span-2' : ''">
                                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500"
                                            x-text="providerForm.person_type === 'RUC' ? 'Razón social *' : 'Nombres *'"></label>
                                        <input type="text" x-model="providerForm.first_name"
                                            :placeholder="providerForm.person_type === 'RUC' ? 'Ingrese la razón social' : 'Ingrese los nombres'"
                                            class="{{ $iC }}" />
                                    </div>
                                    <div x-show="providerForm.person_type !== 'RUC'" x-cloak>
                                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">Apellidos</label>
                                        <input type="text" x-model="providerForm.last_name"
                                            placeholder="Ingrese los apellidos" class="{{ $iC }}" />
                                    </div>
                                </div>

                                {{-- Contacto --}}
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div x-show="providerForm.person_type !== 'RUC'" x-cloak>
                                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">F. Nacimiento</label>
                                        <input type="date" x-model="providerForm.fecha_nacimiento" class="{{ $iC }}" />
                                    </div>
                                    <div x-show="providerForm.person_type !== 'RUC'" x-cloak>
                                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">Género</label>
                                        <select x-model="providerForm.genero" class="{{ $iC }}">
                                            <option value="">Seleccione</option>
                                            <option value="MASCULINO">Masculino</option>
                                            <option value="FEMENINO">Femenino</option>
                                            <option value="OTRO">Otro</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">Teléfono</label>
                                        <input type="text" x-model="providerForm.phone" placeholder="999888777" class="{{ $iC }}" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">Email</label>
                                        <input type="email" x-model="providerForm.email" placeholder="proveedor@correo.com" class="{{ $iC }}" />
                                    </div>
                                </div>

                                {{-- Dirección --}}
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">Dirección</label>
                                    <input type="text" x-model="providerForm.address" placeholder="Av. Principal 123..." class="{{ $iC }}" />
                                </div>

                                {{-- Ubicación --}}
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">Departamento</label>
                                        <select x-model="supplierDeptId" @change="supplierProvId=''; supplierDistId=''" class="{{ $iC }}">
                                            <option value="">Seleccione</option>
                                            @foreach($departments ?? [] as $dept)
                                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">Provincia</label>
                                        <select x-model="supplierProvId" @change="supplierDistId=''" class="{{ $iC }}">
                                            <option value="">Seleccione</option>
                                            <template x-for="p in supplierProvinces" :key="p.id">
                                                <option :value="String(p.id)" x-text="p.name"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">Distrito</label>
                                        <select x-model="supplierDistId" @change="providerForm.location_id = $event.target.value" class="{{ $iC }}">
                                            <option value="">Seleccione</option>
                                            <template x-for="d in supplierDistricts" :key="d.id">
                                                <option :value="String(d.id)" x-text="d.name"></option>
                                            </template>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {{-- Footer --}}
                            <div class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4 dark:border-gray-700 bg-gray-50/60 rounded-b-2xl">
                                <button type="button" @click="closeModalProveedor()"
                                    class="px-5 py-2.5 rounded-xl text-sm font-medium text-gray-600 bg-white border border-gray-300 hover:bg-gray-50 transition-colors">
                                    Cancelar
                                </button>
                                <button type="button" @click="submitProviderModal()" :disabled="providerLoading"
                                    class="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-[#FF4622] hover:bg-[#C43B25] disabled:opacity-50 transition-colors inline-flex items-center gap-2">
                                    <i :class="providerLoading ? 'ri-loader-4-line animate-spin' : 'ri-save-line'"></i>
                                    <span x-text="providerLoading ? 'Guardando...' : 'Guardar proveedor'"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Catálogo Productos --}}
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-gray-700">CATÁLOGO Productos</h3>
                        <div class="relative mb-4">
                            <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg"></i>
                            <input type="text" x-model="catalogSearch" placeholder="Buscar por nombre o categoría"
                                class="h-10 w-full rounded-lg border border-gray-300 pl-10 pr-3 text-sm focus:border-[#FF4622] focus:outline-none focus:ring-1 focus:ring-[#FF4622]" />
                        </div>
                        <div class="flex flex-wrap gap-2 mb-4">
                            <button type="button" @click="selectedCategory = null"
                                class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                                :class="selectedCategory === null ? 'bg-[#FF4622] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                                General
                            </button>
                            <template x-for="cat in categories" :key="cat.id">
                                <button type="button" @click="selectedCategory = cat.id"
                                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                                    :class="selectedCategory === cat.id ? 'bg-[#FF4622] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                    x-text="cat.name">
                                </button>
                            </template>
                        </div>
                        <div
                            class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 max-h-[400px] overflow-y-auto">
                            <template x-for="product in filteredCatalogProducts" :key="product.id">
                                <button type="button" @click="addProductToCart(product)"
                                    class="flex flex-col rounded-xl border border-gray-200 bg-white p-3 text-left hover:border-[#FF4622] hover:shadow-md transition-all group">
                                    <div class="relative aspect-square mb-2 rounded-lg bg-gray-100 overflow-hidden">
                                        <img :src="product.image_url || 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect fill=%22%23e5e7eb%22 width=%22100%22 height=%22100%22/%3E%3C/svg%3E'"
                                            :alt="product.name"
                                            class="w-full h-full object-cover group-hover:scale-105 transition-transform"
                                            @@error="$el.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect fill=%22%23e5e7eb%22 width=%22100%22 height=%22100%22/%3E%3C/svg%3E'" />
                                        <span
                                            class="absolute top-1 right-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-[#FF4622]/90 text-white"
                                            x-text="'Stock: ' + (product.stock ?? 0)"></span>
                                    </div>
                                    <p class="text-xs font-medium text-gray-800 line-clamp-2 mb-1" x-text="product.name">
                                    </p>
                                    <p class="text-sm font-bold text-[#FF4622]" x-text="money(product.cost)"></p>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Panel derecho: Resumen y Pago (altura igual al panel izquierdo) --}}
                <div class="w-full center xl:w-2/5 min-h-[500px] shrink-0 flex flex-col gap-4 self-stretch">
                    <div
                        class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden flex flex-col flex-1 min-h-0">
                        {{-- Tabs --}}
                        <div class="flex border-b border-gray-200">
                            <button type="button" @click="activeTab = 'resumen'"
                                class="flex-1 px-4 py-3 text-sm font-semibold transition-colors"
                                :class="activeTab === 'resumen' ? 'bg-[#FF4622] text-white' : 'text-gray-600 hover:bg-gray-50'">
                                Resumen
                            </button>
                            <button type="button" @click="activeTab = 'pago'"
                                class="flex-1 px-4 py-3 text-sm font-semibold transition-colors"
                                :class="activeTab === 'pago' ? 'bg-[#FF4622] text-white' : 'text-gray-600 hover:bg-gray-50'">
                                Pago
                            </button>
                        </div>

                        <div class="p-4 flex-1 overflow-y-auto min-h-0">
                            {{-- Tab Resumen: Config, carrito, totales, notas --}}
                            <div x-show="activeTab === 'resumen'" x-cloak class="space-y-4">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label
                                            class="mb-1 block text-[11px] font-bold uppercase text-gray-500 tracking-wider">TIPO
                                            DETALLE</label>
                                        <select name="tipo_detalle"
                                            class="h-9 w-full rounded-lg border border-gray-300 px-2 text-sm focus:border-[#FF4622] focus:ring-1 focus:ring-[#FF4622] bg-white">
                                            <option value="DETALLADO" @selected(old('tipo_detalle', $purchaseMovement?->tipo_detalle ?? 'DETALLADO') === 'DETALLADO')>DETALLADO
                                            </option>
                                            <option value="GLOSA" @selected(old('tipo_detalle', $purchaseMovement?->tipo_detalle ?? 'DETALLADO') === 'GLOSA')>GLOSA</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1 block text-[11px] font-bold uppercase text-gray-500 tracking-wider">AFECTA
                                            KARDEX</label>
                                        <select name="affects_kardex" x-model="affectsKardex"
                                            class="h-9 w-full rounded-lg border border-gray-300 px-2 text-sm focus:border-[#FF4622] focus:ring-1 focus:ring-[#FF4622] bg-white">
                                            <option value="S">Si</option>
                                            <option value="N">No</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1 block text-[11px] font-bold uppercase text-gray-500 tracking-wider">INCLUYE
                                            IGV</label>
                                        <select name="includes_tax" x-model="includesTax"
                                            class="h-9 w-full rounded-lg border border-gray-300 px-2 text-sm focus:border-[#FF4622] focus:ring-1 focus:ring-[#FF4622] bg-white">
                                            <option value="S">Si</option>
                                            <option value="N">No</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1 block text-[11px] font-bold uppercase text-gray-500 tracking-wider">IGV
                                            %</label>
                                        <input type="number" name="tax_rate_percent" x-model.number="taxRate" step="0.01"
                                            min="0" max="100"
                                            class="h-9 w-full rounded-lg border border-gray-300 px-2 text-sm focus:border-[#FF4622] focus:ring-1 focus:ring-[#FF4622]"
                                            required>
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1 block text-[11px] font-bold uppercase text-gray-500 tracking-wider">MONEDA</label>
                                        <select name="currency" x-model="currency"
                                            class="h-9 w-full rounded-lg border border-gray-300 px-2 text-sm focus:border-[#FF4622] focus:ring-1 focus:ring-[#FF4622] bg-white"
                                            required>
                                            <option value="PEN">PEN</option>
                                            <option value="USD">USD</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1 block text-[11px] font-bold uppercase text-gray-500 tracking-wider">TIPO
                                            CAMBIO</label>
                                        <input type="number" name="exchange_rate" x-model.number="exchangeRate" step="0.001"
                                            min="0.001"
                                            class="h-9 w-full rounded-lg border border-gray-300 px-2 text-sm focus:border-[#FF4622] focus:ring-1 focus:ring-[#FF4622]"
                                            required>
                                    </div>
                                </div>
                                <input type="hidden" name="branch_id" :value="selectedBranchId">

                                {{-- Artículos en carrito --}}
                                <div class="space-y-3">
                                    <template x-for="(item, idx) in items" :key="idx">
                                        <div
                                            class="flex gap-3 items-center rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                                            <div class="w-14 h-14 shrink-0 rounded-lg overflow-hidden bg-gray-100">
                                                <img :src="item.image_url || 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%23d1d5db%22%3E%3Cpath d=%22M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z%22/%3E%3C/svg%3E'"
                                                    class="w-full h-full object-cover" :alt="item.description">
                                            </div>
                                            <div class="flex-1 min-w-0 grid grid-cols-1 gap-1">
                                                <p class="text-[11px] sm:text-xs font-bold text-gray-800 truncate uppercase mb-1"
                                                    x-text="(item.description || '').toUpperCase()"></p>

                                                <div class="flex items-center gap-2 mt-2">
                                                    <div class="w-2/3 relative">
                                                        <label
                                                            class="text-[9px] text-gray-500 font-semibold uppercase block mb-0.5">Precio
                                                            Unitario</label>
                                                        <div class="relative">
                                                            <span
                                                                class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-xs font-medium"
                                                                x-text="currency === 'USD' ? '$' : 'S/'"></span>
                                                            <input type="number" x-model.number="item.amount" min="0"
                                                                step="0.01"
                                                                class="w-full pl-6 pr-2 py-1.5 text-xs font-bold text-gray-700 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-[#FF4622] focus:ring-1 focus:ring-[#FF4622] outline-none transition-colors"
                                                                @input="item.total_tmp = ((item.quantity || 0) * (item.amount || 0)).toFixed(2)">
                                                        </div>
                                                    </div>
                                                    <div class="w-1/3 flex justify-end items-end pt-3">
                                                        <p class="text-[10px] text-gray-500 text-right"><span
                                                                class="font-bold text-[#FF4622] block text-xs"
                                                                x-text="money((item.quantity || 0) * (item.amount || 0))"></span>Subtotal
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                <div
                                                    class="inline-flex items-center rounded-lg border border-gray-200 bg-gray-50 overflow-hidden">
                                                    <button type="button"
                                                        @click="updateQuantity(idx, -1); item.total_tmp = ((item.quantity || 0) * (item.amount || 0)).toFixed(2);"
                                                        class="w-8 h-8 flex items-center justify-center text-gray-600 hover:bg-gray-200 text-base font-bold transition-colors">−</button>
                                                    <input type="number" :name="`items[${idx}][quantity]`"
                                                        x-model.number="item.quantity"
                                                        @input="item.total_tmp = ((item.quantity || 0) * (item.amount || 0)).toFixed(2);"
                                                        min="0.01" max="999999.99" step="0.01"
                                                        class="w-12 h-8 text-center text-sm font-bold text-gray-800 border-x border-gray-200 bg-white focus:ring-0 focus:outline-none">
                                                    <button type="button"
                                                        @click="updateQuantity(idx, 1); item.total_tmp = ((item.quantity || 0) * (item.amount || 0)).toFixed(2);"
                                                        class="w-8 h-8 flex items-center justify-center text-gray-600 hover:bg-gray-200 text-base font-bold transition-colors">+</button>
                                                </div>
                                                <button type="button" @click="removeItem(idx)"
                                                    class="w-9 h-9 rounded-lg border border-red-200 flex items-center justify-center text-gray-500 hover:text-red-500 hover:bg-red-50 transition-colors">
                                                    <i class="ri-delete-bin-line text-lg"></i>
                                                </button>
                                            </div>
                                            <input type="hidden" :name="`items[${idx}][product_id]`"
                                                :value="item.product_id">
                                            <input type="hidden" :name="`items[${idx}][unit_id]`" :value="item.unit_id">
                                            <input type="hidden" :name="`items[${idx}][description]`"
                                                :value="item.description">
                                            <input type="hidden" :name="`items[${idx}][amount]`" :value="item.amount">
                                            <input type="hidden" :name="`items[${idx}][comment]`"
                                                :value="item.comment || ''">
                                        </div>
                                    </template>
                                </div>

                                {{-- Totales --}}
                                <div class="mt-4 pt-4 border-t-2 border-gray-200">
                                    <div class="grid grid-cols-[1fr_auto] gap-x-6 items-center py-1">
                                        <span class="text-sm text-gray-500 font-medium text-left">Subtotal</span>
                                        <span class="text-sm font-semibold text-gray-800 text-right"
                                            x-text="money(summary.subtotal)"></span>
                                    </div>
                                    <div class="grid grid-cols-[1fr_auto] gap-x-6 items-center py-1">
                                        <span class="text-sm text-gray-500 font-medium text-left">IGV</span>
                                        <span class="text-sm font-semibold text-gray-800 text-right"
                                            x-text="money(summary.tax)"></span>
                                    </div>
                                    <div
                                        class="grid grid-cols-[1fr_auto] gap-x-6 items-center pt-3 mt-2 border-t border-gray-200">
                                        <span
                                            class="text-base font-bold uppercase tracking-wide text-gray-700 text-left">TOTAL
                                            A PAGAR</span>
                                        <span class="text-xl font-black text-[#FF4622] text-right"
                                            x-text="money(summary.total)"></span>
                                    </div>
                                    <p x-show="payment_type === 'CONTADO' && totalPaid > 0 && Math.abs(totalPaid - summary.total) > 0.02"
                                        x-cloak class="mt-2 text-xs text-red-600 font-medium">
                                        La suma de pagos (<span x-text="money(totalPaid)"></span>) no coincide con el total.
                                    </p>
                                </div>

                                {{-- Notas --}}
                                <div>
                                    <label
                                        class="mb-1 block text-[11px] font-bold uppercase text-gray-500 tracking-wider">NOTAS</label>
                                    <textarea name="comment" rows="2"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#FF4622] focus:ring-1 focus:ring-[#FF4622]"
                                        placeholder="Comentario opcional...">{{ old('comment', $purchase?->comment ?? '') }}</textarea>
                                </div>
                            </div>

                            {{-- Tab Pago: Tipo pago, afecta caja, métodos, adjuntar --}}
                            <div x-show="activeTab === 'pago'" x-cloak class="space-y-2">
                                <div>
                                    <label
                                        class="mb-1 block text-[11px] font-bold uppercase text-gray-500 tracking-wider">TIPO
                                        DE PAGO</label>
                                    <select name="payment_type" x-model="payment_type"
                                        class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-[#FF4622] focus:ring-1 focus:ring-[#FF4622] bg-white">
                                        <option value="CONTADO">Contado</option>
                                        <option value="CREDITO">Crédito</option>
                                    </select>
                                </div>
                                <div>
                                    <label
                                        class="mb-1 block text-[11px] font-bold uppercase text-gray-500 tracking-wider">AFECTA
                                        CAJA</label>
                                    <select name="affects_cash"
                                        class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-[#FF4622] focus:ring-1 focus:ring-[#FF4622] bg-white">
                                        <option value="N" @selected(old('affects_cash', $purchaseMovement?->afecta_caja ?? 'N') === 'N')>No</option>
                                        <option value="S" @selected(old('affects_cash', $purchaseMovement?->afecta_caja ?? 'N') === 'S')>Si</option>
                                    </select>
                                </div>
                                <div x-show="payment_type === 'CREDITO'" x-cloak
                                    class="rounded-xl border border-orange-200 bg-orange-50/40 p-3 space-y-3">
                                    <p class="text-sm text-orange-800">
                                        Esta compra se registrará como deuda y se enviará a cuentas por pagar.
                                    </p>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label
                                                class="mb-1 block text-[11px] font-bold uppercase text-orange-700 tracking-wider">DIAS
                                                DE CREDITO</label>
                                            <input type="number" min="0" step="1" name="credit_days"
                                                x-model.number="creditDays" @input="recalculateDueDate()"
                                                class="h-10 w-full rounded-lg border border-orange-300 bg-white px-3 text-sm focus:border-[#FF4622] focus:ring-1 focus:ring-[#FF4622]">
                                        </div>
                                        <div>
                                            <label
                                                class="mb-1 block text-[11px] font-bold uppercase text-orange-700 tracking-wider">FECHA
                                                VENCIMIENTO</label>
                                            <x-form.date-picker type="date" name="due_date"
                                                @date-change="onDueDateChange($event)"
                                                class="h-10 w-full rounded-lg border border-orange-300 bg-white px-3 text-sm focus:border-[#FF4622] focus:ring-1 focus:ring-[#FF4622]"
                                                dateFormat="Y-m-d"
                                                :defaultDate="old('due_date', $purchase?->due_date ? \Carbon\Carbon::parse($purchase->due_date)->format('Y-m-d') : '')"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div x-show="payment_type === 'CONTADO'" class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-sm font-bold text-gray-700">Métodos de pago</h4>
                                        <button type="button" @click="addPaymentRow()"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-[#FF4622] text-white text-xs font-bold hover:bg-[#C43B25] transition-colors">
                                            <i class="ri-add-line text-sm"></i> Agregar método
                                        </button>
                                    </div>
                                    <template x-for="(row, index) in paymentRows" :key="row.id">
                                        <div class="space-y-2 p-3 rounded-lg border border-gray-200 bg-white">
                                            <div class="flex gap-2 items-start flex-wrap">
                                                {{-- Combobox método de pago --}}
                                                <div class="flex-1 min-w-[180px] relative">
                                                    <label class="block text-[10px] text-gray-500 mb-0.5">Método</label>
                                                    <x-form.select.combobox label="" name="" x-model="row.methodId"
                                                        :options="($paymentMethods ?? collect())->map(fn($pm) => [
                                                            'id' => $pm->id,
                                                            'description' => $pm->description ?? '',
                                                        ])->values()->all()"
                                                        placeholder="Buscar método..." icon="ri-bank-card-line" />
                                                    <input type="hidden" :name="`payments[${index}][payment_method_id]`"
                                                        :value="row.methodId">
                                                    <input type="hidden" :name="`payments[${index}][payment_method]`"
                                                        :value="methodNameById(row.methodId)">
                                                </div>
                                                <div class="flex items-end gap-2">
                                                    <div>
                                                        <label class="block text-[10px] text-gray-500 mb-0.5">Monto</label>
                                                        <div class="flex items-center gap-1">
                                                            <span class="text-gray-500 text-sm"
                                                                x-text="currency === 'USD' ? '$' : 'S/'"></span>
                                                            <input type="number" x-model.number="row.amount"
                                                                :name="`payments[${index}][amount]`" step="0.01" min="0"
                                                                max="99999999.99" placeholder="0.00"
                                                                class="w-24 h-9 rounded-lg border border-gray-300 px-2 text-sm"
                                                                :required="payment_type === 'CONTADO'">
                                                        </div>
                                                    </div>
                                                    <button type="button"
                                                        @click="paymentRows.length > 1 && removePaymentRow(index)"
                                                        class="h-9 px-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded"
                                                        title="Eliminar">
                                                        <i class="ri-delete-bin-line text-lg"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            {{-- Tarjeta: tipo tarjeta + pasarela --}}
                                            <div x-show="needsCard(methodNameById(row.methodId))"
                                                class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2 border-t border-gray-200">
                                                <div>
                                                    <label class="block text-[10px] text-gray-500 mb-0.5">Tipo
                                                        Tarjeta</label>
                                                    <select :name="`payments[${index}][card_id]`"
                                                        class="w-full h-8 rounded border border-gray-300 px-2 text-xs bg-white">
                                                        <option value="">Seleccionar</option>
                                                        @php $cardsCr = collect($cards ?? [])->where('type', 'C');
                                                        $cardsDb = collect($cards ?? [])->where('type', 'D'); @endphp
                                                        @if($cardsCr->count())
                                                            <optgroup label="Crédito">
                                                                @foreach($cardsCr as $card)
                                                                    <option value="{{ $card->id ?? '' }}">
                                                                        {{ $card->description ?? '' }}</option>
                                                                @endforeach
                                                            </optgroup>
                                                        @endif
                                                        @if($cardsDb->count())
                                                            <optgroup label="Débito">
                                                                @foreach($cardsDb as $card)
                                                                    <option value="{{ $card->id ?? '' }}">
                                                                        {{ $card->description ?? '' }}</option>
                                                                @endforeach
                                                            </optgroup>
                                                        @endif
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] text-gray-500 mb-0.5">Pasarela
                                                        (POS)</label>
                                                    <select :name="`payments[${index}][payment_gateway_id]`"
                                                        class="w-full h-8 rounded border border-gray-300 px-2 text-xs bg-white">
                                                        <option value="">Ninguno</option>
                                                        @foreach($paymentGateways ?? [] as $pg)
                                                            <option value="{{ $pg->id ?? '' }}">{{ $pg->description ?? '' }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <input type="text" :name="`payments[${index}][number]`"
                                                        placeholder="N° Lote / Operación (Opcional)"
                                                        class="w-full h-8 rounded border border-gray-300 px-2 text-xs">
                                                </div>
                                            </div>
                                            {{-- Transferencia: banco --}}
                                            <div x-show="needsBank(methodNameById(row.methodId))"
                                                class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2 border-t border-gray-200">
                                                <div>
                                                    <label class="block text-[10px] text-gray-500 mb-0.5">Banco
                                                        Destino</label>
                                                    <select :name="`payments[${index}][bank_id]`"
                                                        class="w-full h-8 rounded border border-gray-300 px-2 text-xs bg-white">
                                                        <option value="">Seleccionar</option>
                                                        @foreach($banks ?? [] as $bank)
                                                            <option value="{{ $bank->id ?? '' }}">{{ $bank->description ?? '' }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] text-gray-500 mb-0.5">N°
                                                        Operación</label>
                                                    <input type="text" :name="`payments[${index}][number]`"
                                                        placeholder="Ej: 001234"
                                                        class="w-full h-8 rounded border border-gray-300 px-2 text-xs">
                                                </div>
                                            </div>
                                            {{-- Billetera digital: Yape, Plin, etc. --}}
                                            <div x-show="needsWallet(methodNameById(row.methodId))"
                                                class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2 border-t border-gray-200">
                                                <div>
                                                    <label class="block text-[10px] text-gray-500 mb-0.5">Aplicación</label>
                                                    <select :name="`payments[${index}][digital_wallet_id]`"
                                                        class="w-full h-8 rounded border border-gray-300 px-2 text-xs bg-white">
                                                        <option value="">Seleccionar</option>
                                                        @foreach($digitalWallets ?? [] as $dw)
                                                            <option value="{{ $dw->id ?? '' }}">{{ $dw->description ?? '' }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] text-gray-500 mb-0.5">N° Celular /
                                                        Ref.</label>
                                                    <input type="text" :name="`payments[${index}][number]`"
                                                        placeholder="Ej: 999..."
                                                        class="w-full h-8 rounded border border-gray-300 px-2 text-xs">
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                <div
                                    class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:bg-gray-50 cursor-pointer relative">
                                    <input type="file" name="payment_image" accept="image/*"
                                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                                    <i class="ri-upload-cloud-2-line text-2xl text-gray-400 block mb-1"></i>
                                    <span class="text-xs text-gray-600">Adjuntar comprobante</span>
                                </div>
                            </div>
                        </div>
                        {{-- Botones --}}
                        <div class="flex gap-3 justify-end shrink-0 mb-3 mr-3">
                            <a href="{{ route('purchase.index', !empty($viewId) ? ['view_id' => $viewId] : []) }}"
                                class="inline-flex items-center rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 transition-colors"
                                :class="{'opacity-50 pointer-events-none': isSubmitting}">
                                <i class="ri-close-line mr-2"></i>Cancelar
                            </a>
                            <button type="submit"
                                class="inline-flex items-center rounded-lg bg-[#FF4622] px-6 py-2.5 text-sm font-bold text-white shadow-md hover:bg-[#C43B25] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="!canSubmit || isSubmitting"
                                :class="isSubmitting ? 'opacity-75 cursor-wait pointer-events-none' : ''"
                                :title="submitErrorMessage">
                                <i class="ri-save-line mr-2" x-show="!isSubmitting"></i>
                                <i class="ri-loader-4-line mr-2 animate-spin" x-show="isSubmitting"
                                    style="display: none;"></i>
                                <span x-text="isSubmitting ? 'Guardando...' : 'Guardar compra'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
    </form>

    @once
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
            <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
            <script>
                function purchaseFormCatalog({
                    products, categories, units, initialProviderId, initialItems, taxRate, includesTax,
                    initialCurrency, initialBranchId, initialPaymentType, defaultExchangeRate, initialAffectsKardex,
                    paymentMethods, cards, paymentGateways, digitalWallets, banks, initialProviderOptions,
                    initialCreditDays, initialDueDate
                }) {
                    const toItem = (p, qty = 1) => ({
                        product_id: Number(p.id),
                        unit_id: Number(p.unit_id) || (units[0]?.id ?? 0),
                        description: String(p.name || ''),
                        quantity: qty,
                        amount: Number(p.cost) || 0,
                        comment: '',
                        image_url: p.image_url || null,
                        total_tmp: (Number(p.cost) || 0).toFixed(2),
                    });

                    return {
                        isSubmitting: false,
                        products,
                        categories: categories || [],
                        units: Array.isArray(units) ? units : Object.values(units || {}),
                        selectedProviderId: initialProviderId || '',
                        providerOptions: Array.isArray(initialProviderOptions) ? initialProviderOptions : [],
                        showModalProveedor: false,
                        providerForm: { first_name: '', last_name: '', person_type: 'RUC', document_number: '', phone: '', email: '', address: '', fecha_nacimiento: '', genero: '', location_id: '' },
                        providerLoading: false,
                        providerError: '',
                        // Estado para búsqueda API en modal proveedor
                        supplierSearchLoading: false,
                        supplierApiError: '',
                        supplierDeptId: '',
                        supplierProvId: '',
                        supplierDistId: '',
                        allProvinces: @js(($provinces ?? collect())->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'parent_location_id' => $p->parent_location_id])->values()->all()),
                        allDistricts: @js(($districts ?? collect())->map(fn($d) => ['id' => $d->id, 'name' => $d->name, 'parent_location_id' => $d->parent_location_id])->values()->all()),
                        get supplierProvinces() {
                            return this.allProvinces.filter(p => String(p.parent_location_id) === String(this.supplierDeptId));
                        },
                        get supplierDistricts() {
                            return this.allDistricts.filter(d => String(d.parent_location_id) === String(this.supplierProvId));
                        },
                        onSupplierTypeChange() {
                            this.supplierApiError = '';
                            if (this.providerForm.person_type === 'RUC') {
                                this.providerForm.last_name = '';
                                this.providerForm.genero = '';
                                this.providerForm.fecha_nacimiento = '';
                                if (this.providerForm.document_number.length > 11)
                                    this.providerForm.document_number = this.providerForm.document_number.slice(0, 11);
                            } else if (this.providerForm.document_number.length > 8) {
                                this.providerForm.document_number = this.providerForm.document_number.slice(0, 8);
                            }
                        },
                        async searchSupplierApi() {
                            const doc = this.providerForm.document_number.trim();
                            const isRuc = this.providerForm.person_type === 'RUC';
                            const expected = isRuc ? 11 : 8;
                            if (doc.length !== expected) {
                                this.supplierApiError = isRuc ? 'Ingrese un RUC de 11 dígitos.' : 'Ingrese un DNI de 8 dígitos.';
                                return;
                            }
                            this.supplierSearchLoading = true;
                            this.supplierApiError = '';
                            try {
                                const endpoint = isRuc ? `/api/ruc/${encodeURIComponent(doc)}` : `/api/dni/${encodeURIComponent(doc)}`;
                                const response = await fetch(endpoint, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                                const data = await response.json();
                                if (!response.ok) { this.supplierApiError = data.error || 'No se encontraron datos.'; return; }
                                if (isRuc) {
                                    this.providerForm.first_name = data.razon_social || '';
                                    this.providerForm.last_name = '';
                                    this.providerForm.address = data.direccion || this.providerForm.address;
                                    this.providerForm.fecha_nacimiento = '';
                                    this.setSupplierLocationByNames(data.departamento, data.provincia, data.distrito);
                                } else {
                                    this.providerForm.first_name = data.nombres || '';
                                    this.providerForm.last_name = [data.apellido_paterno, data.apellido_materno].filter(Boolean).join(' ');
                                    const raw = String(data.fecha_nacimiento || '').trim();
                                    if (/^\d{2}\/\d{2}\/\d{4}$/.test(raw)) {
                                        const [d, m, y] = raw.split('/');
                                        this.providerForm.fecha_nacimiento = `${y}-${m}-${d}`;
                                    }
                                    const g = String(data.genero || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toUpperCase();
                                    this.providerForm.genero = (g === 'M' || g === 'MASCULINO') ? 'MASCULINO' : (g === 'F' || g === 'FEMENINO') ? 'FEMENINO' : (g ? 'OTRO' : '');
                                }
                            } catch (e) {
                                this.supplierApiError = 'No se pudo consultar el documento.';
                            } finally {
                                this.supplierSearchLoading = false;
                            }
                        },
                        setSupplierLocationByNames(deptName, provName, distName) {
                            const norm = v => String(v || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toUpperCase();
                            const allDepts = @js(($departments ?? collect())->map(fn($d) => ['id' => $d->id, 'name' => $d->name])->values()->all());
                            const dept = allDepts.find(d => norm(d.name) === norm(deptName));
                            this.supplierDeptId = dept ? String(dept.id) : '';
                            const prov = this.supplierProvinces.find(p => norm(p.name) === norm(provName));
                            this.supplierProvId = prov ? String(prov.id) : '';
                            const dist = this.supplierDistricts.find(d => norm(d.name) === norm(distName));
                            this.supplierDistId = dist ? String(dist.id) : '';
                            this.providerForm.location_id = dist ? String(dist.id) : '';
                        },
                        openModalCreateProveedor() {
                            this.providerError = '';
                            this.supplierApiError = '';
                            this.supplierDeptId = '';
                            this.supplierProvId = '';
                            this.supplierDistId = '';
                            this.providerForm = { first_name: '', last_name: '', person_type: 'RUC', document_number: '', phone: '', email: '', address: '', fecha_nacimiento: '', genero: '', location_id: '' };
                            this.showModalProveedor = true;
                        },
                        closeModalProveedor() {
                            this.showModalProveedor = false;
                            this.providerError = '';
                            this.supplierApiError = '';
                        },
                        async submitProviderModal() {
                            this.providerError = '';
                            if (!this.providerForm.first_name?.trim() || !this.providerForm.document_number?.trim()) {
                                this.providerError = 'Complete los campos obligatorios (nombre y documento).';
                                return;
                            }
                            this.providerLoading = true;
                            try {
                                const res = await fetch('{{ route("purchase.store-proveedor") }}', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value, 'Accept': 'application/json' },
                                    body: JSON.stringify(this.providerForm),
                                });
                                const data = await res.json().catch(() => ({}));
                                if (!res.ok) {
                                    const validationMessage = (data.errors && typeof data.errors === 'object')
                                        ? Object.values(data.errors).flat().join(' ') : '';
                                    this.providerError = validationMessage || data.message || 'Error al crear el proveedor.';
                                    return;
                                }
                                const alreadyExists = this.providerOptions.some(opt => String(opt.id) === String(data.id));
                                if (!alreadyExists) {
                                    this.providerOptions.push({ id: data.id, description: data.description });
                                }
                                window.dispatchEvent(new CustomEvent('update-combobox-options', { detail: { name: 'person_id', options: this.providerOptions } }));
                                this.selectedProviderId = String(data.id);
                                this.closeModalProveedor();
                            } catch (e) {
                                this.providerError = 'Error de conexión. Intente de nuevo.';
                            } finally {
                                this.providerLoading = false;
                            }
                        },
                        items: (initialItems && initialItems.length)
                            ? initialItems.map(i => ({
                                ...i,
                                product_id: Number(i.product_id || 0),
                                unit_id: Number(i.unit_id || 0),
                                description: String(i.description || ''),
                                quantity: Number(i.quantity || 1),
                                amount: Number(i.amount || 0),
                                comment: String(i.comment || ''),
                                image_url: i.image_url || null,
                                total_tmp: ((Number(i.quantity) || 1) * (Number(i.amount) || 0)).toFixed(2)
                            }))
                            : [],
                        taxRate: Number(taxRate || 18),
                        includesTax: includesTax || 'S',
                        currency: initialCurrency || 'PEN',
                        selectedBranchId: Number(initialBranchId || 0),
                        payment_type: initialPaymentType || 'CONTADO',
                        exchangeRate: Number(defaultExchangeRate || 3.5),
                        affectsKardex: initialAffectsKardex || 'S',
                        creditDays: Number(initialCreditDays || 0),
                        dueDate: String(initialDueDate || ''),
                        catalogSearch: '',
                        selectedCategory: null,
                        activeTab: 'resumen',
                        paymentMethods: paymentMethods || [],
                        paymentRows: [{ id: Date.now(), methodId: '{{ ($paymentMethods ?? collect())->first()?->id ?? '' }}', methodName: '{{ ($paymentMethods ?? collect())->first()?->description ?? '' }}', amount: '' }],
                        _previousTotal: 0,

                        needsCard(desc) {
                            const d = (desc || '').toLowerCase();
                            return /tarjeta|crédito|débito|débito|credito|debito/.test(d);
                        },
                        needsBank(desc) {
                            const d = (desc || '').toLowerCase();
                            return /transferencia|banco/.test(d);
                        },
                        needsWallet(desc) {
                            const d = (desc || '').toLowerCase();
                            return /yape|plin|tunki|billetera|wallet|digital/.test(d);
                        },
                        methodNameById(id) {
                            const target = Number(id || 0);
                            if (!target) return '';
                            const found = (this.paymentMethods || []).find(p => Number(p.id) === target);
                            return found ? String(found.description || '') : '';
                        },

                        get filteredCatalogProducts() {
                            let list = this.products || [];
                            const term = String(this.catalogSearch || '').toLowerCase().trim();
                            if (term) {
                                list = list.filter(p =>
                                    String(p.name || '').toLowerCase().includes(term) ||
                                    String(p.code || '').toLowerCase().includes(term) ||
                                    String(p.category || '').toLowerCase().includes(term)
                                );
                            }
                            if (this.selectedCategory) {
                                list = list.filter(p => Number(p.category_id) === Number(this.selectedCategory));
                            }
                            return list.slice(0, 50);
                        },

                        addProductToCart(product) {
                            const existing = this.items.find(i => Number(i.product_id) === Number(product.id));
                            if (existing) {
                                existing.quantity = (existing.quantity || 1) + 1;
                            } else {
                                this.items.push(toItem(product, 1));
                            }
                        },

                        updateQuantity(idx, delta) {
                            const item = this.items[idx];
                            if (!item) return;
                            const next = Math.max(0.01, (item.quantity || 1) + delta);
                            item.quantity = next;
                            if (next < 0.01) this.items.splice(idx, 1);
                        },

                        removeItem(idx) {
                            this.items.splice(idx, 1);
                        },

                        addPaymentRow() {
                            const firstId = (this.paymentMethods && this.paymentMethods[0]) ? this.paymentMethods[0].id : '';
                            const firstDesc = (this.paymentMethods && this.paymentMethods[0]) ? this.paymentMethods[0].description : '';
                            this.paymentRows.push({ id: Date.now(), methodId: String(firstId), methodName: firstDesc, amount: this.paymentDifference > 0 ? this.paymentDifference.toFixed(2) : '' });
                        },

                        removePaymentRow(index) {
                            if (this.paymentRows.length > 1) this.paymentRows.splice(index, 1);
                        },

                        get summary() {
                            const lineTotal = this.items.reduce((sum, i) => sum + ((Number(i.quantity) || 0) * (Number(i.amount) || 0)), 0);
                            const r = (Number(this.taxRate) || 0) / 100;
                            if (this.includesTax === 'S') {
                                const subtotal = r > 0 ? (lineTotal / (1 + r)) : lineTotal;
                                const tax = lineTotal - subtotal;
                                return { subtotal, tax, total: lineTotal };
                            }
                            const subtotal = lineTotal;
                            const tax = subtotal * r;
                            return { subtotal, tax, total: subtotal + tax };
                        },

                        get totalPaid() {
                            return this.paymentRows.reduce((sum, row) => sum + (Number(row.amount) || 0), 0);
                        },

                        get paymentDifference() {
                            return Math.max(0, this.summary.total - this.totalPaid);
                        },

                        get canSubmit() {
                            if (!this.items || this.items.length === 0) return false;
                            if (this.payment_type === 'CONTADO') {
                                const total = this.summary.total;
                                const paid = this.totalPaid;
                                if (paid <= 0) return false;
                                if (Math.abs(paid - total) > 0.02) return false;
                            }
                            return true;
                        },

                        get submitErrorMessage() {
                            if (!this.items || this.items.length === 0) return 'Agregue al menos un producto al carrito.';
                            if (this.payment_type === 'CONTADO') {
                                if (this.totalPaid <= 0) return 'Registre un método de pago con monto.';
                                if (Math.abs(this.totalPaid - this.summary.total) > 0.02) {
                                    return 'La suma de pagos (' + this.money(this.totalPaid) + ') no coincide con el total (' + this.money(this.summary.total) + ').';
                                }
                            }
                            return '';
                        },

                        money(v) {
                            return `${this.currency === 'USD' ? '$' : 'S/'} ${Number(v || 0).toFixed(2)}`;
                        },
                        formatDateYmd(date) {
                            const y = date.getFullYear();
                            const m = String(date.getMonth() + 1).padStart(2, '0');
                            const d = String(date.getDate()).padStart(2, '0');
                            return `${y}-${m}-${d}`;
                        },
                        onDueDateChange(event) {
                            this.dueDate = String(event?.detail?.dateStr || '').trim();
                        },
                        setDueDatePickerValue(value) {
                            const input = document.querySelector('input[name="due_date"]');
                            if (!input) return;
                            const next = String(value || '').trim();
                            if (input._flatpickr) {
                                input._flatpickr.setDate(next || null, true, 'Y-m-d');
                            } else {
                                input.value = next;
                            }
                        },
                        recalculateDueDate() {
                            const movedAtRaw = String(this.$refs?.movedAtInput?.value || '').trim();
                            const base = movedAtRaw
                                ? new Date(movedAtRaw.replace(' ', 'T'))
                                : new Date();
                            if (Number.isNaN(base.getTime())) return;
                            const days = Math.max(0, parseInt(this.creditDays || 0, 10));
                            base.setHours(0, 0, 0, 0);
                            base.setDate(base.getDate() + days);
                            this.dueDate = this.formatDateYmd(base);
                            this.setDueDatePickerValue(this.dueDate);
                        },

                        initForm() {
                            const applyAutofill = (total) => {
                                if (this.paymentRows.length >= 1 && total > 0) {
                                    const first = this.paymentRows[0];
                                    const isEmpty = first.amount === '' || first.amount === undefined || first.amount === null;
                                    const matchesPrev = Number(first.amount) === Number(this._previousTotal);
                                    if (isEmpty || (this.paymentRows.length === 1 && matchesPrev)) {
                                        first.amount = Number(total).toFixed(2);
                                    }
                                }
                                this._previousTotal = total;
                            };
                            if (this.payment_type === 'CREDITO' && !this.dueDate) {
                                this.recalculateDueDate();
                            }
                            if (this.dueDate) {
                                this.$nextTick(() => this.setDueDatePickerValue(this.dueDate));
                            }
                            this.$watch('summary.total', (value) => applyAutofill(value));
                            this.$watch('payment_type', (value) => {
                                if (value === 'CREDITO' && !this.dueDate) {
                                    this.recalculateDueDate();
                                }
                            });
                            this.$nextTick(() => applyAutofill(this.summary.total));
                            if (typeof flatpickr !== 'undefined') {
                                const input = this.$refs?.movedAtInput;
                                if (input) {
                                    flatpickr(input, {
                                        enableTime: true,
                                        time_24hr: true,
                                        dateFormat: 'Y-m-d H:i',
                                        altInput: true,
                                        altFormat: 'd/m/Y H:i',
                                        defaultDate: input.value || new Date(),
                                        locale: 'es',
                                        onChange: () => {
                                            if (this.payment_type === 'CREDITO') {
                                                this.recalculateDueDate();
                                            }
                                        }
                                    });
                                    input.addEventListener('change', () => {
                                        if (this.payment_type === 'CREDITO') {
                                            this.recalculateDueDate();
                                        }
                                    });
                                }
                            }
                        }
                    };
                }
            </script>
        @endpush
    @endonce
@endsection
