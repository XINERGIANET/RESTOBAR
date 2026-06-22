@extends('layouts.app')

@section('content')
    <style>
        [x-cloak] { display: none !important; }
        .bg-purple-gradient { background: linear-gradient(135deg, #a855f7 0%, #7e22ce 100%); }
        .bg-green-gradient { background: linear-gradient(135deg, #10b981 0%, #047857 100%); }
        .sidebar-item-label { color: #6b7280; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem; }
        .sidebar-item-value { background-color: #f9fafb; border: 1px solid #f3f4f6; border-radius: 0.5rem; padding: 0.5rem 0.75rem; font-size: 0.8125rem; font-weight: 600; color: #1f2937; display: flex; align-items: center; gap: 0.5rem; }
        .sidebar-item-value i { color: #FF4622; font-size: 1rem; }
    </style>

    @php
        use Illuminate\Support\Js;
        $responsibleName = $lastOpeningMovement->responsible_name ?? $lastOpeningMovement->user_name ?? 'ADMIN';
        $shiftName = $lastOpeningMovement->shift->name ?? 'Turno';
        $boxName = $lastOpeningMovement->cashMovement->cash_register ?? 'Principal';
        $personName = $lastOpeningMovement->person_name ?? '0 - CLIENTES VARIOS';
    @endphp

    <div x-data="{
        montoReal: 0,
        currency: 'Soles',
        open: true,
        formConcept: 'Cierre de caja',
        formConceptId: '{{ $conceptsEgreso->firstWhere('description', 'Cierre de caja')?->id ?? ($conceptsEgreso->firstWhere('description', 'like', '%Cierre%')?->id ?? '') }}',
        formDocId: '{{ $egresoDocId }}',
        refEgresoId: '{{ $egresoDocId }}',
        currentBalance: {{ $currentBalance }},
        currentTurnSummary: {{ Js::from($currentTurnSummary) }},
        aperturaEfectivo: {{ $aperturaEfectivo }},
        turnSummary: {{ Js::from($turnSummary) }},
        shiftId: '{{ $lastOpeningMovement->shift_id ?? "" }}'
    }" class="pb-10">
        
        {{-- Header / Breadcrumb --}}
        <div class="mb-6 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold text-gray-900">Caja chica | Cerrar caja</h1>
            </div>
            <nav class="flex text-xs text-gray-500" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-2">
                    <li class="inline-flex items-center">
                        <a href="#" class="hover:text-[#FF4622]">Home</a>
                    </li>
                    <i class="ri-arrow-right-s-line text-lg"></i>
                    <li class="inline-flex items-center">
                        <a href="#" class="hover:text-[#FF4622]">Caja chica</a>
                    </li>
                    <i class="ri-arrow-right-s-line text-lg"></i>
                    <li class="inline-flex items-center">
                        <span class="font-semibold text-gray-800">Cerrar caja</span>
                    </li>
                </ol>
            </nav>
        </div>

        @if ($errors->any())
            <div class="mb-6 rounded-2xl border border-red-100 bg-red-50 p-4 dark:border-red-500/30 dark:bg-red-500/10">
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-red-500 text-white shadow-lg shadow-red-500/20">
                        <i class="ri-error-warning-line"></i>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-red-800 dark:text-red-400">Error al procesar el cierre</h4>
                        <ul class="mt-1 text-xs text-red-700 dark:text-red-300">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            
            {{-- SIDEBAR --}}
            <div class="lg:col-span-3">
                <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="p-6">
                        <div class="mb-6 flex items-center gap-3 border-b border-gray-50 pb-4 dark:border-gray-800">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#FF4622]/10 text-[#FF4622]">
                                <i class="ri-information-line text-xl"></i>
                            </div>
                            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-800 dark:text-white">Información de caja</h3>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <p class="sidebar-item-label">Persona</p>
                                <div class="sidebar-item-value">
                                    <i class="ri-user-follow-line text-[#FF4622]"></i>
                                    <span>{{ $personName }}</span>
                                </div>
                            </div>

                            <div>
                                <p class="sidebar-item-label">Responsable</p>
                                <div class="sidebar-item-value">
                                    <i class="ri-shield-user-line"></i>
                                    <span class="uppercase">{{ $responsibleName }}</span>
                                </div>
                            </div>

                            <div>
                                <p class="sidebar-item-label">Turno</p>
                                <div class="sidebar-item-value">
                                    <i class="ri-time-line"></i>
                                    <span>{{ $shiftName }}</span>
                                </div>
                            </div>

                            <div>
                                <p class="sidebar-item-label">Caja</p>
                                <div class="sidebar-item-value">
                                    <i class="ri-layout-grid-line"></i>
                                    <span>{{ $boxName }}</span>
                                </div>
                            </div>

                            <div class="rounded-xl bg-gray-50/50 p-4 dark:bg-gray-800/50">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-gray-600">Total según sistema</span>
                                    <span class="text-lg font-black text-gray-900">S/ {{ number_format($currentBalance, 2) }}</span>
                                </div>
                            </div>

                            <div class="pt-2">
                                <p class="sidebar-item-label">Monto real de cierre (Manual)</p>
                                <input type="number" step="0.01" x-model="montoReal"
                                    class="w-full rounded-xl border-gray-200 bg-gray-50 p-3 text-sm font-bold focus:border-[#FF4622] focus:ring-[#FF4622] dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    placeholder="0.00">
                            </div>

                            <div class="flex items-center justify-between rounded-xl bg-emerald-50/50 p-4 dark:bg-emerald-500/5">
                                <div>
                                    <p class="text-[10px] font-bold uppercase text-emerald-600">Total Contado</p>
                                    <p class="text-[9px] text-emerald-500">(billetes / monedas)</p>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs font-bold text-emerald-600">S/</span>
                                    <span class="text-xl font-black text-emerald-700" x-text="parseFloat(montoReal || 0).toFixed(2)">0.00</span>
                                </div>
                            </div>

                            <div class="pt-2">
                                <p class="sidebar-item-label">Tipo de moneda</p>
                                <div class="flex gap-2">
                                    <button @click="currency = 'Dolar'" :class="currency === 'Dolar' ? 'bg-[#FF4622] text-white shadow-[#FF4622]/20' : 'bg-white border-gray-200 text-gray-500'" class="flex-1 rounded-xl border py-2.5 text-xs font-bold transition-all duration-200">
                                        Dólar $
                                    </button>
                                    <button @click="currency = 'Soles'" :class="currency === 'Soles' ? 'bg-[#FF4622] text-white shadow-[#FF4622]/20' : 'bg-white border-gray-200 text-gray-500'" class="flex-1 rounded-xl border py-2.5 text-xs font-bold transition-all duration-200">
                                        Soles S/
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- MAIN CONTENT --}}
            <div class="lg:col-span-9 space-y-6">
                
                {{-- Top Summary Stats --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4">
                    {{-- Ventas --}}
                    <div class="flex items-center justify-between rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400">Ventas en efectivo</p>
                            <p class="mt-1 text-lg font-black text-gray-900 dark:text-white">S/ {{ number_format($currentTurnSummary['ventas'], 2) }}</p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-500">
                            <i class="ri-arrow-right-up-line text-xl"></i>
                        </div>
                    </div>
                    {{-- Apertura --}}
                    <div class="flex items-center justify-between rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400">Apertura en efectivo</p>
                            <p class="mt-1 text-lg font-black text-gray-900 dark:text-white">S/ {{ number_format($aperturaEfectivo, 2) }}</p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#FF4622]/10 text-[#FF4622]">
                            <i class="ri-coin-line text-xl"></i>
                        </div>
                    </div>
                    {{-- Ingresos --}}
                    <div class="flex items-center justify-between rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400">Ingresos en efectivo</p>
                            <p class="mt-1 text-lg font-black text-gray-900 dark:text-white">S/ {{ number_format($currentTurnSummary['ingresos'], 2) }}</p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-orange-100/50 text-[#FF4622]">
                            <i class="ri-add-line text-xl"></i>
                        </div>
                    </div>
                    {{-- Egresos --}}
                    <div class="flex items-center justify-between rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400">Egresos en efectivo</p>
                            <p class="mt-1 text-lg font-black text-gray-900 dark:text-white">S/ {{ number_format($currentTurnSummary['egresos'], 2) }}</p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-50 text-red-500">
                            <i class="ri-subtract-line text-xl"></i>
                        </div>
                    </div>
                </div>

                {{-- Balance Cards --}}
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    {{-- Total en caja --}}
                    <div class="bg-purple-gradient relative overflow-hidden rounded-3xl p-8 shadow-lg shadow-purple-500/20">
                        <div class="relative z-10">
                            <p class="text-xs font-bold uppercase tracking-widest text-white/70">Total en caja</p>
                            <p class="mt-4 text-3xl font-black text-white">S/ {{ number_format($currentBalance, 2) }}</p>
                        </div>
                        <div class="absolute right-0 top-0 h-full w-40 bg-white/10" style="clip-path: circle(80% at 100% 0%);"></div>
                        <div class="absolute -bottom-6 -right-6 h-24 w-24 rounded-full bg-white/5"></div>
                    </div>

                    {{-- Total en caja cierre --}}
                    <div class="bg-green-gradient relative overflow-hidden rounded-3xl p-8 shadow-lg shadow-emerald-500/20">
                        <div class="relative z-10 flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-widest text-white/70">Total en caja cierre</p>
                                <p class="mt-4 text-3xl font-black text-white">S/ <span x-text="parseFloat(montoReal || currentBalance).toFixed(2)"></span></p>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] font-bold uppercase text-white/50">Diferencia</p>
                                <p class="text-xl font-bold" :class="parseFloat(montoReal - currentBalance) < 0 ? 'text-red-200' : 'text-emerald-100'">
                                    S/ <span x-text="parseFloat(montoReal - currentBalance).toFixed(2)"></span>
                                </p>
                            </div>
                        </div>
                        <div class="absolute right-0 top-0 h-full w-48 bg-white/10" style="clip-path: ellipse(80% 60% at 100% 50%);"></div>
                    </div>
                </div>

                {{-- Table Section --}}
                <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-50 px-6 py-5 dark:border-gray-800">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Detalle de cierre</h3>
                        <p class="text-xs text-gray-500">Registro de movimientos de caja</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-[#C43B25] text-[10px] font-bold uppercase tracking-wider text-white">
                                <tr>
                                    <th class="px-6 py-4">Flujo</th>
                                    <th class="px-6 py-4">Tipo</th>
                                    <th class="px-6 py-4">Monto</th>
                                    <th class="px-6 py-4">Medio</th>
                                    <th class="px-6 py-4">Detalles</th>
                                    <th class="px-6 py-4 text-center">Notas</th>
                                    <th class="px-6 py-4 text-center">Operaciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                                @forelse ($shiftMovements as $movement)
                                    @php
                                        $type = $movement->cashMovement?->paymentConcept?->type ?? 'I';
                                        $conceptName = $movement->cashMovement?->paymentConcept?->description ?? '-';
                                        $total = $movement->cashMovement?->total ?? 0;
                                        $methods = collect($movement->cashMovement?->details ?? [])->pluck('payment_method')->unique()->implode(', ');
                                        $paymentSpecifics = collect($movement->cashMovement?->details ?? [])
                                            ->map(fn($d) => $d->digital_wallet ?: ($d->card ?: ($d->bank ?: ($d->payment_method ?: '-'))))
                                            ->unique()
                                            ->implode(', ');
                                        
                                        $isVenta = ($movement->movement_type_id == 2 || str_contains(strtolower($conceptName), 'venta'));
                                    @endphp
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <div class="flex items-center gap-2">
                                                <div class="flex h-6 w-6 items-center justify-center rounded-lg {{ $type === 'I' ? 'bg-emerald-50 text-emerald-500' : 'bg-rose-50 text-rose-500' }}">
                                                    <i class="{{ $type === 'I' ? 'ri-arrow-right-up-line' : 'ri-arrow-right-down-line' }} text-xs"></i>
                                                </div>
                                                <span class="text-[11px] font-bold {{ $type === 'I' ? 'text-emerald-700' : 'text-rose-700' }}">
                                                    {{ $type === 'I' ? 'Ingreso' : 'Egreso' }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <span class="rounded-lg {{ $isVenta ? 'bg-[#FF4622]/10 text-[#FF4622]' : 'bg-gray-100 text-gray-600' }} px-2 py-1 text-[10px] font-bold">
                                                {{ $isVenta ? 'Pagado' : ($movement->status == '1' ? 'Pagado' : 'Deuda') }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-[11px] font-bold text-gray-900 dark:text-white">
                                            S/ {{ number_format($total, 2) }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-[11px] text-gray-500">
                                            {{ $methods ?: '-' }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-[11px] text-gray-500">
                                            {{ $paymentSpecifics ?: '-' }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center justify-center">
                                                <span class="inline-block rounded-full bg-[#FF4622] px-3 py-0.5 text-[9px] font-semibold text-white">
                                                   {{ $conceptName }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <a href="{{ route('petty-cash.show', ['cash_register_id' => $cash_register_id, 'movement' => $movement->id, 'view_id' => $viewId]) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-[#FF4622] text-white shadow-sm transition-transform hover:scale-110 active:scale-95">
                                                <i class="ri-eye-line text-sm"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-10 text-center text-xs text-gray-500">No hay movimientos registrados en este turno.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Table Footer Summary --}}
                    <div class="bg-gray-50/50 px-6 py-4 dark:bg-gray-800/50">
                        <div class="flex flex-wrap items-center justify-between gap-6">
                            <div class="flex flex-col">
                                <span class="text-[9px] font-bold uppercase tracking-wider text-gray-400">Total de Ventas</span>
                                <span class="text-sm font-black text-emerald-600">S/ {{ number_format($turnSummary['ventas'], 2) }}</span>
                            </div>
                            <div class="flex flex-col text-center">
                                <span class="text-[9px] font-bold uppercase tracking-wider text-gray-400">Total de Ingresos</span>
                                <span class="text-sm font-black text-[#FF4622]">S/ {{ number_format($turnSummary['ingresos'], 2) }}</span>
                            </div>
                            <div class="flex flex-col text-right">
                                <span class="text-[9px] font-bold uppercase tracking-wider text-gray-400">Total de Egresos</span>
                                <span class="text-sm font-black text-rose-600">S/ {{ number_format($turnSummary['egresos'], 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex items-center justify-end gap-3 pt-4">
                    <form id="closure-form" method="POST" action="{{ route('petty-cash.store', ['cash_register_id' => $cash_register_id]) }}">
                        @csrf
                        @if ($viewId)
                            <input type="hidden" name="view_id" value="{{ $viewId }}">
                        @endif
                        <input type="hidden" name="document_type_id" value="{{ $egresoDocId }}">
                        <input type="hidden" name="payment_concept_id" value="{{ $conceptsEgreso->firstWhere('description', 'Cierre de caja')?->id ?? ($conceptsEgreso->firstWhere('description', 'like', '%Cierre%')?->id ?? '') }}">
                        <input type="hidden" name="cash_register_id" value="{{ $cash_register_id }}">
                        <input type="hidden" name="comment" value="Cierre de caja generado automáticamente">
                        <input type="hidden" name="shift_id" value="{{ $lastOpeningMovement->shift_id ?? ($summary['lastOpeningMovement']->shift_id ?? '') }}">
                        
                        {{-- Payments breakdown (Closing move usually records full balance in Cash) --}}
                        <input type="hidden" name="payments[0][amount]" :value="parseFloat(montoReal || currentBalance)">
                        <input type="hidden" name="payments[0][payment_method_id]" value="1">
                        <input type="hidden" name="payments[0][payment_method]" value="Efectivo">

                        <x-ui.button type="submit" size="lg" variant="primary" class="h-12 px-8 font-black uppercase tracking-wider shadow-lg shadow-[#FF4622]/20" style="background-color: #FF4622;">
                            <i class="ri-lock-2-line mr-2"></i> Confirmar y Guardar Cierre
                        </x-ui.button>
                    </form>
                    
                    <x-ui.link-button variant="outline" size="lg" href="{{ route('petty-cash.index', ['cash_register_id' => $cash_register_id, 'view_id' => $viewId]) }}" class="h-12 px-8 font-bold uppercase tracking-wider">
                        Cancelar
                    </x-ui.link-button>
                </div>

            </div>
        </div>
    </div>
@endsection
