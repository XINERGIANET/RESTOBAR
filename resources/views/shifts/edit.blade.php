@extends('layouts.app')

@section('content')
    @php($viewId = $viewId ?? request('view_id'))

    <x-common.page-breadcrumb pageTitle="Turnos" />

    <x-ui.modal
        x-data="{
            open: true,
            close() {
                const target = '{{ route('shifts.index', $viewId ? ['view_id' => $viewId] : []) }}';
                if (window.Turbo && typeof window.Turbo.visit === 'function') {
                    window.Turbo.visit(target, { action: 'replace' });
                } else {
                    window.location.href = target;
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
                        <i class="ri-time-line text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Editar turno</h3>
                        <p class="mt-1 text-sm text-gray-500">Actualiza la informacion del turno y sus horarios.</p>
                    </div>
                </div>
                <a
                    href="{{ route('shifts.index', $viewId ? ['view_id' => $viewId] : []) }}"
                    onclick="if (window.Turbo && typeof window.Turbo.visit === 'function') { window.Turbo.visit('{{ route('shifts.index', $viewId ? ['view_id' => $viewId] : []) }}', { action: 'replace' }); return false; }"
                    class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                    aria-label="Cerrar"
                >
                    <i class="ri-close-line text-xl"></i>
                </a>
            </div>

            @if ($errors->any())
                <div class="mb-5">
                    <x-ui.alert variant="error" title="Revisa los campos">
                        <ul class="list-disc list-inside text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-ui.alert>
                </div>
            @endif

            <form method="POST" action="{{ route('shifts.update', $shift) }}" class="space-y-6">
                @csrf
                @method('PUT')
                @if ($viewId)
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif

                @include('shifts._form', ['shift' => $shift])

                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit" size="md" variant="primary">
                        <i class="ri-save-line"></i>
                        <span>Actualizar Turno</span>
                    </x-ui.button>

                    <x-ui.link-button
                        size="md"
                        variant="outline"
                        href="{{ route('shifts.index', $viewId ? ['view_id' => $viewId] : []) }}"
                        onclick="if (window.Turbo && typeof window.Turbo.visit === 'function') { window.Turbo.visit('{{ route('shifts.index', $viewId ? ['view_id' => $viewId] : []) }}', { action: 'replace' }); return false; }"
                    >
                        <i class="ri-close-line"></i>
                        <span>Cancelar</span>
                    </x-ui.link-button>
                </div>
            </form>
        </div>
    </x-ui.modal>
@endsection
