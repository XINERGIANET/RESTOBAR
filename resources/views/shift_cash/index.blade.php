@extends('layouts.app')

@section('content')
    <div x-data="{}">
        @php
            use Illuminate\Support\Facades\Route;

            $viewId = request('view_id');
            $operacionesCollection = collect($operaciones ?? []);
            $topOperations = $operacionesCollection->where('type', 'T');
            $rowOperations = $operacionesCollection->where('type', 'R');

            // --- Helpers ---
            $resolveActionUrl = function ($action, $shift = null, $operation = null) use ($viewId) {
                if (!$action) return '#';
                if (str_starts_with($action, '/') || str_starts_with($action, 'http')) {
                    $url = $action;
                } else {
                    $routeCandidates = [$action];
                    if (!str_starts_with($action, 'admin.')) $routeCandidates[] = 'admin.' . $action;
                    $routeCandidates = array_merge($routeCandidates, array_map(fn($name) => $name . '.index', $routeCandidates));
                    $routeName = null;
                    foreach ($routeCandidates as $candidate) {
                        if (Route::has($candidate)) { $routeName = $candidate; break; }
                    }
                    if ($routeName) {
                        try { $url = $shift ? route($routeName, $shift) : route($routeName); } catch (\Exception $e) { $url = '#'; }
                    } else { $url = '#'; }
                }
                $targetViewId = $viewId;
                if ($operation && !empty($operation->view_id_action)) $targetViewId = $operation->view_id_action;
                if ($targetViewId && $url !== '#') {
                    $separator = str_contains($url, '?') ? '&' : '?';
                    $url .= $separator . 'view_id=' . urlencode($targetViewId);
                }
                return $url;
            };

            $resolveTextColor = function ($operation) {
                $action = $operation->action ?? '';
                if (str_contains($action, 'shift-cash.create')) return '#111827';
                return '#FFFFFF';
            };
        @endphp

        <x-common.page-breadcrumb pageTitle="Turno por caja " />

        <x-common.component-card title="Gestión de Turnos" desc="Resumen detallado de ingresos y egresos por turno.">
            
            {{-- BUSCADOR Y BOTONES --}}
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between mb-4">
                <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                    @if ($viewId) <input type="hidden" name="view_id" value="{{ $viewId }}"> @endif
                    <x-ui.per-page-selector :per-page="$perPage" />
                    <div class="relative flex-1">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"><i class="ri-search-line"></i></span>
                        <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por N° apertura..." class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-10 text-sm focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                    </div>
                    <div class="relative flex w-full sm:w-auto min-w-[200px]">
                        <select 
                            onchange="
                                let baseUrl = '{{ url('/caja/turno-caja/lista') }}/' + this.value;
                                let params = new URLSearchParams(window.location.search);                               
                                params.delete('cash_register_id'); 
                                window.location.href = baseUrl + (params.toString() ? '?' + params.toString() : '');
                            "
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#124731] focus:ring-[#124731]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                            @if (isset($cashRegisters))
                                @foreach ($cashRegisters as $register)
                                    <option value="{{ $register->id }}" {{ $selectedBoxId == $register->id ? 'selected' : '' }}>
                                        {{ $register->number }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <x-ui.button size="md" variant="primary" type="submit" class="h-11 px-4" style="background-color: #0A2E1F;">
                            <i class="ri-search-line text-gray-100"></i> <span class="text-gray-100">Buscar</span>
                        </x-ui.button>
                        <x-ui.link-button size="md" variant="outline" href="{{ route('shift-cash.redirect') }}{{ $viewId ? '?view_id=' . urlencode($viewId) : '' }}" class="h-11 px-4">
                            <i class="ri-refresh-line"></i> <span>Limpiar</span>
                        </x-ui.link-button>
                    </div>
                </form>

                <div class="flex items-center gap-2">
                    @foreach ($topOperations as $operation)
                        @php
                            $topStyle = "background-color: " . ($operation->color ?: '#3B82F6') . "; color: " . $resolveTextColor($operation) . ";";
                            $url = $resolveActionUrl($operation->action, null, $operation);
                        @endphp
                        <x-ui.link-button size="md" variant="primary" style="{{ $topStyle }}" href="{{ $url }}">
                            <i class="{{ $operation->icon }}"></i> <span>{{ $operation->name }}</span>
                        </x-ui.link-button>
                    @endforeach
                </div>
            </div>

            {{-- TABLA --}}
            {{-- TABLA PRINCIPAL --}}
            <div class="table-responsive mt-4 overflow-x-auto max-w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full min-w-[1200px]">
                    <thead style="background-color: #124731; color: #FFFFFF;">
                        <tr class="text-white">
                            <th class="px-5 py-3 text-center sm:px-6 sticky-left-header first:rounded-tl-xl">
                                <p class="font-medium text-theme-xs uppercase">Orden</p>
                            </th>
                            <th class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-theme-xs uppercase">Fecha Inicio</p>
                            </th>
                            <th class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-theme-xs uppercase">Fecha Cierre</p>
                            </th>
                            <th class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-theme-xs uppercase">N° Apertura</p>
                            </th>
                            <th class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-theme-xs uppercase">Detalle (Ingresos/Egresos)</p>
                            </th>
                            <th class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-theme-xs uppercase">N° Cierre</p>
                            </th>
                            <th class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-theme-xs uppercase">Estado</p>
                            </th>
                            <th class="px-5 py-3 text-right sm:px-6 last:rounded-tr-xl">
                                <p class="font-semibold text-theme-xs uppercase">Operaciones</p>
                            </th>
                        </tr>
                    </thead>
                    
                    @forelse ($shift_cash as $shift)
                        @php
                            $startCm = $shift->cashMovementStart;
                            $endCm = $shift->cashMovementEnd;
                            $regId = $startCm?->cash_register_id;
                            $from = $startCm?->created_at ?? $shift->started_at;
                            $to = $endCm?->created_at ?? $shift->ended_at ?? now();

                            $excludeIds = array_values(array_filter([
                                $shift->cash_movement_end_id,
                            ]));

                            $shiftMovements = \App\Models\CashMovements::query()
                                ->with(['paymentConcept', 'details.paymentMethod'])
                                ->whereNotIn('id', $excludeIds)
                                ->where(function($q) use ($shift, $regId, $from, $to) {
                                    $q->where('shift_id', $shift->id);
                                    if ($regId && $from) {
                                        $q->orWhere(function($q2) use ($regId, $from, $to) {
                                            $q2->where('cash_register_id', $regId)
                                               ->whereBetween('created_at', [
                                                   \Carbon\Carbon::parse($from)->startOfSecond(),
                                                   \Carbon\Carbon::parse($to)->endOfSecond()
                                               ]);
                                        });
                                    }
                                })
                                ->orderBy('id')
                                ->get();

                            $conceptGroups = [];
                            foreach ($shiftMovements as $mov) {
                                $conceptName = $mov->paymentConcept?->description ?? 'Otros';
                                $conceptType = $mov->paymentConcept?->type ?? 'I';

                                if (!isset($conceptGroups[$conceptName])) {
                                    $conceptGroups[$conceptName] = [
                                        'name' => $conceptName,
                                        'type' => $conceptType,
                                        'methods' => []
                                    ];
                                }

                                if ($mov->details && $mov->details->count() > 0) {
                                    foreach ($mov->details as $detail) {
                                        $methodName = $detail->paymentMethod->name ?? ($detail->payment_method ?? 'Otros');
                                        $amount = (float) $detail->amount;

                                        if (!isset($conceptGroups[$conceptName]['methods'][$methodName])) {
                                            $conceptGroups[$conceptName]['methods'][$methodName] = 0;
                                        }
                                        $conceptGroups[$conceptName]['methods'][$methodName] += $amount;
                                    }
                                } elseif ((float) $mov->total > 0) {
                                    $methodName = 'Efectivo';
                                    if (!isset($conceptGroups[$conceptName]['methods'][$methodName])) {
                                        $conceptGroups[$conceptName]['methods'][$methodName] = 0;
                                    }
                                    $conceptGroups[$conceptName]['methods'][$methodName] += (float) $mov->total;
                                }
                            }

                            $getConceptIcon = function($name, $type) {
                                $nameLower = mb_strtolower($name);
                                if (str_contains($nameLower, 'apertura')) return '📻';
                                if (str_contains($nameLower, 'pago de cliente') || str_contains($nameLower, 'venta')) return '🛒';
                                if ($type === 'E' || str_contains($nameLower, 'egreso') || str_contains($nameLower, 'gasto')) return '⬇️';
                                return '⬆️';
                            };
                        @endphp

                        <tbody x-data="{ expanded: false }" class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-white/5 align-top">
                                
                                {{-- 0. ORDEN (BOTÓN DESPLEGABLE) --}}
                                <td class="px-3 py-4 text-center sticky-left">
                                    <button type="button" @click="expanded = !expanded"
                                        class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-[#124731] text-white transition hover:bg-[#0A2E1F] dark:bg-[#124731] dark:text-white">
                                        <i class="ri-add-line" x-show="!expanded"></i>
                                        <i class="ri-subtract-line" x-show="expanded" x-cloak></i>
                                    </button>
                                </td>

                                {{-- 1. FECHA INICIO --}}
                                <td class="px-5 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-800 dark:text-white">{{ \Carbon\Carbon::parse($shift->started_at)->format('d/m/Y') }}</span>
                                        <span class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($shift->started_at)->format('H:i:s A') }}</span>
                                    </div>
                                </td>

                                {{-- NUEVO: FECHA CIERRE --}}
                                <td class="px-5 py-4">
                                    @if($shift->ended_at)
                                        <div class="flex flex-col">
                                            <span class="font-bold text-gray-800 dark:text-white">{{ \Carbon\Carbon::parse($shift->ended_at)->format('d/m/Y') }}</span>
                                            <span class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($shift->ended_at)->format('H:i:s A') }}</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400 italic">-- En curso --</span>
                                    @endif
                                </td>

                                {{-- 2. N° APERTURA --}}
                                <td class="px-5 py-4">
                                    <x-ui.badge variant="light" color="info">{{ $shift->cashMovementStart?->movement?->number ?? '---' }}</x-ui.badge>
                                    @if($shift->cashMovementStart?->total)
                                        <div class="text-xs font-bold text-gray-600 mt-1">$ {{ number_format($shift->cashMovementStart->total, 2) }}</div>
                                    @endif
                                </td>

                                {{-- 3. DETALLE (INGRESOS/EGRESOS) --}}
                                <td class="px-5 py-4">
                                    @if(count($conceptGroups) > 0)
                                        <div class="flex flex-col gap-2.5 text-xs">
                                            @foreach($conceptGroups as $cName => $cData)
                                                @php
                                                    $icon = $getConceptIcon($cName, $cData['type']);
                                                    $isEgreso = ($cData['type'] === 'E' || str_contains(mb_strtolower($cName), 'egreso'));
                                                @endphp
                                                <div>
                                                    <div class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-1">
                                                        <span>{{ $icon }}</span>
                                                        <span>{{ $cName }}:</span>
                                                    </div>
                                                    <div class="pl-4 space-y-0.5 text-gray-600 dark:text-gray-300">
                                                        @foreach($cData['methods'] as $mName => $mAmount)
                                                            <div>
                                                                <span>{{ $mName }}:</span>
                                                                <span>{{ $isEgreso ? '-' : '' }}{{ number_format($mAmount, 2) }}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400 italic">Sin movimientos operativos</span>
                                    @endif
                                </td>

                                {{-- 4. N° CIERRE --}}
                                <td class="px-5 py-4">
                                    @if($shift->cashMovementEnd)
                                        <x-ui.badge variant="light" color="warning">{{ $shift->cashMovementEnd->movement->number ?? '---' }}</x-ui.badge>
                                        <div class="text-xs font-bold text-gray-600 mt-1">$ {{ number_format($shift->cashMovementEnd->total, 2) }}</div>
                                    @else
                                        <span class="text-xs text-gray-400 italic">Pendiente</span>
                                    @endif
                                </td>

                                {{-- 5. ESTADO --}}
                                <td class="px-5 py-4">
                                    @if(!$shift->ended_at)
                                        <x-ui.badge variant="solid" color="success" class="animate-pulse">EN CURSO</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="solid" color="secondary">CERRADO</x-ui.badge>
                                    @endif
                                </td>

                                {{-- 6. ACCIONES --}}
                                <td class="px-5 py-4 text-right">
                                    @php
                                        $productsSoldUrl = route('shift-cash.products-sold', $shift) . ($viewId ? '?view_id=' . urlencode($viewId) : '');
                                        $pdfTitle = $shift->cashMovementEnd ? 'PDF cierre de caja' : 'PDF del turno (en curso hasta ahora)';
                                    @endphp
                                    <div class="flex justify-end gap-2">
                                        @if($rowOperations->isNotEmpty())
                                            @foreach ($rowOperations as $op)
                                                @php $url = $resolveActionUrl($op->action, $shift, $op); @endphp
                                                @if ($op->action == 'shift-cash.print')
                                                    <x-ui.button
                                                        size="icon"
                                                        type="button"
                                                        @click="$dispatch('open-shift-cash-modal', { shiftId: {{ $shift->id }} })"
                                                        style="background-color: {{ $op->color }}; color: white;"
                                                        className="rounded-lg"
                                                        title="{{ $pdfTitle }}"
                                                    >
                                                        <i class="{{ $op->icon }}"></i>
                                                    </x-ui.button>
                                                @else
                                                    <x-ui.button
                                                        size="icon"
                                                        type="button"
                                                        href="{{ $url }}"
                                                        style="background-color: {{ $op->color }}; color: white;"
                                                        className="rounded-lg"
                                                    >
                                                        <i class="{{ $op->icon }}"></i>
                                                    </x-ui.button>
                                                @endif
                                            @endforeach
                                        @endif
                                        <a
                                            href="{{ $productsSoldUrl }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-slate-800 text-white shadow-theme-xs transition hover:bg-slate-900 dark:bg-slate-600 dark:hover:bg-slate-500"
                                            title="Ventas por producto (este turno)"
                                        >
                                            <i class="ri-price-tag-3-line text-lg"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>

                            {{-- PANEL DESPLEGABLE CON TU FORMATO EXACTO (Apertura y Cierre) --}}
                            <tr x-show="expanded" x-cloak class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/20">
                                <td colspan="8" class="px-5 py-6 sm:px-6">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 w-full max-w-5xl mx-auto">
                                        
                                        {{-- DATOS APERTURA --}}
                                        @php $movApertura = $shift->cashMovementStart?->movement; @endphp
                                        <div class="mx-auto w-full max-w-xl space-y-1 text-center text-gray-800 dark:text-gray-200">
                                            <p class="font-bold text-[#124731] uppercase text-xs mb-2">Información de Apertura</p>
                                            <div class="grid grid-cols-2 border-b border-gray-200 py-2 dark:border-gray-700">
                                                <span class="font-semibold">Persona</span>
                                                <span>{{ $movApertura?->person_name ?: '-' }}</span>
                                            </div>
                                            <div class="grid grid-cols-2 border-b border-gray-200 py-2 dark:border-gray-700">
                                                <span class="font-semibold">Responsable</span>
                                                <span>{{ $movApertura?->responsible_name ?: '-' }}</span>
                                            </div>
                                            <div class="grid grid-cols-2 border-b border-gray-200 py-2 dark:border-gray-700">
                                                <span class="font-semibold">Origen</span>
                                                <span>{{ $movApertura?->movementType?->description ?? '-' }} - {{ strtoupper(substr($movApertura?->documentType?->name ?? '', 0, 1)) }}{{ $movApertura?->salesMovement?->series ?? '' }}-{{ $movApertura?->number ?? '-' }}</span>
                                            </div>
                                        </div>

                                        {{-- DATOS CIERRE --}}
                                        @php $movCierre = $shift->cashMovementEnd?->movement; @endphp
                                        <div class="mx-auto w-full max-w-xl space-y-1 text-center text-gray-800 dark:text-gray-200">
                                            <p class="font-bold text-red-500 uppercase text-xs mb-2">Información de Cierre</p>
                                            @if($movCierre)
                                                <div class="grid grid-cols-2 border-b border-gray-200 py-2 dark:border-gray-700">
                                                    <span class="font-semibold">Persona</span>
                                                    <span>{{ $movCierre->person_name ?: '-' }}</span>
                                                </div>
                                                <div class="grid grid-cols-2 border-b border-gray-200 py-2 dark:border-gray-700">
                                                    <span class="font-semibold">Responsable</span>
                                                    <span>{{ $movCierre->responsible_name ?: '-' }}</span>
                                                </div>
                                                <div class="grid grid-cols-2 border-b border-gray-200 py-2 dark:border-gray-700">
                                                    <span class="font-semibold">Origen</span>
                                                    <span>{{ $movCierre->movementType?->description ?? '-' }} - {{ strtoupper(substr($movCierre->documentType?->name ?? '', 0, 1)) }}{{ $movCierre->salesMovement?->series ?? '' }}-{{ $movCierre->number ?? '-' }}</span>
                                                </div>
                                            @else
                                                <div class="py-10 text-gray-400 italic">El turno aún no ha sido cerrado.</div>
                                            @endif
                                        </div>

                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    @empty
                        <tbody>
                            <tr><td colspan="8" class="text-center py-8 text-gray-500">No hay turnos registrados</td></tr>
                        </tbody>
                    @endforelse
                </table>
            </div>

            {{-- PAGINACION --}}
            <div class="mt-4">
                {{ $shift_cash->links() }}
            </div>
        </x-common.component-card>

        {{-- MODAL --}}
        <x-ui.modal
            x-data="{ open: false }"
            @open-shift-cash-modal.window="open = true"
            @close-shift-cash-modal.window="open = false"
            :isOpen="false"
            :showCloseButton="false"
            class="max-w-4xl"
        >
            <form
                id="shift-print-form"
                method="GET"
                x-data="{ selectedShiftId: null }"
                @open-shift-cash-modal.window="selectedShiftId = $event.detail?.shiftId ?? null"
            >
                <div class="p-6 sm:p-7 border-b border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-[#124731] dark:text-emerald-400 flex items-center justify-center font-semibold">
                                <i class="ri-file-pdf-2-line text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Imprimir PDF del Turno de Caja</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Turno cerrado o en curso (incluye movimientos hasta ahora). Elige las secciones del PDF.</p>
                            </div>
                        </div>
                        <button type="button" @click="$dispatch('close-shift-cash-modal')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">
                            <i class="ri-close-line text-xl"></i>
                        </button>
                    </div>
                </div>

                <div class="py-2">
                    @include('shift_cash.modal_cash')
                </div>

                <div class="p-5 sm:px-7 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end gap-3 bg-gray-50/50 dark:bg-gray-900/40 rounded-b-2xl">
                    <x-ui.button
                        size="md"
                        variant="outline"
                        type="button"
                        class="h-11 px-5 rounded-xl border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                        @click="$dispatch('close-shift-cash-modal')"
                    >
                        <i class="ri-close-line"></i> <span>Cancelar</span>
                    </x-ui.button>
                    <x-ui.button
                        size="md"
                        variant="primary"
                        type="button"
                        class="h-11 px-6 rounded-xl font-medium shadow-md shadow-emerald-900/20 hover:shadow-emerald-900/30 transition-all flex items-center gap-2"
                        style="background-color: #124731;"
                        x-bind:disabled="!selectedShiftId"
                        @click="
                            if (!selectedShiftId) return;
                            const form = document.getElementById('shift-print-form');
                            const qs = form ? new URLSearchParams(new FormData(form)).toString() : '';
                            const base = '{{ url('/caja/turno-caja') }}/' + selectedShiftId + '/print';
                            window.open(base + (qs ? '?' + qs + '&inline=1' : '?inline=1'), '_blank');
                        "
                    >
                        <i class="ri-external-link-line text-base text-gray-100"></i> <span class="text-gray-100">Abrir PDF</span>
                    </x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection
