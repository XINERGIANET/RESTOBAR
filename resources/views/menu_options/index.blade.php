@extends('layouts.app')

@php
    use App\Helpers\MenuHelper;
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
                    if (str_starts_with($action, 'menu_options.')) {
                        $routeCandidates[] = 'modules.' . $action;
                        $routeCandidates[] = 'admin.modules.' . $action;
                    }
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
                        $attempts = [];
                        if (!empty($routeParams)) {
                            $attempts[] = $routeParams;
                        }
                        if (count($routeParams) > 1) {
                            $attempts[] = array_slice($routeParams, 0, 2);
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
        @endphp
        <x-common.page-breadcrumb
            pageTitle="Opciones de Menú"
            :breadcrumbs="[
                ['name' => 'Módulos', 'href' => route('admin.modules.index')],
                ['name' => $module->name, 'href' => '#'],
                ['name' => 'Opciones', 'href' => route('admin.modules.menu_options.index', $module)],
            ]"
        />

        <x-common.component-card
            title="Opciones: {{ $module->name }}"
            desc="Gestiona los sub-menús asociados a este módulo."
        >
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                
                <form method="GET" action="{{ route('admin.modules.menu_options.index', $module) }}" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    <div class="relative flex-1">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                           <i class="ri-search-line"></i>
                        </span>
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Buscar por nombre o ruta..."
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-10 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button size="md" variant="primary" type="submit" class="flex-1 sm:flex-none h-11 px-4 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95" style="background-color: #C43B25; border-color: #C43B25;">
                            <i class="ri-search-line text-gray-100"></i>
                            <span class="font-medium text-gray-100">Buscar</span>
                        </x-ui.button>
                        <x-ui.link-button size="md" variant="outline" href="{{ route('admin.modules.menu_options.index', $viewId ? [$module, 'view_id' => $viewId] : $module) }}" class="flex-1 sm:flex-none h-11 px-4 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                            <i class="ri-refresh-line"></i>
                            <span class="font-medium">Limpiar</span>
                        </x-ui.link-button>
                    </div>
                </form>

                <div class="flex gap-2">
                    <x-ui.link-button size="md" variant="outline" href="{{ route('admin.modules.index', $viewId ? ['view_id' => $viewId] : []) }}">
                        <i class="ri-arrow-left-line"></i> Volver
                    </x-ui.link-button>

                    @if ($topOperations->isNotEmpty())
                        @foreach ($topOperations as $operation)
                            @php
                                $topColor = $operation->color ?: '#12f00e';
                                $topStyle = "background-color: {$topColor}; color: #111827;";
                                $actionValue = $operation->action ?? '';
                                $isCreate = ($operation->type ?? '') === 'T'
                                    || str_contains($actionValue, 'menu_options.create')
                                    || str_contains($actionValue, 'menu_options.store')
                                    || str_contains($actionValue, 'create')
                                    || str_contains($actionValue, 'open-create-modal')
                                    || str_contains(strtolower($operation->name ?? ''), 'nuevo');
                                $topActionUrl = $resolveActionUrl($operation->action ?? '', [$module], $operation);
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
                                <x-ui.link-button size="md" variant="primary" style="{{ $topStyle }}" href="{{ $topActionUrl }}">
                                    <i class="{{ $operation->icon }}"></i>
                                    <span>{{ $operation->name }}</span>
                                </x-ui.link-button>
                            @endif
                        @endforeach
                 
                     
                    @endif
                </div>
            </div>

            {{-- TOTALIZADOR --}}
            <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span>Total Opciones</span>
                    <x-ui.badge size="sm" variant="light" color="info">{{ $menuOptions->total() }}</x-ui.badge>
                </div>
            </div>

            {{-- TABLA DE RESULTADOS --}}
            <div class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <table class="w-full min-w-max">
                        <thead>
                            <tr class="text-white">
                                <th style="background-color: #FF4622;" class="px-5 py-3 text-left sm:px-6 first:rounded-tl-xl sticky-left-header"><p class="font-semibold text-gray-100 text-theme-xs uppercase">Orden (ID)</p></th>
                                <th style="background-color: #FF4622;" class="px-5 py-3 text-left sm:px-6"><p class="font-semibold text-gray-100 text-theme-xs uppercase">Icono</p></th>
                                <th style="background-color: #FF4622;" class="px-5 py-3 text-left sm:px-6"><p class="font-semibold text-gray-100 text-theme-xs uppercase">Nombre</p></th>
                                <th style="background-color: #FF4622;" class="px-5 py-3 text-left sm:px-6"><p class="font-semibold text-gray-100 text-theme-xs uppercase">Ruta / Acción</p></th>
                                <th style="background-color: #FF4622;" class="px-5 py-3 text-left sm:px-6"><p class="font-semibold text-gray-100 text-theme-xs uppercase">Estado</p></th>
                                <th style="background-color: #FF4622;" class="px-5 py-3 text-right sm:px-6 last:rounded-tr-xl"><p class="font-semibold text-gray-100 text-theme-xs uppercase">Acciones</p></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($menuOptions as $option)
                                <tr class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                                    <td class="px-5 py-4 sm:px-6">
                                        <span class="font-bold text-gray-700 dark:text-gray-200">#{{ $option->id }}</span>
                                    </td>
                                    <td class="px-5 py-4 sm:px-6">
                                        <div class="flex h-8 w-8 items-center justify-center rounded bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                            @if(class_exists('App\Helpers\MenuHelper'))
                                                <span class="w-5 h-5 fill-current">{!! MenuHelper::getIconSvg($option->icon) !!}</span>
                                            @else
                                                <i class="{{ $option->icon }}"></i>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 sm:px-6">
                                        <p class="font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $option->name }}</p>
                                        @if($option->quick_access)
                                            <span class="mt-1 inline-block text-[10px] text-[#FF4622] bg-[#FF4622]/10 px-1 rounded border border-brand-100">Acceso Rápido</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 sm:px-6">
                                        <code class="text-xs bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded text-gray-600 dark:text-gray-400">
                                            {{ $option->action }}
                                        </code>
                                    </td>
                                    <td class="px-5 py-4 sm:px-6">
                                        <x-ui.badge variant="light" color="{{ $option->status ? 'success' : 'error' }}">
                                            {{ $option->status ? 'Activo' : 'Inactivo' }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="px-5 py-4 sm:px-6">
                                        <div class="flex items-center justify-end gap-2">
                                            @if ($rowOperations->isNotEmpty())
                                                @foreach ($rowOperations as $operation)
                                                    @php
                                                        $action = $operation->action ?? '';
                                                        $isDelete = str_contains($action, 'destroy');
                                                        $actionUrl = $resolveActionUrl($action, [$module, $option], $operation);
                                                        if ($actionUrl === '#' && str_contains($action, 'edit')) {
                                                            $actionUrl = route('admin.modules.menu_options.edit', [$module, $option, 'view_id' => $viewId]);
                                                        }
                                                        if ($actionUrl === '#' && str_contains($action, 'destroy')) {
                                                            $actionUrl = route('admin.modules.menu_options.destroy', [$module, $option, 'view_id' => $viewId]);
                                                        }
                                                        $textColor = '#FFFFFF';
                                                        $buttonColor = $operation->color ?: '#3B82F6';
                                                        $buttonStyle = "background-color: {$buttonColor}; color: {$textColor};";
                                                        $variant = $isDelete ? 'eliminate' : (str_contains($action, 'edit') ? 'edit' : 'primary');
                                                    @endphp
                                                    @if ($isDelete)
                                                        <form
                                                            method="POST"
                                                            action="{{ $actionUrl }}"
                                                            class="relative group js-delete-item"
                                                            data-name="{{ $option->name }}"
                                                        >
                                                            @csrf
                                                            @method('DELETE')
                                                            @if ($viewId)
                                                                <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                            @endif
                                                            <x-ui.button
                                                                size="icon"
                                                                variant="{{ $variant }}"
                                                                className="bg-error-500 text-white hover:bg-error-600 ring-0 rounded-xl"
                                                                style="{{ $buttonStyle }}"
                                                                aria-label="{{ $operation->name }}"
                                                                type="submit"
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
                                                                className="bg-warning-500 text-white hover:bg-warning-600 ring-0 rounded-xl"
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
                                                {{-- Editar --}}
                                                <div class="relative group">
                                                    <x-ui.link-button
                                                        size="icon"
                                                        variant="outline"
                                                        href="{{ route('admin.modules.menu_options.edit', [$module, $option, 'view_id' => $viewId]) }}"
                                                        className="bg-warning-500 text-white hover:bg-warning-600 ring-0 rounded-full"
                                                        style="border-radius: 100%; background-color: #FBBF24; color: #111827;"
                                                        aria-label="Editar"
                                                    >
                                                        <i class="ri-pencil-line"></i>
                                                    </x-ui.link-button>
                                                    <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50">Editar</span>
                                                </div>

                                                {{-- Eliminar --}}
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.modules.menu_options.destroy', [$module, $option, 'view_id' => $viewId]) }}"
                                                    class="relative group js-delete-item"
                                                    data-name="{{ $option->name }}"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-ui.button
                                                        size="icon"
                                                        variant="outline"
                                                        className="bg-error-500 text-white hover:bg-error-600 ring-0 rounded-full"
                                                        style="border-radius: 100%; background-color: #EF4444; color: #FFFFFF;"
                                                        aria-label="Eliminar"
                                                        type="submit"
                                                    >
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
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="rounded-full bg-gray-100 p-3 text-gray-400 dark:bg-gray-800 dark:text-gray-300">
                                                <i class="ri-list-settings-line text-2xl"></i>
                                            </div>
                                            <p class="text-base font-semibold text-gray-700 dark:text-gray-200">No hay opciones registradas</p>
                                            <p class="text-gray-500">Agrega la primera opción al menú de este módulo.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
            </div>

            <div class="mt-4">
                {{ $menuOptions->links() }}
            </div>
        </x-common.component-card>

        <x-ui.modal x-data="{ open: false }" @open-create-modal.window="open = true" @close-create-modal.window="open = false" :isOpen="false" class="max-w-3xl">
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FF4622]/10 text-[#FF4622] dark:bg-[#FF4622]/20">
                            <i class="ri-menu-add-line text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar Opción de Menú</h3>
                            <p class="mt-1 text-sm text-gray-500">Módulo actual: <strong>{{ $module->name }}</strong></p>
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

                <form method="POST" action="{{ route('admin.modules.menu_options.store', $module) }}" class="space-y-6">
                    @csrf
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    @include('menu_options._form', ['menuOption' => null, 'views' => $views])

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

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const bindDeleteSweetAlert = () => {
        document.querySelectorAll('.js-delete-item').forEach((form) => {
            if (form.dataset.swalBound === 'true') return;
            form.dataset.swalBound = 'true';

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                const name = form.dataset.name || 'este elemento';

                if (!window.Swal) {
                    form.submit();
                    return;
                }

                Swal.fire({
                    title: '¿Eliminar opción?',
                    text: `Se eliminará "${name}".`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
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
@endsection
