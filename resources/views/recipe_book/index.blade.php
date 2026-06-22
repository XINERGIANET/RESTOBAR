@extends('layouts.app')

@php
    use Illuminate\Support\HtmlString;
    
    // --- ICONOS ---
    $SearchIcon = new HtmlString('<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8" /><path d="M20 20L16.5 16.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>');
    $ClearIcon = new HtmlString('<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>');
    $PlusIcon = new HtmlString('<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>');
    
    $totalRecipes = count($recipes);
@endphp

@section('content')

<style>
    /* Estilos para las tarjetas de recetas */
    .recipes-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.5rem;
        margin-top: 1.5rem;
    }

    @media (max-width: 1400px) {
        .recipes-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 992px) {
        .recipes-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 576px) {
        .recipes-grid {
            grid-template-columns: 1fr;
        }
    }

    .recipe-card {
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        border: 1px solid rgba(229, 231, 235, 0.8);
    }

    .recipe-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    }

    .recipe-image {
        position: relative;
        width: 100%;
        height: 280px;
        overflow: hidden;
        background: #e9ecef;
    }

    .recipe-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
        transition: transform 0.5s ease;
    }

    .recipe-card:hover .recipe-image img {
        transform: scale(1.06);
    }

    .category-badge {
        position: absolute;
        top: 1rem;
        right: 1rem;
        padding: 0.45rem 1rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        z-index: 2;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }

    .badge-plato {
        background: #ffc107;
        color: #000;
    }

    .badge-entrada {
        background: #17a2b8;
        color: #fff;
    }

    .badge-bebida {
        background: #28a745;
        color: #fff;
    }

    .badge-postre {
        background: #dc3545;
        color: #fff;
    }

    .recipe-content {
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }

    .recipe-meta {
        display: flex;
        gap: 1rem;
        margin-bottom: 0.75rem;
        font-size: 0.8rem;
        color: #6c757d;
    }

    .recipe-meta span {
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }

    .recipe-meta i {
        font-size: 0.95rem;
    }

    .recipe-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 0.6rem;
        line-height: 1.3;
    }

    .recipe-description {
        font-size: 0.85rem;
        color: #6c757d;
        line-height: 1.5;
        margin-bottom: 1rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        flex-grow: 1;
    }

    .recipe-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 1rem;
        border-top: 1px solid #e9ecef;
        margin-top: auto;
    }

    .price-info {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
    }

    .price-label {
        font-size: 0.65rem;
        font-weight: 600;
        color: #95a5a6;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .price-value {
        font-size: 1.3rem;
        font-weight: 800;
        color: #2c3e50;
    }

    .btn-view-recipe {
        padding: 0.5rem 1.15rem;
        font-size: 0.8rem;
        font-weight: 600;
        border-radius: 20px;
        border: 1.5px solid #0d6efd;
        color: #0d6efd;
        background: transparent;
        transition: all 0.3s ease;
        white-space: nowrap;
        cursor: pointer;
    }

    .btn-view-recipe:hover {
        background: #0d6efd;
        color: #fff;
        transform: translateX(3px);
    }

    /* Ajuste para el select en dark mode */
    .dark select {
        color-scheme: dark;
    }
</style>

<div x-data="{}">
    
    <x-common.page-breadcrumb pageTitle="Recetario" />

    <x-common.component-card title="Recetario Maestro" desc="Gestión de fichas técnicas y costos de platillos">
        
        <!-- Sección de búsqueda y filtros -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end justify-between sm:gap-6">
            <form method="GET" action="#" class="flex flex-1 flex-col sm:flex-row sm:items-end gap-3">
                <!-- Campo de búsqueda -->
                <div class="flex-1">
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Buscar Platillo
                    </label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                            {!! $SearchIcon !!}
                        </span>
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Ej. Lomo Saltado, Ceviche..."
                            class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 pl-10 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-[#FF4622]"
                        />
                    </div>
                </div>

                <!-- Categoría -->
                <x-form.select.combobox name="category" :options="$categories" placeholder="Seleccione categoría" icon="ri-layout-grid-line" />

                <!-- Estado -->
                <div class="w-full sm:w-40">
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Estado
                    </label>
                    <select 
                        name="status"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-[#FF4622]"
                    >
                        <option value="">Todos</option>
                        <option value="active">Activos</option>
                        <option value="development">En desarrollo</option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <x-ui.button size="md" variant="primary" type="submit" class="flex-1 sm:flex-none h-11 px-6 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95" style="background-color: #C43B25; border-color: #C43B25;">
                        <i class="ri-search-line text-gray-100"></i>
                        <span class="font-medium text-gray-100">Buscar</span>
                    </x-ui.button>
                    <x-ui.link-button size="md" variant="outline" href="" class="flex-1 sm:flex-none h-11 px-6 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                        <i class="ri-refresh-line"></i>
                        <span class="font-medium">Limpiar</span>
                    </x-ui.link-button>
                </div>
            </form>
            
            <div class="flex items-end w-full justify-end sm:w-auto">
                <x-ui.button size="md" variant="primary" type="button"
                    style=" background-color: #12f00e; color: #111827;" @click="$dispatch('open-recipe-modal')">
                    <i class="ri-add-line"></i>
                    <span>Nuevo producto</span>
                </x-ui.button>
            </div>
        </div>

        <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 text-sm text-gray-500">
                <span>Total de recetas</span>
                <x-ui.badge size="sm" variant="light" color="info">{{ $totalRecipes }}</x-ui.badge>
            </div>
        </div>

        <!-- Grid de tarjetas de recetas (datos reales de BD) -->
        <div class="recipes-grid">
            @foreach($recipes as $recipe)
            <div class="recipe-card">
                <div class="recipe-image">
                    <span class="category-badge {{ $recipe->category['class'] ?? '' }}">
                        {{ $recipe->category->description ?? '' }}
                    </span>
                    <img src="{{ asset('storage/' . $recipe->image) }}">
                </div>
                <div class="recipe-content">
                    <div class="recipe-meta">
                        <span><i class="ri-time-line"></i> {{ $recipe->preparation_time ? $recipe->preparation_time . ' min' : '--' }}</span>
                        <span><i class="ri-fire-line"></i> {{ $recipe->preparation_method ?? '--' }}</span>
                    </div>
                    <h5 class="recipe-title">{{ $recipe->name }}</h5>
                    
                    <p class="recipe-description" title="{{ $recipe->description }}">
                        {{ \Illuminate\Support\Str::limit($recipe->description ?? 'Sin descripción', 70, '...') }}
                    </p>
                    <div class="recipe-footer">
                        <div class="price-info">
                            <span class="price-label">Costo Insumos</span>
                            <span class="price-value">S/ {{ number_format($recipe->cost_total, 2) }}</span>
                        </div>
                        
                        <div class="flex items-center gap-1">
                            <a href="{{ route('recipe-book.show', ['recipe' => $recipe, 'view_id' => request('view_id')]) }}"
                            class="p-2 rounded-full text-blue-500 hover:bg-blue-50 hover:text-blue-700 transition-colors" 
                            title="Ver Ficha">
                                <i class="ri-eye-line text-lg"></i>
                            </a>

                            <a href="{{ route('recipe-book.edit', ['recipe' => $recipe, 'view_id' => request('view_id')]) }}"
                            class="p-2 rounded-full text-amber-500 hover:bg-amber-50 hover:text-amber-700 transition-colors" 
                            title="Editar">
                                <i class="ri-pencil-line text-lg"></i>
                            </a>

                            <form action="{{ route('recipe-book.destroy', $recipe) }}" method="POST" class="inline-block" 
                                onsubmit="return confirm('¿Estás seguro de querer eliminar esta receta?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" 
                                        class="p-2 rounded-full text-red-500 hover:bg-red-50 hover:text-red-700 transition-colors" 
                                        title="Eliminar">
                                    <i class="ri-delete-bin-line text-lg"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

    </x-common.component-card>

    <!-- Modal para nueva receta -->
    <x-ui.modal x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }" @open-recipe-modal.window="open = true"
        @close-product-modal.window="open = false" :isOpen="$errors->any() ? true : false" :showCloseButton="false" class="w-full max-w-5xl sm:max-w-6xl lg:max-w-7xl">
        <div class="flex w-full flex-col min-h-0 p-6 sm:p-8">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FF4622]/10 text-[#FF4622] dark:bg-[#FF4622]/20">
                        <i class="ri-restaurant-line text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar nueva receta</h3>
                        <p class="mt-1 text-sm text-gray-500">Ingresa la informacion de la nueva receta.</p>
                    </div>
                </div>
                <button type="button" @click="open = false"
                    class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                    aria-label="Cerrar">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            @if ($errors->any())
                <div class="mb-5">
                    <x-ui.alert variant="error" title="Revisa los campos"
                        message="Hay errores en el formulario, corrige los datos e intenta nuevamente." />
                </div>
            @endif

            <form method="POST" action="{{ route('recipe-book.store') }}" enctype="multipart/form-data" class="flex w-full flex-col min-h-0 space-y-6">
                @csrf
                <input type="hidden" name="view_id" value="{{ $viewId }}">

                @include('recipe_book._form', [
                    'recipe'        => new \App\Models\Recipe(),
                    'product'       => null,
                    'currentBranch' => $currentBranch ?? null,
                    'taxRates'      => $taxRates ?? collect(),
                    'productBranch' => null,
                ])

                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit" size="md" variant="primary">
                        <i class="ri-save-line"></i>
                        <span>Guardar</span>
                    </x-ui.button>
                    <x-ui.button type="button" size="md" variant="outline" @click="open = false">
                        <i class="ri-close-line"></i>
                        <span>Cancelar</span>
                    </x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>

    @if ($errors->any())
        <script>
            document.addEventListener('alpine:init', () => {
                setTimeout(() => {
                    window.dispatchEvent(new CustomEvent('open-recipe-modal'));
                }, 100);
            });
        </script>
    @endif
    
</div>

@endsection
