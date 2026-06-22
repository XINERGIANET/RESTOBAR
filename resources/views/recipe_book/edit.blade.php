@extends('layouts.app')

@section('content')
    <div x-data="{}">
        <x-common.page-breadcrumb pageTitle="Editar receta" />

        <x-common.component-card title="Editar receta" desc="Actualiza la informacion de la receta.">
            @if ($errors->any())
                <div class="mb-5">
                    <x-ui.alert 
                            variant="error" 
                            title="Revisa los campos" 
                        >
                        <div class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                            <p class="font-medium">Hay errores en el formulario, corrige los datos e intenta nuevamente:</p>
                            <ul class="list-disc pl-5 mt-1 space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </x-ui.alert>
                </div>
            @endif

            <form action="{{ route('recipe-book.update', $recipe) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')
                    <input type="hidden" name="view_id" value="{{ $viewId }}">

                @include('recipe_book._form', ['recipe' => $recipe, 'categories' => $categories, 'units' => $units, 'products' => $products])

                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit" size="md" variant="primary">
                        <i class="ri-save-line"></i>
                        <span>Actualizar</span>
                    </x-ui.button>
                    <x-ui.link-button size="md" variant="outline" href="{{ route('recipe-book.index', !empty($viewId) ? ['view_id' => $viewId] : []) }}">
                        <i class="ri-close-line"></i>
                        <span>Cancelar</span>
                    </x-ui.link-button>
                </div>
            </form>
        </x-common.component-card>
    </div>
@endsection
