@extends('layouts.app')

@section('content')
@php
    /** @var \App\Models\PrinterBranch $printer */
    $viewId = $viewId ?? request('view_id');
@endphp

<x-common.page-breadcrumb pageTitle="Editar ticketera" />

<x-common.component-card title="Editar ticketera" desc="Actualiza la información de la ticketera.">
    <form method="POST"
        action="{{ route('printers_branch.update', ['printerBranch' => $printer->id] + ($viewId ? ['view_id' => $viewId] : [])) }}"
        class="flex w-full flex-col min-h-0 space-y-6">
        @csrf
        @method('PUT')

        @if ($viewId)
            <input type="hidden" name="view_id" value="{{ $viewId }}">
        @endif

        @include('printers_branch._form', ['printer' => $printer])

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Estado</label>
            <select name="status" required
                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="E" @selected(old('status', $printer->status) === 'E')>Activo</option>
                <option value="I" @selected(old('status', $printer->status) === 'I')>Inactivo</option>
            </select>
            @error('status')
                <p class="mt-1 text-sm text-error-500">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-wrap gap-3">
            <x-ui.button type="submit" size="md" variant="primary">
                <i class="ri-save-line"></i>
                <span>Guardar</span>
            </x-ui.button>
            <x-ui.link-button size="md" variant="outline"
                href="{{ route('printers_branch.index', $viewId ? ['view_id' => $viewId] : []) }}">
                <i class="ri-arrow-left-line"></i>
                <span>Volver</span>
            </x-ui.link-button>
        </div>
    </form>
</x-common.component-card>
@endsection
