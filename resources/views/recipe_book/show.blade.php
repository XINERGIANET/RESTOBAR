@extends('layouts.app')

@section('content')
    <div x-data="{}">
        <x-common.page-breadcrumb pageTitle="Visualizar receta" />

        <x-common.component-card title="Detalles de la receta" desc="Información completa de la receta (Solo lectura).">
            
            @include('recipe_book._form', [
                'recipe' => $recipe, 
                'categories' => $categories, 
                'units' => $units, 
                'products' => $products,
                'readonly' => true  
            ])

            <div class="flex flex-wrap gap-3 mt-6">
                {{-- Solo dejamos el botón de Regresar/Cancelar --}}
                <x-ui.link-button size="md" variant="primary" href="{{ route('recipe-book.index', !empty($viewId) ? ['view_id' => $viewId] : []) }}">
                    <i class="ri-arrow-left-line"></i>
                    <span>Regresar</span>
                </x-ui.link-button>
            </div>

        </x-common.component-card>
    </div>
@endsection
