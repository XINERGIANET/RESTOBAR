@extends('layouts.app')

@php
    use Illuminate\Support\HtmlString;

    $SaveIcon = new HtmlString('
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    ');

    $BackIcon = new HtmlString('
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    ');
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Opciones de Menú" />

    <x-ui.modal
        x-data="{
            open: true,
            close() {
                if (window.Turbo && typeof window.Turbo.visit === 'function') {
                    window.Turbo.visit('{{ route('admin.modules.menu_options.index', $module) }}', { action: 'replace' });
                } else {
                    window.location.href = '{{ route('admin.modules.menu_options.index', $module) }}';
                }
            }
        }"
        :isOpen="true"
        :showCloseButton="false"
        class="max-w-3xl"
    >
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FF4622]/10 text-[#FF4622] dark:bg-[#FF4622]/20">
                        <i class="ri-pencil-line text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Editar opción de menú</h3>
                        <p class="mt-1 text-sm text-gray-500">Módulo: <strong>{{ $module->name }}</strong></p>
                    </div>
                </div>
                <a
                    href="{{ route('admin.modules.menu_options.index', $module) }}"
                    onclick="if (window.Turbo && typeof window.Turbo.visit === 'function') { window.Turbo.visit('{{ route('admin.modules.menu_options.index', $module) }}', { action: 'replace' }); return false; }"
                    class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                    aria-label="Cerrar"
                >
                    <i class="ri-close-line text-xl"></i>
                </a>
            </div>

            @if ($errors->any())
                <div class="mb-5">
                    <x-ui.alert variant="error" title="Revisa los campos" message="Hay errores en el formulario, corrige los datos e intenta nuevamente." />
                </div>
            @endif

            <form method="POST" action="{{ route('admin.modules.menu_options.update', [$module, $menuOption]) }}" class="space-y-6">
                @csrf
                @method('PUT')

                @include('menu_options._form', ['menuOption' => $menuOption, 'views' => $views])

                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit" size="md" variant="primary" :startIcon="$SaveIcon">Actualizar</x-ui.button>
                    <x-ui.link-button
                        size="md"
                        variant="outline"
                        href="{{ route('admin.modules.menu_options.index', $module) }}"
                        :startIcon="$BackIcon"
                        onclick="if (window.Turbo && typeof window.Turbo.visit === 'function') { window.Turbo.visit('{{ route('admin.modules.menu_options.index', $module) }}', { action: 'replace' }); return false; }"
                    >
                        Cancelar
                    </x-ui.link-button>
                </div>
            </form>
        </div>
    </x-ui.modal>
@endsection
