@extends('layouts.app')

@php
    use Illuminate\Support\HtmlString;
    $SearchIcon = new HtmlString('<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8" /><path d="M20 20L16.5 16.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>');
    $ClearIcon = new HtmlString('<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>');
@endphp

@section('content')
    <div x-data="{}">
        @php
            use Illuminate\Support\Facades\Route;

            $viewId = request('view_id');
            $operacionesCollection = collect($operaciones ?? []);
            $topOperations = $operacionesCollection->where('type', 'T');
            $rowOperations = $operacionesCollection->where('type', 'R');

            $resolveActionUrl = function ($action, array $routeParams = [], $operation = null) use ($viewId) {
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
                    if (str_starts_with($action, 'operations.')) {
                        $routeCandidates[] = 'admin.views.' . $action;
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
                        $attempts = [];
                        if (!empty($routeParams)) {
                            $attempts[] = $routeParams;
                        }
                        if (count($routeParams) > 1) {
                            $attempts[] = array_slice($routeParams, 0, 1);
                        }
                        $attempts[] = [];

                        $url = '#';
                        foreach ($attempts as $params) {
                            try {
                                $url = empty($params) ? route($routeName) : route($routeName, $params);
                                break;
                            } catch (\Exception $e) {
                                $url = '#';
                            }
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
                if (($operation->type ?? null) === 'T') {
                    return '#111827';
                }
                $action = $operation->action ?? '';
                if (str_contains($action, 'views.operations.store') || str_contains($action, 'views.operations.create')) {
                    return '#111827';
                }
                return '#FFFFFF';
            };
        @endphp

        <x-common.page-breadcrumb
            pageTitle="Operaciones"
            :breadcrumbs="[
                ['name' => 'Vistas', 'href' => route('admin.views.index')],
                ['name' => $view->name . ' | Operaciones', 'href' => '#'],
            ]"
        />

        <x-common.component-card
            title="Botones de: {{ $view->name }}"
            desc="Gestiona las operaciones (botones) que pertenecen a esta vista."
        >
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                
                {{-- BUSCADOR --}}
                <form method="GET" action="{{ route('admin.views.operations.index', $view) }}" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    <div class="relative flex-1">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                            {!! $SearchIcon !!}
                        </span>
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Buscar por nombre o acción..."
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-10 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button size="md" variant="primary" type="submit" class="flex-1 sm:flex-none h-11 px-4 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95" style="background-color: #C43B25; border-color: #C43B25;">
                            <i class="ri-search-line text-gray-100"></i>
                            <span class="font-medium text-gray-100">Buscar</span>
                        </x-ui.button>
                        <x-ui.link-button size="md" variant="outline" href="{{ route('admin.views.operations.index', $viewId ? [$view, 'view_id' => $viewId] : $view) }}" class="flex-1 sm:flex-none h-11 px-4 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                            <i class="ri-refresh-line"></i>
                            <span class="font-medium">Limpiar</span>
                        </x-ui.link-button>
                    </div>
                </form>

                {{-- BOTONES DE ACCIÓN --}}
                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($topOperations as $operation)
                        @php
                            $topTextColor = $resolveTextColor($operation);
                            $topColor = $operation->color ?: '#3B82F6';
                            $topStyle = "background-color: {$topColor}; color: {$topTextColor};";
                            $topActionUrl = $resolveActionUrl($operation->action ?? '', [$view], $operation);
                            $isCreate = ($operation->type ?? null) === 'T'
                                || str_contains($operation->action ?? '', 'views.operations.store')
                                || str_contains($operation->action ?? '', 'views.operations.create')
                                || str_contains($operation->action ?? '', 'open-create-modal');
                        @endphp
                        @if ($isCreate)
                            <x-ui.button
                                size="md"
                                variant="primary"
                                type="button"
                                style="{{ $topStyle }}"
                                @click="$dispatch('open-create-modal')"
                            >
                                <i class="{{ $operation->icon }}"></i>
                                <span>{{ $operation->name }}</span>
                            </x-ui.button>
                        @else
                            <x-ui.link-button
                                size="md"
                                variant="primary"
                                style="{{ $topStyle }}"
                                href="{{ $topActionUrl }}"
                            >
                                <i class="{{ $operation->icon }}"></i>
                                <span>{{ $operation->name }}</span>
                            </x-ui.link-button>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span>Total</span>
                    <x-ui.badge size="sm" variant="light" color="info">{{ $operations->total() }}</x-ui.badge>
                </div>
            </div>

            {{-- TABLA DE RESULTADOS --}}
            <div class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <table class="w-full min-w-[880px]">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 text-white">
                                <th style="background-color: #FF4622;" class="px-5 py-3 text-left sm:px-6 first:rounded-tl-xl sticky-left-header"><p class="font-medium text-white text-theme-xs dark:text-white">ID</p></th>
                                <th style="background-color: #FF4622;" class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-white text-theme-xs dark:text-white">Icono</p></th>
                                <th style="background-color: #FF4622;" class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-white text-theme-xs dark:text-white">Nombre</p></th>
                                <th style="background-color: #FF4622;" class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-white text-theme-xs dark:text-white">Tipo</p></th>
                                <th style="background-color: #FF4622;" class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-white text-theme-xs dark:text-white">Acción</p></th>
                                <th style="background-color: #FF4622;" class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-white text-theme-xs dark:text-white">Estado</p></th>
                                <th style="background-color: #FF4622;" class="px-5 py-3 text-right sm:px-6 last:rounded-tr-xl text-white"><p class="font-medium text-white text-theme-xs dark:text-white">Acciones</p></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($operations as $operation)
                                <tr class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                                    
                                    <td class="px-5 py-4 sm:px-6 sticky-left">
                                        <span class="font-bold text-gray-700 dark:text-gray-200">#{{ $operation->id }}</span>
                                    </td>

                                    <td class="px-5 py-4 sm:px-6">
                                        <div class="flex h-10 w-10 items-center justify-center rounded bg-gray-50 border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                                            <i class="{{ $operation->icon }} text-lg" style="color: {{ $operation->color }};"></i>
                                        </div>
                                    </td>
                                    
                                    <td class="px-5 py-4 sm:px-6">
                                        <p class="font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $operation->name }}</p>
                                        <div class="flex items-center gap-1 mt-1">
                                            <span class="w-2 h-2 rounded-full" style="background-color: {{ $operation->color }}"></span>
                                            <span class="text-xs text-gray-400">{{ $operation->color }}</span>
                                        </div>
                                    </td>

                                    <td class="px-5 py-4 sm:px-6">
                                        <x-ui.badge variant="light" color="{{ $operation->type === 'T' ? 'info' : 'success' }}">
                                            {{ $operation->type === 'T' ? 'Tabla' : 'Registro' }}
                                        </x-ui.badge>
                                    </td>

                                    <td class="px-5 py-4 sm:px-6">
                                        <code class="text-xs bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700">
                                            {{ $operation->action }}
                                        </code>
                                    </td>

                                    <td class="px-5 py-4 sm:px-6">
                                        <x-ui.badge variant="light" color="{{ $operation->status ? 'success' : 'error' }}">
                                            {{ $operation->status ? 'Activo' : 'Inactivo' }}
                                        </x-ui.badge>
                                    </td>

                                    <td class="px-5 py-4 sm:px-6">
                                        <div class="flex items-center justify-end gap-2">
                                            @foreach ($rowOperations as $operationRow)
                                                @php
                                                    $action = $operationRow->action ?? '';
                                                    $isDelete = str_contains($action, 'destroy');
                                                    $actionUrl = $resolveActionUrl($action, [$view, $operation], $operationRow);
                                                    $textColor = $resolveTextColor($operationRow);
                                                    $buttonColor = $operationRow->color ?: '#3B82F6';
                                                    $buttonStyle = "background-color: {$buttonColor}; color: {$textColor};";
                                                    $variant = $isDelete ? 'eliminate' : (str_contains($action, 'edit') ? 'edit' : 'primary');
                                                @endphp
                                                @if ($isDelete)
                                                    <form method="POST" action="{{ $actionUrl }}" class="relative group js-delete-item" data-name="{{ $operation->name }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        @if ($viewId)
                                                            <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                        @endif
                                                        <x-ui.button
                                                            size="icon" variant="{{ $variant }}" className="bg-error-500 text-white hover:bg-error-600 ring-0 rounded-xl"
                                                            style="{{ $buttonStyle }}"
                                                            aria-label="{{ $operationRow->name }}"
                                                            type="submit"
                                                        >
                                                            <i class="{{ $operationRow->icon }}"></i>
                                                        </x-ui.button>
                                                        <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">{{ $operationRow->name }}</span>
                                                    </form>
                                                @else
                                                    <div class="relative group">
                                                        <x-ui.link-button
                                                            size="icon" variant="{{ $variant }}"
                                                            href="{{ $actionUrl }}"
                                                            className="rounded-xl"
                                                            style="{{ $buttonStyle }}"
                                                            aria-label="{{ $operationRow->name }}"
                                                        >
                                                            <i class="{{ $operationRow->icon }}"></i>
                                                        </x-ui.link-button>
                                                        <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">{{ $operationRow->name }}</span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                        No hay operaciones registradas para esta vista.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
            </div>

            <div class="mt-4">
                {{ $operations->links() }}
            </div>


        <x-ui.modal x-data="{ open: false }" @open-create-modal.window="open = true" @close-create-modal.window="open = false" :isOpen="false" class="max-w-3xl">
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FF4622]/10 text-[#FF4622] dark:bg-[#FF4622]/20">
                            <i class="ri-add-circle-line text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Nueva Operación</h3>
                            <p class="mt-1 text-sm text-gray-500">Vista asociada: <strong>{{ $view->name }}</strong></p>
                        </div>
                    </div>
                    <button type="button" @click="open = false" class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>

                @if ($errors->any())
                    <div class="mb-5">
                        <x-ui.alert variant="error" title="Revisa los campos" message="Hay errores en el formulario." />
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.views.operations.store', $view) }}" class="space-y-6">
                    @csrf

                    @include('views.operations._form', ['operation' => null, 'viewsList' => $viewsList ?? []])

                    <div class="flex flex-wrap gap-3">
                        <x-ui.button type="submit" size="md" variant="primary">
                            <i class="ri-save-line mr-1"></i>
                            <span>Guardar</span>
                        </x-ui.button>
                        <x-ui.button type="button" size="md" variant="outline" @click="open = false">
                            <i class="ri-close-line mr-1"></i>
                            <span>Cancelar</span>
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </x-ui.modal>

        </x-common.component-card>
    </div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const bindDeleteSweetAlert = () => {
        document.querySelectorAll('.js-delete-item').forEach((form) => {
            if (form.dataset.swalBound === 'true') return;
            form.dataset.swalBound = 'true';

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                const name = form.dataset.name || 'esta operación';

                if (!window.Swal) {
                    form.submit();
                    return;
                }

                Swal.fire({
                    title: '¿Eliminar operación?',
                    text: `Se eliminará "${name}".`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Si, eliminar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280',
                    reverseButtons: true,
                    focusCancel: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    };
    document.addEventListener('DOMContentLoaded', bindDeleteSweetAlert);
    document.addEventListener('turbo:load', bindDeleteSweetAlert);
</script>
@endpush
