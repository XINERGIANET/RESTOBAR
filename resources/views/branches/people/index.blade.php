@extends('layouts.app')

@section('content')
    <div x-data="{ openRow: null }">
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

                return $url;
            };

            $resolveTextColor = function ($operation) {
                $action = $operation->action ?? '';
                if (str_contains($action, 'people.create')) {
                    return '#111827';
                }
                return '#FFFFFF';
            };

            $isFromConfig = request('from_config');
            $crumbs = $isFromConfig ? [
                ['label' => 'Configuración', 'url' => '#'],
                ['label' => 'Personal']
            ] : [
                ['label' => 'Empresas', 'url' => route('admin.companies.index', $companyViewId ? ['view_id' => $companyViewId] : [])],
                ['label' =>  $company->legal_name . ' | Sucursales', 'url' => route('admin.companies.branches.index', array_merge([$company], array_filter(['view_id' => $branchViewId ?: $viewId, 'company_view_id' => $companyViewId, 'icon' => $requestIcon])))],
                ['label' =>  $branch->legal_name . ' | Personal' ]
            ];
        @endphp
        <x-common.page-breadcrumb
            pageTitle="Personal"
            :iconHtml="$pageIconHtml"
            :crumbs="$crumbs"
        />

        <x-common.component-card
            title="Personal de {{ $branch->legal_name }}"
            desc="Gestiona el personal asociado a esta sucursal."
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
                    @if ($isFromConfig)
                        <input type="hidden" name="from_config" value="1">
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
                            placeholder="Buscar por nombre, documento..."
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-10 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>

                    <div class="relative min-w-[160px]">
                        <select
                            name="role_id"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-700 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        >
                            <option value="">Role: Todos</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" {{ $roleId == $role->id ? 'selected' : '' }}>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="relative min-w-[160px]">
                        <select
                            name="profile_id"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-700 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        >
                            <option value="">Perfil: Todos</option>
                            @foreach ($profiles as $profile)
                                <option value="{{ $profile->id }}" {{ $profileIdParam == $profile->id ? 'selected' : '' }}>
                                    {{ $profile->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button size="md" variant="primary" type="submit" class="flex-1 sm:flex-none h-11 px-4 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95" style="background-color: #C43B25; border-color: #C43B25;">
                            <i class="ri-search-line text-gray-100"></i>
                            <span class="font-medium text-gray-100">Buscar</span>
                        </x-ui.button>
                        <x-ui.link-button size="md" variant="outline" href="{{ $isFromConfig ? route('configuracion.personal.index', array_filter(['view_id' => $viewId, 'icon' => $requestIcon, 'from_config' => 1])) : route('admin.companies.branches.people.index', array_merge([$company, $branch], array_filter(['view_id' => $viewId, 'company_view_id' => $companyViewId, 'branch_view_id' => $branchViewId, 'icon' => $requestIcon]))) }}" class="flex-1 sm:flex-none h-11 px-4 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
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
                            $topActionUrl = $resolveActionUrl($operation->action ?? '', [$company, $branch], $operation);
                            $isCreate = str_contains($operation->action ?? '', 'people.create');
                        @endphp
                        @if ($isCreate)
                            <x-ui.button
                                size="md"
                                variant="primary"
                                type="button"
                                style="{{ $topStyle }}"
                                @click="$dispatch('open-person-modal')"
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

            <div class="table-responsive mt-4 rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full min-w-max border-separate border-spacing-0">
                    <thead class="text-white">
                        <tr style="background-color: #FF4622;" class="text-white text-theme-xs uppercase tracking-wider">
                            <th class="w-12 px-4 py-3 text-center first:rounded-tl-2xl sticky-left-header"></th>
                            <th class="px-5 py-3 text-left sm:px-6 whitespace-nowrap">Documento</th>
                            <th class="px-3 py-3 text-left sm:px-6 whitespace-nowrap">Nombres</th>
                            <th class="px-5 py-3 text-left sm:px-6 whitespace-nowrap">Teléfono</th>
                            <th class="px-5 py-3 text-left sm:px-6 whitespace-nowrap">Perfil</th>
                            <th class="px-5 py-3 text-left sm:px-6 whitespace-nowrap">Rol</th>
                            <th class="px-5 py-3 text-right sm:px-6 whitespace-nowrap last:rounded-tr-2xl">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($people as $person)
                            <tr class="group/row transition hover:bg-gray-50/80 dark:hover:bg-white/5">
                                <td class="px-4 py-4 text-center sticky-left">
                                    <button type="button"
                                        @click="openRow === {{ $person->id }} ? openRow = null : openRow = {{ $person->id }}"
                                        class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-[#FF4622] text-white transition hover:bg-[#C43B25] dark:bg-[#FF4622] dark:text-white">
                                        <i class="ri-add-line" x-show="openRow !== {{ $person->id }}"></i>
                                        <i class="ri-subtract-line" x-show="openRow === {{ $person->id }}"></i>
                                    </button>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="font-medium text-gray-700 text-theme-sm dark:text-gray-200">{{ $person->document_number }}</p>
                                </td>
                                <td class="px-3 py-4 sm:px-6">
                                    <p class="font-medium text-gray-800 text-theme-sm dark:text-white/90 truncate" title="{{ $person->first_name }} {{ $person->last_name }}">
                                        {{ $person->first_name }} {{ $person->last_name }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $person->phone ?? '-' }}</p>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="text-gray-500 text-theme-sm dark:text-gray-400 truncate" title="{{ $person->user?->profile?->name ?? 'Sin perfil' }}">
                                        {{ $person->user?->profile?->name ?? '-' }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse($person->roles as $role)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                                {{ $role->name }}
                                            </span>
                                        @empty
                                            <span class="text-gray-400 text-xs">-</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <div class="flex items-center justify-end gap-2">
                                        @php
                                            $user = $person->user;
                                            $userPayload = $user ? [
                                                'name' => $user->name,
                                                'email' => $user->email,
                                                'profile' => $user->profile?->name,
                                            ] : null;
                                        @endphp
                                        @foreach ($rowOperations as $operation)
                                            @php
                                                $action = $operation->action ?? '';
                                                $isDelete = str_contains($action, 'destroy');
                                                $opName = mb_strtolower($operation->name ?? '', 'UTF-8');
                                                $isResetPassword = str_contains($action, 'people.user.password')
                                                    || str_contains($opName, 'restablecer')
                                                    || str_contains($opName, 'contraseña');
                                                $isViewUser = (str_contains($action, 'people.user') && !$isResetPassword)
                                                    || str_contains($opName, 'ver usuario')
                                                    || (str_contains($opName, 'usuario') && !$isResetPassword);
                                                $actionUrl = $resolveActionUrl($action, [$company, $branch, $person], $operation);
                                                $textColor = $resolveTextColor($operation);
                                                $buttonColor = $operation->color ?: '#3B82F6';
                                                $buttonStyle = "background-color: {$buttonColor}; color: {$textColor};";
                                                $variant = $isDelete ? 'eliminate' : (str_contains($action, 'edit') ? 'edit' : 'primary');
                                            @endphp
                                            @if ($isViewUser)
                                                <div class="relative group">
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl shadow-theme-xs transition hover:opacity-90"
                                                        style="{{ $buttonStyle }}"
                                                        aria-label="{{ $operation->name }}"
                                                        @click="$dispatch('open-user-modal', { person: @js($person->first_name . ' ' . $person->last_name), user: @js($userPayload) })"
                                                    >
                                                        <i class="{{ $operation->icon }}"></i>
                                                    </button>
                                                    <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">{{ $operation->name }}</span>
                                                </div>
                                                @continue
                                            @endif
                                            @if ($isResetPassword)
                                                <div class="relative group">
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl shadow-theme-xs transition hover:opacity-90"
                                                        style="{{ $buttonStyle }}"
                                                        aria-label="{{ $operation->name }}"
                                                        @click="$dispatch('open-reset-password', { action: '{{ route('admin.companies.branches.people.user.password', [$company, $branch, $person]) . ($viewId ? '?view_id=' . $viewId : '') . ($isFromConfig ? ($viewId ? '&' : '?') . 'from_config=1' : '') }}', person: @js($person->first_name . ' ' . $person->last_name) })"
                                                    >
                                                        <i class="{{ $operation->icon }}"></i>
                                                    </button>
                                                    <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">{{ $operation->name }}</span>
                                                </div>
                                                @continue
                                            @endif
                                            @if ($isDelete)
                                                <form
                                                    method="POST"
                                                    action="{{ $actionUrl }}"
                                                    class="relative group js-swal-delete"
                                                    data-swal-title="Eliminar personal?"
                                                    data-swal-text="Se eliminara {{ $person->first_name }} {{ $person->last_name }}. Esta accion no se puede deshacer."
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
                                                    @if ($isFromConfig)
                                                        <input type="hidden" name="from_config" value="1">
                                                    @endif
                                                    <x-ui.button
                                                        size="icon"
                                                        variant="{{ $variant }}"
                                                        type="submit"
                                                        className="bg-error-500 text-white hover:bg-error-600 ring-0 rounded-xl"
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
                                    </div>
                                </td>
                            </tr>
                            <tr x-show="openRow === {{ $person->id }}" x-cloak class="bg-gray-50/60 dark:bg-gray-800/40">
                                <td colspan="8" class="px-6 py-4">
                                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                                        <div>
                                            <p class="text-xs uppercase tracking-wide text-gray-400">Email</p>
                                            <p class="font-medium text-gray-700 dark:text-gray-200">{{ $person->email }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs uppercase tracking-wide text-gray-400">Telefono</p>
                                            <p class="font-medium text-gray-700 dark:text-gray-200">{{ $person->phone }}</p>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <p class="text-xs uppercase tracking-wide text-gray-400">Direccion</p>
                                            <p class="font-medium text-gray-700 dark:text-gray-200">{{ $person->address }}</p>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12">
                                    <div class="flex flex-col items-center gap-3 text-center text-sm text-gray-500">
                                        <div class="rounded-full bg-gray-100 p-3 text-gray-400 dark:bg-gray-800 dark:text-gray-300">
                                            <i class="ri-team-line"></i>
                                        </div>
                                        <p class="text-base font-semibold text-gray-700 dark:text-gray-200">No hay personal registrado.</p>
                                        <p class="text-gray-500">Crea el primer registro para esta sucursal.</p>
                                        <x-ui.button size="sm" variant="primary" type="button" @click="$dispatch('open-person-modal')">
                                            <i class="ri-add-line"></i>
                                            <span>Registrar personal</span>
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
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $people->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $people->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $people->total() }}</span>
                </div>
                <div>
                    {{ $people->links() }}
                </div>
            </div>
        </x-common.component-card>

        <x-ui.modal
            x-data="{ open: false, data: null }"
            @open-user-modal.window="open = true; data = $event.detail"
            :isOpen="false"
            class="max-w-md"
        >
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FF4622]/10 text-[#FF4622] dark:bg-[#FF4622]/20">
                            <i class="ri-user-3-line text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Usuario asignado</h3>
                            <p class="mt-1 text-sm text-gray-500" x-text="data?.person ?? ''"></p>
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

                <template x-if="data && data.user">
                    <div class="grid gap-4 text-sm">
                        <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-white/[0.03]">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Nombre</p>
                            <p class="font-semibold text-gray-800 dark:text-white/90" x-text="data.user.name"></p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-white/[0.03]">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Email</p>
                            <p class="font-semibold text-gray-800 dark:text-white/90" x-text="data.user.email"></p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-white/[0.03]">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Perfil</p>
                            <p class="font-semibold text-gray-800 dark:text-white/90" x-text="data.user.profile ?? '-'"></p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-white/[0.03]">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Contraseña</p>
                            <p class="font-semibold text-gray-800 dark:text-white/90">********</p>
                        </div>
                    </div>
                </template>

                <template x-if="data && !data.user">
                    <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900/30">
                        Esta persona no tiene usuario asignado.
                    </div>
                </template>
            </div>
        </x-ui.modal>

        <x-ui.modal
            x-data="{ open: false, data: null }"
            @open-reset-password.window="open = true; data = $event.detail"
            :isOpen="false"
            :showCloseButton="false"
            class="max-w-md"
        >
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-purple-50 text-purple-500 dark:bg-purple-500/10">
                            <i class="ri-key-2-line text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Restablecer contraseña</h3>
                            <p class="mt-1 text-sm text-gray-500" x-text="data?.person ?? ''"></p>
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

                <form method="POST" :action="data?.action" class="space-y-5">
                    @csrf
                    @method('PATCH')
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nueva contraseña</label>
                        <input
                            type="password"
                            name="password"
                            required
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Confirmar contraseña</label>
                        <input
                            type="password"
                            name="password_confirmation"
                            required
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <x-ui.button type="submit" size="md" variant="primary">
                            <i class="ri-save-line"></i>
                            <span>Actualizar</span>
                        </x-ui.button>
                        <x-ui.button type="button" size="md" variant="outline" @click="open = false">
                            <i class="ri-close-line"></i>
                            <span>Cancelar</span>
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </x-ui.modal>

        <x-ui.modal x-data="{ open: false }" @open-person-modal.window="open = true" @close-person-modal.window="open = false" :isOpen="false" :showCloseButton="false" class="max-w-6xl">
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FF4622]/10 text-[#FF4622] dark:bg-[#FF4622]/20">
                            <i class="ri-team-line text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Registrar personal</h3>
                            <p class="mt-1 text-sm text-gray-500">Ingresa la informacion del personal.</p>
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
                        <x-ui.alert variant="error" title="Revisa los campos">
                            <ul class="list-disc list-inside text-sm text-red-700 dark:text-red-300 mt-2 space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </x-ui.alert>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.companies.branches.people.store', [$company, $branch]) }}" class="space-y-6">
                    @csrf
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif

                    @include('branches.people._form', ['person' => null, 'viewOptions' => $viewOptions ?? []])

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
