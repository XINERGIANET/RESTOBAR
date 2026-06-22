{{--
    Selector de producto/item genérico para filas repetidas.
    Reutilizable para ingredientes, ítems, materiales, etc.

    Uso:
        <template x-for="(row, index) in items" :key="index">
            <x-recipe.product-selector-row
                name-prefix="items"
                product-list-var="product_list"
                is-selected-fn="isProductSelected"
                label="Producto"
                placeholder="Buscar..."
            />
        </template>

    Requisitos del padre (x-data):
    - Variable con la lista de productos (ej: product_list)
    - Función isProductSelected(productId) que retorna boolean
    - x-for con (row, index)

    Props:
    - namePrefix: prefijo del name del input (ingredients, items, materials...)
    - productListVar: nombre de la variable en el padre con la lista (default: product_list)
    - isSelectedFn: nombre de la función en el padre (default: isProductSelected)
    - productIdField: campo en la fila para el id (default: product_id)
    - unitCostField: campo en la fila para costo/precio (default: unit_cost)
    - productIdKey: clave del id en cada producto (default: id)
    - productDescriptionKey: clave de descripción (default: description)
    - productPriceKey: clave del precio (default: current_price)
    - label, placeholder, showPrice, pricePrefix, colSpan
--}}
@props([
    'namePrefix' => 'ingredients',
    'productListVar' => 'product_list',
    'isSelectedFn' => 'isProductSelected',
    'productIdField' => 'product_id',
    'unitCostField' => 'unit_cost',
    'productIdKey' => 'id',
    'productDescriptionKey' => 'description',
    'productPriceKey' => 'current_price',
    'label' => 'Insumo',
    'placeholder' => 'Buscar insumo...',
    'showPrice' => true,
    'pricePrefix' => 'S/',
    'colSpan' => 'col-span-1 md:col-span-5',
])
@php
    $productListVarJs = \Illuminate\Support\Js::from($productListVar);
    $isSelectedFnJs = \Illuminate\Support\Js::from($isSelectedFn);
    $productIdFieldJs = \Illuminate\Support\Js::from($productIdField);
    $unitCostFieldJs = \Illuminate\Support\Js::from($unitCostField);
    $productIdKeyJs = \Illuminate\Support\Js::from($productIdKey);
    $productDescriptionKeyJs = \Illuminate\Support\Js::from($productDescriptionKey);
    $productPriceKeyJs = \Illuminate\Support\Js::from($productPriceKey);
@endphp
<div class="{{ $colSpan }} relative" x-data="{
    open: false,
    search: '',
    productListVar: {{ $productListVarJs }},
    isSelectedFn: {{ $isSelectedFnJs }},
    productIdField: {{ $productIdFieldJs }},
    unitCostField: {{ $unitCostFieldJs }},
    productIdKey: {{ $productIdKeyJs }},
    productDescriptionKey: {{ $productDescriptionKeyJs }},
    productPriceKey: {{ $productPriceKeyJs }},

    get productList() {
        return this.$parent[this.productListVar] || [];
    },

    isSelected(pid) {
        const fn = this.$parent[this.isSelectedFn];
        return typeof fn === 'function' ? fn(pid) : false;
    },

    init() {
        const pid = row[this.productIdField];
        if (pid) {
            const found = this.productList.find(p => p[this.productIdKey] == pid);
            if (found) this.search = found[this.productDescriptionKey] || '';
        }
    },

    get filteredProducts() {
        const list = this.productList;
        if (this.search === '') {
            return list.filter(p => !this.isSelected(p[this.productIdKey]) || p[this.productIdKey] == row[this.productIdField]);
        }
        const searchLower = this.search.toLowerCase();
        return list.filter(product => {
            const desc = (product[this.productDescriptionKey] || '').toLowerCase();
            const matchesSearch = desc.includes(searchLower);
            const isAlreadySelected = this.isSelected(product[this.productIdKey]) && product[this.productIdKey] != row[this.productIdField];
            return matchesSearch && !isAlreadySelected;
        });
    },

    selectProduct(product) {
        row[this.productIdField] = product[this.productIdKey];
        this.search = product[this.productDescriptionKey] || '';
        const price = product[this.productPriceKey];
        row[this.unitCostField] = parseFloat(price || 0);
        this.open = false;
    },

    closeDropdown() {
        this.open = false;
    }
}"
    @mousedown.outside="closeDropdown()" @keydown.escape.window="closeDropdown()">
    <label class="md:hidden text-xs font-bold text-gray-500 mb-1 block">{{ $label }}</label>

    <div class="relative">
        <input type="text" x-model="search" @focus="open = true" @click="open = true"
            placeholder="{{ $placeholder }}"
            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-white"
            autocomplete="off">
        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-gray-400">
            <i class="ri-arrow-down-s-line transition-transform duration-200"
                :class="{ 'rotate-180': open }"></i>
        </div>
    </div>

    <ul x-show="open" x-transition.opacity.duration.100ms
        class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none dark:bg-gray-800 dark:ring-gray-700 border border-gray-100 dark:border-gray-600"
        style="display: none;">
        <li x-show="filteredProducts.length === 0"
            class="px-4 py-2 text-xs text-gray-500 cursor-default select-none">
            No se encontraron coincidencias.
        </li>

        <template x-for="product in filteredProducts" :key="product[productIdKey]">
            <li @mousedown.prevent="selectProduct(product)"
                class="cursor-pointer select-none px-4 py-2 text-sm text-gray-900 hover:bg-blue-50 hover:text-blue-700 dark:text-white dark:hover:bg-gray-700 transition-colors border-b border-gray-50 last:border-0 dark:border-gray-700">
                <div class="flex justify-between items-center gap-2">
                    <span x-text="product[productDescriptionKey]" class="font-medium truncate"></span>
                    @if($showPrice)
                    <span class="text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded dark:bg-gray-700 shrink-0">
                        {{ $pricePrefix }} <span x-text="parseFloat(product[productPriceKey] || 0).toFixed(2)"></span>
                    </span>
                    @endif
                </div>
            </li>
        </template>
    </ul>

    <input type="hidden" :name="`{{ $namePrefix }}[${index}][{{ $productIdField }}]`"
        x-model="row.{{ $productIdField }}">
</div>
