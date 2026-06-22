@extends('layouts.app')

@section('content')
    <div x-data="{}">
        @php
            $viewId = request('view_id');
            $companyViewId = request('company_view_id');
            $branchViewId = request('branch_view_id') ?? session('branch_view_id');
            $profileViewId = request('profile_view_id') ?? session('profile_view_id');
            $requestIcon = request('icon');
        @endphp
        <x-common.page-breadcrumb
            pageTitle="Operaciones"
            :crumbs="[
                ['label' => 'Empresas', 'url' => route('admin.companies.index', $companyViewId ? ['view_id' => $companyViewId] : [])],
                ['label' => $company->legal_name . ' | Sucursales', 'url' => route('admin.companies.branches.index', array_merge([$company], array_filter(['view_id' => $branchViewId ?: $viewId, 'company_view_id' => $companyViewId, 'icon' => $requestIcon])))],
                ['label' => $branch->legal_name . ' | Perfiles', 'url' => route('admin.companies.branches.profiles.index', array_merge([$company, $branch], array_filter(['view_id' => $profileViewId ?: $viewId, 'company_view_id' => $companyViewId, 'branch_view_id' => $branchViewId, 'icon' => $requestIcon])))],
                ['label' => $profile->name . ' | Operaciones']
            ]"
        />

        <x-common.component-card
            title="Operaciones de {{ $profile->name }}"
            desc="Operaciones asignadas en la sucursal {{ $branch->legal_name }}."
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
                    @if ($profileViewId)
                        <input type="hidden" name="profile_view_id" value="{{ $profileViewId }}">
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
                            placeholder="Buscar por nombre o accion"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-10 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button size="sm" variant="primary" type="submit">
                            <i class="ri-search-line"></i>
                            <span>Buscar</span>
                        </x-ui.button>
                        <x-ui.link-button size="sm" variant="outline" href="{{ route('admin.companies.branches.profiles.operations.index', array_merge([$company, $branch, $profile], array_filter(['view_id' => $viewId, 'company_view_id' => $companyViewId, 'branch_view_id' => $branchViewId, 'profile_view_id' => $profileViewId, 'icon' => $requestIcon]))) }}">
                            <i class="ri-close-line"></i>
                            <span>Limpiar</span>
                        </x-ui.link-button>
                    </div>
                </form>
                <div class="flex flex-wrap gap-2">
                    <x-ui.link-button size="md" variant="outline" href="{{ route('admin.companies.branches.profiles.index', array_merge([$company, $branch], array_filter(['view_id' => $profileViewId ?: $viewId, 'company_view_id' => $companyViewId, 'branch_view_id' => $branchViewId, 'icon' => $requestIcon]))) }}">
                        <i class="ri-arrow-left-line"></i>
                        <span>Volver a perfiles</span>
                    </x-ui.link-button>
                    <x-ui.button size="md" variant="primary" type="button" style=" background-color: #12f00e; color: #111827;"
                        @click="$dispatch('open-assign-profile-operations')">
                        <i class="ri-add-line"></i>
                        <span>Asignar operaciones</span>
                    </x-ui.button>
                </div>
            </div>

            <div class="mt-4 rounded-xl border border-gray-200 bg-white overflow-visible dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <th class="px-5 py-3 text-left sm:px-6">
                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Operacion</p>
                            </th>
                            <th class="px-5 py-3 text-left sm:px-6">
                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Accion</p>
                            </th>
                            <th class="px-5 py-3 text-left sm:px-6">
                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Estado</p>
                            </th>
                            <th class="px-5 py-3 text-center sm:px-6">
                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Acciones</p>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($operations as $operation)
                            <tr class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                                <td class="px-5 py-4 sm:px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 items-center justify-center rounded-full text-white"
                                            style="background-color: {{ $operation->color }}">
                                            <i class="{{ $operation->icon }}"></i>
                                        </div>
                                        <p class="font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $operation->name }}</p>
                                    </div>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $operation->action }}</p>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <x-ui.badge variant="light" color="{{ $operation->status ? 'success' : 'error' }}">
                                        {{ $operation->status ? 'Activo' : 'Inactivo' }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <div class="flex items-center justify-center">
                                        <form
                                            method="POST"
                                            action="{{ route('admin.companies.branches.profiles.operations.toggle', [$company, $branch, $profile, $operation->id]) }}"
                                            class="relative group"
                                        >
                                            @csrf
                                            @method('PATCH')
                                            @if ($viewId)
                                                <input type="hidden" name="view_id" value="{{ $viewId }}">
                                            @endif
                                            @if ($companyViewId)
                                                <input type="hidden" name="company_view_id" value="{{ $companyViewId }}">
                                            @endif
                                            @if ($branchViewId)
                                                <input type="hidden" name="branch_view_id" value="{{ $branchViewId }}">
                                            @endif
                                            @if ($profileViewId)
                                                <input type="hidden" name="profile_view_id" value="{{ $profileViewId }}">
                                            @endif
                                            @if ($requestIcon)
                                                <input type="hidden" name="icon" value="{{ $requestIcon }}">
                                            @endif
                                            <x-ui.button
                                                size="icon"
                                                variant="primary"
                                                type="submit"
                                                className="{{ $operation->status ? 'bg-[#FF4622] text-white hover:bg-[#C43B25]' : 'bg-gray-500 text-white hover:bg-gray-600' }} ring-0 rounded-full"
                                                style="border-radius: 100%;"
                                                aria-label="{{ $operation->status ? 'Desactivar' : 'Activar' }}"
                                            >
                                                <i class="{{ $operation->status ? 'ri-eye-line' : 'ri-eye-off-line' }}"></i>
                                            </x-ui.button>
                                            <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">
                                                {{ $operation->status ? 'Desactivar' : 'Activar' }}
                                            </span>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12">
                                    <div class="flex flex-col items-center gap-3 text-center text-sm text-gray-500">
                                        <div class="rounded-full bg-gray-100 p-3 text-gray-400 dark:bg-gray-800 dark:text-gray-300">
                                            <i class="ri-list-check-2"></i>
                                        </div>
                                        <p class="text-base font-semibold text-gray-700 dark:text-gray-200">No hay operaciones asignadas.</p>
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
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $operations->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $operations->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $operations->total() }}</span>
                </div>
                <div>
                    {{ $operations->links() }}
                </div>
            </div>
        </x-common.component-card>

        <x-ui.modal x-data="{ open: false }" @open-assign-profile-operations.window="open = true" @close-assign-profile-operations.window="open = false" :isOpen="false" class="max-w-3xl">
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FF4622]/10 text-[#FF4622] dark:bg-[#FF4622]/20">
                            <i class="ri-list-check-2 text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Asignar operaciones</h3>
                            <p class="mt-1 text-sm text-gray-500">Perfil: <strong>{{ $profile->name }}</strong></p>
                        </div>
                    </div>
                    <button type="button" @click="open = false"
                        class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                        aria-label="Cerrar">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>

                <form
                    method="POST"
                    action="{{ route('admin.companies.branches.profiles.operations.assign', [$company, $branch, $profile]) }}"
                    class="space-y-6"
                    data-select-scope
                >
                    @csrf

                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input
                            type="checkbox"
                            data-select-all
                            class="h-4 w-4 rounded border-gray-300 text-[#FF4622] focus:ring-brand-500"
                            @change="const scope = $el.closest('[data-select-scope]') || $el.closest('form') || document; scope.querySelectorAll('input[data-select-item]').forEach((input) => { if (!input.disabled) { input.checked = $el.checked; } });"
                        />
                        <span>Seleccionar todos</span>
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @forelse ($availableOperations as $operation)
                            <label class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 shadow-sm transition hover:border-brand-300 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                                <input
                                    type="checkbox"
                                    name="operations[]"
                                    value="{{ $operation->id }}"
                                    data-select-item
                                    class="h-4 w-4 rounded border-gray-300 text-[#FF4622] focus:ring-brand-500"
                                    @checked(in_array($operation->id, $assignedOperationIds ?? [], true))
                                />
                                <span class="flex h-9 w-9 items-center justify-center rounded-lg text-white" style="background-color: {{ $operation->color }}">
                                    <i class="{{ $operation->icon }}"></i>
                                </span>
                                <span class="flex-1">
                                    <span class="block font-medium">{{ $operation->name }}</span>
                                    <span class="block text-xs text-gray-500">{{ $operation->action }}</span>
                                </span>
                            </label>
                        @empty
                            <div class="sm:col-span-2 rounded-xl border border-dashed border-gray-200 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-800">
                                No hay operaciones disponibles para esta sucursal.
                            </div>
                        @endforelse
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
    </div>
@endsection
