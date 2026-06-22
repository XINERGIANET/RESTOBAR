@extends('layouts.app')

@section('content')
    <div x-data="{}">
        @php($viewId = $viewId ?? request('view_id'))
        <x-common.page-breadcrumb pageTitle="Editar mesa" />

        <x-common.component-card title="Editar mesa" desc="Actualiza la informacion de la mesa.">
            @if ($errors->any())
                <div class="mb-5">
                    <x-ui.alert variant="error" title="Revisa los campos" message="Hay errores en el formulario, corrige los datos e intenta nuevamente." />
                </div>
            @endif

            <form method="POST" action="{{ route('tables.update', $table) }}" class="space-y-6">
                @csrf
                @method('PUT')
                @if ($viewId)
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif

                @include('tables._form', ['table' => $table, 'areas' => $areas])

                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit" size="md" variant="primary">
                        <i class="ri-save-line"></i>
                        <span>Actualizar</span>
                    </x-ui.button>
                    <x-ui.link-button size="md" variant="outline" href="{{ route('tables.index', $viewId ? ['view_id' => $viewId] : []) }}">
                        <i class="ri-close-line"></i>
                        <span>Cancelar</span>
                    </x-ui.link-button>
                </div>
            </form>
        </x-common.component-card>
    </div>
@endsection
