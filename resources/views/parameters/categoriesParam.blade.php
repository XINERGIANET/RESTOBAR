@extends('layouts.app')

@section('content')
    @php
        use Illuminate\Support\Facades\Route;

        $viewId = request('view_id');
        $operacionesCollection = collect($operaciones ?? []);
        $topOperations = $operacionesCollection->where('type', 'T');
        $rowOperations = $operacionesCollection->where('type', 'R');

        $resolveActionUrl = function ($action, $model = null, $operation = null) use ($viewId) {
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
                        $url = $model ? route($routeName, $model) : route($routeName);
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

        $isCreateOp = fn ($operation) => str_contains($operation->action ?? '', 'parameters.categories.create')
            || str_contains($operation->action ?? '', 'parameters.categories.store')
            || str_contains($operation->action ?? '', 'open-create-category-modal');
        $isEditOp = fn ($operation) => str_contains($operation->action ?? '', 'parameters.categories.edit')
            || str_contains($operation->action ?? '', 'parameters.categories.update');
        $isDeleteOp = fn ($operation) => str_contains($operation->action ?? '', 'parameters.categories.destroy');
    @endphp
    <x-common.page-breadcrumb pageTitle="{{ 'Categorias de parametros' }}" />
    <x-common.component-card title="Listado de categorias de parametros"
        desc="Gestiona las categorias de parametros registradas en el sistema.">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                @if ($viewId)
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif
                <div class="relative flex-1">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"><i class="ri-search-line"></i>

                    </span>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por descripcion"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-12 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-ui.button size="sm" variant="primary" type="submit">
                        <i class="ri-search-line"></i>
                        <span>Buscar</span>
                    </x-ui.button>
                    <x-ui.link-button size="sm" variant="outline" href="{{ $viewId ? route('admin.parameters.categories.index', ['view_id' => $viewId]) : route('admin.parameters.categories.index') }}">
                        <i class="ri-close-line"></i>
                        <span>Limpiar</span>
                    </x-ui.link-button>
                    @if ($topOperations->isNotEmpty())
                        @foreach ($topOperations as $operationRow)
                            @php
                                $topTextColor = str_contains($operationRow->action ?? '', 'parameters.categories.create') ? '#111827' : '#FFFFFF';
                                $topStyle = "background-color: " . ($operationRow->color ?: '#3B82F6') . "; color: {$topTextColor};";
                            @endphp
                            @if ($isCreateOp($operationRow))
                                <x-ui.button size="md" variant="create" style="{{ $topStyle }}" @click="$dispatch('open-create-category-modal')">
                                    <i class="{{ $operationRow->icon }}"></i>
                                    <span>{{ $operationRow->name }}</span>
                                </x-ui.button>
                            @else
                                <x-ui.link-button size="md" variant="primary" style="{{ $topStyle }}" href="{{ $resolveActionUrl($operationRow->action, null, $operationRow) }}">
                                    <i class="{{ $operationRow->icon }}"></i>
                                    <span>{{ $operationRow->name }}</span>
                                </x-ui.link-button>
                            @endif
                        @endforeach
                    @else
                        <x-ui.button size="md" variant="create" @click="$dispatch('open-create-category-modal')">
                            <i class="ri-add-line"></i>
                            <span>Crear Categoria</span>
                        </x-ui.button>
                    @endif
                </div>
            </form>
        </div>
        @if ($parameterCategories->count() > 0)
            <div
                class="mt-4 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[880px]">
                        <thead class="text-left text-theme-xs dark:text-gray-400">
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">ID</th>
                                <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">Descripcion</th>
                                <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">Fecha de creacion</th>
                                <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($parameterCategories as $parameterCategory)
                                <tr
                                    class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                                    <td class="px-5 py-4 sm:px-6 text-center">
                                        <p class="font-medium text-gray-900 text-theme-sm dark:text-white/90">
                                            {{ $parameterCategory->id }}</p>
                                    </td>
                                    <td class="px-5 py-4 sm:px-6 text-center">
                                        <p class="font-medium text-gray-900 text-theme-sm dark:text-white/90">
                                            {{ $parameterCategory->description }}</p>
                                    </td>
                                    <td class="px-5 py-4 sm:px-6 text-center">
                                        <p class="font-medium text-gray-600 text-theme-sm dark:text-gray-200">
                                            {{ $parameterCategory->created_at->format('d/m/Y H:i:s') }}</p>
                                    </td>
                                    <td class="px-5 py-4 sm:px-6 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            @if ($rowOperations->isNotEmpty())
                                                @foreach ($rowOperations as $operationRow)
                                                    @php
                                                        $rowTextColor = $isEditOp($operationRow) ? '#111827' : '#FFFFFF';
                                                        $buttonStyle = "background-color: " . ($operationRow->color ?: '#3B82F6') . "; color: {$rowTextColor};";
                                                    @endphp
                                                    @if ($isEditOp($operationRow))
                                                        <div class="relative group">
                                                            <x-ui.button size="icon" variant="edit" style="{{ $buttonStyle }}"
                                                                x-on:click.prevent="$dispatch('open-edit-category-modal', {{ Illuminate\Support\Js::from(['id' => $parameterCategory->id, 'description' => $parameterCategory->description]) }})"
                                                                aria-label="{{ $operationRow->name }}">
                                                                <i class="{{ $operationRow->icon }}"></i>
                                                            </x-ui.button>
                                                            <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50">{{ $operationRow->name }}</span>
                                                        </div>
                                                    @elseif ($isDeleteOp($operationRow))
                                                        <form action="{{ $viewId ? route('admin.parameters.categories.destroy', $parameterCategory) . '?view_id=' . $viewId : route('admin.parameters.categories.destroy', $parameterCategory) }}"
                                                            method="POST"
                                                            class="relative group js-swal-delete"
                                                            data-swal-title="Eliminar categoria?"
                                                            data-swal-text="Se eliminara {{ $parameterCategory->description }}. Esta accion no se puede deshacer."
                                                            data-swal-confirm="Si, eliminar"
                                                            data-swal-cancel="Cancelar"
                                                            data-swal-confirm-color="#ef4444"
                                                            data-swal-cancel-color="#6b7280">
                                                            @csrf
                                                            @method('DELETE')
                                                            <x-ui.button size="icon" variant="eliminate" type="submit" style="{{ $buttonStyle }}" aria-label="{{ $operationRow->name }}">
                                                                <i class="{{ $operationRow->icon }}"></i>
                                                            </x-ui.button>
                                                            <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50">{{ $operationRow->name }}</span>
                                                        </form>
                                                    @else
                                                        <div class="relative group">
                                                            <x-ui.link-button size="icon" variant="primary" style="{{ $buttonStyle }}"
                                                                href="{{ $resolveActionUrl($operationRow->action, $parameterCategory, $operationRow) }}"
                                                                aria-label="{{ $operationRow->name }}">
                                                                <i class="{{ $operationRow->icon }}"></i>
                                                            </x-ui.link-button>
                                                            <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50">{{ $operationRow->name }}</span>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            @else
                                                <div class="relative group">
                                                    <x-ui.button size="icon" variant="edit"
                                                        x-on:click.prevent="$dispatch('open-edit-category-modal', {{ Illuminate\Support\Js::from(['id' => $parameterCategory->id, 'description' => $parameterCategory->description]) }})">
                                                        <i class="ri-pencil-line"></i>
                                                    </x-ui.button>
                                                    <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50">Editar</span>
                                                </div>
                                                <form action="{{ route('admin.parameters.categories.destroy', $parameterCategory) }}" method="POST" data-swal-title="Eliminar categoria?"
                                                    class="relative group js-swal-delete"
                                                    data-swal-text="Se eliminara {{ $parameterCategory->description }}. Esta accion no se puede deshacer."
                                                    data-swal-confirm="Si, eliminar"
                                                    data-swal-cancel="Cancelar"
                                                    data-swal-confirm-color="#ef4444"
                                                    data-swal-cancel-color="#6b7280">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-ui.button size="icon" variant="eliminate" type="submit">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </x-ui.button>
                                                    <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50">Eliminar</span>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-12">
                                        <div class="flex flex-col items-center gap-3 text-center text-sm text-gray-500">
                                            <div
                                                class="rounded-full bg-gray-100 p-3 text-gray-400 dark:bg-gray-800 dark:text-gray-300">
                                                {!! $BuildingIcon !!}
                                            </div>
                                            <p class="text-base font-semibold text-gray-700 dark:text-gray-200">No hay
                                                categorias de parametros registradas.</p>
                                            <p class="text-gray-500">Crea tu primera categoria para comenzar.</p>
                                            <x-ui.button size="sm" variant="primary" type="button" :startIcon="$PlusIcon"
                                                @click="$dispatch('open-category-modal')">
                                                Registrar categoria
                                            </x-ui.button>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="p-6 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400 sm:text-base">
                        No hay categorias de parametros disponibles.
                    </p>
                </div>
            </div>
        @endif
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-gray-500">
                Mostrando
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $parameterCategories->firstItem() ?? 0 }}</span>
                -
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $parameterCategories->lastItem() ?? 0 }}</span>
                de
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $parameterCategories->total() }}</span>
            </div>
            <div>
                {{ $parameterCategories->links() }}
            </div>
            <div>
                <form method="GET" action="{{ route('admin.parameters.categories.index') }}">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    <input type="hidden" name="search" value="{{ $search }}">
                    <select
                        name="per_page"
                        onchange="this.form.submit()"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    >
                        @foreach ($allowedPerPage as $size)
                            <option value="{{ $size }}" @selected($perPage == $size)>{{ $size }} / pagina</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    </x-common.component-card>


    <!--Modal de creacion de categoria-->
    <x-ui.modal x-data="{ open: false }" @open-create-category-modal.window="open = true"
        @close-create-category-modal.window="open = false" :isOpen="false" class="max-w-md">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FF4622]/10 text-[#FF4622] dark:bg-[#FF4622]/20">
                        <i class="ri-add-circle-line text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Crear Categoria</h3>
                        <p class="mt-1 text-sm text-gray-500">Ingresa la descripcion para la nueva categoria.</p>
                    </div>
                </div>
                <button type="button" @click="open = false"
                    class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                    aria-label="Cerrar">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <form id="create-category-form" class="space-y-6" action="{{ $viewId ? route('admin.parameters.categories.store') . '?view_id=' . $viewId : route('admin.parameters.categories.store') }}"
                method="POST" enctype="multipart/form-data">
                @csrf
                @if ($viewId)
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Descripcion</label>
                    <input type="text" name="description" id="description" value="{{ old('description') }}"
                        placeholder="Ingrese la descripcion" required
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                </div>
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

    <!--Modal de edicion de categoria-->
    <x-ui.modal x-data="{
        open: false,
        categoryId: null,
        description: ''
    }"
        @open-edit-category-modal.window="open = true; categoryId = $event.detail.id; description = $event.detail.description"
        @close-edit-category-modal.window="open = false" :isOpen="false" :showCloseButton="false" class="max-w-md">
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FF4622]/10 text-[#FF4622] dark:bg-[#FF4622]/20">
                        <i class="ri-edit-line text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Editar Categoria</h3>
                        <p class="mt-1 text-sm text-gray-500">Actualiza la informacion de la categoria.</p>
                    </div>
                </div>
                <button type="button" @click="open = false"
                    class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                    aria-label="Cerrar">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <form id="edit-category-form" class="space-y-6"
                x-bind:action="categoryId ? '{{ url('/admin/herramientas/parametros/categorias') }}/' + categoryId + '{{ $viewId ? '?view_id=' . $viewId : '' }}' : '#'"
                method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @if ($viewId)
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Descripcion</label>
                    <input type="text" name="description" id="edit-description" x-model="description"
                        placeholder="Ingrese la descripcion" required
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                </div>
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
@endsection
