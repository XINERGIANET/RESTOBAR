@extends('layouts.app')

@section('content')
    <div x-data="{}">
        <x-common.page-breadcrumb pageTitle="Editar tipo de producto" />

        <x-common.component-card title="Editar tipo de producto" desc="Actualiza la información del tipo de producto.">
            @if ($errors->any())
                <div class="mb-5">
                    <x-ui.alert variant="error" title="Revisa los campos">
                        <div class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                            <p class="font-medium">Hay errores en el formulario. Corrige los datos e intenta nuevamente:</p>
                            <ul class="list-disc pl-5 mt-1 space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </x-ui.alert>
                </div>
            @endif

            <form method="POST" action="{{ route('product_types.update', $productType) }}" class="space-y-6">
                @csrf
                @method('PUT')admin.companies.branches.profiles.permissions.index
                @if (!empty($viewId))
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif

                @include('product_types._form', ['productType' => $productType])

                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit" size="md" variant="primary">
                        <i class="ri-save-line"></i>
                        <span>Actualizar</span>
                    </x-ui.button>
                    <x-ui.link-button size="md" variant="outline" href="{{ route('product_types.index', !empty($viewId) ? ['view_id' => $viewId] : []) }}">
                        <i class="ri-close-line"></i>
                        <span>Cancelar</span>
                    </x-ui.link-button>
                </div>
            </form>
        </x-common.component-card>
    </div>
@endsection
