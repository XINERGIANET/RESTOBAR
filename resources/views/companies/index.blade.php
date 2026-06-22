@extends('layouts.app')

@section('content')
    <div x-data="{}">
        @php
            use Illuminate\Support\Facades\Route;

            $viewId = request('view_id');
            $operacionesCollection = collect($operaciones ?? []);
            $topOperations = $operacionesCollection->where('type', 'T');
            $rowOperations = $operacionesCollection->where('type', 'R');

            $resolveActionUrl = function ($action, $company = null, $operation = null) use ($viewId) {
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
                            $url = $company ? route($routeName, $company) : route($routeName);
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

                if ($viewId && $operation && !empty($operation->view_id_action) && $url !== '#') {
                    $separator = str_contains($url, '?') ? '&' : '?';
                    $url .= $separator . 'company_view_id=' . urlencode($viewId);
                }

                if ($operation && !empty($operation->icon) && str_contains($action, 'branches') && $url !== '#') {
                    $separator = str_contains($url, '?') ? '&' : '?';
                    $url .= $separator . 'icon=' . urlencode($operation->icon);
                }

                return $url;
            };

            $resolveTextColor = function ($operation) {
                $action = $operation->action ?? '';
                if ($action === 'companies.create') {
                    return '#111827';
                }
                return '#FFFFFF';
            };
        @endphp

        <x-common.page-breadcrumb pageTitle="Empresas" />


        <x-common.component-card title="Listado de empresas" desc="Gestiona las empresas registradas en el sistema.">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between mb-6">
                <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center min-w-0">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    <div class="w-auto flex-none">
                        <label class="mb-1.5 block text-xs font-medium text-gray-500 sm:hidden">Por página</label>
                        <select name="per_page"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            onchange="this.form.submit()">
                            @foreach ([10, 20, 50, 100] as $size)
                                <option value="{{ $size }}" @selected($perPage == $size)>{{ $size }} /
                                    pág</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="relative min-w-0 flex-1">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="ri-search-line"></i>
                        </span>
                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Buscar por razón social, RUC o dirección"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-10 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>
                    <div class="flex flex-none flex-wrap gap-2">
                        <x-ui.button size="md" variant="primary" type="submit" class="flex-1 sm:flex-none h-11 px-4 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95" style="background-color: #C43B25; border-color: #C43B25;">
                            <i class="ri-search-line text-gray-100"></i>
                            <span class="font-medium text-gray-100">Buscar</span>
                        </x-ui.button>
                        <x-ui.link-button size="md" variant="outline" href="{{ route('admin.companies.index', $viewId ? ['view_id' => $viewId] : []) }}" class="flex-1 sm:flex-none h-11 px-4 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                            <i class="ri-refresh-line"></i>
                            <span class="font-medium">Limpiar</span>
                        </x-ui.link-button>
                    </div>
                </form>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-3 flex-none ml-auto w-full sm:w-auto">
                    @foreach ($topOperations as $operation)
                        @php
                            $topTextColor = $resolveTextColor($operation);
                            $topColor = $operation->color ?: '#FF4622';
                            $topStyle = "background-color: {$topColor}; color: {$topTextColor};";
                            $topActionUrl = $resolveActionUrl($operation->action ?? '', null, $operation);
                        @endphp
                        @if ($operation->action === 'companies.create')
                            <x-ui.link-button size="sm" variant="primary"
                                class="w-full sm:w-auto flex-1 sm:flex-none h-11 px-4 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200"
                                style="{{ $topStyle }}"
                                href="{{ route('admin.companies.create', $viewId ? ['view_id' => $viewId] : []) }}">
                                <i class="{{ $operation->icon }} text-lg"></i>
                                <span>{{ $operation->name }}</span>
                            </x-ui.link-button>
                        @else
                            <x-ui.link-button size="sm" variant="primary"
                                class="w-full sm:w-auto flex-1 sm:flex-none h-11 px-4 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200"
                                style="{{ $topStyle }}"
                                href="{{ $topActionUrl }}"> 
                                <i class="{{ $operation->icon }} text-lg"></i>
                                <span>{{ $operation->name }}</span>
                            </x-ui.link-button>
                        @endif
                    @endforeach
                </div>
            </div>


            <div
                class="table-responsive rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full min-w-max">
                        <thead>
                            <tr style="background-color: #FF4622;" class="text-white">
                                <th class="px-3 py-4 text-center whitespace-nowrap first:rounded-tl-xl sticky-left-header w-32 max-w-[128px] sm:w-auto sm:max-w-none">
                                    <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">Razón social</p>
                                </th>
                                <th class="px-5 py-4 text-center whitespace-nowrap">
                                    <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">RUC</p>
                                </th>
                                <th class="px-5 py-4 text-center whitespace-nowrap">
                                    <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">Dirección</p>
                                </th>
                                <th class="px-5 py-4 text-center whitespace-nowrap last:rounded-tr-xl">
                                    <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">Acciones</p>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($companies as $company)
                                <tr class="group/row transition hover:bg-gray-50/80 dark:hover:bg-white/5">
                                    <td class="px-3 py-4 text-center whitespace-nowrap w-32 sm:sticky-left-header max-w-[128px] sm:w-auto sm:max-w-none">
                                        <div class="flex items-center justify-center gap-2">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-600 dark:bg-gray-800/50 dark:text-gray-400 shrink-0">
                                                <i class="ri-building-line text-sm"></i>
                                            </div>
                                            <p class="font-semibold text-gray-800 text-theme-sm dark:text-white/90 text-center" title="{{ $company->legal_name }}">
                                                {{ $company->legal_name }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                            {{ $company->tax_id }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-center whitespace-nowrap">
                                        <p class="text-gray-600 text-theme-sm dark:text-gray-400 line-clamp-1" title="{{ $company->address }}">
                                            {{ $company->address }}
                                        </p>
                                    </td>
                                    <td class="px-5 py-4 text-center whitespace-nowrap">
                                        <div class="flex items-center justify-center gap-2">
                                            @foreach ($rowOperations as $operation)
                                                @php
                                                    $action = $operation->action ?? '';
                                                    $isDelete = str_contains($action, 'destroy');
                                                    $actionUrl = $resolveActionUrl($action, $company, $operation);
                                                    $textColor = $resolveTextColor($operation);
                                                    $buttonColor = $operation->color ?: '#FF4622';
                                                    $buttonStyle = "background-color: {$buttonColor}; color: {$textColor};";
                                                    $variant = $isDelete ? 'eliminate' : (str_contains($action, 'edit') ? 'edit' : 'primary');
                                                @endphp
                                                @if ($isDelete)
                                                    <form method="POST" action="{{ $actionUrl }}"
                                                        class="relative group js-swal-delete" data-swal-title="Eliminar empresa?"
                                                        data-swal-text="Se eliminará {{ $company->legal_name }}. Esta acción no se puede deshacer."
                                                        data-swal-confirm="Sí, eliminar" data-swal-cancel="Cancelar"
                                                        data-swal-confirm-color="#ef4444" data-swal-cancel-color="#6b7280">
                                                        @csrf
                                                        @method('DELETE')
                                                        @if ($viewId)
                                                            <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                        @endif
                                                        <x-ui.button size="icon" variant="{{ $variant }}" type="submit"
                                                            className="h-9 w-9 rounded-xl shadow-sm transform transition duration-150 hover:-translate-y-0.5 hover:shadow-md active:scale-95"
                                                            style="{{ $buttonStyle }}"
                                                            aria-label="{{ $operation->name }}">
                                                            <i class="{{ $operation->icon }} text-lg"></i>
                                                        </x-ui.button>
                                                        <span
                                                            class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.3s;">
                                                            {{ $operation->name }}
                                                        </span>
                                                    </form>
                                                @else
                                                    <div class="relative group">
                                                        <x-ui.link-button size="icon" variant="{{ $variant }}"
                                                            href="{{ $actionUrl }}"
                                                            className="h-9 w-9 rounded-xl shadow-sm transform transition duration-150 hover:-translate-y-0.5 hover:shadow-md active:scale-95"
                                                            style="{{ $buttonStyle }}"
                                                            aria-label="{{ $operation->name }}">
                                                            <i class="{{ $operation->icon }} text-lg"></i>
                                                        </x-ui.link-button>
                                                    <span
                                                        class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.3s;">
                                                        {{ $operation->name }}
                                                    </span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-16">
                                        <div class="flex flex-col items-center gap-4 text-center">
                                            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-50 text-gray-400 dark:bg-gray-800/50 dark:text-gray-600">
                                                <i class="ri-building-line text-3xl"></i>
                                            </div>
                                            <div class="space-y-1">
                                                <p class="text-base font-semibold text-gray-800 dark:text-white/90">No hay empresas registradas</p>
                                                <p class="text-sm text-gray-500">Comienza registrando tu primera empresa para gestionar el sistema.</p>
                                            </div>
                                            <x-ui.link-button size="md" variant="primary" class="mt-2"
                                                href="{{ route('admin.companies.create', $viewId ? ['view_id' => $viewId] : []) }}">
                                                <i class="ri-add-line text-lg"></i>
                                                <span>Registrar empresa</span>
                                            </x-ui.link-button>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                </table>
            </div>


            <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Mostrando
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $companies->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $companies->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $companies->total() }}</span>
                </div>
                <div class="flex-none pagination-simple">
                    {{ $companies->links('components.ui.pagination-simple-rounded') }}
                </div>
            </div>

        </x-common.component-card>


        <x-ui.modal x-data="{ open: false }" @open-company-modal.window="open = true"
            @close-company-modal.window="open = false" :isOpen="false" :showCloseButton="false" class="max-w-3xl">
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between relative">
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FF4622]/10 text-[#FF4622] dark:bg-[#FF4622]/20">
                            <i class="ri-building-line text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar empresa</h3>
                            <p class="mt-1 text-sm text-gray-500">Ingresa la informacion principal de la empresa.</p>
                        </div>
                    </div>
                    <button type="button" @click="open = false"
                        class="flex h-9 w-9 sm:h-11 sm:w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white absolute right-0 top-0 sm:static"
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

                <form method="POST" action="{{ route('admin.companies.store') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif

                    @include('companies._form', ['company' => null])

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
