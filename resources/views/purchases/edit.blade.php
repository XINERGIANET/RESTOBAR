@extends('layouts.app')

@section('content')
<div>
    <x-common.page-breadcrumb pageTitle="Editar Compra" />

    <x-common.component-card title="Editar Compra #{{ $purchaseMovement->id }}" desc="Actualiza los datos de la compra y sus detalles.">
        <form id="purchaseForm" method="POST" action="{{ route('purchase.update', $purchaseMovement) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <input type="hidden" id="json_persona" name="json_persona" value="{{ htmlspecialchars($purchaseMovement->json_persona ?? '{}') }}" />

            {{-- ROW 1: Datos Generales --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Proveedor *</label>
                    <select name="supplier_id" id="supplierSelect" required disabled
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-[#FF4622] focus:outline-hidden focus:ring-2 focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] opacity-50">
                        @foreach($suppliers ?? [] as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->legal_name ?? $supplier->name }}</option>
                        @endforeach
                    </select>
                    <small class="text-xs text-gray-500 mt-1 block">No se puede cambiar el proveedor en edición</small>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Serie *</label>
                    <input type="text" name="serie" id="serie" placeholder="F001" required
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-[#FF4622] focus:outline-hidden focus:ring-2 focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622]"
                        value="{{ old('serie', $purchaseMovement->serie) }}" />
                    @error('serie') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Año *</label>
                    <input type="text" name="anio" id="anio" placeholder="2026" required maxlength="4"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-[#FF4622] focus:outline-hidden focus:ring-2 focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622]"
                        value="{{ old('anio', $purchaseMovement->anio) }}" />
                    @error('anio') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Sucursal *</label>
                    <select name="branch_id" required
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-[#FF4622] focus:outline-hidden focus:ring-2 focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622]">
                        <option value="">-- Seleccionar Sucursal --</option>
                        @foreach($branches ?? [] as $branch)
                            <option value="{{ $branch->id }}" @selected(old('branch_id', $purchaseMovement->branch_id) == $branch->id)>
                                {{ $branch->legal_name ?? $branch->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('branch_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- ROW 2: Opciones de Compra --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Tipo Detalle *</label>
                    <select name="tipo_detalle" required
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-[#FF4622] focus:outline-hidden focus:ring-2 focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622]">
                        <option value="DETALLADO" @selected(old('tipo_detalle', $purchaseMovement->tipo_detalle) == 'DETALLADO')>DETALLADO</option>
                        <option value="GLOSA" @selected(old('tipo_detalle', $purchaseMovement->tipo_detalle) == 'GLOSA')>GLOSA</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Incluye IGV *</label>
                    <select name="incluye_igv" required
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-[#FF4622] focus:outline-hidden focus:ring-2 focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622]">
                        <option value="N" @selected(old('incluye_igv', $purchaseMovement->incluye_igv) == 'N')>No incluye IGV</option>
                        <option value="S" @selected(old('incluye_igv', $purchaseMovement->incluye_igv) == 'S')>Incluye IGV</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Tipo Pago *</label>
                    <select name="tipo_pago" required
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-[#FF4622] focus:outline-hidden focus:ring-2 focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622]">
                        <option value="CONTADO" @selected(old('tipo_pago', $purchaseMovement->tipo_pago) == 'CONTADO')>CONTADO</option>
                        <option value="CREDITO" @selected(old('tipo_pago', $purchaseMovement->tipo_pago) == 'CREDITO')>CRÉDITO</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Moneda *</label>
                    <select name="moneda" required
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-[#FF4622] focus:outline-hidden focus:ring-2 focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622]">
                        <option value="PEN" @selected(old('moneda', $purchaseMovement->moneda) == 'PEN')>PEN (Soles)</option>
                        <option value="USD" @selected(old('moneda', $purchaseMovement->moneda) == 'USD')>USD (Dólares)</option>
                    </select>
                </div>
            </div>

            {{-- ROW 3: Moneda y Tipo de Cambio --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Tipo de Cambio *</label>
                    <input type="number" name="tipocambio" step="0.01" required
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-[#FF4622] focus:outline-hidden focus:ring-2 focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622]"
                        value="{{ old('tipocambio', $purchaseMovement->tipocambio) }}" />
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Afecta Caja</label>
                    <select name="afecta_caja"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-[#FF4622] focus:outline-hidden focus:ring-2 focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622]">
                        <option value="N" @selected(old('afecta_caja', $purchaseMovement->afecta_caja) == 'N')>No</option>
                        <option value="S" @selected(old('afecta_caja', $purchaseMovement->afecta_caja) == 'S')>Sí</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Afecta Kardex</label>
                    <select name="afecta_kardex"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-[#FF4622] focus:outline-hidden focus:ring-2 focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622]">
                        <option value="S" @selected(old('afecta_kardex', $purchaseMovement->afecta_kardex) == 'S')>Sí</option>
                        <option value="N" @selected(old('afecta_kardex', $purchaseMovement->afecta_kardex) == 'N')>No</option>
                    </select>
                </div>
            </div>

            {{-- TABLA DE ITEMS --}}
            <div class="mt-8 border-t pt-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Detalles de Compra</h3>

                <div class="relative overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full min-w-[800px] text-sm">
                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Código</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Descripción</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Cantidad</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Precio Unit.</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Monto</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="itemsTable">
                            @forelse($purchaseMovement->details ?? [] as $detail)
                                <tr class="border-t border-gray-200 dark:border-gray-700 item-row" id="item_{{ $loop->index }}">
                                    <td class="px-4 py-3">
                                        <input type="text" name="items[{{ $loop->index }}][codigo]" placeholder="Código producto"
                                            class="w-full h-9 rounded border border-gray-300 px-2 text-sm dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                            value="{{ $detail->codigo ?? '' }}">
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="text" name="items[{{ $loop->index }}][descripcion]" placeholder="Descripción"
                                            class="w-full h-9 rounded border border-gray-300 px-2 text-sm dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                            value="{{ $detail->descripcion ?? '' }}">
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" name="items[{{ $loop->index }}][cantidad]" step="0.01" 
                                            class="w-full h-9 rounded border border-gray-300 px-2 text-sm text-right dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                            value="{{ (float)($detail->cantidad ?? 0) }}"
                                            onchange="recalculateTotal()">
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" name="items[{{ $loop->index }}][monto]" step="0.01" placeholder="0.00"
                                            class="w-full h-9 rounded border border-gray-300 px-2 text-sm text-right dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                            value="{{ (float)($detail->monto ?? 0) }}"
                                            onchange="recalculateTotal()">
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold">
                                        <span class="item-monto">{{ number_format((float)($detail->cantidad ?? 0) * (float)($detail->monto ?? 0), 2) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button type="button" class="text-red-600 hover:text-red-800 text-sm"
                                            onclick="removeItem({{ $loop->index }})">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr id="emptyRow" class="border-t border-gray-200 dark:border-gray-700">
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                        No hay items. Pulsa el botón "Agregar Item" para agregar detalles
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <button type="button" onclick="addItem()"
                    class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-[#FF4622] text-white rounded-lg text-sm font-bold hover:bg-[#C43B25] transition-colors">
                    <i class="ri-add-line"></i>Agregar Item
                </button>
            </div>

            {{-- RESUMEN DE TOTALES --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-8 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <div class="text-right">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Subtotal:</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">S/ <span id="subtotalDisplay">{{ number_format($purchaseMovement->subtotal ?? 0, 2) }}</span></p>
                    <input type="hidden" name="subtotal" id="subtotalInput" value="{{ (float)($purchaseMovement->subtotal ?? 0) }}" />
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">IGV (18%):</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">S/ <span id="igvDisplay">{{ number_format($purchaseMovement->igv ?? 0, 2) }}</span></p>
                    <input type="hidden" name="igv" id="igvInput" value="{{ (float)($purchaseMovement->igv ?? 0) }}" />
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Total:</p>
                    <p class="text-2xl font-bold text-[#FF4622] dark:text-[#FF4622]">S/ <span id="totalDisplay">{{ number_format($purchaseMovement->total ?? 0, 2) }}</span></p>
                    <input type="hidden" name="total" id="totalInput" value="{{ (float)($purchaseMovement->total ?? 0) }}" />
                </div>
            </div>

            {{-- BOTONES --}}
            <div class="flex gap-3 justify-end pt-6 border-t">
                <a href="{{ route('purchase.index') }}"
                    class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg font-semibold hover:bg-gray-50 transition-colors dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                    Cancelar
                </a>
                <button type="submit"
                    class="px-6 py-2 bg-[#FF4622] text-white rounded-lg font-semibold hover:bg-[#C43B25] transition-colors">
                    Actualizar Compra
                </button>
            </div>
        </form>
    </x-common.component-card>
</div>

@push('scripts')
<script>
    let itemCount = {{ count($purchaseMovement->details ?? []) }};

    function addItem() {
        itemCount++;
        const itemRow = `
            <tr class="border-t border-gray-200 dark:border-gray-700 item-row" id="item_${itemCount}">
                <td class="px-4 py-3">
                    <input type="text" name="items[${itemCount}][codigo]" placeholder="Código producto"
                        class="w-full h-9 rounded border border-gray-300 px-2 text-sm dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                </td>
                <td class="px-4 py-3">
                    <input type="text" name="items[${itemCount}][descripcion]" placeholder="Descripción"
                        class="w-full h-9 rounded border border-gray-300 px-2 text-sm dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                </td>
                <td class="px-4 py-3">
                    <input type="number" name="items[${itemCount}][cantidad]" step="0.01" value="1" 
                        class="w-full h-9 rounded border border-gray-300 px-2 text-sm text-right dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                        onchange="recalculateTotal()">
                </td>
                <td class="px-4 py-3">
                    <input type="number" name="items[${itemCount}][monto]" step="0.01" value="0" placeholder="0.00"
                        class="w-full h-9 rounded border border-gray-300 px-2 text-sm text-right dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                        onchange="recalculateTotal()">
                </td>
                <td class="px-4 py-3 text-right font-semibold">
                    <span class="item-monto">0.00</span>
                </td>
                <td class="px-4 py-3 text-center">
                    <button type="button" class="text-red-600 hover:text-red-800 text-sm"
                        onclick="removeItem(${itemCount})">
                        <i class="ri-delete-bin-line"></i>
                    </button>
                </td>
            </tr>
        `;

        const table = document.getElementById('itemsTable');
        const emptyRow = document.getElementById('emptyRow');
        
        if (emptyRow) emptyRow.remove();

        table.insertAdjacentHTML('beforeend', itemRow);

        document.querySelector(`#item_${itemCount} input[name="items[${itemCount}][cantidad]"]`).addEventListener('change', recalculateTotal);
        document.querySelector(`#item_${itemCount} input[name="items[${itemCount}][monto]"]`).addEventListener('change', recalculateTotal);
    }

    function removeItem(id) {
        const row = document.getElementById(`item_${id}`);
        if (row) row.remove();

        const hasRows = document.querySelectorAll('.item-row').length;
        if (hasRows === 0) {
            document.getElementById('itemsTable').innerHTML = `
                <tr id="emptyRow" class="border-t border-gray-200 dark:border-gray-700">
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                        No hay items. Pulsa el botón "Agregar Item" para agregar detalles
                    </td>
                </tr>
            `;
        }

        recalculateTotal();
    }

    function recalculateTotal() {
        let totalSubtotal = 0;

        document.querySelectorAll('.item-row').forEach((row) => {
            const cantidadInput = row.querySelector('input[name*="[cantidad]"]');
            const montoInput = row.querySelector('input[name*="[monto]"]');
            const montoDisplay = row.querySelector('.item-monto');

            const cantidad = parseFloat(cantidadInput.value) || 0;
            const monto = parseFloat(montoInput.value) || 0;
            const itemTotal = cantidad * monto;

            montoDisplay.textContent = itemTotal.toFixed(2);
            totalSubtotal += itemTotal;
        });

        const igvRate = 0.18;
        const igv = totalSubtotal * igvRate;
        const total = totalSubtotal + igv;

        document.getElementById('subtotalDisplay').textContent = totalSubtotal.toFixed(2);
        document.getElementById('subtotalInput').value = totalSubtotal.toFixed(2);

        document.getElementById('igvDisplay').textContent = igv.toFixed(2);
        document.getElementById('igvInput').value = igv.toFixed(2);

        document.getElementById('totalDisplay').textContent = total.toFixed(2);
        document.getElementById('totalInput').value = total.toFixed(2);
    }

    document.getElementById('purchaseForm').addEventListener('submit', function(e) {
        const hasItems = document.querySelectorAll('.item-row').length > 0;
        if (!hasItems) {
            e.preventDefault();
            alert('Debe agregar al menos un item a la compra');
        }
    });

    // Inicializar cálculos al cargar
    recalculateTotal();
</script>
@endpush
@endsection
