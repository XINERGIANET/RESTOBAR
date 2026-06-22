<div class="space-y-8">

    {{-- ========================================================= --}}
    {{-- SECCIÓN 1: DATOS GENERALES --}}
    {{-- ========================================================= --}}
    <input type="hidden" name="movement_type_id" value="4">

    <div>
        <h3 class="text-base font-medium text-gray-800 dark:text-white mb-4 flex items-center gap-2">
            <i class="ri-file-list-3-line text-[#FF4622]"></i> Información General
        </h3>
        <div
            class="bg-gray-50 rounded-xl p-5 border border-gray-100 dark:bg-white/[0.02] dark:border-gray-800 grid grid-cols-1 gap-5">

            {{-- NOTA / DESCRIPCIÓN --}}
            <div class="col-span-full">
                <label class="mb-1.5 block text-sm font-medium text-gray-600 dark:text-gray-400">Nota / Descripción <span
                        class="text-red-500">*</span></label>
                <input type="text" name="comment" required placeholder="Ej: Pago de servicios de luz..."
                    x-model="formConcept"
                    :readonly="formConcept === 'Apertura de caja' || formConcept === 'Cierre de caja'"
                    :class="formConcept === 'Apertura de caja' || formConcept === 'Cierre de caja' ?
                        'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white dark:bg-dark-900'"
                    class="h-11 w-full rounded-lg border-gray-200 px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-[#FF4622] focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:text-white/90 transition-all" />
                @error('comment')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                {{-- TURNO --}}
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-600 dark:text-gray-400">Turno <span
                            class="text-red-500">*</span></label>
                    <div class="relative">
                        <i class="ri-time-line absolute top-1/2 left-3 -translate-y-1/2 text-gray-400"></i>
                        <select name="shift_id" required
                            class="h-11 w-full rounded-lg border-gray-200 bg-white pl-10 pr-4 text-sm text-gray-800 focus:border-[#FF4622] focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-dark-900 dark:text-white/90 appearance-none transition-all">
                            @if (isset($shifts) && count($shifts) > 0)
                                @foreach ($shifts as $shift)
                                    <option value="{{ $shift->id }}"
                                        {{ old('shift_id') == $shift->id ? 'selected' : '' }}>{{ $shift->name }}
                                    </option>
                                @endforeach
                            @else
                                <option value="" disabled selected>⚠️ No hay turnos</option>
                            @endif
                        </select>
                    </div>
                </div>

                {{-- CONCEPTO (filtrado en tiempo real según Ingreso o Egreso) --}}
                <div x-effect="$dispatch('update-combobox-options', { name: 'payment_concept_id', options: currentConcepts })">
                    <x-form.select.combobox 
                        label="Concepto" 
                        :required="true"
                        name="payment_concept_id"        
                        x-model="formConceptId"          
                        placeholder="Buscar concepto..."
                        icon="ri-price-tag-3-line"
                    />
                </div>
            </div>
        </div>
    </div>


    {{-- ========================================================= --}}
    {{-- SECCIÓN 2: DESGLOSE DE PAGOS (DINÁMICO) --}}
    {{-- ========================================================= --}}

    <div x-data="{
        rows: [{ id: 1, methodId: '1', methodName: 'Efectivo', amount: '' }],

        addNewRow() {
            this.rows.push({ id: Date.now(), methodId: '1', methodName: 'Efectivo', amount: '' });
        },

        removeRow(index) {
            if (this.rows.length > 1) {
                this.rows.splice(index, 1);
            }
        },

        get totalAmount() {
            return this.rows.reduce((sum, row) => sum + (parseFloat(row.amount) || 0), 0).toFixed(2);
        }
    }"
    @fill-apertura-amount.window="
        const amount = Number($event.detail?.amount || 0).toFixed(2);
        rows = [{ id: Date.now(), methodId: '1', methodName: 'Efectivo', amount: amount }];
    "
    @reset-payment-rows.window="
        rows = [{ id: Date.now(), methodId: '1', methodName: 'Efectivo', amount: '' }];
    ">

        {{-- HEADER DE PAGOS Y TOTAL --}}
        <div
            class="flex flex-col sm:flex-row sm:items-end justify-between mb-6 pb-4 border-b border-gray-100 dark:border-gray-800">
            <div>
                <h3 class="text-base font-medium text-gray-800 dark:text-white flex items-center gap-2">
                    <i class="ri-wallet-3-line text-[#FF4622]"></i> Desglose de Pagos
                </h3>
                <p class="text-sm text-gray-500 mt-1">Agrega uno o más métodos para cubrir el monto.</p>
            </div>

            <div class="mt-4 sm:mt-0 text-right px-4 py-3 rounded-xl shadow-sm border border-gray-200/70 bg-white/80 backdrop-blur-sm dark:border-gray-700 dark:bg-gray-900/60 transition-colors duration-300"
                :class="formConcept === 'Cierre de caja' ? 'ring-1 ring-red-200/70 dark:ring-red-900/40' : 'ring-1 ring-brand-200/70 dark:ring-brand-900/40'">
                <span class="block text-[11px] font-semibold uppercase tracking-[0.18em] mb-1"
                    :class="formConcept === 'Cierre de caja' ? 'text-red-600 dark:text-red-400' : 'text-[#C43B25] dark:text-[#FF4622]/80'"
                    x-text="formConcept === 'Cierre de caja' ? 'Balance del Turno' : 'Total a Pagar'">
                </span>
                <span class="block text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white leading-tight">
                    S/. <span x-text="formConcept === 'Cierre de caja' ? Number(currentBalance).toFixed(2) : totalAmount"></span>
                </span>
                <span class="mt-2 inline-flex items-center justify-end gap-1 text-[11px] font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <i class="ri-safe-line text-xs opacity-70"></i>
                    <span>Monto en caja: S/. {{ number_format($currentBalance, 2) }}</span>
                </span>
            </div>
        </div>

        {{-- DETALLE DEL TURNO (solo al Cerrar caja) --}}
        <template x-if="formConcept === 'Cierre de caja' && (currentTurnBreakdown?.length > 0 || (currentTurnSummary?.ventas + currentTurnSummary?.ingresos + currentTurnSummary?.egresos > 0))">
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50/50 p-4 dark:border-red-900/50 dark:bg-red-900/10">
                <h4 class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                    <i class="ri-pie-chart-2-line text-red-500"></i>
                    Detalle del turno (para cierre)
                </h4>
                <div class="">
                    {{-- Por método de pago: tabla compacta --}}
                    <div class="min-w-0">
                        <p class="mb-1.5 text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Por método de pago</p>
                        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800" style="max-height: 14rem;">
                            <div class="overflow-y-auto">
                                <table class="w-full text-xs">
                                    <thead class="sticky top-0 bg-gray-50 dark:bg-gray-800/95 text-left">
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <th class="py-1.5 pl-2 pr-1 font-semibold text-gray-500 dark:text-gray-400">Método</th>
                                            <th class="py-1.5 px-1 text-right font-semibold text-gray-500 dark:text-gray-400">Ingresos</th>
                                            <th class="py-1.5 px-1 text-right font-semibold text-gray-500 dark:text-gray-400">Egresos</th>
                                            <th class="py-1.5 pl-1 pr-2 text-right font-semibold text-gray-500 dark:text-gray-400">Saldo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="item in currentTurnBreakdown || []" :key="item.method">
                                            <tr class="border-b border-gray-100 dark:border-gray-700/80 last:border-0">
                                                <td class="py-1.5 pl-2 pr-1">
                                                    <span class="flex items-center gap-1.5 font-medium text-gray-700 dark:text-gray-300">
                                                        <i class="text-[#FF4622] shrink-0 text-sm"
                                                            :class="item.method === 'Efectivo' ? 'ri-money-dollar-circle-line' : (['Billetera Digital','Yape','Plin'].includes(item.method) ? 'ri-smartphone-line' : (item.method === 'Tarjeta de Crédito/Débito' ? 'ri-bank-card-line' : (item.method === 'Transferencia Bancaria' ? 'ri-bank-line' : 'ri-wallet-3-line')))"></i>
                                                        <span class="truncate" x-text="item.method"></span>
                                                    </span>
                                                </td>
                                                <td class="py-1.5 px-1 text-right font-medium text-green-600 dark:text-green-400" x-text="'S/. ' + Number(item.ingresos || 0).toFixed(2)"></td>
                                                <td class="py-1.5 px-1 text-right font-medium text-red-600 dark:text-red-400" x-text="'S/. ' + Number(item.egresos || 0).toFixed(2)"></td>
                                                <td class="py-1.5 pl-1 pr-2 text-right font-semibold text-gray-900 dark:text-white" x-text="'S/. ' + Number(item.saldo || 0).toFixed(2)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
        </template>

        {{-- LISTA DE FILAS DE PAGO --}}
        <div class="space-y-4">
            <template x-for="(row, index) in rows" :key="row.id">

                {{-- CONTENEDOR DE FILA INDIVIDUAL --}}
                <div
                    class="relative bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 transition-all hover:border-brand-300 dark:hover:border-brand-700">
                    <p class="mb-3 text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400" x-show="formConcept === 'Cierre de caja'">
                        Monto en caja
                    </p>
                    {{-- Botón Eliminar (Visible si hay > 1) --}}
                    <template x-if="rows.length > 1">
                        <button type="button" @click="removeRow(index)"
                            class="absolute top-4 right-4 text-gray-400 hover:text-red-500 transition-colors p-1">
                            <i class="ri-delete-bin-line text-lg"></i>
                        </button>
                    </template>


                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-start">

                        {{-- 1. NUMERACIÓN Y MÉTODO (Ancho MD: 5) --}}
                        <div class="md:col-span-5 flex gap-3">
                            {{-- Número de Fila --}}
                            <div
                                class="flex-shrink-0 h-11 w-11 flex items-center justify-center bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-300 font-bold rounded-lg">
                                <span x-text="index + 1"></span>
                            </div>

                            <div class="flex-grow">
                                <label
                                    class="mb-1.5 block text-xs font-semibold uppercase text-gray-500 tracking-wider">Método</label>
                                <div class="relative">
                                    {{-- Icono dinámico según selección --}}
                                    <i class="absolute top-1/2 left-3 -translate-y-1/2 text-[#FF4622]"
                                        :class="{
                                            'ri-money-dollar-circle-line': row.methodId == '1',
                                            'ri-bank-card-line': row.methodId == '2',
                                            'ri-bank-line': row.methodId == '3',
                                            'ri-smartphone-line': row.methodId == '5',
                                            'ri-wallet-3-line': row.methodId == ''
                                        }"></i>

                                    <select x-model="row.methodId" 
                                        @change="row.methodName = $event.target.options[$event.target.selectedIndex].text"
                                        :name="`payments[${index}][payment_method_id]`" required 
                                        class="h-11 w-full rounded-lg border-gray-200 bg-white pl-10 pr-8 text-sm text-gray-800 focus:border-[#FF4622] focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-dark-900 dark:text-white/90 appearance-none transition-all font-medium">
                                        <option value="">Seleccionar...</option>
                                        <option value="1">Efectivo</option>
                                        <option value="2">Tarjeta de Crédito/Débito</option>
                                        <option value="5">Billetera Digital</option>
                                        <option value="3">Transferencia Bancaria</option>
                                    </select>

                                    <i
                                        class="ri-arrow-down-s-line absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 pointer-events-none"></i>

                                    {{-- INPUT OCULTO: Envía el NOMBRE (Ej: 'Efectivo') al backend --}}
                                    <input type="hidden" :name="`payments[${index}][payment_method]`"
                                        x-model="row.methodName">
                                </div>
                            </div>
                        </div>

                        {{-- 2. MONTO (Ancho MD: 3) --}}
                        
                        <div
                            class="md:col-span-3 md:pr-8 relative md:before:absolute md:before:right-0 md:before:top-1/2 md:before:-translate-y-1/2 md:before:h-8 md:before:w-px md:before:bg-gray-200 dark:md:before:bg-gray-700">
                            <label
                                class="mb-1.5 block text-xs font-semibold uppercase text-gray-500 tracking-wider">Monto</label>
                            <div class="relative">
                                <span
                                    class="absolute top-1/2 left-3 -translate-y-1/2 text-gray-500 font-medium">S/.</span>
                                <input type="number" step="0.01" min="0.00"
                                    x-model="row.amount"
                                    x-effect="if (formConcept === 'Cierre de caja') row.amount = Number(currentBalance).toFixed(2)"
                                    :name="`payments[${index}][amount]`"
                                    required
                                    class="h-11 w-full rounded-lg border-gray-200 bg-white pl-10 pr-4 text-base font-bold text-gray-900 focus:border-[#FF4622] focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-dark-900 dark:text-white placeholder:text-gray-300 transition-all"
                                    :class="formConcept === 'Cierre de caja' ? 'bg-gray-100 text-gray-500' : ''"
                                    placeholder="0.00">
                            </div>
                        </div>

                        {{-- 3. DETALLES CONDICIONALES (Ancho MD: 4) --}}
                        <div class="md:col-span-4 pt-1">

                            {{-- A. TARJETA + PASARELA --}}
                            <template x-if="row.methodId == '2'">
                                <div class="space-y-3 animate-fade-in text-sm">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">Tipo Tarjeta</label>
                                            <select :name="`payments[${index}][card_id]`" required
                                                class="w-full rounded-md border-gray-200 py-2 px-3 text-sm focus:border-[#FF4622] focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-dark-900">
                                                <option value="">Seleccionar</option>
                                                @foreach ($cards as $card)
                                                    <option value="{{ $card->id }}">{{ $card->description }}
                                                        ({{ $card->type }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">Procesador (POS)</label>
                                            <select :name="`payments[${index}][payment_gateway_id]`"
                                                class="w-full rounded-md border-gray-200 py-2 px-3 text-sm focus:border-[#FF4622] focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-dark-900">
                                                <option value="">Ninguno</option>
                                                @foreach ($paymentGateways as $pg)
                                                    <option value="{{ $pg->id }}">{{ $pg->description }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <input type="text" :name="`payments[${index}][number]`"
                                            placeholder="N° Lote / Operación (Opcional)"
                                            class="w-full rounded-md border-gray-200 py-2 px-3 text-sm focus:border-[#FF4622] focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-dark-900">
                                    </div>
                                </div>
                            </template>

                            {{-- B. BILLETERA DIGITAL --}}
                            <template x-if="row.methodId == '5'">
                                <div class="space-y-3 animate-fade-in text-sm">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">Aplicación</label>
                                            <select :name="`payments[${index}][digital_wallet_id]`" required
                                                class="w-full rounded-md border-gray-200 py-2 px-3 text-sm focus:border-[#FF4622] focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-dark-900">
                                                <option value="">Seleccionar</option>
                                                @foreach ($digitalWallets as $dw)
                                                    <option value="{{ $dw->id }}">{{ $dw->description }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">N° Celular / Ref.</label>
                                            <input type="text" :name="`payments[${index}][number]`" required
                                                placeholder="Ej: 999..."
                                                class="w-full rounded-md border-gray-200 py-2 px-3 text-sm focus:border-[#FF4622] focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-dark-900">
                                        </div>
                                    </div>
                                </div>
                            </template>

                            {{-- C. BANCO / TRANSFERENCIA --}}
                            <template x-if="row.methodId == '3'">
                                <div class="space-y-3 animate-fade-in text-sm">
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Banco Destino</label>
                                        <select :name="`payments[${index}][bank_id]`" required
                                            class="w-full rounded-md border-gray-200 py-2 px-3 text-sm focus:border-[#FF4622] focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-dark-900">
                                            <option value="">Seleccionar</option>
                                            @foreach ($banks as $bank)
                                                <option value="{{ $bank->id }}">{{ $bank->description }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <input type="text" :name="`payments[${index}][number]`" required
                                            placeholder="N° de Operación / Constancia"
                                            class="w-full rounded-md border-gray-200 py-2 px-3 text-sm focus:border-[#FF4622] focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-dark-900">
                                    </div>
                                </div>
                            </template>

                            {{-- PLACEHOLDER PARA EFECTIVO O SIN SELECCIÓN --}}
                            <template x-if="formConcept === 'Apertura de caja'">
                                <div class="h-full flex items-center md:justify-center py-2 md:py-0">
                                    <span class="text-xs text-gray-400 italic inline-flex items-center gap-1">
                                        <i class="ri-information-line"></i> Datos recuperados de caja anterior.
                                    </span>
                                </div>
                            </template>
                            <template x-if="formConcept !== 'Apertura de caja'">
                                <div class="h-full flex items-center md:justify-center py-2 md:py-0">
                                    <span class="text-xs text-gray-400 italic inline-flex items-center gap-1">
                                        <i class="ri-information-line"></i> Sin detalles adicionales requeridos.
                                    </span>
                                </div>
                            </template>
                            

                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="mt-5">
            <button type="button" @click="addNewRow()"
                class="inline-flex items-center gap-2 text-sm font-medium text-[#C43B25] hover:text-brand-700 transition-colors py-2 px-3 rounded-lg hover:bg-[#FF4622]/10 dark:hover:bg-brand-900/20">
                <span>Agregar otro método de pago</span>
                <i class="ri-add-fill text-xl"></i>
            </button>
        </div>

    </div>
</div>

<style>
    .animate-fade-in {
        animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-4px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>
