@extends('layouts.app')

@php
    use Illuminate\Support\HtmlString;
    
    // --- ICONOS ---
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
                if (str_contains($action, 'views.create')) {
                    return '#111827';
                }
                return '#FFFFFF';
            };
        @endphp
    
    <x-common.page-breadcrumb pageTitle="Vistas" />

    <x-common.component-card title="Gestión de Vistas" desc="Administra las vistas del sistema para asociarlas a los menús.">
        
        {{-- BARRA DE BÚSQUEDA Y ACCIONES --}}
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
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
                        placeholder=" Buscar vista..."
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-10 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                    />
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-ui.button size="md" variant="primary" type="submit" class="flex-1 sm:flex-none h-11 px-4 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95" style="background-color: #C43B25; border-color: #C43B25;">
                        <i class="ri-search-line text-gray-100"></i>
                        <span class="font-medium text-gray-100">Buscar</span>
                    </x-ui.button>
                    <x-ui.link-button size="md" variant="outline" href="{{ route('admin.views.index', $viewId ? ['view_id' => $viewId] : []) }}" class="flex-1 sm:flex-none h-11 px-4 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
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
                        $isCreate = str_contains($operation->action ?? '', 'views.create');
                    @endphp
                    @if ($isCreate)
                        <x-ui.button
                            size="md"
                            variant="primary"
                            type="button"
                            style="{{ $topStyle }}"
                            @click="$dispatch('open-view-modal')"
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
                <x-ui.badge size="sm" variant="light" color="info">{{ $views->total() }}</x-ui.badge>
            </div>
        </div>

        {{-- TABLA DE DATOS --}}
        <div class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full min-w-[880px]">
                    <thead>
                        <tr style="background-color: #FF4622;" class="text-white">
                            <th class="px-3 py-3 text-center sm:px-6 first:rounded-tl-xl sticky-left-header">
                                <p class="font-semibold text-white text-theme-xs">ID</p>
                            </th>
                            <th class="px-5 py-3 text-center sm:px-6"><p class="font-semibold text-white text-theme-xs uppercase">Nombre</p></th>
                            <th class="px-5 py-3 text-center sm:px-6"><p class="font-semibold text-white text-theme-xs uppercase">Abreviatura</p></th>
                            <th class="px-5 py-3 text-center sm:px-6"><p class="font-semibold text-white text-theme-xs uppercase">Estado</p></th>
                            <th class="px-5 py-3 text-center sm:px-6 last:rounded-tr-xl"><p class="font-semibold text-white text-theme-xs uppercase">Acciones</p></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($views as $view)
                            <tr class="group/row border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                                <td class="px-3 py-4 sm:px-6 sticky-left text-center">
                                    <span class="font-bold text-gray-700 dark:text-gray-200 truncate block text-xs" title="#{{ $view->id }}">#{{ $view->id }}</span>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <p class="font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $view->name }}</p>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $view->abbreviation ?? '-' }}</p>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <x-ui.badge variant="light" color="{{ $view->status ? 'success' : 'error' }}">
                                        {{ $view->status ? 'Activo' : 'Inactivo' }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        @foreach ($rowOperations as $operation)
                                            @php
                                                $action = $operation->action ?? '';
                                                $isDelete = str_contains($action, 'destroy');
                                                $actionUrl = $resolveActionUrl($action, $view, $operation);
                                                $textColor = $resolveTextColor($operation);
                                                $buttonColor = $operation->color ?: '#3B82F6';
                                                $buttonStyle = "background-color: {$buttonColor}; color: {$textColor};";
                                                $variant = $isDelete ? 'eliminate' : (str_contains($action, 'edit') ? 'edit' : 'primary');
                                            @endphp
                                            @if ($isDelete)
                                                <form method="POST" action="{{ $actionUrl }}"
                                                    class="relative group js-swal-delete"
                                                    data-swal-title="Eliminar vista?"
                                                    data-swal-text="Se eliminara {{ $view->name }}. Esta accion no se puede deshacer."
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
                                                        className="h-9 w-9 rounded-xl shadow-sm transition-transform active:scale-95 group-hover:shadow-md"
                                                        style="{{ $buttonStyle }}"
                                                        aria-label="{{ $operation->name }}">
                                                        <i class="{{ $operation->icon }} text-lg"></i>
                                                    </x-ui.button>
                                                    <span
                                                        class="invisible group-hover:visible absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-lg bg-gray-900 px-2.5 py-1.5 text-[11px] font-medium text-white shadow-xl z-50">
                                                        {{ $operation->name }}
                                                        <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                    </span>
                                                </form>
                                            @else
                                                <div class="relative group">
                                                    <x-ui.link-button size="icon" variant="{{ $variant }}"
                                                        href="{{ $actionUrl }}"
                                                        className="h-9 w-9 rounded-xl shadow-sm transition-transform active:scale-95 group-hover:shadow-md"
                                                        style="{{ $buttonStyle }}"
                                                        aria-label="{{ $operation->name }}">
                                                        <i class="{{ $operation->icon }} text-lg"></i>
                                                    </x-ui.link-button>
                                                    <span
                                                        class="invisible group-hover:visible absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-lg bg-gray-900 px-2.5 py-1.5 text-[11px] font-medium text-white shadow-xl z-50">
                                                        {{ $operation->name }}
                                                        <span class="absolute top-full left-1/2 -ml-1 border-4 border-transparent border-t-gray-900"></span>
                                                    </span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    No hay vistas registradas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        <div class="mt-4">
            {{ $views->links() }}
        </div>
    </x-common.component-card>

    {{-- 
        MODAL DE CREACIÓN 
        Aquí está la corrección principal:
        1. x-data evalúa si hay errores para iniciar en 'true'.
        2. x-ui.alert muestra el mensaje específico de la excepción.
    --}}
    <x-ui.modal 
        x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }" 
        @open-view-modal.window="open = true" 
        @close-view-modal.window="open = false" 
        :isOpen="false" 
        class="max-w-3xl"
    >
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-gray-400">Sistema</p>
                    <h3 class="mt-2 text-lg font-semibold text-gray-800 dark:text-white/90">Registrar vista</h3>
                    <p class="mt-1 text-sm text-gray-500">Ingresa la información principal de la vista.</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FF4622]/10 text-[#FF4622] dark:bg-[#FF4622]/20">
                    <i class="ri-eye-line"></i>
                </div>
            </div>

            {{-- 
               BLOQUE DE ERRORES CORREGIDO
               Si viene 'error' (del catch), lo muestra. Si no, muestra validación genérica.
            --}}
            @if ($errors->any())
                <div class="mb-5">
                    <x-ui.alert 
                        variant="error" 
                        title="Atención" 
                        message="{{ $errors->first('error') ?: 'Existen errores de validación. Revisa los campos.' }}" 
                    />
                    
                    {{-- Opcional: Listar los errores de campos específicos debajo --}}
                    @if(!$errors->has('error'))
                        <ul class="mt-2 list-disc list-inside text-sm text-red-500">
                            @foreach($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ route('admin.views.store') }}" class="space-y-6">
                @csrf
                @if ($viewId)
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif

                @include('views._form', ['view' => null])

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
