@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb pageTitle="Ver Movimiento" />

@php
    $cm = $movement->cashMovement;
    $totalAmount = $cm->details->sum('amount');
    $backUrl = route('petty-cash.index', array_merge(['cash_register_id' => $cash_register_id], !empty($viewId) ? ['view_id' => $viewId] : []));
@endphp

<x-ui.modal :isOpen="true" :showCloseButton="false" class="max-w-4xl">
    <div class="p-6 sm:p-8" data-back-url="{{ e($backUrl) }}" x-data="{ open: true }" x-init="$watch('open', value => { if (!value) window.location.href = $el.dataset.backUrl })" @keydown.escape.window="window.location.href = $el.dataset.backUrl">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-gray-100 pb-4 dark:border-gray-800">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FF4622]/10 text-[#C43B25] dark:bg-[#FF4622]/20">
                    <i class="ri-eye-line text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Movimiento #{{ $movement->number }}</h3>
                    <p class="text-sm text-gray-500">Caja: {{ $cash_register_id }}</p>
                </div>
            </div>

            <a href="{{ $backUrl }}" class="h-10 w-10 flex items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 transition-all hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300" aria-label="Cerrar">
                <i class="ri-close-line text-xl"></i>
            </a>
        </div>

        <div class="space-y-8">
            {{-- Información del movimiento --}}
            <div>
                <h3 class="text-base font-medium text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                    <i class="ri-file-list-3-line text-[#FF4622]"></i> Información del Movimiento
                </h3>
                <div class="bg-gray-50 rounded-xl p-5 border border-gray-100 dark:bg-white/[0.02] dark:border-gray-800 space-y-5">
                    <div class="col-span-full">
                        <label class="mb-1.5 block text-sm font-medium text-gray-600 dark:text-gray-400">Nota / Descripción</label>
                        <p class="h-11 flex items-center rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-dark-900 px-4 py-2.5 text-sm text-gray-800 dark:text-white/90">
                            {{ $movement->comment }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-600 dark:text-gray-400">Turno</label>
                            <p class="h-11 flex items-center rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-dark-900 px-4 py-2.5 text-sm text-gray-800 dark:text-white/90">
                                {{ $cm->shift->name ?? '—' }}
                            </p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-600 dark:text-gray-400">Concepto</label>
                            <p class="h-11 flex items-center rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-dark-900 px-4 py-2.5 text-sm text-gray-800 dark:text-white/90">
                                {{ $cm->paymentConcept->description ?? '—' }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-600 dark:text-gray-400">Tipo de documento</label>
                        <p class="h-11 flex items-center rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-dark-900 px-4 py-2.5 text-sm text-gray-800 dark:text-white/90">
                            {{ $movement->documentType->name ?? '—' }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Desglose de pagos (solo lectura) --}}
            <div>
                <h3 class="text-base font-medium text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                    <i class="ri-wallet-3-line text-[#FF4622]"></i> Desglose de Pagos
                </h3>

                <div class="space-y-4">
                    @forelse($cm->details as $detail)
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                                <div class="md:col-span-5">
                                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Método de Pago</label>
                                    <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $detail->payment_method_name ?? $detail->payment_method ?? '—' }}</p>
                                </div>
                                <div class="md:col-span-3">
                                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Monto (S/.)</label>
                                    <p class="text-lg font-bold text-gray-800 dark:text-white">{{ number_format($detail->amount, 2) }}</p>
                                </div>
                                <div class="md:col-span-4">
                                    @if($detail->card_id || $detail->number)
                                        <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Detalle</label>
                                        <p class="text-sm text-gray-600 dark:text-gray-300">
                                            @if($detail->card_id && ($detail->card ?? null))
                                                {{ $detail->card }}@if($detail->number) · {{ $detail->number }}@endif
                                            @elseif($detail->bank_id && ($detail->bank ?? null))
                                                {{ $detail->bank }}@if($detail->number) · {{ $detail->number }}@endif
                                            @elseif($detail->digital_wallet_id && ($detail->digital_wallet ?? null))
                                                {{ $detail->digital_wallet }}@if($detail->number) · {{ $detail->number }}@endif
                                            @else
                                                {{ $detail->number ?? '—' }}
                                            @endif
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 py-2">Sin detalles de pago.</p>
                    @endforelse
                </div>

                <div class="w-fit ml-auto mt-4 flex items-center gap-3 bg-[#FF4622]/10 dark:bg-brand-900/20 px-5 py-2 rounded-lg border border-brand-100 dark:border-brand-800 shadow-sm">
                    <span class="text-xs text-[#C43B25] uppercase font-bold tracking-wider">Total</span>
                    <span class="text-xl font-black text-gray-900 dark:text-white tracking-tight">
                        S/. {{ number_format($totalAmount, 2) }}
                    </span>
                </div>
            </div>

            <div class="flex gap-3 pt-6 border-t border-gray-100 dark:border-gray-800">
                <a href="{{ $backUrl }}" class="inline-flex items-center justify-center font-medium gap-2 rounded-xl transition px-5 py-3.5 text-sm bg-[#FF4622] text-white shadow-theme-xs hover:bg-[#C43B25] flex-1 sm:flex-none">
                    <i class="ri-arrow-left-line mr-2"></i> Volver
                </a>
            </div>
        </div>
    </div>
</x-ui.modal>
@endsection
