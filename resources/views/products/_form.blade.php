@php
    $productTypes = $productTypes ?? collect();
    $productBranch = $productBranch ?? null;
    $productTypeIdInit = old('product_type_id', $product->product_type_id ?? null);
    $productTypeIdInit = is_numeric($productTypeIdInit) ? (int) $productTypeIdInit : null;

    $unitSaleInit = old('unit_sale', $productBranch?->unit_sale ?? null);
    $unitSaleInit = is_numeric($unitSaleInit) ? (int) $unitSaleInit : null;

    $selectedBranchInit = old('branch_id', $productBranch?->branch_id ?? session('branch_id') ?? null);
    $selectedBranchInit = is_numeric($selectedBranchInit) ? (int) $selectedBranchInit : null;

    $printerIdsInit = old('printer_ids', $productBranch?->printers?->pluck('id')->all() ?? []);
    if (! is_array($printerIdsInit)) {
        $printerIdsInit = [$printerIdsInit];
    }
    $printerIdsInit = array_values(array_unique(array_filter(array_map(
        fn($id) => is_numeric($id) ? (int) $id : null,
        $printerIdsInit
    ))));

    $categoriesSafe = $categories ?? collect();
    $unitsSafe = $units ?? collect();
    $printersSafe = $printers ?? collect();
    $platosCategoriaIds = $categoriesSafe
        ->filter(fn($c) => str_contains(strtolower((string) ($c->description ?? '')), 'platos a la carta'))
        ->pluck('id')
        ->values();

    $igvByBranchId = $igvByBranchId ?? [];
    $productTypesById = $productTypes->keyBy('id')->map(fn($pt) => ['id' => $pt->id, 'name' => $pt->name, 'behavior' => $pt->behavior]);

    // Con errores de validación: fusionar old() en productBranchesByBranchId para conservar lo que el usuario envió
    $productBranchesByBranchId = collect($productBranchesByBranchId ?? []);
    if ($errors->any() && $selectedBranchInit !== null) {
        $current = $productBranchesByBranchId->get($selectedBranchInit, []);
        $productBranchesByBranchId = $productBranchesByBranchId->put($selectedBranchInit, array_merge($current, [
            'price' => old('price', $current['price'] ?? ''),
            'purchase_price' => (float) old('purchase_price', $current['purchase_price'] ?? 0),
            'stock' => old('stock', $current['stock'] ?? ''),
            'stock_minimum' => (float) old('stock_minimum', $current['stock_minimum'] ?? 0),
            'stock_maximum' => (float) old('stock_maximum', $current['stock_maximum'] ?? 0),
            'minimum_sell' => (float) old('minimum_sell', $current['minimum_sell'] ?? 0),
            'minimum_purchase' => (float) old('minimum_purchase', $current['minimum_purchase'] ?? 0),
            'tax_rate_id' => old('tax_rate_id', $current['tax_rate_id'] ?? null),
            'unit_sale' => old('unit_sale') !== null && old('unit_sale') !== '' ? (int) old('unit_sale') : ($current['unit_sale'] ?? null),
            'expiration_date' => old('expiration_date', $current['expiration_date'] ?? ''),
            'supplier_id' => old('supplier_id', $current['supplier_id'] ?? null),
            'favorite' => old('favorite', $current['favorite'] ?? 'N'),
            'duration_minutes' => (float) old('duration_minutes', $current['duration_minutes'] ?? 0),
            'printer_ids' => $printerIdsInit,
        ]));
    }
@endphp

<div x-data="{
    productTypeId: @js($productTypeIdInit),
    productTypesById: @js($productTypesById),
    get showComplements() {
        const pt = this.productTypeId != null ? this.productTypesById[this.productTypeId] : null;
        return pt ? (pt.behavior === 'SELLABLE' || pt.behavior === 'BOTH') : false;
    },
    get showBranchDetail() {
        const pt = this.productTypeId != null ? this.productTypesById[this.productTypeId] : null;
        return pt ? (pt.behavior === 'SELLABLE' || pt.behavior === 'BOTH') : false;
    },
    get showPurchaseFields() {
        const pt = this.productTypeId != null ? this.productTypesById[this.productTypeId] : null;
        return pt ? (pt.behavior === 'BOTH' || pt.behavior === 'SUPPLY') : false;
    },
    get disableStockEdit() {
        // Solo bloquear edición de stock en productos vendibles (se recomienda gestionarlo vía kardex/almacén).
        // Para insumos/suministros, se permite ajustar stock directamente aquí.
        return this.isEdit && this.showBranchDetail && this.branchStore && (this.branchStore[this.selectedBranchId] !== undefined);
    },
    get showSupplyFields() {
        const pt = this.productTypeId != null ? this.productTypesById[this.productTypeId] : null;
        return pt ? pt.behavior === 'SUPPLY' : false;
    },
    complementValue: '{{ old('complement', $product->complement ?? 'NO') }}',
    complementMode: '{{ old('complement_mode', $product->complement_mode ?? '') }}',
    classificationValue: '{{ old('classification', $product->classification ?? 'GOOD') }}',
    complements: {{ \Illuminate\Support\Js::from(old('complements', [])) }},
    categoryId: @js(old('category_id', $product->category_id ?? null)),
    baseUnitId: @js(old('base_unit_id', $product->base_unit_id ?? null)),
    supplierId: @js(old('supplier_id', $productBranch?->supplier_id ?? null)),
    unitSaleId: @js($unitSaleInit),
    stockMin: @js((float) old('stock_minimum', $productBranch?->stock_minimum ?? 0)),
    stockMax: @js((float) old('stock_maximum', $productBranch?->stock_maximum ?? 0)),
    selectedBranchId: @js($selectedBranchInit),
    isEdit: {{ !empty($product?->id) ? 'true' : 'false' }},
    branchStore: @js($productBranchesByBranchId ?? []),
    igvByBranchId: @js($igvByBranchId),
    printerIds: @js($printerIdsInit),
    branchDrafts: {},
    branchFields: {
        price: @js(old('price', $productBranch?->price ?? '')),
        purchase_price: @js(old('purchase_price', $productBranch?->purchase_price ?? 0)),
        stock: @js(old('stock', $productBranch?->stock ?? '')),
        stock_minimum: @js(old('stock_minimum', $productBranch?->stock_minimum ?? 0)),
        stock_maximum: @js(old('stock_maximum', $productBranch?->stock_maximum ?? 0)),
        minimum_sell: @js(old('minimum_sell', $productBranch?->minimum_sell ?? 0)),
        minimum_purchase: @js(old('minimum_purchase', $productBranch?->minimum_purchase ?? 0)),
        tax_rate_id: @js(old('tax_rate_id', $productBranch?->tax_rate_id ?? '')),
        unit_sale: {{ $unitSaleInit === null ? 'null' : $unitSaleInit }},
        expiration_date: @js(old('expiration_date', $productBranch?->expiration_date ?? '')),
        supplier_id: @js(old('supplier_id', $productBranch?->supplier_id ?? null)),
        favorite: @js(old('favorite', $productBranch?->favorite ?? 'N')),
        duration_minutes: @js(old('duration_minutes', $productBranch?->duration_minutes ?? 0)),
        printer_ids: @js($printerIdsInit),
    },
    // IDs resueltos por nombre en servidor (evita comparar strings en el frontend)
    platosCategoriaIds: @js($platosCategoriaIds),

    init() {
        const defaults = () => ({
            price: '',
            purchase_price: 0,
            stock: '',
            stock_minimum: 0,
            stock_maximum: 0,
            minimum_sell: 0,
            minimum_purchase: 0,
            tax_rate_id: (this.igvByBranchId && this.selectedBranchId != null && this.igvByBranchId[this.selectedBranchId]) ? this.igvByBranchId[this.selectedBranchId] : '',
            unit_sale: null,
            expiration_date: '',
            supplier_id: null,
            favorite: 'N',
            duration_minutes: 0,
            printer_ids: [],
        });

        const applyIgvDefault = (id) => {
            if (id == null) return;
            if (!this.igvByBranchId) return;
            if (!this.igvByBranchId[id]) return;
            if (this.branchFields && (this.branchFields.tax_rate_id === '' || this.branchFields.tax_rate_id == null)) {
                this.branchFields.tax_rate_id = this.igvByBranchId[id];
            }
        };

        if (this.isEdit) {
            this.branchDrafts = JSON.parse(JSON.stringify(this.branchStore || {}));
            if (this.selectedBranchId == null) {
                const firstKey = Object.keys(this.branchDrafts)[0];
                this.selectedBranchId = firstKey ? Number(firstKey) : null;
            }
            if (this.selectedBranchId != null && !this.branchDrafts[this.selectedBranchId]) {
                this.branchDrafts[this.selectedBranchId] = defaults();
            }
            if (this.selectedBranchId != null) {
                this.branchFields = this.branchDrafts[this.selectedBranchId];
                this.printerIds = Array.isArray(this.branchFields.printer_ids) ? [...this.branchFields.printer_ids] : [];
            }
        } else {
            // Creación: guardamos borrador por sede mientras cambian el select
            if (this.selectedBranchId != null) {
                this.branchDrafts[this.selectedBranchId] = this.branchFields;
                this.printerIds = Array.isArray(this.branchFields.printer_ids) ? [...this.branchFields.printer_ids] : [];
            }
        }

        // Aplicar IGV por defecto en la sede inicial (sin esperar a cambiar el select)
        applyIgvDefault(this.selectedBranchId);

        this.$watch('selectedBranchId', (id) => {
            if (id == null) return;
            if (!this.branchDrafts[id]) {
                this.branchDrafts[id] = defaults();
            }
            this.branchFields = this.branchDrafts[id];
            this.unitSaleId = this.branchFields.unit_sale;
            this.supplierId = this.branchFields.supplier_id;
            this.printerIds = Array.isArray(this.branchFields.printer_ids) ? [...this.branchFields.printer_ids] : [];

            // IGV por defecto de parámetros por sede (solo si aún no eligieron uno)
            applyIgvDefault(id);
        });

        this.$watch('unitSaleId', (val) => {
            if (this.branchFields) this.branchFields.unit_sale = val;
        });
        this.$watch('supplierId', (val) => {
            if (this.branchFields) this.branchFields.supplier_id = val;
        });
        this.$watch('printerIds', (val) => {
            if (this.branchFields) this.branchFields.printer_ids = Array.isArray(val) ? [...val] : [];
        });

        this.$watch('productTypeId', (id) => {
            const pt = id != null ? this.productTypesById[id] : null;
            if (pt && pt.behavior === 'SUPPLY') {
                this.complementValue = 'NO';
                this.complementMode = '';
                this.classificationValue = 'GOOD';
            }
        });
    },

    addComplement() { this.complements.push({ product: '', qty: 1 }); },
    removeComplement(i) { this.complements.splice(i, 1); },

    handleProductTypeChange(e) {
        const id = e.target.value ? Number(e.target.value) : null;
        this.productTypeId = id;
        const pt = id != null ? this.productTypesById[id] : null;
        if (pt && pt.behavior === 'SUPPLY') {
            this.complementValue = 'NO';
            this.complementMode = '';
            this.classificationValue = 'GOOD';
        }
    }
}"
    @open-product-form-with-type.window="if ($event.detail && $event.detail.product_type_id != null) productTypeId = $event.detail.product_type_id">

    <!-- INFORMACIÓN GENERAL -->
    <div class="mb-8 p-6 bg-gray-50 dark:bg-gray-800/30 rounded-lg border border-gray-200 dark:border-gray-700">
        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Información General</h3>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Codigo <span
                        class="text-red-500">*</span></label>
                <input type="text" name="code" value="{{ old('code', $product->code ?? '') }}" required
                    placeholder="Ingrese el codigo"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                @error('code')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nombre <span
                        class="text-red-500">*</span></label>
                <input type="text" name="description" value="{{ old('description', $product->description ?? '') }}"
                    required placeholder="Ingrese la descripcion"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                @error('description')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Abreviatura <span
                        class="text-red-500">*</span></label>
                <input type="text" name="abbreviation" value="{{ old('abbreviation', $product->abbreviation ?? '') }}"
                    required placeholder="Ingrese la abreviatura"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                @error('abbreviation')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tipo de producto <span
                        class="text-red-500">*</span></label>
                <input type="hidden" name="product_type_id" id="product-type-id-select" :value="productTypeId" required>
                <select id="product-type-id-select-display" disabled x-model.number="productTypeId"
                    class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-gray-100 px-4 py-2.5 text-sm text-gray-800 cursor-not-allowed dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:bg-gray-800/80">
                    <option value="">Seleccione tipo</option>
                    @foreach ($productTypes as $pt)
                        <option value="{{ $pt->id }}" @selected($productTypeIdInit === (int) $pt->id)>{{ $pt->name }}</option>
                    @endforeach
                </select>
                @error('product_type_id')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                {{-- Combobox visual para categoría, ligado a categoryId --}}
                <x-form.select.combobox label="Categoría" :required="true" :options="$categoriesSafe" name=""
                    x-model="categoryId" placeholder="Seleccione categoría" icon="ri-layout-grid-line" />
                {{-- Valor real que se envía al backend --}}
                <input type="hidden" name="category_id" x-model="categoryId">
                @error('category_id')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                {{-- Combobox visual para unidad base, ligado a baseUnitId --}}
                <x-form.select.combobox label="Unidad base" :required="true" :options="$unitsSafe" name=""
                    x-model="baseUnitId" placeholder="Seleccione la unidad" icon="ri-ruler-2-line" />
                <input type="hidden" name="base_unit_id" x-model="baseUnitId">
                @error('base_unit_id')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Estado <span
                        class="text-red-500">*</span></label>
                <select name="status" required
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="A" @selected(old('status', $product->status ?? 'A') === 'A')>Activo</option>
                    <option value="I" @selected(old('status', $product->status ?? 'A') === 'I')>Inactivo</option>
                </select>
                @error('status')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-3 block text-sm font-medium text-gray-700 dark:text-gray-400">Detalles del pedido</label>
                <textarea name="detail_options_lines" rows="6"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                    placeholder="Ingrese un detalle por linea, por ejemplo:&#10;Papa&#10;Arroz&#10;Mote">{{ old('detail_options_lines', collect($product->detail_options ?? [])->implode(PHP_EOL)) }}</textarea>
                @error('detail_options_lines')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Se mostraran al tomar el pedido como opciones marcables para ese producto.
                </p>
            </div>
        </div>
    </div>

    <!-- CONFIGURACIÓN DEL PRODUCTO -->
    <div class="mb-8 p-6 bg-gray-50 dark:bg-gray-800/30 rounded-lg border border-gray-200 dark:border-gray-700">
        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Configuración</h3>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div x-show="showComplements">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Receta <span
                        class="text-red-500">*</span></label>
                <input type="hidden" name="recipe" value="0" x-show="!showComplements" x-cloak>
                <select x-bind:name="showComplements ? 'recipe' : 'recipe_skip'" x-bind:required="showComplements"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="0" @selected(old('recipe', $product?->recipe ?? 0) == 0)>No</option>
                    <option value="1" @selected(old('recipe', $product?->recipe ?? 0) == 1)>Sí</option>
                </select>
                @error('recipe')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Kardex <span
                        class="text-red-500">*</span></label>
                <select name="kardex" required
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="N" @selected(old('kardex', $product->kardex ?? 'N') === 'N')>No</option>
                    <option value="S" @selected(old('kardex', $product->kardex ?? 'N') === 'S')>Sí</option>
                </select>
                @error('kardex')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">¿Es favorito? <span
                        class="text-red-500">*</span></label>
                <select name="favorite" required x-model="branchFields.favorite"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="N" @selected(old('favorite', $productBranch?->favorite ?? 'N') === 'N')>No</option>
                    <option value="S" @selected(old('favorite', $productBranch?->favorite ?? 'N') === 'S')>Sí</option>
                </select>
                @error('favorite')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <!--Configuracion de ticketera (solo para productos vendibles, no para ingredientes)-->
            <div x-show="!showSupplyFields" x-cloak>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Ticketeras</label>
                <div class="rounded-lg border border-gray-300 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                    <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Marca una o varias impresoras para este producto.</p>
                    <div class="max-h-44 space-y-2 overflow-y-auto pr-1">
                        @forelse ($printersSafe as $printer)
                            <label class="flex items-center gap-3 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200">
                                <input type="checkbox"
                                    name="printer_ids[]"
                                    value="{{ $printer->id }}"
                                    x-model="printerIds"
                                    :disabled="showSupplyFields"
                                    class="h-4 w-4 rounded border-gray-300 text-[#C43B25] focus:ring-brand-500">
                                <span>{{ $printer->name }}</span>
                                <span class="ml-auto text-xs text-gray-400">{{ $printer->ip ?? '-' }}</span>
                            </label>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">No hay ticketeras registradas para esta sucursal.</p>
                        @endforelse
                    </div>
                </div>
                @error('printer_ids')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                @error('printer_ids.*')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div style="display: none;">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Duración
                    (minutos)</label>
                <input type="number" name="duration_minutes" x-model.number="branchFields.duration_minutes"
                    value="{{ old('duration_minutes', $productBranch?->duration_minutes ?? '') }}"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    placeholder="Ej: 30" />
                @error('duration_minutes')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    <!-- INFORMACIÓN DE PRECIOS Y STOCK (DETALLE POR SEDE) - solo para tipo vendible (SELLABLE) y compras/ventas (BOTH) -->
    <template x-if="!showBranchDetail && !showSupplyFields">
        <div class="mb-8 p-6 bg-gray-50 dark:bg-gray-800/30 rounded-lg border border-gray-200 dark:border-gray-700"
            x-cloak>
            <p class="text-sm text-gray-600 dark:text-gray-400">Seleccione un tipo de producto para ver los campos.</p>
        </div>
    </template>
    <template x-if="!showBranchDetail && showSupplyFields">
        <div class="hidden" aria-hidden="true">
            <input type="hidden" name="price" value="0">
            <input type="hidden" name="minimum_sell" value="0">
            <input type="hidden" name="minimum_purchase" value="0">
            <input type="hidden" name="tax_rate_id" value="">
            <input type="hidden" name="unit_sale" value="">
            <input type="hidden" name="expiration_date" value="">
        </div>
    </template>
    <!-- Configuración de suministro: stock y proveedor (sin precio de venta) -->
    <div x-show="showSupplyFields" x-cloak x-transition
        class="mb-8 p-6 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">📦 Configuración de Suministro</h3>
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Los suministros tienen stock por sede para inventario,
            pero no precio de venta. Configura stock y proveedor.</p>
        @if (isset($branches) && $branches->isNotEmpty())
            <div class="mb-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Sede <span
                            class="text-red-500">*</span></label>
                    <select name="branch_id" x-model="selectedBranchId" required
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}">{{ $b->legal_name }}</option>
                        @endforeach
                    </select>
                    @error('branch_id')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        @endif
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Precio de compra <span
                        class="text-red-500">*</span></label>
                <input type="number" name="purchase_price" step="0.01" x-model.number="branchFields.purchase_price"
                    value="{{ old('purchase_price', $productBranch?->purchase_price ?? 0) }}" required min="0"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    placeholder="0.00" />
                @error('purchase_price')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Compra mínima</label>
                <input type="number" name="minimum_purchase" step="0.01" x-model.number="branchFields.minimum_purchase"
                    value="{{ old('minimum_purchase', $productBranch?->minimum_purchase ?? '') }}"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    placeholder="0.00" />
                @error('minimum_purchase')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Stock actual <span
                        class="text-red-500">*</span></label>
                <input type="number" name="stock" step="0.01" x-model.number="branchFields.stock"
                    value="{{ old('stock', $productBranch?->stock ?? '') }}" required min="0"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    placeholder="0.00" />
                @error('stock')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>


            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Stock mínimo <span
                        class="text-red-500">*</span></label>
                <input type="number" name="stock_minimum" step="0.01" x-model.number="branchFields.stock_minimum"
                    value="{{ old('stock_minimum', $productBranch?->stock_minimum ?? '') }}" required min="0"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    placeholder="0.00" />
                @error('stock_minimum')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Stock máximo <span
                        class="text-red-500">*</span></label>
                <input type="number" name="stock_maximum" step="0.01" x-model.number="branchFields.stock_maximum"
                    value="{{ old('stock_maximum', $productBranch?->stock_maximum ?? '') }}" required min="0"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    placeholder="0.00" />
                @error('stock_maximum')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <x-form.select.combobox label="Proveedor" :required="false" :options="$suppliers ?? []"
                    name="supplier_id" x-model="supplierId" placeholder="Seleccione proveedor" icon="ri-truck-line" />
            </div>
        </div>
    </div>
    <div class="mb-8 p-6 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800"
        x-show="showBranchDetail" x-cloak x-transition>
        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">💰 Información Detalle por Sede</h3>
        <p class="mb-4 text-xs text-gray-600 dark:text-gray-400">Precio, stock y datos por sucursal (Vendible y
            Compras/ventas)</p>
        @if (isset($branches) && $branches->isNotEmpty())
            <div class="mb-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Sede <span
                            class="text-red-500">*</span></label>
                    <select name="branch_id" x-model="selectedBranchId" required
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}">{{ $b->legal_name }}</option>
                        @endforeach
                    </select>
                    @error('branch_id')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        @endif
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Precio <span
                        class="text-red-500">*</span></label>
                <input type="number" :name="showBranchDetail ? 'price' : 'price_skip'" step="0.01"
                    x-model.number="branchFields.price" value="{{ old('price', $productBranch?->price ?? '') }}"
                    :required="showBranchDetail"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    placeholder="0.00" />
                @error('price')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <template x-if="!showPurchaseFields">
                <input type="hidden" name="purchase_price" value="0" />
            </template>
            <div x-show="showPurchaseFields" x-cloak>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Precio de compra <span
                        class="text-red-500">*</span></label>
                <input type="number" :name="showPurchaseFields ? 'purchase_price' : 'purchase_price_skip'" step="0.01"
                    x-model.number="branchFields.purchase_price"
                    value="{{ old('purchase_price', $productBranch?->purchase_price ?? 0) }}"
                    :required="showPurchaseFields" min="0"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    placeholder="0.00" />
                @error('purchase_price')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    Stock actual <span class="text-red-500">*</span>
                    <span class="ml-1 text-xs text-gray-400 dark:text-gray-500"
                        x-show="isEdit && branchStore && (branchStore[selectedBranchId] !== undefined)">
                        (Gestionar en movimientos de almacén)
                    </span>
                    <span class="ml-1 text-xs text-gray-400 dark:text-gray-500"
                        x-show="isEdit && (!branchStore || (branchStore[selectedBranchId] === undefined))">
                        (configurar por sede)
                    </span>
                </label>
                <input type="number" step="0.01" :name="showBranchDetail ? 'stock' : 'stock_skip'"
                    :required="showBranchDetail" x-model.number="branchFields.stock"
                    x-bind:disabled="disableStockEdit"
                    :min="Number(branchFields.stock_minimum || 0)"
                    :max="Number(branchFields.stock_maximum || 0) > 0 ? Number(branchFields.stock_maximum) : undefined"
                    class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:text-white/90 dark:placeholder:text-white/30"
                    :class="disableStockEdit
                        ? 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500'
                        : 'bg-transparent focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] text-gray-800 focus:ring-3 focus:outline-hidden dark:bg-gray-900'"
                    placeholder="0.00" />
                @error('stock')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Stock mínimo <span
                        class="text-red-500">*</span></label>
                <input type="number" :name="showBranchDetail ? 'stock_minimum' : 'stock_minimum_skip'" step="0.01"
                    value="{{ old('stock_minimum', $productBranch?->stock_minimum ?? '') }}"
                    x-model.number="branchFields.stock_minimum" :required="showBranchDetail" min="0"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    placeholder="0.00" />
                @error('stock_minimum')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Stock máximo <span
                        class="text-red-500">*</span></label>
                <input type="number" :name="showBranchDetail ? 'stock_maximum' : 'stock_maximum_skip'" step="0.01"
                    value="{{ old('stock_maximum', $productBranch?->stock_maximum ?? '') }}"
                    x-model.number="branchFields.stock_maximum" :required="showBranchDetail" min="0"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    placeholder="0.00" />
                @error('stock_maximum')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Venta mínima</label>
                <input type="number" :name="showBranchDetail ? 'minimum_sell' : 'minimum_sell_skip'" step="0.01"
                    x-model.number="branchFields.minimum_sell"
                    value="{{ old('minimum_sell', $productBranch?->minimum_sell ?? '') }}" :required="showBranchDetail"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    placeholder="0.00" />
                @error('minimum_sell')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <template x-if="!showPurchaseFields">
                <input type="hidden" name="minimum_purchase" value="0" />
            </template>
            <div x-show="showPurchaseFields" x-cloak>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Compra mínima</label>
                <input type="hidden" name="minimum_purchase"
                    x-bind:value="platosCategoriaIds.includes(Number(categoryId)) ? 0 : null"
                    x-show="platosCategoriaIds.includes(Number(categoryId))">
                <input type="number" step="0.01"
                    x-bind:name="showPurchaseFields && !platosCategoriaIds.includes(Number(categoryId)) ? 'minimum_purchase' : null"
                    x-model.number="branchFields.minimum_purchase"
                    value="{{ old('minimum_purchase', $productBranch?->minimum_purchase ?? '') }}"
                    x-bind:disabled="platosCategoriaIds.includes(Number(categoryId))"
                    x-effect="if (platosCategoriaIds.includes(Number(categoryId))) { $el.value = 0; branchFields.minimum_purchase = 0 }"
                    class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:text-white/90 dark:placeholder:text-white/30"
                    :class="platosCategoriaIds.includes(Number(categoryId))
                        ? 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500'
                        : 'bg-transparent focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] text-gray-800 focus:ring-3 focus:outline-hidden dark:bg-gray-900'"
                    placeholder="0.00" />
                @error('minimum_purchase')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-form.select.combobox label="Unidad de venta" :required="true" :options="$unitsSafe" name="unit_sale"
                    x-model="unitSaleId" placeholder="Seleccione la unidad" icon="ri-ruler-2-line" />
            </div>

            <div x-show="showBranchDetail" x-cloak>
                @php
                    $expirationDefault = old('expiration_date');
                    if ($expirationDefault === null || $expirationDefault === '') {
                        $expirationDefault = $productBranch?->expiration_date
                            ? \Carbon\Carbon::parse($productBranch?->expiration_date)->format('Y-m-d')
                            : '';
                    }
                @endphp
                <x-form.date-picker name="expiration_date" label="Fecha de expiración"
                    placeholder="Seleccione fecha de expiración" dateFormat="Y-m-d" :defaultDate="$expirationDefault" />
                @error('expiration_date')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    <!-- CONFIGURACIÓN AVANZADA (solo Producto final) -->
    <div x-show="showComplements" x-transition
        class="mb-8 p-6 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">⚙️ Configuración Avanzada</h3>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tasa
                    impositiva</label>
                <select name="tax_rate_id" x-model="branchFields.tax_rate_id"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="">Seleccione tasa impositiva</option>
                    @if (isset($taxRates))
                        @foreach ($taxRates as $rate)
                            <option value="{{ $rate->id }}" @selected(old('tax_rate_id', $productBranch?->tax_rate_id ?? '') == $rate->id)>
                                {{ $rate->description }}
                            </option>
                        @endforeach
                    @endif
                </select>
                @error('tax_rate_id')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div x-show="showPurchaseFields" x-cloak>
                <x-form.select.combobox label="Proveedor" :required="false" :options="$suppliers ?? []"
                    name="supplier_id" x-model="supplierId" placeholder="Seleccione proveedor" icon="ri-truck-line" />
            </div>
        </div>
    </div>

    <!-- COMPLEMENTOS -->
    <div x-show="showComplements"
        class="mb-8 p-6 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-800">
        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">🎁 Complementos</h3>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Complemento <span
                        class="text-red-500">*</span></label>
                <select name="complement" x-model="complementValue" x-bind:required="showComplements"
                    x-ref="complementSelect"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="NO" @selected(old('complement', $product->complement ?? 'NO') === 'NO')>No</option>
                    <option value="HAS" @selected(old('complement', $product->complement ?? 'NO') === 'HAS')>Tiene
                        complementos</option>
                    <option value="IS" @selected(old('complement', $product->complement ?? 'NO') === 'IS')>Es complemento
                    </option>
                </select>
                @error('complement')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Modo
                    complemento</label>
                <select name="complement_mode" x-model="complementMode" x-ref="modeSelect"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="" @selected(old('complement_mode', $product->complement_mode ?? '') === '')>Sin modo
                    </option>
                    <option value="ALL" @selected(old('complement_mode', $product->complement_mode ?? '') === 'ALL')>Todo
                        gratis</option>
                    <option value="QUANTITY" @selected(old('complement_mode', $product->complement_mode ?? '') === 'QUANTITY')>Cantidad gratis</option>
                </select>
                @error('complement_mode')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Clasificación <span
                        class="text-red-500">*</span></label>
                <select name="classification" x-model="classificationValue" x-bind:required="showComplements"
                    x-ref="classificationSelect"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="GOOD" x-bind:selected="classificationValue === 'GOOD'">Bien</option>
                    <option value="SERVICE" x-bind:selected="classificationValue === 'SERVICE'">Servicio</option>
                </select>
                @error('classification')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    <!-- MULTIMEDIA E INFORMACIÓN ADICIONAL -->
    <div class="mb-8 p-6 bg-orange-50 dark:bg-orange-900/20 rounded-lg border border-orange-200 dark:border-orange-800">
        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">📸 Multimedia e Información Adicional</h3>
        <div class="grid gap-5 lg:grid-cols-2" x-data="{
            imagePreview: '{{ isset($product) && $product->image ? asset('storage/' . $product->image) : '' }}',
            fileName: '{{ isset($product) && $product->image ? basename($product->image) : '' }}',
            defaultPlaceholder: 'https://placehold.co/100x100?text=Sin+Imagen',
        
            showPreview(event) {
                const file = event.target.files[0];
                if (!file) {
                    this.imagePreview = '{{ isset($product) && $product->image ? asset('storage/' . $product->image) : '' }}';
                    this.fileName = '{{ isset($product) && $product->image ? basename($product->image) : '' }}';
                    return;
                }
        
                if (file.size > 2048 * 1024) {
                    alert('El archivo es demasiado grande. Máximo 2MB.');
                    event.target.value = '';
                    return;
                }
        
                this.fileName = file.name;
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.imagePreview = e.target.result;
                };
                reader.readAsDataURL(file);
            },
        
            removeImage() {
                this.imagePreview = '';
                this.fileName = '';
                document.getElementById('image-input').value = '';
            }
        }">

            <!-- Imagen -->
            <div>
                <label class="mb-3 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    Imagen del producto (opcional)
                </label>

                <div
                    class="mb-3 flex items-center gap-4 p-4 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700">

                    <img :src="imagePreview || defaultPlaceholder" alt="Vista previa"
                        class="h-20 w-20 object-cover rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-gray-200 dark:bg-gray-700">

                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate"
                            x-text="fileName || 'Sin archivo seleccionado'">
                        </p>

                        <template x-if="imagePreview">
                            <button type="button" @click="removeImage()"
                                class="mt-2 inline-block text-xs text-red-600 hover:text-red-800 font-semibold">
                                ✕ Quitar archivo
                            </button>
                        </template>

                        <template x-if="!imagePreview">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Esperando imagen...</span>
                        </template>
                    </div>
                </div>

                <input type="file" name="image" id="image-input" accept="image/*" @change="showPreview($event)"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 file:mr-4 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-[#FF4622]/10 file:text-[#FF4622] hover:file:bg-[#FF4622]/20 dark:file:bg-[#FF4622]/20 dark:file:text-[#FF4622]" />

                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    📁 JPG, PNG, GIF, WEBP • Máximo 2MB
                </p>

                @error('image')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <!-- Características -->
            <div>
                <label class="mb-3 block text-sm font-medium text-gray-700 dark:text-gray-400">Características</label>
                <textarea name="features" rows="6"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                    placeholder="Describa las características principales del producto...">{{ old('features', $product->features ?? '') }}</textarea>
                @error('features')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    💡 Ingrese las características separadas por saltos de línea
                </p>
            </div>
        </div>
</div>
