{{-- Modal de selección de tipo de producto --}}
<div class="flex flex-col p-6 sm:p-8">
    {{-- Header --}}
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-lg">
                <i class="ri-box-3-line text-2xl"></i>
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Seleccionar Tipo de Producto</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Elige el tipo de producto que deseas registrar</p>
            </div>
        </div>
        <button type="button"
            @click="$dispatch('close-product-type-modal')"
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition-colors hover:bg-gray-200 hover:text-gray-700"
            aria-label="Cerrar">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    @php
        $productTypes = $productTypes ?? collect();
        $isSellable = function ($pt) {
            return $pt->behavior === \App\Models\ProductType::BEHAVIOR_SELLABLE
                || $pt->behavior === \App\Models\ProductType::BEHAVIOR_BOTH;
        };
        $isBoth = function ($pt) { return $pt->behavior === \App\Models\ProductType::BEHAVIOR_BOTH; };
    @endphp
    {{-- Cards: una por cada tipo de producto de la sucursal --}}
    <div class="mb-8 grid gap-6 sm:grid-cols-3">
        @foreach ($productTypes as $pt)
            @php
                $isSellableType = $isSellable($pt);
                $ringClass = $isSellableType
                    ? 'ring-blue-200/60 hover:border-blue-300 hover:ring-blue-300 dark:ring-blue-500/30 dark:hover:ring-blue-500/50'
                    : 'ring-orange-200/60 hover:border-orange-300 hover:ring-orange-300 dark:ring-orange-500/30 dark:hover:ring-orange-500/50';
                $iconBgClass = $isSellableType
                    ? 'from-blue-500 to-blue-600'
                    : 'from-orange-400 to-orange-600';
                $bulletClass = $isSellableType ? 'bg-blue-500' : 'bg-orange-500';
                $iconHtml = $pt->icon && preg_match('/^ri-[a-z0-9-]+$/', $pt->icon)
                    ? '<i class="' . e($pt->icon) . ' text-3xl"></i>'
                    : ($isSellableType
                        ? '<svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>'
                        : '<i class="ri-seedling-line text-3xl"></i>');
            @endphp
            <button type="button"
                @click="$dispatch('open-product-form-with-type', { product_type_id: {{ $pt->id }} }); $dispatch('close-product-type-modal')"
                class="group flex flex-col rounded-2xl border-2 border-transparent bg-white p-6 text-left shadow-sm ring-2 transition-all duration-200 hover:shadow-md dark:bg-gray-800/50 {{ $ringClass }}">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br {{ $iconBgClass }} text-white shadow-lg">
                    {!! $iconHtml !!}
                </div>
                <h3 class="mb-2 text-lg font-bold text-gray-900 dark:text-white">{{ $pt->name }}</h3>
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">{{ $pt->description ?? ($isBoth($pt) ? 'Aparece en compras y en ventas. Requiere precio y stock por sucursal.' : ($isSellableType ? 'Productos listos para la venta. Requieren precio y stock por sucursal.' : 'Repuestos, insumos o materiales de apoyo. No requieren precio ni stock de venta por sede.')) }}</p>
                <hr class="mb-4 border-gray-200 dark:border-gray-700" />
                <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    @if($isSellableType)
                        <li class="flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full {{ $bulletClass }}"></span>
                            Puede tener receta
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full {{ $bulletClass }}"></span>
                            Control completo de kardex
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full {{ $bulletClass }}"></span>
                            Detalle por sede (precio, stock)
                        </li>
                    @else
                        <li class="flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full {{ $bulletClass }}"></span>
                            No requiere receta
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full {{ $bulletClass }}"></span>
                            Configuración simplificada
                        </li>
                    @endif
                </ul>
            </button>
        @endforeach
    </div>
    @if($productTypes->isEmpty())
        <p class="mb-6 text-sm text-amber-600 dark:text-amber-400">No hay tipos de producto para esta sucursal. Crea tipos en <strong>Productos → Tipos de producto</strong> o asegúrate de tener una sucursal seleccionada.</p>
    @endif

    {{-- Footer --}}
    <div class="flex justify-end">
        <button type="button"
            @click="$dispatch('close-product-type-modal')"
            class="rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
            Cancelar
        </button>
    </div>
</div>
