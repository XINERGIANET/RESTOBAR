@extends('layouts.app')

@php
    use App\Helpers\MenuHelper;
@endphp

@section('content')
    <div x-data="{}">
        <x-common.page-breadcrumb
            pageTitle="Permisos"
            :crumbs="[
                ['label' => 'Empresas', 'url' => route('admin.companies.index')],
                ['label' =>  $company->legal_name . ' | Sucursales', 'url' => route('admin.companies.branches.index', $company)],
                ['label' =>  $branch->legal_name . ' | Perfiles', 'url' => route('admin.companies.branches.profiles.index', [$company, $branch])],
                ['label' =>  $profile->name . ' | Permisos' ]
            ]"
        />

        <x-common.component-card
            title="Permisos de {{ $profile->name }}"
            desc="Activa o desactiva permisos para esta sucursal."
        >
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                    <x-ui.per-page-selector :per-page="$perPage" />
                    <div class="relative flex-1">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="ri-search-line"></i>
                        </span>
                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Buscar por permiso"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-10 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button size="sm" variant="primary" type="submit">
                            <i class="ri-search-line"></i>
                            <span>Buscar</span>
                        </x-ui.button>
                        <x-ui.link-button size="sm" variant="outline" href="{{ route('admin.companies.branches.profiles.permissions.index', [$company, $branch, $profile]) }}">
                            <i class="ri-close-line"></i>
                            <span>Limpiar</span>
                        </x-ui.link-button>
                    </div>
                </form>

                <div class="flex flex-wrap gap-2">
                    <x-ui.link-button
                        size="md"
                        variant="outline"
                        href="{{ route('admin.companies.branches.profiles.index', [$company, $branch]) }}"
                    >
                        <i class="ri-arrow-left-line"></i>
                        <span>Volver a perfiles</span>
                    </x-ui.link-button>
                    <x-ui.button
                        size="md"
                        variant="primary"
                        type="button"
                        style=" background-color: #12f00e; color: #111827;"
                        @click="$dispatch('open-assign-permissions')"
                    >
                        <i class="ri-add-line"></i>
                        <span>Asignar permisos</span>
                    </x-ui.button>
                </div>
            </div>

            <div class="mt-4 rounded-xl border border-gray-200 bg-white overflow-visible dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                <p class="font-medium text-white text-theme-xs dark:text-white">Permiso</p>
                            </th>
                            <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                <p class="font-medium text-white text-theme-xs dark:text-white">Modulo</p>
                            </th>
                            <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                <p class="font-medium text-white text-theme-xs dark:text-white">Estado</p>
                            </th>
                            <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                <p class="font-medium text-white text-theme-xs dark:text-white">Acciones</p>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($permissions as $permission)
                            <tr class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <p class="font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $permission->name }}</p>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $permission->module_name }}</p>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <x-ui.badge variant="light" color="{{ $permission->status ? 'success' : 'error' }}">
                                        {{ $permission->status ? 'Activo' : 'Inactivo' }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    <div class="flex items-center justify-center">
                                        <form
                                            method="POST"
                                            action="{{ route('admin.companies.branches.profiles.permissions.toggle', [$company, $branch, $profile, $permission->id]) }}"
                                            class="relative group"
                                        >
                                            @csrf
                                            @method('PATCH')
                                            <x-ui.button
                                                size="icon"
                                                variant="primary"
                                                type="submit"
                                                className="{{ $permission->status ? 'bg-[#FF4622] text-white hover:bg-[#C43B25]' : 'bg-gray-500 text-white hover:bg-gray-600' }} ring-0 rounded-full"
                                                style="border-radius: 100%;"
                                                aria-label="{{ $permission->status ? 'Desactivar' : 'Activar' }}"
                                            >
                                                <i class="{{ $permission->status ? 'ri-eye-line' : 'ri-eye-off-line' }}"></i>
                                            </x-ui.button>
                                            <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">
                                                {{ $permission->status ? 'Desactivar' : 'Activar' }}
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
                                            <i class="ri-lock-line"></i>
                                        </div>
                                        <p class="text-base font-semibold text-gray-700 dark:text-gray-200">No hay permisos registrados.</p>
                                        <p class="text-gray-500">Asegura que existan permisos cargados en el sistema.</p>
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
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $permissions->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $permissions->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $permissions->total() }}</span>
                </div>
                <div>
                    {{ $permissions->links() }}
                </div>
            </div>
        </x-common.component-card>

        <x-ui.modal x-data="{ open: false }" @open-assign-permissions.window="open = true" @close-assign-permissions.window="open = false" :isOpen="false" :showCloseButton="false" class="max-w-6xl">
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FF4622]/10 text-[#FF4622] dark:bg-[#FF4622]/20">
                            <i class="ri-lock-line text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Asignar permisos</h3>
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
                    action="{{ route('admin.companies.branches.profiles.permissions.assign', [$company, $branch, $profile]) }}"
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

                    @if ($modules->isEmpty())
                        <div class="rounded-xl border border-dashed border-gray-200 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-800">
                            No hay opciones de menú disponibles para asignar.
                        </div>
                    @else
                        <div class="space-y-5">
                            @foreach ($modules as $module)
                                <div class="rounded-2xl border border-gray-200 bg-white/60 p-4 dark:border-gray-800 dark:bg-gray-900/40">
                                    <div class="mb-4 flex items-center gap-3">
                                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                            @if(class_exists('App\\Helpers\\MenuHelper'))
                                                <span class="w-5 h-5 fill-current">{!! MenuHelper::getIconSvg($module->icon) !!}</span>
                                            @else
                                                <i class="{{ $module->icon }}"></i>
                                            @endif
                                        </div>
                                        <div>
                                            <h4 class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $module->name }}</h4>
                                            <p class="text-xs text-gray-500">Opciones: {{ $module->menuOptions->count() }}</p>
                                        </div>
                                    </div>

                                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        @foreach ($module->menuOptions as $option)
                                            <label class="flex items-start gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 shadow-sm transition hover:border-brand-300 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                                                <input
                                                    type="checkbox"
                                                    name="permissions[]"
                                                    value="{{ $option->id }}"
                                                    data-select-item
                                                    class="mt-1 h-4 w-4 rounded border-gray-300 text-[#FF4622] focus:ring-brand-500"
                                                    @checked(in_array($option->id, $assignedMenuOptionIds ?? [], true))
                                                />
                                                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                                    @if(class_exists('App\\Helpers\\MenuHelper'))
                                                        <span class="w-5 h-5 fill-current">{!! MenuHelper::getIconSvg($option->icon) !!}</span>
                                                    @else
                                                        <i class="{{ $option->icon }}"></i>
                                                    @endif
                                                </span>
                                                <span class="flex-1">
                                                    <span class="block font-medium">{{ $option->name }}</span>
                                                    <span class="block text-xs text-gray-500">{{ $option->action }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

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
