@extends('layouts.app')
@php
    use Illuminate\Support\HtmlString;
    $SearchIcon = new HtmlString('
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8" />
            <path d="M20 20L16.5 16.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
        </svg>
    ');
@endphp
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

        $resolveTextColor = function ($operation) {
            $action = $operation->action ?? '';
            if (str_contains($action, 'parameters.create')) {
                return '#111827';
            }
            return '#FFFFFF';
        };

        $isCreateOp = fn ($operation) => str_contains($operation->action ?? '', 'parameters.create')
            || str_contains($operation->action ?? '', 'parameters.store')
            || str_contains($operation->action ?? '', 'open-create-parameter-modal');
        $isEditOp = fn ($operation) => str_contains($operation->action ?? '', 'parameters.edit')
            || str_contains($operation->action ?? '', 'parameters.update');
        $isDeleteOp = fn ($operation) => str_contains($operation->action ?? '', 'parameters.destroy');
    @endphp
    <x-common.page-breadcrumb pageTitle="{{ 'Parametros' }}" />
    <x-common.component-card title="Listado de parametros" desc="Gestiona los parametros registrados en el sistema.">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                @if ($viewId)
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif
                <div class="relative flex-1">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"> {!! $SearchIcon !!}
                    </span>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por descripcion"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-12 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-ui.button size="md" variant="primary" type="submit" class="flex-1 sm:flex-none h-11 px-4 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95" style="background-color: #C43B25; border-color: #C43B25;">
                        <i class="ri-search-line text-gray-100"></i>
                        <span class="font-medium text-gray-100">Buscar</span>
                    </x-ui.button>
                    <x-ui.link-button size="md" variant="outline" href="{{ $viewId ? route('admin.parameters.index', ['view_id' => $viewId]) : route('admin.parameters.index') }}" class="flex-1 sm:flex-none h-11 px-4 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                        <i class="ri-refresh-line"></i>
                        <span class="font-medium">Limpiar</span>
                    </x-ui.link-button>
                </div>
            </form>
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($topOperations as $operation)
                    @php
                        $topTextColor = $resolveTextColor($operation);
                        $topColor = $operation->color ?: '#3B82F6';
                        $topStyle = "background-color: {$topColor}; color: {$topTextColor};";
                        $topActionUrl = $resolveActionUrl($operation->action ?? '', null, $operation);
                        $isCreate = $isCreateOp($operation);
                    @endphp
                    @if ($isCreate)
                        <x-ui.button size="md" variant="primary" type="button"
                            style="{{ $topStyle }}" @click="$dispatch('open-create-parameter-modal')">
                            <i class="{{ $operation->icon }}"></i>
                            <span>{{ $operation->name }}</span>
                        </x-ui.button>
                    @else
                        <x-ui.link-button size="md" variant="primary"
                            style="{{ $topStyle }}" href="{{ $topActionUrl }}">
                            <i class="{{ $operation->icon }}"></i>
                            <span>{{ $operation->name }}</span>
                        </x-ui.link-button>
                    @endif
                @endforeach
            </div>
        </div>
        @if ($parameters->count() > 0)
            <div class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full min-w-max">
                        <thead class="text-left text-theme-xs dark:text-gray-400">
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6 sticky-left-header">
                                    ID
                                </th>
                                <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                    Descripcion
                                </th>
                                <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                    Valor
                                </th>
                                <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                    Categoria
                                </th>
                                <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                    Estado
                                </th>
                                <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                    Fecha de creacion
                                </th>
                                <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($parameters as $parameter)
                                <tr
                                    class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                                    <td class="px-5 py-4 sm:px-6 text-center sticky-left">
                                        <p class="font-medium text-gray-900 text-theme-sm dark:text-white/90">
                                            {{ $parameter->id }}</p>
                                    </td>
                                    <td class="px-5 py-4 sm:px-6 text-center">
                                        <p class="font-medium text-gray-900 text-theme-sm dark:text-white/90">
                                            {{ $parameter->description }}</p>
                                    </td>
                                    <td class="px-5 py-4 sm:px-6 text-center">
                                        <p class="font-medium text-gray-900 text-theme-sm dark:text-white/90">
                                            {{ $parameter->value }}</p>
                                    </td>
                                    <td class="px-5 py-4 sm:px-6 text-center">
                                        <p class="font-medium text-gray-900 text-theme-sm dark:text-white/90">
                                            {{ $parameter->parameterCategory?->description ?? 'Sin categoría' }}</p>
                                    </td>
                                    <td class="px-5 py-4 sm:px-6 text-center">
                                        <p class="font-medium text-gray-900 text-theme-sm dark:text-white/90">
                                            {{ $parameter->status == 1 ? 'Activo' : 'Inactivo' }}</p>
                                    </td>
                                    <td class="px-5 py-4 sm:px-6 text-center">
                                        <p class="font-medium text-gray-600 text-theme-sm dark:text-gray-200">
                                            {{ $parameter->created_at->format('d/m/Y H:i:s') }}</p>
                                    </td>
                                    
                                    <td class="px-5 py-4 sm:px-6 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            @foreach ($rowOperations as $operation)
                                                @php
                                                    $action = $operation->action ?? '';
                                                    $isDelete = str_contains($action, 'destroy');
                                                    $isEdit = $isEditOp($operation);
                                                    $actionUrl = $resolveActionUrl($action, $parameter, $operation);
                                                    $textColor = $resolveTextColor($operation);
                                                    $buttonColor = $operation->color ?: '#3B82F6';
                                                    $buttonStyle = "background-color: {$buttonColor}; color: {$textColor};";
                                                    $variant = $isDelete ? 'eliminate' : (str_contains($action, 'edit') ? 'edit' : 'primary');
                                                @endphp
                                                @if ($isDelete)
                                                    <form method="POST" action="{{ $actionUrl }}"
                                                        class="relative group js-swal-delete"
                                                        data-swal-title="Eliminar parametro?"
                                                        data-swal-text="Se eliminara {{ $parameter->description }}. Esta accion no se puede deshacer."
                                                        data-swal-confirm="Si, eliminar"
                                                        data-swal-cancel="Cancelar"
                                                        data-swal-confirm-color="#ef4444"
                                                        data-swal-cancel-color="#6b7280">
                                                        @csrf
                                                        @method('DELETE')
                                                        @if ($viewId)
                                                            <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                        @endif
                                                        <x-ui.button size="icon" variant="{{ $variant }}" type="submit"
                                                            className="bg-error-500 text-white hover:bg-error-600 ring-0 rounded-xl"
                                                            style="{{ $buttonStyle }}"
                                                            aria-label="{{ $operation->name }}">
                                                            <i class="{{ $operation->icon }}"></i>
                                                        </x-ui.button>
                                                        <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">{{ $operation->name }}</span>
                                                    </form>
                                                @elseif ($isEdit)
                                                    <div class="relative group">
                                                        <x-ui.button size="icon" variant="{{ $variant }}" type="button"
                                                            className="rounded-xl"
                                                            style="{{ $buttonStyle }}"
                                                            aria-label="{{ $operation->name }}"
                                                            x-on:click.prevent="$dispatch('open-edit-parameter-modal', {{ Illuminate\Support\Js::from(['id' => $parameter->id, 'description' => $parameter->description, 'value' => $parameter->value, 'parameter_category_id' => $parameter->parameterCategory?->id, 'status' => $parameter->status]) }})">
                                                            <i class="{{ $operation->icon }}"></i>
                                                        </x-ui.button>
                                                        <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">{{ $operation->name }}</span>
                                                    </div>
                                                @else
                                                    <div class="relative group">
                                                        <x-ui.link-button size="icon" variant="{{ $variant }}"
                                                            href="{{ $actionUrl }}"
                                                            className="rounded-xl"
                                                            style="{{ $buttonStyle }}"
                                                            aria-label="{{ $operation->name }}">
                                                            <i class="{{ $operation->icon }}"></i>
                                                        </x-ui.link-button>
                                                        <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">{{ $operation->name }}</span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                </table>
            </div>
        @else
            <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="p-6 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400 sm:text-base">
                        No hay parametros disponibles.
                    </p>
                </div>
            </div>
        @endif
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-gray-500">
                Mostrando
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $parameters->firstItem() ?? 0 }}</span>
                -
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $parameters->lastItem() ?? 0 }}</span>
                de
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $parameters->total() }}</span>
            </div>
            <div>
                {{ $parameters->links() }}
            </div>
            <div>
                <form method="GET" action="{{ route('admin.parameters.index') }}">
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

    <!--Modal de creacion de parametro-->
    <x-ui.modal x-data="{ open: {{ $errors->any() ? 'true' : 'false' }}, parameter: null }"
        @open-create-parameter-modal.window="open = true; parameter = $event.detail.parameter"
        @close-create-parameter-modal.window="open = false" :isOpen="false" class="max-w-md" x-init="open = @js($errors->any())">
        <div class="p-6 space-y-4">
            <h3 class="mb-6 text-lg font-semibold text-gray-800 dark:text-white/90">Crear Parametro</h3>
            @if ($errors->any())
                <div
                    class="mb-4 rounded-lg border border-error-200 bg-error-50 p-4 dark:border-error-800 dark:bg-error-500/10">
                    <p class="text-sm font-medium text-error-600 dark:text-error-400">Por favor, corrige los siguientes
                        errores:</p>
                    <ul class="mt-2 list-disc list-inside text-sm text-error-600 dark:text-error-400">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form id="create-parameter-form" class="space-y-4" action="{{ $viewId ? route('admin.parameters.store') . '?view_id=' . $viewId : route('admin.parameters.store') }}"
                method="POST" enctype="multipart/form-data">
                @csrf
                @if ($viewId)
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Descripcion</label>
                    <input type="text" name="description" id="description" value="{{ old('description') }}"
                        placeholder="Ingrese la descripcion" required
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border {{ $errors->has('description') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                    @error('description')
                        <p class="mt-1 text-sm text-error-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Valor</label>
                    <input type="text" name="value" id="value" value="{{ old('value') }}"
                        placeholder="Ingrese el valor" required
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border {{ $errors->has('value') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                    @error('value')
                        <p class="mt-1 text-sm text-error-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Categoria</label>
                    <select name="parameter_category_id" id="parameter_category_id"
                        value="{{ old('parameter_category_id') }}" required
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border {{ $errors->has('parameter_category_id') ? 'border-error-500' : 'border-gray-300' }} bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                        <option value="">Seleccione una categoria</option>
                        @foreach ($parameterCategories as $parameterCategory)
                            <option value="{{ $parameterCategory->id }}"
                                {{ old('parameter_category_id') == $parameterCategory->id ? 'selected' : '' }}>
                                {{ $parameterCategory->description }}</option>
                        @endforeach
                    </select>
                    @error('parameter_category_id')
                        <p class="mt-1 text-sm text-error-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit" size="md" variant="primary">Guardar</x-ui.button>
                    <x-ui.button type="button" size="md" variant="outline"
                        @click="open = false; $dispatch('close-create-parameter-modal')">Cancelar</x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>

    <!--Modal de edicion de parametro-->
    <x-ui.modal x-data="{
        open: false,
        parameterId: null,
        description: '',
        value: '',
        parameterCategoryId: null,
        status: '1'
    }"
        @open-edit-parameter-modal.window="open = true; parameterId = $event.detail.id; description = $event.detail.description; value = $event.detail.value; parameterCategoryId = $event.detail.parameter_category_id; status = $event.detail.status.toString()"
        @close-edit-parameter-modal.window="open = false"
        :isOpen="false" :showCloseButton="false" class="max-w-md">
        <div class="p-6 space-y-4">
            <h3 class="mb-6 text-lg font-semibold text-gray-800 dark:text-white/90">Editar Parametro</h3>
            <form id="edit-parameter-form" class="space-y-4"
                x-bind:action="parameterId ? '{{ url('/admin/herramientas/parametros') }}/' + parameterId + '{{ $viewId ? '?view_id=' . $viewId : '' }}' : '#'" 
                method="POST"
                enctype="multipart/form-data">
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
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Valor</label>
                    <input type="text" name="value" id="edit-value" x-model="value"
                        placeholder="Ingrese el valor" required
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Categoria</label>
                    <select name="parameter_category_id" id="edit-parameter_category_id" x-model="parameterCategoryId"
                        required
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                        <option value="">Seleccione una categoria</option>
                        @foreach ($parameterCategories as $parameterCategory)
                            <option value="{{ $parameterCategory->id }}">{{ $parameterCategory->description }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Estado</label>
                    <select name="status" id="edit-status" x-model="status"
                        required
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>
                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit" size="md" variant="primary">Guardar</x-ui.button>
                    <x-ui.button type="button" size="md" variant="outline"
                        @click="open = false">Cancelar</x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>
@endsection
