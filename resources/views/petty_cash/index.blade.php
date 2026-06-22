@extends('layouts.app')

@php
    use Illuminate\Support\HtmlString;
    use Illuminate\Support\Js;
    use Illuminate\Support\Facades\Route;
    use \Illuminate\Support\Str;

    // --- ICONOS ---
    $SearchIcon = new HtmlString(
        '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8" /><path d="M20 20L16.5 16.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>',
    );
    $ClearIcon = new HtmlString(
        '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>',
    );

    $viewId = request('view_id');

    $operacionesCollection = collect($operaciones ?? []);
    $rawTopOperations = $operacionesCollection->where('type', 'T');
    $topOperations = $rawTopOperations->filter(function ($op) use ($hasOpening) {
        $name = strtolower($op->name ?? '');
        $action = strtolower($op->action ?? '');

        $esApertura = str_contains($name, 'apertura') || str_contains($action, 'apertura');
        $esMovimiento = str_contains($name, 'ingreso') || str_contains($name, 'egreso') ||
            str_contains($name, 'cierre') || str_contains($name, 'cerrar') ||
            str_contains($action, 'ingreso') || str_contains($action, 'egreso') ||
            str_contains($action, 'cierre');

        if ($hasOpening) {
            if ($esApertura)
                return false;
        } else {
            if ($esMovimiento)
                return false;
        }

        return true;
    });

    $rowOperations = $operacionesCollection->where('type', 'R');

    // --- Helpers de URL y Color ---
    $resolveActionUrl = function ($action, $movement = null, $operation = null) use ($viewId, $selectedBoxId) {
        if (!$action) {
            return '#';
        }

        if (str_starts_with($action, '/') || str_starts_with($action, 'http')) {
            $url = $action;
        } else {
            $routeCandidates = [$action];
            if (Str::startsWith($action, 'admin.')) {
                $routeCandidates[] = Str::after($action, 'admin.');
            }
            $routeCandidates = array_merge(
                $routeCandidates,
                array_map(fn($name) => $name . '.index', $routeCandidates)
            );

            $routeName = null;
            foreach ($routeCandidates as $candidate) {
                if (Route::has($candidate)) {
                    $routeName = $candidate;
                    break;
                }
            }

            $url = '#';
            if ($routeName) {
                try {
                    if ($movement) {
                        $url = route($routeName, ['cash_register_id' => $selectedBoxId, 'movement' => $movement->id]);
                    } else {
                        $url = route($routeName, ['cash_register_id' => $selectedBoxId]);
                    }
                } catch (\Exception $e) {
                    try {
                        $url = route($routeName, ['cash_register_id' => $selectedBoxId]);
                    } catch (\Exception $e2) {
                        try {
                            $url = $movement ? route($routeName, $movement) : route($routeName);
                        } catch (\Exception $e3) {
                            $url = '#';
                        }
                    }
                }
            }
        }

        $targetViewId = $viewId;
        if ($operation && !empty($operation->view_id_action)) {
            $targetViewId = $operation->view_id_action;
        }

        if ($targetViewId && $url !== '#') {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . 'view_id=' . urlencode($targetViewId);
        }

        return $url;
    };

    $resolveTextColor = function ($operation) {
        $action = $operation->action ?? '';
        if (str_contains($action, 'create') || str_contains($action, 'store')) {
            return '#111827';
        }
        return '#FFFFFF';
    };
@endphp

@section('content')
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    <div x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            formConcept: '{{ old('comment') }}',
            formConceptId: '{{ old('payment_concept_id') }}',
            formDocId: '{{ old('document_type_id') }}',
            formAmount: '{{ old('amount') }}',
            ingresoId: '{{ $ingresoDocId }}',
            refIngresoId: '{{ $ingresoDocId }}',
            refEgresoId: '{{ $egresoDocId }}',
            listIngresos: {{ Js::from($conceptsIngreso) }},
            listEgresos: {{ Js::from($conceptsEgreso) }},
            currentConcepts: [],
            currentBalance: {{ $currentBalance }},
            currentTurnBreakdown: {{ Js::from($currentTurnBreakdown ?? []) }},
            currentTurnSummary: {{ Js::from($currentTurnSummary ?? ['ventas' => 0, 'ingresos' => 0, 'egresos' => 0]) }},
            lastClosingTotal: {{ $lastClosingTotal }},
            lastClosingBreakdown: {{ Js::from($lastClosingBreakdown ?? []) }},
            turnSummary: {{ Js::from($turnSummary ?? ['ventas' => 0, 'ingresos' => 0, 'egresos' => 0]) }}
            }" @open-movement-modal.window="
                let conceptText = $event.detail.concept || ''; 
                let receivedId = String($event.detail.docId);

                // Resetear formulario
                formConcept = conceptText;
                formAmount = ''; 
                formConceptId = ''; 
                formDocId = receivedId;

                // Filtrar listas según si es Ingreso o Egreso
                if (receivedId === refIngresoId) {
                    if (conceptText === 'Apertura de caja') {
                        currentConcepts = listIngresos.filter(c => c.description.toLowerCase().includes('apertura'));
                        if (currentConcepts.length > 0) formConceptId = currentConcepts[0].id;
                    } else {
                        currentConcepts = listIngresos.filter(c => !c.description.toLowerCase().includes('apertura'));
                    }
                } else {
                    if (conceptText === 'Cierre de caja') {
                        currentConcepts = listEgresos.filter(c => c.description.toLowerCase().includes('cierre'));
                        if (currentConcepts.length > 0) formConceptId = currentConcepts[0].id;
                    } else {
                        currentConcepts = listEgresos.filter(c => !c.description.toLowerCase().includes('cierre'));
                    }
                }

                // Forzar actualización del componente hijo (combobox)
                $dispatch('update-combobox-options', { name: 'payment_concept_id', options: Alpine.raw(currentConcepts) });
                // Prellenar monto de apertura con el total del último cierre
                if (conceptText === 'Apertura de caja') {
                    $nextTick(() => $dispatch('fill-apertura-amount', { amount: lastClosingTotal }));
                } else if (conceptText !== 'Cierre de caja') {
                    // Ingreso o Egreso normal: desglose en 0
                    $nextTick(() => $dispatch('reset-payment-rows'));
                }
                open = true; 
            ">

        <x-common.page-breadcrumb pageTitle="Movimientos de Caja" />

        <x-common.component-card title="Gestión de Movimientos" desc="Control de ingresos, egresos y traslados de fondos.">

            @php
                $selectFilterClass = 'dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 cursor-pointer';
                $inputFilterClass = 'dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
                $labelFilterClass = 'mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
                $pettyCashClearUrl = route('petty-cash.index', array_filter(['cash_register_id' => $selectedBoxId, 'view_id' => $viewId ?? null]));
            @endphp

            <div class="flex flex-row gap-5">
                <form method="GET" class="w-full">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif

                    <div
                        class="rounded-x from-slate-50/90 to-white p-4 dark:border-gray-800 dark:from-gray-900/50 dark:to-gray-900/30 sm:p-5 space-y-4">

                        {{-- Fila 1: Per page + Buscar + Caja + Turno --}}
                        <div class="grid grid-cols-2 gap-3 md:grid-cols-12 md:items-end md:gap-4">
                            <div class="col-span-1 md:col-span-2">
                                <label class="{{ $labelFilterClass }}">Por página</label>
                                <x-ui.per-page-selector :per-page="$perPage" :submit-form="true" class="!w-full" />
                            </div>
                            <div class="col-span-1 md:col-span-4">
                                <label class="{{ $labelFilterClass }}">Buscar</label>
                                <div class="relative">
                                    <span
                                        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                        <i class="ri-search-line"></i>
                                    </span>
                                    <input type="text" name="search" value="{{ request('search') }}"
                                        placeholder="Buscar movimiento..."
                                        class="{{ $inputFilterClass }} pl-10 placeholder:text-gray-400 dark:placeholder:text-white/30" />
                                </div>
                            </div>
                            <div class="col-span-1 md:col-span-3">
                                <label class="{{ $labelFilterClass }}">Caja</label>
                                <select name="cash_register_id" onchange="this.form.submit()"
                                    class="{{ $selectFilterClass }}">
                                    @foreach ($cashRegisters as $register)
                                        <option value="{{ $register->id }}" @selected((int) $selectedBoxId === (int) $register->id)>
                                            {{  $register->number }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-1 md:col-span-3">
                                <label class="{{ $labelFilterClass }}">Turno</label>
                                <select name="cash_shift_relation_id" class="{{ $selectFilterClass }}">
                                    <option value="">Todos</option>
                                    @foreach ($cashShiftSessions ?? [] as $csr)
                                        @php
                                            $shiftName = $csr->cashMovementStart?->shift?->name ?? 'Turno';
                                            $started = $csr->started_at ? \Illuminate\Support\Carbon::parse($csr->started_at)->format('Y-m-d H:i:s') : '';
                                            $csrStatus = (string) ($csr->status ?? '');
                                            $statusLabel = $csrStatus === '1' ? 'En curso' : 'Cerrado';
                                            $csrLabel = $shiftName . ($started ? ' | ' . $started : '') . ' (' . $statusLabel . ')';
                                        @endphp
                                        <option value="{{ $csr->id }}" @selected((int) ($selectedCashShiftRelationId ?? 0) === (int) $csr->id)>
                                            {{ $csrLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Fila 2: Concepto + Tipo movimiento + Desde + Hasta + Botones --}}
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end md:gap-4">
                            <div class="col-span-1 md:col-span-3">
                                <x-form.select.combobox :options="$paymentConceptFilterOptions" label="Concepto de pago"
                                    name="payment_concept_id" x-on:click="clear()" :value="$selectedPaymentConceptFilterId"
                                    displayField="description" placeholder="Seleccione concepto" />
                            </div>
                            <div class="col-span-1 md:col-span-3">
                                <x-form.select.combobox :options="$documentTypeFilterOptions" label="Tipo de movimiento"
                                    name="document_type_id" x-on:click="clear()" :value="$selectedDocumentTypeId"
                                    displayField="name" placeholder="Seleccione tipo" />
                            </div>
                            <div class="col-span-1 md:col-span-2">
                                <label class="{{ $labelFilterClass }}">Desde</label>
                                <div class="relative">
                                    <span
                                        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                        <i class="ri-calendar-line"></i>
                                    </span>
                                    <input type="date" name="date_from" value="{{ old('date_from', $dateFrom ?? '') }}"
                                        class="{{ $inputFilterClass }} pl-10" />
                                </div>
                            </div>
                            <div class="col-span-1 md:col-span-2">
                                <label class="{{ $labelFilterClass }}">Hasta</label>
                                <div class="relative">
                                    <span
                                        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                        <i class="ri-calendar-line"></i>
                                    </span>
                                    <input type="date" name="date_to" value="{{ old('date_to', $dateTo ?? '') }}"
                                        class="{{ $inputFilterClass }} pl-10" />
                                </div>
                            </div>
                            <div class="col-span-1 md:col-span-2">
                                <div class="flex flex-col gap-2 md:flex-row md:items-end">
                                    <x-ui.button size="md" variant="primary" type="submit"
                                        class="h-11 w-full md:flex-1 px-4 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95"
                                        style="background-color: #C43B25; border-color: #C43B25;">
                                        <i class="ri-search-line text-gray-100"></i>
                                    </x-ui.button>
                                    <x-ui.link-button size="md" variant="outline" href="{{ $pettyCashClearUrl }}"
                                        class="h-11 w-full md:flex-1 px-4 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                                        <i class="ri-refresh-line"></i>
                                        <span class="font-medium">Limpiar</span>
                                    </x-ui.link-button>
                                </div>
                            </div>
                        </div>

                        {{-- Fila 3: Botones de acción --}}
                        <div
                            class="flex flex-wrap items-center gap-2 border-t border-gray-100 pt-4 dark:border-gray-700/80">
                            @if ($topOperations->isNotEmpty())
                                @foreach ($topOperations as $operation)
                                    @php
                                        $topTextColor = $resolveTextColor($operation);
                                        $topColor = $operation->color ?: '#3B82F6';
                                        $topStyle = "background-color: {$topColor}; color: {$topTextColor};";
                                        $topAction = $operation->action ?? '';
                                        $topNameLower = mb_strtolower($operation->name ?? '');
                                        $topActionLower = mb_strtolower($topAction);
                                        $isIncomeOp = Str::contains($topNameLower, ['ingreso', 'income']) || Str::contains($topActionLower, ['ingreso', 'income']);
                                        $isExpenseOp = Str::contains($topNameLower, ['egreso', 'gasto', 'expense']) || Str::contains($topActionLower, ['egreso', 'gasto', 'expense']);
                                        $isOpenOp = Str::contains($topNameLower, ['apertura', 'open', 'abrir']) || Str::contains($topActionLower, ['apertura', 'open', 'abrir']);
                                        $isCloseOp = Str::contains($topNameLower, ['cierre', 'cerrar', 'close']) || Str::contains($topActionLower, ['cierre', 'cerrar', 'close']);
                                        $isCreateLike = Str::contains($topActionLower, ['create', 'store']);
                                        $isPettyCashModalOp = ($isCreateLike || $isIncomeOp || $isExpenseOp || $isOpenOp);
                                        $modalDocId = ($isExpenseOp || $isCloseOp) ? $egresoDocId : $ingresoDocId;
                                        $modalConcept = $isOpenOp ? 'Apertura de caja' : ($isCloseOp ? 'Cierre de caja' : '');
                                        $topActionUrl = $isPettyCashModalOp ? '#' : $resolveActionUrl($topAction, null, $operation);
                                        if ($isCloseOp) {
                                            $topActionUrl = route('petty-cash.cierre', ['cash_register_id' => $selectedBoxId, 'view_id' => $viewId]);
                                        }
                                    @endphp
                                    @if ($isPettyCashModalOp)
                                        <x-ui.button size="md" variant="primary" type="button" style="{{ $topStyle }}"
                                            @click="$dispatch('open-movement-modal', { concept: '{{ $modalConcept }}', docId: '{{ $modalDocId }}' })">
                                            <i class="{{ $operation->icon }}"></i>
                                            <span>{{ $operation->name }}</span>
                                        </x-ui.button>
                                    @else
                                        <x-ui.link-button size="md" variant="primary" style="{{ $topStyle }}"
                                            href="{{ $topActionUrl }}">
                                            <i class="{{ $operation->icon }}"></i>
                                            <span>{{ $operation->name }}</span>
                                        </x-ui.link-button>
                                    @endif
                                @endforeach
                            @else
                                @if (!$hasOpening)
                                    <x-ui.button size="md" variant="primary" style="background-color: #3B82F6; color: #FFFFFF;"
                                        @click="$dispatch('open-movement-modal', { concept: 'Apertura de caja', docId: '{{ $ingresoDocId }}' })">
                                        <i class="ri-key-2-line"></i>
                                        <span>Aperturar Caja</span>
                                    </x-ui.button>
                                @else
                                    <x-ui.button size="md" variant="primary" style="background-color: #00A389; color: #FFFFFF;"
                                        @click="$dispatch('open-movement-modal', { concept: '', docId: '{{ $ingresoDocId }}' })">
                                        <i class="ri-add-line"></i>
                                        <span>Ingreso</span>
                                    </x-ui.button>
                                    <x-ui.button size="md" variant="primary"
                                        style="background-color: #EF4444; color: #FFFFFF; border: none;"
                                        @click="$dispatch('open-movement-modal', { concept: '', docId: '{{ $egresoDocId }}' })">
                                        <i class="ri-subtract-line"></i>
                                        <span>Egreso</span>
                                    </x-ui.button>
                                    <x-ui.link-button size="md" style="background-color: #FACC15; color: #111827;"
                                        href="{{ route('petty-cash.cierre', ['cash_register_id' => $selectedBoxId, 'view_id' => $viewId]) }}">
                                        <i class="ri-lock-2-line"></i>
                                        <span>Cerrar</span>
                                    </x-ui.link-button>
                                @endif
                            @endif
                        </div>

                    </div>
                </form>
            </div>

            {{-- TABLA --}}
            <div
                class="mt-4 w-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full min-w-0">
                    <thead class="bg-[#FF4622] text-white shadow-sm">
                        <tr>
                            <th class="w-14 px-3 py-3.5 text-center first:rounded-tl-xl">
                                <span class="text-xs font-semibold uppercase tracking-wider">Orden</span>
                            </th>
                            <th class="min-w-0 px-3 py-3.5 text-left">
                                <span class="text-xs font-semibold uppercase tracking-wider truncate block">Número</span>
                            </th>
                            <th class="min-w-0 px-3 py-3.5 text-left">
                                <span class="text-xs font-semibold uppercase tracking-wider truncate block">Tipo</span>
                            </th>
                            <th class="min-w-0 px-3 py-3.5 text-left">
                                <span class="text-xs font-semibold uppercase tracking-wider truncate block">Concepto</span>
                            </th>
                            <th class="min-w-0 px-3 py-3.5 text-left">
                                <span class="text-xs font-semibold uppercase tracking-wider truncate block">Total
                                    (S/.)</span>
                            </th>
                            <th class="hidden lg:table-cell min-w-0 px-3 py-3.5 text-left">
                                <span class="text-xs font-semibold uppercase tracking-wider truncate block">Caja</span>
                            </th>
                            <th class="hidden xl:table-cell min-w-0 px-3 py-3.5 text-left">
                                <span class="text-xs font-semibold uppercase tracking-wider truncate block">Turno</span>
                            </th>
                            <th class="px-6 py-3.5 text-center">
                                <span class="text-xs font-semibold uppercase tracking-wider truncate block">Métodos de
                                    pago</span>
                            </th>
                            <th class="px-6 py-3.5 text-center">
                                <span
                                    class="text-xs font-semibold uppercase tracking-wider truncate block">Operaciones</span>
                            </th>
                        </tr>
                    </thead>
                    @forelse ($movements as $movement)
                            @php
                                $docName = $movement->documentType?->name ?? 'General';
                                $conceptName = $movement->cashMovement?->paymentConcept?->description ?? '-';
                                $isIngreso = stripos($docName, 'ingreso') !== false;
                                $movementStatus = (string) ($movement->status ?? '1');
                                $isActive = in_array($movementStatus, ['1', 'A'], true);
                                $paymentSummary = collect($movement->cashMovement?->details ?? [])
                                    ->groupBy('payment_method')
                                    ->map(fn($items, $method) => trim(($method ?: 'Metodo') . ': ' . number_format($items->sum('amount'), 2)))
                                    ->values()
                                    ->implode(' | ');
                            @endphp
                            <tbody x-data="{ expanded: false }" class="group/row">
                                <tr
                                    class="border-b border-gray-100 bg-white dark:border-gray-800 dark:bg-white/[0.03] transition-colors hover:bg-sky-50/50 dark:hover:bg-white/5 align-middle even:bg-gray-50/50 dark:even:bg-white/[0.02]">
                                    <td class="px-3 py-3 text-center">
                                        <button type="button" @click="expanded = !expanded"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#FF4622] text-white transition hover:bg-[#C43B25] hover:scale-110 active:scale-95 dark:bg-[#FF4622] dark:text-white">
                                            <i class="ri-add-line text-sm" x-show="!expanded"></i>
                                            <i class="ri-subtract-line text-sm" x-show="expanded"></i>
                                        </button>
                                    </td>
                                    <td class="px-3 py-3 align-middle min-w-0 overflow-hidden">
                                        <p class="font-semibold text-gray-800 text-sm dark:text-white/90 truncate"
                                            title="{{ $movement->number }}">{{ $movement->number }}</p>
                                    </td>
                                    <td class="px-3 py-3 align-middle min-w-0 overflow-hidden">
                                        <x-ui.badge variant="light" color="{{ $isIngreso ? 'success' : 'error' }}"
                                            class="text-[11px]">{{ $isIngreso ? 'Ingreso' : 'Egreso' }}</x-ui.badge>
                                    </td>
                                    <td class="px-3 py-3 align-middle min-w-0 overflow-hidden">
                                        <x-ui.badge variant="light" :color="
                                                            str_contains(strtolower($conceptName), 'apertura') ? 'blue' :
                        (str_contains(strtolower($conceptName), 'cierre') ? 'danger' : 'warning')
                                                        "
                                            class="text-[11px] truncate max-w-full inline-block" title="{{ $conceptName }}">
                                            {{ $conceptName }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="px-3 py-3 align-middle min-w-0 overflow-hidden">
                                        <p class="font-bold text-gray-900 text-sm tabular-nums dark:text-white">
                                            {{ number_format($movement->cashMovement?->total ?? 0, 2) }}</p>
                                    </td>
                                    <td class="hidden lg:table-cell px-3 py-3 align-middle min-w-0 overflow-hidden">
                                        <p class="text-gray-600 text-xs dark:text-gray-400 capitalize">
                                            {{ $movement->cashMovement?->cash_register ?: '-' }}</p>
                                    </td>
                                    <td class="hidden xl:table-cell px-3 py-3 align-middle min-w-0 overflow-hidden">
                                        <p class="text-gray-600 text-xs dark:text-gray-400 capitalize">
                                            {{ $movement->cashMovement?->shift?->name ?: '-' }}</p>
                                    </td>
                                    <td class="px-6 py-3 align-middle min-w-0 overflow-hidden">
                                        <p class="text-gray-700 text-xs text-center font-medium dark:text-gray-300 truncate"
                                            title="{{ $paymentSummary }}">
                                            {{ $paymentSummary ?: '-' }}
                                        </p>
                                    </td>
                                    <td class="px-6 py-3 align-middle">
                                        <div class="flex items-center justify-end gap-2">
                                            @if ($rowOperations->isNotEmpty())
                                                @foreach ($rowOperations as $operation)
                                                    @php
                                                        $action = $operation->action ?? '';
                                                        $isDelete = str_contains($action, 'destroy');
                                                        $actionUrl = $resolveActionUrl($action, $movement, $operation);
                                                        $textColor = $resolveTextColor($operation);
                                                        $buttonColor = $operation->color ?: '#3B82F6';
                                                        $buttonStyle = "background-color: {$buttonColor}; color: {$textColor};";
                                                        $variant = $isDelete ? 'eliminate' : (str_contains($action, 'edit') ? 'edit' : 'primary');
                                                    @endphp
                                                    @if ($isDelete)
                                                        <form method="POST" action="{{ $actionUrl }}" class="relative group js-swal-delete"
                                                            data-swal-title="Eliminar movimiento?"
                                                            data-swal-text="Se eliminara {{ $movement->number }}. Esta accion no se puede deshacer."
                                                            data-swal-confirm="Si, eliminar" data-swal-cancel="Cancelar"
                                                            data-swal-confirm-color="#ef4444" data-swal-cancel-color="#6b7280">
                                                            @csrf
                                                            @method('DELETE')
                                                            @if ($viewId)
                                                                <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                            @endif
                                                            <x-ui.button size="icon" variant="{{ $variant }}" type="submit"
                                                                className="rounded-xl" style="{{ $buttonStyle }}"
                                                                aria-label="{{ $operation->name }}">
                                                                <i class="{{ $operation->icon }}"></i>
                                                            </x-ui.button>
                                                            <span
                                                                class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50"
                                                                style="transition-delay: 0.5s;">{{ $operation->name }}</span>
                                                        </form>
                                                    @else
                                                        <div class="relative group">
                                                            <x-ui.link-button size="icon" variant="{{ $variant }}" href="{{ $actionUrl }}"
                                                                className="rounded-xl" style="{{ $buttonStyle }}"
                                                                aria-label="{{ $operation->name }}">
                                                                <i class="{{ $operation->icon }}"></i>
                                                            </x-ui.link-button>
                                                            <span
                                                                class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50"
                                                                style="transition-delay: 0.5s;">{{ $operation->name }}</span>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                <tr x-show="expanded" x-cloak
                                    class="bg-gray-50/70 dark:bg-gray-800/40 border-b border-gray-100 dark:border-gray-800">
                                    <td colspan="10" class="px-5 py-3 sm:px-6">
                                        <div class="grid w-full grid-cols-5 gap-x-6 gap-y-3">
                                            {{-- Fila 1 --}}
                                            <div>
                                                <div
                                                    class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                                    Fecha</div>
                                                <div class="mt-0.5 text-sm text-gray-800 dark:text-white/90 whitespace-nowrap">
                                                    {{ $movement->moved_at ? $movement->moved_at->format('d/m/Y H:i') : '-' }}
                                                </div>
                                            </div>
                                            <div>
                                                <div
                                                    class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                                    Usuario</div>
                                                <div class="mt-0.5 text-sm text-gray-800 dark:text-white/90 whitespace-nowrap">
                                                    {{ $movement->user_name ?: '-' }}
                                                </div>
                                            </div>
                                            <div>
                                                <div
                                                    class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                                    Persona</div>
                                                <div class="mt-0.5 text-sm text-gray-800 dark:text-white/90 whitespace-nowrap">
                                                    {{ $movement->person_name ?: '-' }}
                                                </div>
                                            </div>
                                            <div>
                                                <div
                                                    class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                                    Moneda</div>
                                                <div class="mt-0.5 text-sm text-gray-800 dark:text-white/90 whitespace-nowrap">
                                                    {{ $movement->cashMovement?->currency ?? 'PEN' }}
                                                </div>
                                            </div>
                                            <div>
                                                <div
                                                    class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                                    T. cambio</div>
                                                <div class="mt-0.5 text-sm text-gray-800 dark:text-white/90 whitespace-nowrap">
                                                    {{ number_format((float) ($movement->cashMovement?->exchange_rate ?? 1), 3) }}
                                                </div>
                                            </div>
                                            {{-- Fila 2 --}}
                                            <div>
                                                <div
                                                    class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                                    Responsable</div>
                                                <div class="mt-0.5 text-sm text-gray-800 dark:text-white/90 whitespace-nowrap">
                                                    {{ $movement->responsible_name ?: '-' }}
                                                </div>
                                            </div>
                                            <div>
                                                <div
                                                    class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                                    Caja</div>
                                                <div class="mt-0.5 text-sm text-gray-800 dark:text-white/90 whitespace-nowrap">
                                                    {{ $movement->cashMovement?->cash_register ?: '-' }}
                                                </div>
                                            </div>
                                            <div>
                                                <div
                                                    class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                                    Turno</div>
                                                <div class="mt-0.5 text-sm text-gray-800 dark:text-white/90 whitespace-nowrap">
                                                    {{ $movement->cashMovement?->shift?->name ?: '-' }}
                                                </div>
                                            </div>
                                            <div>
                                                <div
                                                    class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                                    Comentario</div>
                                                <div class="mt-0.5 text-sm text-gray-800 dark:text-white/90">
                                                    {{ Str::limit($movement->comment ?? '-', 40) }}
                                                </div>
                                            </div>
                                            <div>
                                                <div
                                                    class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                                    Desglose pagos</div>
                                                <div class="mt-0.5 text-sm text-gray-800 dark:text-white/90">
                                                    @php $details = $movement->cashMovement?->details ?? collect(); @endphp
                                                    @forelse ($details as $d)
                                                        <span class="inline-block mr-2">{{ ($d->payment_method ?: 'Otro') }}: S/
                                                            {{ number_format($d->amount, 2) }}</span>
                                                    @empty
                                                        -
                                                    @endforelse
                                                </div>
                                            </div>
                                            {{-- Fila 3: Origen --}}
                                            <div class="col-span-5">
                                                <div
                                                    class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                                    Origen</div>
                                                <div class="mt-0.5 text-sm text-gray-800 dark:text-white/90">
                                                    {{ $movement->movement?->movementType?->description ?? $movement->documentType?->name ?? '-' }}
                                                    –
                                                    {{ strtoupper(substr($movement->documentType?->name ?? '', 0, 1)) }}{{ $movement->movement?->salesMovement?->series ?? '' }}-{{ $movement->number }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                    @empty
                        <tbody>
                            <tr>
                                <td colspan="10" class="px-6 py-16 text-center">
                                    <div
                                        class="mx-auto flex max-w-sm flex-col items-center gap-3 text-gray-500 dark:text-gray-400">
                                        <div
                                            class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                            <i class="ri-inbox-line text-xl"></i>
                                        </div>
                                        <p class="text-sm font-medium">
                                            @if(!$lastOpeningMovement)
                                                Realice una apertura de caja para comenzar a registrar movimientos.
                                            @elseif(request('search'))
                                                No hay movimientos que coincidan con la búsqueda.
                                            @else
                                                No hay movimientos en el turno actual.
                                            @endif
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    @endforelse
                </table>
            </div>
            <div class="mt-4">{{ $movements->links() }}</div>

        </x-common.component-card>

        <x-ui.modal x-data="{}" x-show="open" x-cloak class="max-w-3xl z-[9999]" :showCloseButton="true">
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.3em] text-gray-400">
                            <span x-text="formDocId == ingresoId ? 'Ingreso' : 'Egreso'"></span>
                        </p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-800 dark:text-white/90"
                            x-text="formConcept === 'Apertura de caja' ? 'Registrar Apertura de caja' : (formConcept === 'Cierre de caja' ? 'Registrar Cierre de caja' : (formDocId == ingresoId ? 'Registrar Ingreso' : 'Registrar Egreso'))">
                        </h3>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl transition-colors duration-300"
                        :class="formDocId == ingresoId ? 'bg-[#FF4622]/10 text-[#FF4622]' : 'bg-red-50 text-red-500'">
                        <i class="text-xl" :class="formDocId == ingresoId ? 'ri-add-line' : 'ri-subtract-line'"></i>
                    </div>
                </div>

                <form method="POST" action="{{ route('petty-cash.store', ['cash_register_id' => $selectedBoxId]) }}"
                    class="space-y-6">
                    @csrf
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    <input type="hidden" name="document_type_id" x-model="formDocId">
                    <input type="hidden" name="cash_register_id" value="{{ $selectedBoxId }}">

                    @include('petty_cash._form', ['movement' => null])

                    <div class="flex flex-wrap gap-3">
                        <x-ui.button type="submit" size="md" variant="primary">
                            <i class="ri-save-line"></i><span>Guardar</span>
                        </x-ui.button>
                        <x-ui.button type="button" size="md" variant="outline" @click="open = false">
                            <i class="ri-close-line"></i><span>Cancelar</span>
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </x-ui.modal>

    </div>
@endsection
