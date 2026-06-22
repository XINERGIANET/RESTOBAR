@extends('layouts.app')

@section('content')
    <div x-data="{}">
        @php
            use Illuminate\Support\Facades\Route;

            $viewId = request('view_id');
            $operacionesCollection = collect($operaciones ?? []);
            $topOperations = $operacionesCollection->where('type', 'T');
            $rowOperations = $operacionesCollection->where('type', 'R');

            $resolveActionUrl = function ($action, $category = null, $operation = null) use ($viewId) {
                if (!$action) {
                    return '#';
                }

                if (str_starts_with($action, '/') || str_starts_with($action, 'http')) {
                    $url = $action;
                } else {
                    $routeCandidates = [$action];
                    if (!str_starts_with($action, 'admin.')) {
                        $routeCandidates[] = 'admin.' . $action;
                    }
                    $routeCandidates = array_merge(
                        $routeCandidates,
                        array_map(fn ($name) => $name . '.index', $routeCandidates)
                    );

                    $routeName = null;
                    foreach ($routeCandidates as $candidate) {
                        if (Route::has($candidate)) {
                            $routeName = $candidate;
                            break;
                        }
                    }

                    if ($routeName) {
                        try {
                            $url = $category ? route($routeName, $category) : route($routeName);
                        } catch (\Exception $e) {
                            $url = '#';
                        }
                    } else {
                        $url = '#';
                    }
                }

                $targetViewId = $viewId;
                if ($operation && !empty($operation->view_id_action)) {
                    $targetViewId = $operation->view_id_action;
                }

                if ($targetViewId && $url !== '#') {
                    $separator = str_contains($url, '?') ? '&' : '?';
                    $url .= $separator . 'view_id=' . urlencode($targetViewId);
                }

                return $url;
            };

            $resolveTextColor = function ($operation) {
                $action = $operation->action ?? '';
                if (str_contains($action, 'categories.create')) {
                    return '#111827';
                }
                return '#FFFFFF';
            };
        @endphp
        <x-common.page-breadcrumb pageTitle="Categorias" />

        <x-common.component-card title="Categorias" desc="Gestiona las categorias de productos.">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                
                    <x-ui.per-page-selector :per-page="$perPage" />

                    <div class="relative flex-1">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="ri-search-line"></i>
                        </span>
                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Buscar por descripcion o abreviatura"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-12 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button size="md" variant="primary" type="submit" class="flex-1 sm:flex-none h-11 px-6 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95" style="background-color: #C43B25; border-color: #C43B25;">
                            <i class="ri-search-line text-gray-100"></i>
                            <span class="font-medium text-gray-100">Buscar</span>
                        </x-ui.button>
                        <x-ui.link-button size="md" variant="outline" href="{{ route('categories.index', $viewId ? ['view_id' => $viewId] : []) }}" class="flex-1 sm:flex-none h-11 px-6 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                            <i class="ri-refresh-line"></i>
                            <span class="font-medium">Limpiar</span>
                        </x-ui.link-button>
                    </div>
                </form>

                <div class="flex items-center gap-2">
                    @foreach ($topOperations as $operation)
                        @php
                            $topTextColor = $resolveTextColor($operation);
                            $topColor = $operation->color ?: '#3B82F6';
                            $topStyle = "background-color: {$topColor}; color: {$topTextColor};";
                            $topActionUrl = $resolveActionUrl($operation->action ?? '', null, $operation);
                            $isCreate = str_contains($operation->action ?? '', 'categories.create');
                        @endphp
                        @if ($isCreate)
                            <x-ui.button size="md" variant="primary" type="button" style="{{ $topStyle }}" @click="$dispatch('open-category-modal')">
                                <i class="{{ $operation->icon }}"></i>
                                <span>{{ $operation->name }}</span>
                            </x-ui.button>
                        @else
                            <x-ui.link-button size="md" variant="primary" style="{{ $topStyle }}" href="{{ $topActionUrl }}">
                                <i class="{{ $operation->icon }}"></i>
                                <span>{{ $operation->name }}</span>
                            </x-ui.link-button>
                        @endif
                    @endforeach
                    @if ($topOperations->isEmpty())
                        <x-ui.button size="md" variant="primary" type="button"
                            style=" background-color: #12f00e; color: #111827;" @click="$dispatch('open-category-modal')">
                            <i class="ri-add-line"></i>
                            <span>Nueva categoria</span>
                        </x-ui.button>
                    @endif
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span>Total</span>
                    <x-ui.badge size="sm" variant="light" color="info">{{ $categories->total() }}</x-ui.badge>
                </div>
            </div>

            <div class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full min-w-[1100px]">
                    <thead>
                        <tr style="background-color: #FF4622; color: #FFFFFF;" class="text-white">
                            <th class="px-5 py-3 text-left sm:px-6 first:rounded-tl-xl sticky-left-header transition-colors">
                                <p class="font-semibold text-white text-theme-xs">Imagen</p>
                            </th>
                            <th class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-white text-theme-xs">Descripción</p>
                            </th>
                            <th class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Abreviatura</p>
                            </th>
                            <th class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Tipo de menú</p>
                            </th>
                            <th class="px-5 py-3 text-center sm:px-6 last:rounded-tr-xl transition-colors">
                                <p class="font-semibold text-white text-theme-xs">Acciones</p>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                                <td class="px-6 py-4 whitespace-nowrap sticky-left">
                                    <div class="flex items-center">
                                        @if($category->image)
                                            <img class="h-12 w-12 object-cover rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm" 
                                                src="{{ asset('storage/' . $category->image) }}" 
                                                alt="{{ $category->description }}">
                                        @else
                                            <div class="h-12 w-12 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center border border-dashed border-gray-300 dark:border-gray-600">
                                                <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $category->description }}</p>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $category->abbreviation }}</p>
                                </td>
                                <td class="px-5 py-4 text-center sm:px-6">
                                    @switch($category->menu_type)
                                        @case('VENTAS_PEDIDOS')
                                            <x-ui.badge variant="light" color="info" size="sm">Ventas / Pedidos</x-ui.badge>
                                            @break
                                        @case('COMPRAS')
                                            <x-ui.badge variant="light" color="warning" size="sm">Compras</x-ui.badge>
                                            @break
                                        @case('GENERAL')
                                            <x-ui.badge variant="light" color="success" size="sm">General (Ventas y Compras)</x-ui.badge>
                                            @break
                                        @default
                                            <span class="text-gray-400">-</span>
                                    @endswitch
                                </td>

                                <td class="px-5 py-4 sm:px-6">
                                    <div class="flex items-center justify-center gap-2">
                                        @if ($rowOperations->isNotEmpty())
                                            @foreach ($rowOperations as $operation)
                                                @php
                                                    $action = $operation->action ?? '';
                                                    $isDelete = str_contains($action, 'destroy');
                                                    $actionUrl = $resolveActionUrl($action, $category, $operation);
                                                    $textColor = $resolveTextColor($operation);
                                                    $buttonColor = $operation->color ?: '#3B82F6';
                                                    $buttonStyle = "background-color: {$buttonColor}; color: {$textColor};";
                                                    $variant = $isDelete ? 'eliminate' : (str_contains($action, 'edit') ? 'edit' : 'primary');
                                                @endphp
                                                @if ($isDelete)
                                                    <form
                                                        method="POST"
                                                        action="{{ $actionUrl }}"
                                                        class="relative group js-swal-delete"
                                                        data-swal-title="Eliminar categoria?"
                                                        data-swal-text="Se eliminara {{ $category->description }}. Esta accion no se puede deshacer."
                                                        data-swal-confirm="Si, eliminar"
                                                        data-swal-cancel="Cancelar"
                                                        data-swal-confirm-color="#ef4444"
                                                        data-swal-cancel-color="#6b7280"
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        @if ($viewId)
                                                            <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                        @endif
                                                        <x-ui.button
                                                            size="icon"
                                                            variant="{{ $variant }}"
                                                            type="submit"
                                                            className="rounded-xl"
                                                            style="{{ $buttonStyle }}"
                                                            aria-label="{{ $operation->name }}"
                                                        >
                                                            <i class="{{ $operation->icon }}"></i>
                                                        </x-ui.button>
                                                        <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">{{ $operation->name }}</span>
                                                    </form>
                                                @else
                                                    <div class="relative group">
                                                        <x-ui.link-button
                                                            size="icon"
                                                            variant="{{ $variant }}"
                                                            href="{{ $actionUrl }}"
                                                            className="rounded-xl"
                                                            style="{{ $buttonStyle }}"
                                                            aria-label="{{ $operation->name }}"
                                                        >
                                                            <i class="{{ $operation->icon }}"></i>
                                                        </x-ui.link-button>
                                                        <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">{{ $operation->name }}</span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        @else
                                            <div class="relative group">
                                                <x-ui.link-button
                                                    size="icon"
                                                    variant="edit"
                                                    href="{{ route('categories.edit', array_merge([$category], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                    className="bg-warning-500 text-white hover:bg-warning-600 ring-0 rounded-xl"
                                                    style="background-color: #FBBF24; color: #111827;"
                                                    aria-label="Editar"
                                                >
                                                    <i class="ri-pencil-line"></i>
                                                </x-ui.link-button>
                                                <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">Editar</span>
                                            </div>
                                            <form
                                                method="POST"
                                                action="{{ route('categories.destroy', array_merge([$category], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                class="relative group js-swal-delete"
                                                data-swal-title="Eliminar categoria?"
                                                data-swal-text="Se eliminara {{ $category->description }}. Esta accion no se puede deshacer."
                                                data-swal-confirm="Si, eliminar"
                                                data-swal-cancel="Cancelar"
                                                data-swal-confirm-color="#ef4444"
                                                data-swal-cancel-color="#6b7280"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                @if ($viewId)
                                                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                @endif
                                                <x-ui.button
                                                    size="icon"
                                                    variant="eliminate"
                                                    type="submit"
                                                    className="bg-error-500 text-white hover:bg-error-600 ring-0 rounded-xl"
                                                    style="background-color: #EF4444; color: #FFFFFF;"
                                                    aria-label="Eliminar"
                                                >
                                                    <i class="ri-delete-bin-line"></i>
                                                </x-ui.button>
                                                <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">Eliminar</span>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12">
                                    <div class="flex flex-col items-center gap-3 text-center text-sm text-gray-500">
                                        <div class="rounded-full bg-gray-100 p-3 text-gray-400 dark:bg-gray-800 dark:text-gray-300">
                                            <i class="ri-folder-line"></i>
                                        </div>
                                        <p class="text-base font-semibold text-gray-700 dark:text-gray-200">No hay categorias registradas.</p>
                                        <p class="text-gray-500">Crea la primera categoria para comenzar.</p>
                                        <x-ui.button size="sm" variant="primary" type="button" @click="$dispatch('open-category-modal')">
                                            <i class="ri-add-line"></i>
                                            <span>Registrar categoria</span>
                                        </x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-500">
                    Mostrando
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $categories->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $categories->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $categories->total() }}</span>
                </div>
                <div>
                    {{ $categories->links() }}
                </div>
            </div>
        </x-common.component-card>

        <x-ui.modal x-data="{ open: false }" @open-category-modal.window="open = true" @close-category-modal.window="open = false" :isOpen="false" class="max-w-3xl">
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FF4622]/10 text-[#FF4622] dark:bg-[#FF4622]/20">
                            <i class="ri-folder-line text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar categoria</h3>
                            <p class="mt-1 text-sm text-gray-500">Ingresa la informacion de la categoria.</p>
                        </div>
                    </div>
                    <button type="button" @click="open = false"
                        class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                        aria-label="Cerrar">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>

                @if ($errors->any())
                    <div class="mb-5 rounded-lg border border-error-500/30 bg-error-500/10 px-4 py-3 text-sm text-error-700 dark:border-error-500/50 dark:bg-error-500/20 dark:text-error-300">
                        <p class="font-semibold mb-2">Revisa los campos</p>
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('categories.store') }}" class="space-y-6" enctype="multipart/form-data">
                    @csrf
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif

                    @include('categories._form', ['category' => null])

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
    </div>
@endsection


