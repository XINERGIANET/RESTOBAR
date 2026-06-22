@extends('layouts.app')

@section('content')
@php
    use Illuminate\Support\Js;

    $existingPayments = $movement->cashMovement->details->map(function($detail) {
        return [
            'id' => $detail->id,
            'methodId' => (string)$detail->payment_method_id,
            'methodName' => $detail->payment_method_name,
            'amount' => number_format($detail->amount, 2, '.', ''),
            'card_id' => $detail->card_id,
            'payment_gateway_id' => $detail->payment_gateway_id,
            'digital_wallet_id' => $detail->digital_wallet_id,
            'bank_id' => $detail->bank_id,
            'number' => $detail->number
        ];
    });

    $isIngreso = stripos($movement->documentType->name, 'Ingreso') !== false;
    $initialConcepts = $isIngreso ? $conceptsIngreso : $conceptsEgreso;

    $savedConceptId = old('payment_concept_id', $movement->cashMovement->payment_concept_id);
@endphp

<x-common.page-breadcrumb pageTitle="Editar Movimiento" />

<x-ui.modal
    x-data="{ 
        open: true,
        formConcept: '{{ old('comment', $movement->comment) }}', 
        formConceptId: '{{ $savedConceptId }}',
        formDocId: '{{ $movement->document_type_id }}',
        currentConcepts: {{ Js::from($initialConcepts) }},

        rows: {{ $existingPayments->count() > 0 ? Js::from($existingPayments) : '[{ id: Date.now(), methodId: \'\', methodName: \'\', amount: \'\' }]' }},

        redirectToIndex() {
            window.location.href = '{{ route('petty-cash.index', array_merge(['cash_register_id' => $cash_register_id], !empty($viewId) ? ['view_id' => $viewId] : [])) }}';
        },
        addNewRow() {
            this.rows.push({ id: Date.now(), methodId: '', methodName: '', amount: '' });
        },
        removeRow(index) {
            if (this.rows.length > 1) this.rows.splice(index, 1);
        },
        get totalAmount() {
            return this.rows.reduce((sum, row) => sum + (parseFloat(row.amount) || 0), 0).toFixed(2);
        }
    }"
    x-init="$watch('open', value => { if (!value) redirectToIndex(); })"
    @keydown.escape.window="redirectToIndex()"
    :isOpen="true"
    :showCloseButton="false"
    class="max-w-4xl"
>
    <div class="p-6 sm:p-8">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-gray-100 pb-4 dark:border-gray-800">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-warning-50 text-warning-600 dark:bg-warning-500/10">
                    <i class="ri-pencil-line text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Editar Movimiento #{{ $movement->number }}</h3>
                    <p class="text-sm text-gray-500">Caja Actual: {{ $cash_register_id }}</p>
                </div>
            </div>
            
            <button type="button" @click="redirectToIndex()" class="h-10 w-10 flex items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 transition-all">
                <i class="ri-close-line text-xl"></i>
            </button>
        </div>

        @if ($errors->any())
            <div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200">
                <div class="flex items-center gap-2 text-red-700 font-bold mb-2">
                    <i class="ri-error-warning-line"></i>
                    <span>Lista de Errores Detectados:</span>
                </div>
                <ul class="list-disc list-inside text-sm text-red-600 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <div class="mt-2 text-xs text-gray-500 font-mono">
                    Dump de errores: {{ json_encode($errors->messages()) }}
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('petty-cash.update', ['cash_register_id' => $cash_register_id, 'movement' => $movement->id]) }}" class="space-y-8">
            @csrf
            @method('PUT')
            @if (!empty($viewId))
                <input type="hidden" name="view_id" value="{{ $viewId }}">
            @endif

            <input type="hidden" name="document_type_id" value="{{ old('document_type_id', $movement->document_type_id) }}">

            <div>
                <h3 class="text-base font-medium text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                    <i class="ri-file-list-3-line text-[#FF4622]"></i> Información del Movimiento
                </h3>
                <div class="bg-gray-50 rounded-xl p-5 border border-gray-100 dark:bg-white/[0.02] dark:border-gray-800 space-y-5">
                    
                    <div class="col-span-full">
                        <label class="mb-1.5 block text-sm font-medium text-gray-600 dark:text-gray-400">Nota / Descripción <span class="text-red-500">*</span></label>
                        <input type="text" name="comment" required x-model="formConcept"
                            :readonly="formConcept.includes('Apertura') || formConcept.includes('Cierre')"
                            :class="formConcept.includes('Apertura') || formConcept.includes('Cierre') ? 'bg-gray-100 text-gray-400' : 'bg-white dark:bg-dark-900'"
                            class="h-11 w-full rounded-lg border-gray-200 px-4 py-2.5 text-sm transition-all" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-600 dark:text-gray-400">Turno <span class="text-red-500">*</span></label>
                            <select name="shift_id" required class="h-11 w-full rounded-lg border-gray-200 dark:bg-dark-900 dark:text-white/90">
                                @foreach($shifts as $shift)
                                    <option value="{{ $shift->id }}" {{ old('shift_id', $movement->cashMovement->shift_id) == $shift->id ? 'selected' : '' }}>
                                        {{ $shift->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-600 dark:text-gray-400">Concepto <span class="text-red-500">*</span></label>
                            <select name="payment_concept_id" required x-model="formConceptId" class="h-11 w-full rounded-lg border-gray-200 dark:bg-dark-900 dark:text-white/90">
                                <template x-for="item in currentConcepts" :key="item.id">
                                    <option :value="item.id" x-text="item.description" :selected="item.id == formConceptId"></option>
                                </template>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SECCIÓN 2: DESGLOSE DE PAGOS --}}
            
            <div>
                <div class="flex items-end justify-between mb-4">
                    <h3 class="text-base font-medium text-gray-800 dark:text-white flex items-center gap-2">
                        <i class="ri-wallet-3-line text-[#FF4622]"></i> Desglose de Pagos
                    </h3>
                </div>

                <div class="space-y-4">
                    <template x-for="(row, index) in rows" :key="row.id">
                        <div class="relative bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 transition-all hover:shadow-sm">
                            
                            <template x-if="rows.length > 1">
                                <button type="button" @click="removeRow(index)" class="absolute top-4 right-4 text-gray-400 hover:text-red-500 transition-colors">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </template>

                            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                                <div class="md:col-span-5">
                                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Método de Pago</label>
                                    <select x-model="row.methodId" 
                                        :name="`payments[${index}][payment_method_id]`" 
                                        @change="row.methodName = $event.target.options[$event.target.selectedIndex].text"
                                        required class="w-full h-11 rounded-lg border-gray-200 dark:bg-dark-900 dark:text-white/90">
                                        <option value="">Seleccionar...</option>
                                        <option value="1">Efectivo</option>
                                        <option value="2">Tarjeta</option>
                                        <option value="5">Billetera Digital</option>
                                        <option value="3">Transferencia</option>
                                    </select>
                                    <input type="hidden" :name="`payments[${index}][payment_method]`" x-model="row.methodName">
                                </div>

                                <div class="md:col-span-3">
                                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Monto (S/.)</label>
                                    <input type="number" step="0.01" x-model="row.amount" :name="`payments[${index}][amount]`" required 
                                        class="w-full h-11 rounded-lg border-gray-200 dark:bg-dark-900 dark:text-white font-bold text-lg" />
                                </div>

                                <div class="md:col-span-4">
                                    <template x-if="row.methodId == '2'">
                                        <div class="space-y-2">
                                            <select :name="`payments[${index}][card_id]`" x-model="row.card_id" required class="w-full rounded-lg text-sm border-gray-200">
                                                <option value="">¿Qué tarjeta?</option>
                                                @foreach($cards as $card) <option value="{{ $card->id }}">{{ $card->description }}</option> @endforeach
                                            </select>
                                            <input type="text" :name="`payments[${index}][number]`" x-model="row.number" placeholder="N° Operación" class="w-full rounded-lg text-sm border-gray-200">
                                        </div>
                                    </template>

                                    <template x-if="row.methodId == '5'">
                                        <div class="space-y-2">
                                            <select :name="`payments[${index}][digital_wallet_id]`" x-model="row.digital_wallet_id" required class="w-full rounded-lg text-sm border-gray-200">
                                                <option value="">¿Qué Wallet?</option>
                                                @foreach($digitalWallets as $dw) <option value="{{ $dw->id }}">{{ $dw->description }}</option> @endforeach
                                            </select>
                                            <input type="text" :name="`payments[${index}][number]`" x-model="row.number" placeholder="Celular / Referencia" class="w-full rounded-lg text-sm border-gray-200">
                                        </div>
                                    </template>

                                    <template x-if="row.methodId == '3'">
                                        <div class="space-y-2">
                                            <select :name="`payments[${index}][bank_id]`" x-model="row.bank_id" required class="w-full rounded-lg text-sm border-gray-200">
                                                <option value="">Seleccionar Banco</option>
                                                @foreach($banks as $bank) <option value="{{ $bank->id }}">{{ $bank->description }}</option> @endforeach
                                            </select>
                                            <input type="text" :name="`payments[${index}][number]`" x-model="row.number" placeholder="Código Operación" class="w-full rounded-lg text-sm border-gray-200">
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                    
                    <button type="button" @click="addNewRow()" class="mt-2 text-[#C43B25] font-medium text-sm flex items-center gap-1 hover:underline">
                        <i class="ri-add-circle-line"></i> Agregar otro método de pago
                    </button>

                   <div class="w-fit ml-auto flex items-center gap-3 bg-[#FF4622]/10 dark:bg-brand-900/20 px-5 py-2 rounded-lg border border-brand-100 dark:border-brand-800 shadow-sm">
                        <span class="text-xs text-[#C43B25] uppercase font-bold tracking-wider">Total</span>
                        <span class="text-xl font-black text-gray-900 dark:text-white tracking-tight">
                            S/. <span x-text="totalAmount"></span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 pt-6 border-t border-gray-100 dark:border-gray-800">
                <x-ui.button type="submit" size="lg" variant="primary" class="flex-1 sm:flex-none">
                    <i class="ri-save-line mr-2"></i> Actualizar Cambios
                </x-ui.button>
                <x-ui.button type="button" @click="redirectToIndex()" size="lg" variant="outline" class="flex-1 sm:flex-none">
                    Cancelar
                </x-ui.button>
            </div>
        </form>
    </div>
</x-ui.modal>
@endsection
