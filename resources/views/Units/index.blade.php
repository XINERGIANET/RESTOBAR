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

    $resolveTextColor = function ($operation) {
        $action = $operation->action ?? '';
        if (str_contains($action, 'units.create')) {
            return '#111827';
        }
        return '#FFFFFF';
    };

    $isCreateOp = fn ($operation) => str_contains($operation->action ?? '', 'units.create')
        || str_contains($operation->action ?? '', 'units.store')
        || str_contains($operation->action ?? '', 'open-unit-modal');
    $isEditOp = fn ($operation) => str_contains($operation->action ?? '', 'units.edit')
        || str_contains($operation->action ?? '', 'units.update');
@endphp
<div x-data="{}">
    <x-common.page-breadcrumb pageTitle="Unidades" />

    <x-common.component-card title="Unidades" desc="Gestiona las unidades.">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                @if ($viewId)
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif
                <div class="w-29">
                    <select
                        name="per_page"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        onchange="this.form.submit()"
                    >
                        @foreach ([10, 20, 50, 100] as $size)
                            <option value="{{ $size }}" @selected($perPage == $size)>{{ $size }} / pagina</option>
                        @endforeach
                    </select>
                </div>
                <div class="relative flex-1">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                        <i class="ri-search-line"></i>
                    </span>
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Buscar por codigo o descripcion"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-12 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    />
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-ui.button size="md" variant="primary" type="submit" class="flex-1 sm:flex-none h-11 px-4 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95" style="background-color: #C43B25; border-color: #C43B25;">
                        <i class="ri-search-line text-gray-100"></i>
                        <span class="font-medium text-gray-100">Buscar</span>
                    </x-ui.button>
                    <x-ui.link-button size="md" variant="outline" href="{{ $viewId ? route('admin.units.index', ['view_id' => $viewId]) : route('admin.units.index') }}" class="flex-1 sm:flex-none h-11 px-4 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
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
                            style="{{ $topStyle }}" @click="$dispatch('open-unit-modal')">
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

        <div class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full min-w-[1100px]">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6 sticky-left-header">
                            <p class="font-medium text-white text-theme-xs dark:text-white">Descripcion</p>
                        </th>
                        <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                            <p class="font-medium text-white text-theme-xs dark:text-white">Abreviatura</p>
                        </th>
                        <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                            <p class="font-medium text-white text-theme-xs dark:text-white">Tipo</p>
                        </th>
                        <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                            <p class="font-medium text-white text-theme-xs dark:text-white">Is Sunat</p>
                        </th>
                        <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                            <p class="font-medium text-white text-theme-xs dark:text-white">Acciones</p>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($units as $unit)
                        <tr class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                            
                            <td class="px-5 py-4 sm:px-6 text-center">
                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $unit->description  }}</p>
                            </td>
                            <td class="px-5 py-4 sm:px-6 text-center">
                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $unit->abbreviation ?? '-' }}</p>
                            </td>
                            <td class="px-5 py-4 sm:px-6 text-center">
                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $unit->type ?? '-' }}</p>
                            </td>
                            <td class="px-5 py-4 sm:px-6 text-center">
                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $unit->is_sunat ? 'Si' : 'No' }}</p>
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                <div class="flex items-center justify-center gap-2">
                                    @foreach ($rowOperations as $operation)
                                        @php
                                            $action = $operation->action ?? '';
                                            $isDelete = str_contains($action, 'destroy');
                                            $isEdit = $isEditOp($operation);
                                            $actionUrl = $resolveActionUrl($action, $unit, $operation);
                                            $textColor = $resolveTextColor($operation);
                                            $buttonColor = $operation->color ?: '#3B82F6';
                                            $buttonStyle = "background-color: {$buttonColor}; color: {$textColor};";
                                            $variant = $isDelete ? 'eliminate' : (str_contains($action, 'edit') ? 'edit' : 'primary');
                                        @endphp
                                        @if ($isDelete)
                                            <form method="POST" action="{{ $actionUrl }}"
                                                class="relative group js-swal-delete"
                                                data-swal-title="Eliminar unidad?"
                                                data-swal-text="Se eliminara {{ $unit->description }}. Esta accion no se puede deshacer."
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
                                                <x-ui.link-button size="icon" variant="{{ $variant }}"
                                                    href="{{ $actionUrl }}"
                                                    className="rounded-xl"
                                                    style="{{ $buttonStyle }}"
                                                    aria-label="{{ $operation->name }}">
                                                    <i class="{{ $operation->icon }}"></i>
                                                </x-ui.link-button>
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
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12">
                                <div class="flex flex-col items-center gap-3 text-center text-sm text-gray-500">
                                    <div class="rounded-full bg-gray-100 p-3 text-gray-400 dark:bg-gray-800 dark:text-gray-300">
                                        <i class="ri-restaurant-line"></i>
                                    </div>
                                    <p class="text-base font-semibold text-gray-700 dark:text-gray-200">No hay unidades registradas.</p>
                                    <p class="text-gray-500">Crea la primera unidad para comenzar.</p>
                                    <x-ui.button size="sm" variant="primary" type="button" @click="$dispatch('open-unit-modal')">
                                        <i class="ri-add-line"></i>
                                        <span>Registrar unidad</span>
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
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $units->firstItem() ?? 0 }}</span>
                -
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $units->lastItem() ?? 0 }}</span>
                de
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $units->total() }}</span>
            </div>
            <div>
                {{ $units->links() }}
            </div>
            <div>
                <form method="GET" action="{{ route('admin.units.index') }}">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    <input type="hidden" name="search" value="{{ $search }}">
                    <select
                        name="per_page"
                        onchange="this.form.submit()"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    >
                        @foreach ([10, 20, 50, 100] as $size)
                            <option value="{{ $size }}" @selected($perPage == $size)>{{ $size }} / pagina</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    </x-common.component-card>

    <x-ui.modal
        x-data="{ open: false }"
        @open-unit-modal.window="open = true"
        @close-unit-modal.window="open = false"
        :isOpen="false"
        :showCloseButton="false"
        class="max-w-3xl"
    >
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FF4622]/10 text-[#FF4622] dark:bg-[#FF4622]/20">
                        <i class="ri-ruler-line text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar unidad</h3>
                        <p class="mt-1 text-sm text-gray-500">Ingresa la informacion de la unidad.</p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="open = false"
                    class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                    aria-label="Cerrar"
                >
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            @if ($errors->any())
                <div class="mb-5">
                    <x-ui.alert variant="error" title="Revisa los campos" message="Hay errores en el formulario, corrige los datos e intenta nuevamente." />
                </div>
            @endif

            <form method="POST" action="{{ $viewId ? route('admin.units.store') . '?view_id=' . $viewId : route('admin.units.store') }}" class="space-y-6">
                @csrf
                @if ($viewId)
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif

                @include('Units._form', ['unit' => null])

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
