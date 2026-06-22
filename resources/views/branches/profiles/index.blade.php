@extends('layouts.app')

@section('content')
    <div x-data="{}">
        @php
            use Illuminate\Support\Facades\Route;

            $viewId = request('view_id');
            $companyViewId = request('company_view_id');
            $branchViewId = request('branch_view_id') ?? session('branch_view_id');
            $requestIcon = request('icon');
            $pageIconHtml = null;
            if (is_string($requestIcon) && preg_match('/^ri-[a-z0-9-]+$/', $requestIcon)) {
                $pageIconHtml = '<i class="' . $requestIcon . '"></i>';
            }
            $operacionesCollection = collect($operaciones ?? []);
            $topOperations = $operacionesCollection->where('type', 'T');
            $rowOperations = $operacionesCollection->where('type', 'R');

            $resolveActionUrl = function ($action, array $routeParams = [], $operation = null) use ($viewId, $companyViewId, $branchViewId, $requestIcon) {
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
                    if (str_starts_with($action, 'branches.')) {
                        $routeCandidates[] = 'admin.companies.' . $action;
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
                        if (count($routeParams) > 0) {
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

                if ($branchViewId && $url !== '#' && !str_contains($url, 'branch_view_id=')) {
                    $separator = str_contains($url, '?') ? '&' : '?';
                    $url .= $separator . 'branch_view_id=' . urlencode($branchViewId);
                }

                if ($viewId && $url !== '#' && $targetViewId && $targetViewId !== $viewId && !str_contains($url, 'profile_view_id=')) {
                    $separator = str_contains($url, '?') ? '&' : '?';
                    $url .= $separator . 'profile_view_id=' . urlencode($viewId);
                }

                if ($companyViewId && $url !== '#' && !str_contains($url, 'company_view_id=')) {
                    $separator = str_contains($url, '?') ? '&' : '?';
                    $url .= $separator . 'company_view_id=' . urlencode($companyViewId);
                }

                if ($requestIcon && $url !== '#' && !str_contains($url, 'icon=')) {
                    $separator = str_contains($url, '?') ? '&' : '?';
                    $url .= $separator . 'icon=' . urlencode($requestIcon);
                }

                return $url;
            };

            $resolveTextColor = function ($operation) {
                $action = $operation->action ?? '';
                if (str_contains($action, 'profiles.create')) {
                    return '#111827';
                }
                return '#FFFFFF';
            };
        @endphp
        <x-common.page-breadcrumb
            pageTitle="Perfiles"
            :iconHtml="$pageIconHtml"
            :crumbs="[
                ['label' => 'Empresas', 'url' => route('admin.companies.index', $companyViewId ? ['view_id' => $companyViewId] : [])],
                ['label' =>  $company->legal_name . ' | Sucursales', 'url' => route('admin.companies.branches.index', array_merge([$company], array_filter(['view_id' => $branchViewId ?: $viewId, 'company_view_id' => $companyViewId, 'icon' => $requestIcon])))],
                ['label' =>  $branch->legal_name . ' | Perfiles' ]
            ]"
        />

        <x-common.component-card
            title="Perfiles de {{ $branch->legal_name }}"
            desc="Lista los perfiles asignados a esta sucursal."
        >
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    @if ($companyViewId)
                        <input type="hidden" name="company_view_id" value="{{ $companyViewId }}">
                    @endif
                    @if ($branchViewId)
                        <input type="hidden" name="branch_view_id" value="{{ $branchViewId }}">
                    @endif
                    @if ($requestIcon)
                        <input type="hidden" name="icon" value="{{ $requestIcon }}">
                    @endif
                    <x-ui.per-page-selector :per-page="$perPage" />
                    <div class="relative flex-1">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="ri-search-line"></i>
                        </span>
                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Buscar por nombre"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-10 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button size="md" variant="primary" type="submit" class="flex-1 sm:flex-none h-11 px-4 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95" style="background-color: #C43B25; border-color: #C43B25;">
                            <i class="ri-search-line text-gray-100"></i>
                            <span class="font-medium text-gray-100">Buscar</span>
                        </x-ui.button>
                        <x-ui.link-button size="md" variant="outline" href="{{ route('admin.companies.branches.profiles.index', array_merge([$company, $branch], array_filter(['view_id' => $viewId, 'company_view_id' => $companyViewId, 'branch_view_id' => $branchViewId, 'icon' => $requestIcon]))) }}" class="flex-1 sm:flex-none h-11 px-4 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
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
                            $action = $operation->action ?? '';
                            $isPermissionsIndex = str_contains($action, 'profiles.permissions.index');
                            $routeParams = [$company, $branch];
                            if ($isPermissionsIndex && $profiles->isNotEmpty()) {
                                $routeParams[] = $profiles->first();
                            }
                            $topActionUrl = $resolveActionUrl($action, $routeParams, $operation);
                            $isCreate = str_contains($action, 'profiles.create');
                            $isAssign = str_contains($action, 'profiles.assign')
                                || $action === 'admin.companies.branches.profiles.index';
                        @endphp
                        @if ($isCreate)
                            <x-ui.button size="md" variant="primary" type="button"
                                style="{{ $topStyle }}" @click="$dispatch('open-profile-modal')">
                                <i class="{{ $operation->icon }}"></i>
                                <span>{{ $operation->name }}</span>
                            </x-ui.button>
                        @elseif ($isAssign)
                            <x-ui.button size="md" variant="primary" type="button"
                                style="{{ $topStyle }}" @click="$dispatch('open-assign-profiles')">
                                <i class="{{ $operation->icon }}"></i>
                                <span>{{ $operation->name }}</span>
                            </x-ui.button>
                        @else
                            <x-ui.link-button size="md" variant="primary"
                                style="{{ $topStyle }}"
                                href="{{ $topActionUrl }}">
                                <i class="{{ $operation->icon }}"></i>
                                <span>{{ $operation->name }}</span>
                            </x-ui.link-button>
                        @endif
                    @endforeach
                    <x-ui.link-button
                        size="md"
                        variant="outline"
                        href="{{ route('admin.companies.branches.index', array_merge([$company], array_filter(['view_id' => $branchViewId ?: $viewId, 'company_view_id' => $companyViewId, 'icon' => $requestIcon]))) }}"
                    >
                        <i class="ri-arrow-left-line"></i>
                        <span>Volver a sucursales</span>
                    </x-ui.link-button>
                </div>
            </div>

            <div class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full min-w-max">
                    <thead>
                        <tr class="text-white">
                            <th style="background-color: #FF4622;" class="px-5 py-3 text-center sm:px-6 first:rounded-tl-xl sticky-left-header">
                                <p class="font-semibold text-gray-100 text-theme-xs uppercase">Nombre</p>
                            </th>
                            <th style="background-color: #FF4622;" class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-gray-100 text-theme-xs uppercase">Estado</p>
                            </th>
                            <th style="background-color: #FF4622;" class="px-5 py-3 text-center sm:px-6 last:rounded-tr-xl">
                                <p class="font-semibold text-gray-100 text-theme-xs uppercase">Acciones</p>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($profiles as $profile)
                            <tr class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                                <td class="px-5 py-4 sm:px-6 text-center sticky-left">
                                    <div class="space-y-1">
                                        <p class="font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $profile->name }}</p>
                                    </div>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <x-ui.badge variant="light" color="{{ $profile->status ? 'success' : 'error' }}">
                                        {{ $profile->status ? 'Activo' : 'Inactivo' }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        @foreach ($rowOperations as $operation)
                                            @php
                                                $action = $operation->action ?? '';
                                                $isDelete = str_contains($action, 'destroy');
                                                $actionUrl = $resolveActionUrl($action, [$company, $branch, $profile], $operation);
                                                $textColor = $resolveTextColor($operation);
                                                $buttonColor = $operation->color ?: '#3B82F6';
                                                $buttonStyle = "background-color: {$buttonColor}; color: {$textColor};";
                                                $variant = $isDelete ? 'eliminate' : (str_contains($action, 'edit') ? 'edit' : 'primary');
                                            @endphp
                                            @if ($isDelete)
                                                <form method="POST" action="{{ $actionUrl }}"
                                                    class="relative group js-swal-delete"
                                                    data-swal-title="¿Eliminar perfil?"
                                                    data-swal-text="Se eliminara {{ $profile->name }}. Esta accion no se puede deshacer."
                                                    data-swal-confirm="Sí, eliminar"
                                                    data-swal-cancel="Cancelar"
                                                    data-swal-confirm-color="#ef4444"
                                                    data-swal-cancel-color="#6b7280">
                                                    @csrf
                                                    @method('DELETE')
                                                    @if ($viewId)
                                                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                    @endif
                                                    <x-ui.button
                                                        size="icon"
                                                        variant="{{ $variant }}"
                                                        type="submit"
                                                        className="h-9 w-9 rounded-xl shadow-sm transition-transform active:scale-95 group-hover:shadow-md"
                                                        style="{{ $buttonStyle }}"
                                                        aria-label="{{ $operation->name }}"
                                                    >
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
                                                    <x-ui.link-button
                                                        size="icon"
                                                        variant="{{ $variant }}"
                                                        href="{{ $actionUrl }}"
                                                        className="h-9 w-9 rounded-xl shadow-sm transition-transform active:scale-95 group-hover:shadow-md"
                                                        style="{{ $buttonStyle }}"
                                                        aria-label="{{ $operation->name }}"
                                                    >
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
                                <td colspan="3" class="px-6 py-12">
                                    <div class="flex flex-col items-center gap-3 text-center text-sm text-gray-500">
                                        <div class="rounded-full bg-gray-100 p-3 text-gray-400 dark:bg-gray-800 dark:text-gray-300">
                                            <i class="ri-user-settings-line"></i>
                                        </div>
                                        <p class="text-base font-semibold text-gray-700 dark:text-gray-200">No hay perfiles asignados.</p>
                                        <p class="text-gray-500">Todos los perfiles creados se asignan automaticamente.</p>
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
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $profiles->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $profiles->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $profiles->total() }}</span>
                </div>
                <div>
                    {{ $profiles->links() }}
                </div>
            </div>
        </x-common.component-card>

        {{-- Modal: Asignar perfiles a la sucursal --}}
        <x-ui.modal x-data="{ open: false }" @open-assign-profiles.window="open = true"
            @close-assign-profiles.window="open = false" :isOpen="false" class="max-w-2xl">
            <div class="flex h-[70vh] flex-col overflow-hidden p-6 sm:p-8">
                    <div class="mb-6 flex shrink-0 items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FF4622]/10 text-[#FF4622] dark:bg-[#FF4622]/20">
                                <i class="ri-user-add-line text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Asignar perfiles</h3>
                                <p class="mt-1 text-sm text-gray-500">Selecciona los perfiles para <strong>{{ $branch->legal_name }}</strong>.</p>
                            </div>
                        </div>
                        <button type="button" @click="open = false"
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700 dark:hover:text-white">
                            <i class="ri-close-line text-xl"></i>
                        </button>
                    </div>

                    <form method="POST"
                        action="{{ route('admin.companies.branches.profiles.assign', [$company, $branch]) }}"
                        class="flex min-h-0 flex-1 flex-col gap-4 overflow-hidden">
                        @csrf
                        @if ($viewId)
                            <input type="hidden" name="view_id" value="{{ $viewId }}">
                        @endif

                        <div class="min-h-0 flex-1 overflow-y-auto rounded-xl border border-gray-200 bg-white custom-scrollbar dark:border-gray-800 dark:bg-white/[0.03]">
                            <table class="w-full">
                                <thead class="sticky top-0 z-10" style="background-color: #FF4622; color: #FFFFFF;">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase first:rounded-tl-xl">
                                            Asignar
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase">
                                            Nombre
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase last:rounded-tr-xl">
                                            Estado
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse ($allProfiles as $p)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                            <td class="px-4 py-3">
                                                <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                                    <input type="checkbox" name="profiles[]" value="{{ $p->id }}"
                                                        @checked(in_array($p->id, $assignedProfileIds ?? [], true))
                                                        class="h-4 w-4 rounded border-gray-300 text-[#FF4622] focus:ring-brand-500/10" />
                                                    <span>Asignar</span>
                                                </label>
                                            </td>
                                            <td class="px-4 py-3">
                                                <p class="font-medium text-gray-800 text-sm dark:text-white/90">{{ $p->name }}</p>
                                            </td>
                                            <td class="px-4 py-3">
                                                <x-ui.badge variant="light" color="{{ $p->status ? 'success' : 'error' }}">
                                                    {{ $p->status ? 'Activo' : 'Inactivo' }}
                                                </x-ui.badge>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-6 py-10 text-center text-sm text-gray-500">
                                                No hay perfiles creados en el sistema.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="flex shrink-0 gap-3 border-t pt-4">
                            <x-ui.button type="submit" size="md" variant="primary">
                                <i class="ri-save-line"></i>
                                <span>Guardar cambios</span>
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
