@extends('layouts.app')

@section('content')
@php
    $wm = $warehouseMovement;
    $movement = $wm->movement;
    $branch = $wm->branch ?? $movement->branch;
    $statusLabel = $wm->status === 'A' ? 'Anulado' : ($wm->status === 'C' ? 'Cerrado' : 'Activo');
    $statusColor = $wm->status === 'A' ? 'danger' : ($wm->status === 'C' ? 'success' : 'info');
    $indexUrl = route('warehouse_movements.index', request('view_id') ? ['view_id' => request('view_id')] : []);
@endphp

<x-common.page-breadcrumb :pageTitle="$title ?? 'Editar Movimiento de Almacén'" />

<x-ui.modal
    x-data="{ 
        open: true,
        redirectToIndex() {
            window.location.href = '{{ $indexUrl }}';
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
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Editar Movimiento #{{ $movement->number ?? '—' }}</h3>
<p class="text-sm text-gray-500">
                                        {{ $movement->movementType->description ?? '—' }} · {{ $movement->moved_at ? $movement->moved_at->format('d/m/Y H:i') : '—' }} · {{ $branch->legal_name ?? $branch->name ?? '—' }}
                                    </p>
                </div>
            </div>

            <button type="button" @click="redirectToIndex()" class="h-10 w-10 flex items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 transition-all">
                <i class="ri-close-line text-xl"></i>
            </button>
        </div>

        @if ($errors->any())
            <div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 dark:bg-red-900/20 dark:border-red-800">
                <div class="flex items-center gap-2 text-red-700 dark:text-red-400 font-bold mb-2">
                    <i class="ri-error-warning-line"></i>
                    <span>Errores de validación</span>
                </div>
                <ul class="list-disc list-inside text-sm text-red-600 dark:text-red-300 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('warehouse_movements.update', ['warehouseMovement' => $wm->id]) }}" class="space-y-8">
            @csrf
            @method('PUT')

            <div class="space-y-8">
                {{-- Información del movimiento (solo lectura) --}}
                <div>
                    <h3 class="text-base font-medium text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                        <i class="ri-file-list-3-line text-[#FF4622]"></i> Información del Movimiento
                    </h3>
                    <div class="bg-gray-50 rounded-xl p-5 border border-gray-100 dark:bg-white/[0.02] dark:border-gray-800 space-y-5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-600 dark:text-gray-400">Número</label>
                                <p class="h-11 flex items-center rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-dark-900 px-4 py-2.5 text-sm text-gray-800 dark:text-white/90">{{ $movement->number ?? '—' }}</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-600 dark:text-gray-400">Tipo de movimiento</label>
                                <p class="h-11 flex items-center rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-dark-900 px-4 py-2.5 text-sm text-gray-800 dark:text-white/90">{{ $movement->movementType->description ?? '—' }}</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-600 dark:text-gray-400">Tipo de documento</label>
                                <p class="h-11 flex items-center rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-dark-900 px-4 py-2.5 text-sm text-gray-800 dark:text-white/90">{{ $movement->documentType->name ?? '—' }}</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-600 dark:text-gray-400">Sucursal</label>
                                <p class="h-11 flex items-center rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-dark-900 px-4 py-2.5 text-sm text-gray-800 dark:text-white/90">{{ $branch->legal_name ?? $branch->name ?? $branch->id ?? '—' }}</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-600 dark:text-gray-400">Responsable / Usuario</label>
                                <p class="h-11 flex items-center rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-dark-900 px-4 py-2.5 text-sm text-gray-800 dark:text-white/90">{{ $movement->person_name ?? $movement->user_name ?? '—' }}</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-600 dark:text-gray-400">Estado <span class="text-red-500">*</span></label>
                                <select name="status" required class="h-11 w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-dark-900 px-4 py-2.5 text-sm text-gray-800 dark:text-white/90">
                                    <option value="C" {{ old('status', $wm->status) === 'C' ? 'selected' : '' }}>Cerrado</option>
                                    <option value="A" {{ old('status', $wm->status) === 'A' ? 'selected' : '' }}>Anulado</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-span-full">
                            <label class="mb-1.5 block text-sm font-medium text-gray-600 dark:text-gray-400">Comentario / Observación</label>
                            <p class="min-h-[44px] flex items-center rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-dark-900 px-4 py-2.5 text-sm text-gray-800 dark:text-white/90">
                                {{ $movement->comment ?? '—' }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Detalle de productos (solo lectura) --}}
                <div>
                    <h3 class="text-base font-medium text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                        <i class="ri-box-3-line text-[#FF4622]"></i> Detalle de Productos
                    </h3>
                    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-white/[0.02] text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="pb-3 pt-4 px-4">#</th>
                                    <th class="pb-3 pt-4 px-4">Producto</th>
                                    <th class="pb-3 pt-4 px-4 text-right">Cantidad</th>
                                    <th class="pb-3 pt-4 px-4">Unidad</th>
                                    <th class="pb-3 pt-4 px-4">Observación</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse($wm->details as $index => $detail)
                                    @php
                                        $productName = $detail->product_snapshot['description'] ?? $detail->product->description ?? '—';
                                        $unitName = $detail->unit->abbreviation ?? $detail->unit->description ?? '—';
                                    @endphp
                                    <tr class="text-gray-700 dark:text-gray-300">
                                        <td class="py-3 px-4 font-medium">{{ $index + 1 }}</td>
                                        <td class="py-3 px-4">{{ $productName }}</td>
                                        <td class="py-3 px-4 text-right font-semibold">{{ number_format($detail->quantity, 2) }}</td>
                                        <td class="py-3 px-4">{{ $unitName }}</td>
                                        <td class="py-3 px-4 text-gray-500 dark:text-gray-400">{{ $detail->comment ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-gray-500 dark:text-gray-400">Sin detalle de productos.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex gap-3 pt-6 border-t border-gray-100 dark:border-gray-800">
                    <x-ui.button type="submit" size="lg" variant="primary" class="flex-1 sm:flex-none">
                        <i class="ri-save-line mr-2"></i> Actualizar
                    </x-ui.button>
                    <x-ui.button type="button" @click="redirectToIndex()" size="lg" variant="outline" class="flex-1 sm:flex-none">
                        Cancelar
                    </x-ui.button>
                </div>
            </div>
        </form>
    </div>
</x-ui.modal>
@endsection
