@extends('layouts.app')
@section('content')
    @php
        $products = $products ?? collect();
        $productBranches = $productBranches ?? collect();
        $productsForBranch = $productBranches->isNotEmpty()
            ? $products->filter(fn($p) => $productBranches->has($p->id))
            : $products;
        // Opciones para el combobox: id + description con stock actual (restricción visible)
        $comboboxOptions = $productsForBranch->filter(fn($p) => $p->kardex === 'S')
            ->map(function ($p) use ($productBranches) {
                $pb = $productBranches->get($p->id);
                $stock = $pb ? (float) ($pb->stock ?? 0) : 0;
                $name = $p->description ?? $p->code ?? 'Sin nombre';
                return ['id' => $p->id, 'description' => $name . ' (Stock: ' . number_format($stock, 0) . ')'];
        })->values()->all();
    @endphp
    <x-common.page-breadcrumb pageTitle="Nuevo movimiento" />
    <x-common.component-card title="Nueva salida de productos" desc="Registra una nueva salida de productos del almacén." :show-header="false">
        <div class="flex flex-row items-center gap-4 border-b border-gray-200 pb-4 mb-5">
            <a href="{{ route('warehouse_movements.index', request('view_id') ? ['view_id' => request('view_id')] : []) }}" class="text-sm text-gray-500 hover:text-gray-700 border border-gray-200 rounded-lg p-2 shrink-0">
                <i class="ri-arrow-left-line text-xl"></i>
            </a>
            <div class="flex flex-col gap-1">
                <h2 class="text-xl font-bold text-gray-700">Nueva salida</h2>
                <p class="text-sm text-gray-500">Movimiento de almacén</p>
            </div>
        </div>

        <div class="flex flex-col gap-4 px-5">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-lg font-semibold text-gray-700">Productos</h2>
                <x-ui.button size="sm" variant="dark" type="button" class="w-fit" onclick="addSelectedProduct()">
                    <i class="ri-add-line mr-1"></i> Agregar producto
                </x-ui.button>
            </div>
            <div class="bg-gray-100 rounded-lg p-5">
                <p class="text-sm font-medium text-gray-700">Buscar producto</p>
                <div class="flex flex-col gap-3 mt-3">
                    <x-form.select.combobox name="product_id" :options="$comboboxOptions" />
                </div>
            </div>

            {{-- Lista de productos: solo visible cuando hay al menos un producto --}}
            <div id="output-empty-state" class="mt-4 rounded-xl border border-dashed border-gray-300 bg-gray-50 py-6 text-center text-sm text-gray-500">
                No hay productos agregados. Selecciona un producto y haz clic en <strong>Agregar producto</strong>.
            </div>

            <div id="output-table-wrapper" class="mt-4 border border-gray-300 rounded-xl overflow-hidden hidden">
                <table class="w-full text-md border border-gray-300 rounded-xl overflow-hidden">
                    <thead class="bg-gray-100 text-sm font-bold text-gray-500 uppercase tracking-wider">
                        <tr>
                            <th class="px-2 py-2 text-center w-24">Producto</th>
                            <th class="px-2 py-2 text-center w-24">Stock</th>
                            <th class="px-2 py-2 text-center w-24">Cantidad</th>
                            <th class="px-2 py-2 text-center w-24">Stock después</th>
                            <th class="px-2 py-2 text-center w-24">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="output-items" class="bg-white divide-y divide-gray-100">
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-col gap-4 px-5 mt-6">
            <div>
                <p class="mb-1 text-sm font-medium text-gray-700">Comentario (opcional)</p>
                <textarea name="comment" id="output-comment" class="w-full h-24 rounded-lg border border-gray-300 p-2 text-sm focus:border-orange-500 focus:ring-1 focus:ring-orange-500" placeholder="Ej: Merma, vencimiento, transferencia..."></textarea>
            </div>

            <div class="space-y-2 text-sm border-t border-gray-200 pt-3">
                <div class="flex justify-between text-gray-500 font-medium">
                    <span>Total de productos</span>
                    <span class="text-gray-800" id="total-products">0</span>
                </div>
                <div class="flex justify-between text-gray-500 font-medium">
                    <span>Total de unidades</span>
                    <span class="text-gray-800" id="total-units">0</span>
                </div>
            </div>

            <div id="output-error" class="hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700"></div>

            <div class="flex flex-row items-center gap-2">
                <x-ui.button size="sm" variant="dark" type="button" class="w-fit" onclick="saveOutputMovement()">
                    <i class="ri-save-line mr-1"></i> Guardar salida
                </x-ui.button>
                <x-ui.button size="sm" variant="outline" type="button" class="w-fit" onclick="goBackFromOutput()">
                    <i class="ri-close-line mr-1"></i> Cancelar
                </x-ui.button>
            </div>
        </div>
    </x-common.component-card>

    @php
        $outputProducts = $products->map(function ($p) use ($productBranches) {
            $branch = $productBranches->get($p->id);
            $unit = $p->baseUnit ?? null;
            $unitType = $unit ? ($unit->type ?? 'OTRO') : 'OTRO';
            $allowsDecimal = true; // permite decimales en todas las unidades (ej: 19.5 cuyes)
            return [
                'id' => $p->id,
                'code' => $p->code ?? '',
                'name' => $p->description ?? 'Sin nombre',
                'stock' => $branch ? (float) ($branch->stock ?? 0) : 0,
                'unit_id' => $unit ? ($unit->id ?? null) : null,
                'unit_name' => $unit ? ($unit->description ?? 'Unidad') : 'Unidad',
                'allows_decimal' => $allowsDecimal,
            ];
        })->values();
    @endphp

    <script>
        const outputProductsData = @json($outputProducts);
        let outputItems = [];

        function showToast(message, icon) {
            if (window.Swal && typeof window.Swal.fire === 'function') {
                Swal.fire({
                    toast: true,
                    position: 'bottom-end',
                    icon: icon || 'info',
                    title: message,
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true
                });
            }
        }

        function renderOutputItems() {
            const container = document.getElementById('output-items');
            const tableWrapper = document.getElementById('output-table-wrapper');
            const emptyState = document.getElementById('output-empty-state');
            if (!container) return;

            if (outputItems.length === 0) {
                if (tableWrapper) tableWrapper.classList.add('hidden');
                if (emptyState) emptyState.classList.remove('hidden');
                container.innerHTML = '';
                document.getElementById('total-products').textContent = '0';
                document.getElementById('total-units').textContent = '0';
                return;
            }

            if (tableWrapper) tableWrapper.classList.remove('hidden');
            if (emptyState) emptyState.classList.add('hidden');

            const rows = outputItems.map((item, index) => {
                const safeName = String(item.name || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                const safeCode = String(item.code || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                const stockActual = parseFloat(item.stock) || 0;
                const cantidadSalida = parseFloat(item.quantity) || 0;
                const stockDespues = Math.max(0, stockActual - cantidadSalida);
                const overStock = cantidadSalida > stockActual;
                const allowsDecimal = item.allows_decimal === true;
                const inputMin = allowsDecimal ? '0.01' : '1';
                const inputStep = allowsDecimal ? '0.01' : '1';
                const stepNum = allowsDecimal ? 0.01 : 1;
                // Fracción seleccionada según el decimal actual
                const dec = parseFloat((cantidadSalida % 1).toFixed(2));
                const fracSel0  = dec === 0    ? 'selected' : '';
                const fracSel25 = dec === 0.25 ? 'selected' : '';
                const fracSel50 = dec === 0.5  ? 'selected' : '';
                const fracSel75 = dec === 0.75 ? 'selected' : '';
                return `
                    <tr class="hover:bg-gray-50 transition-colors ${overStock ? 'bg-red-50' : ''}">
                        <td class="px-4 py-2 align-middle">
                            <div class="flex flex-col">
                                <span class="font-semibold text-gray-800 truncate">${safeName}</span>
                                <span class="text-xs text-gray-500">Código: ${safeCode || 'N/A'}</span>
                            </div>
                        </td>
                        <td class="px-4 py-2 text-center align-middle">
                            <span class="text-sm font-medium text-gray-800">${stockActual}</span>
                        </td>
                        <td class="px-4 py-2 text-center align-middle">
                            <div class="flex flex-col items-center gap-1">
                                <div class="inline-flex items-center rounded-lg border border-gray-300 bg-white overflow-hidden ${overStock ? 'border-red-500' : ''}">
                                    <button type="button" onclick="var r=this.closest('tr');var i=r.querySelector('input[type=number]');var v=parseFloat(i.value)||0; updateOutputQuantity(${index}, (v-${stepNum}).toString());" class="w-9 h-9 flex items-center justify-center text-gray-600 hover:bg-gray-100 border-r border-gray-300 transition-colors" title="Menos">−</button>
                                    <input type="number" min="${inputMin}" step="${inputStep}" max="${stockActual}" value="${item.quantity}" class="w-14 text-center border-0 px-1 py-1.5 text-sm focus:ring-0 focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" onchange="updateOutputQuantity(${index}, this.value)">
                                    <button type="button" onclick="var r=this.closest('tr');var i=r.querySelector('input[type=number]');var v=parseFloat(i.value)||0; var m=Math.min(v+${stepNum},${stockActual}); updateOutputQuantity(${index}, m.toString());" class="w-9 h-9 flex items-center justify-center text-gray-600 hover:bg-gray-100 border-l border-gray-300 transition-colors" title="Más">+</button>
                                </div>
                                <select onchange="applyFractionOutput(${index}, this.value)"
                                    class="text-xs rounded border border-gray-300 bg-white px-1 py-0.5 text-gray-600 focus:outline-none focus:ring-1 focus:ring-orange-400">
                                    <option value="0"    ${fracSel0}>sin fracción</option>
                                    <option value="0.25" ${fracSel25}>+ ¼ (0.25)</option>
                                    <option value="0.5"  ${fracSel50}>+ ½ (0.5)</option>
                                    <option value="0.75" ${fracSel75}>+ ¾ (0.75)</option>
                                </select>
                            </div>
                        </td>
                        <td class="px-4 py-2 text-center align-middle">
                            <span class="text-sm font-medium ${overStock ? 'text-red-600' : 'text-gray-800'}">${stockDespues}</span>
                        </td>
                        <td class="px-4 py-2 text-center align-middle">
                            <button
                                type="button"
                                onclick="removeOutputItem(${index})"
                                class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-red-500 text-white hover:bg-red-600 shadow-sm transition-colors"
                                title="Borrar"
                            >
                                <i class="ri-delete-bin-line text-base"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');

            container.innerHTML = rows;
            const totalUnits = outputItems.reduce((sum, item) => sum + item.quantity, 0);
            document.getElementById('total-products').textContent = outputItems.length.toString();
            document.getElementById('total-units').textContent = totalUnits.toFixed(2).replace(/\.00$/, '');
        }

        function addSelectedProduct() {
            const hiddenInput = document.querySelector('input[name="product_id"]');
            if (!hiddenInput) return;
            const productId = parseInt(hiddenInput.value, 10);
            if (!productId) {
                showToast('Por favor selecciona un producto.', 'warning');
                return;
            }

            const product = outputProductsData.find(function (p) { return p.id === productId; });
            if (!product) {
                showToast('No se pudo encontrar la información del producto seleccionado.', 'error');
                return;
            }
            const stockDisponible = product.stock ?? 0;
            if (stockDisponible <= 0) {
                showToast('Este producto no tiene stock disponible en esta sucursal.', 'error');
                return;
            }

            const allowsDecimal = product.allows_decimal === true;
            const quantityToAdd = allowsDecimal ? 1 : 1;
            const existing = outputItems.find(function (item) { return item.product_id === productId; });
            if (existing) {
                const newQty = existing.quantity + quantityToAdd;
                existing.quantity = allowsDecimal ? parseFloat(newQty.toFixed(2)) : Math.floor(newQty);
                if (existing.quantity > existing.stock) existing.quantity = existing.stock;
            } else {
                outputItems.push({
                    product_id: product.id,
                    code: product.code,
                    name: product.name,
                    stock: product.stock ?? 0,
                    unit_id: product.unit_id ?? null,
                    unit_name: product.unit_name ?? 'Unidad',
                    allows_decimal: allowsDecimal,
                    quantity: quantityToAdd <= stockDisponible ? quantityToAdd : stockDisponible,
                });
            }
            renderOutputItems();
            window.dispatchEvent(new CustomEvent('clear-combobox', { detail: { name: 'product_id' } }));
        }

        function updateOutputQuantity(index, newValue) {
            const item = outputItems[index];
            if (!item) return;
            const allowsDecimal = item.allows_decimal === true;
            let qty = allowsDecimal ? parseFloat(newValue) : parseInt(newValue, 10);
            const maxStock = item.stock ?? 0;
            if (allowsDecimal) {
                if (isNaN(qty) || qty < 0.01) {
                    outputItems.splice(index, 1);
                } else {
                    item.quantity = parseFloat(Math.min(qty, maxStock).toFixed(2));
                }
            } else {
                if (isNaN(qty) || qty < 1) {
                    outputItems.splice(index, 1);
                } else {
                    item.quantity = Math.min(qty, maxStock);
                }
            }
            renderOutputItems();
        }

        function applyFractionOutput(index, fraction) {
            const item = outputItems[index];
            if (!item) return;
            const whole   = Math.floor(parseFloat(item.quantity) || 0);
            const frac    = parseFloat(fraction) || 0;
            const maxStock = item.stock ?? 0;
            const newQty  = parseFloat((whole + frac).toFixed(2));
            item.quantity = Math.min(newQty < 0.01 ? 0.01 : newQty, maxStock);
            renderOutputItems();
        }

        function removeOutputItem(index) {
            outputItems.splice(index, 1);
            renderOutputItems();
        }

        function showOutputError(message) {
            const el = document.getElementById('output-error');
            if (!el) return;
            if (!message) {
                el.classList.add('hidden');
                el.textContent = '';
                return;
            }
            el.textContent = message;
            el.classList.remove('hidden');
            showToast(message, 'error');
        }

        function goBackFromOutput() {
            const viewId = new URLSearchParams(window.location.search).get('view_id');
            const url = viewId
                ? `{{ route('warehouse_movements.index') }}?view_id=${viewId}`
                : `{{ route('warehouse_movements.index') }}`;
            window.location.href = url;
        }

        function hasOverStock() {
            return outputItems.some(function (item) { return (item.quantity || 0) > (item.stock || 0); });
        }

        async function saveOutputMovement() {
            if (outputItems.length === 0) {
                showOutputError('Agrega al menos un producto antes de guardar la salida.');
                return;
            }
            if (hasOverStock()) {
                showOutputError('Hay productos con cantidad mayor al stock disponible. Ajusta las cantidades.');
                return;
            }
            showOutputError('');

            const commentEl = document.getElementById('output-comment');
            const comment = (commentEl && commentEl.value) ? commentEl.value.trim() : '';

            const payload = {
                items: outputItems.map(function (item) {
                    return {
                        product_id: item.product_id,
                        quantity: item.quantity,
                        comment: '',
                    };
                }),
                comment: comment || 'Salida de productos del almacén',
            };

            try {
                const response = await fetch('{{ route("warehouse_movements.output.store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });
                const data = await response.json();

                if (response.ok && data.success) {
                    sessionStorage.setItem('flash_success_message', data.message || 'Salida de productos guardada correctamente');
                    goBackFromOutput();
                } else {
                    const msg = data.message || 'No se pudo guardar la salida de productos.';
                    sessionStorage.setItem('flash_error_message', msg);
                    showOutputError(msg);
                }
            } catch (error) {
                console.error(error);
                const msg = 'Error inesperado al guardar la salida de productos.';
                sessionStorage.setItem('flash_error_message', msg);
                showOutputError(msg);
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            renderOutputItems();
        });
    </script>
@endsection
