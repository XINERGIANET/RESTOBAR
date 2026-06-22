@extends('layouts.app')

@section('content')
    <div x-data="{}">
        <x-common.page-breadcrumb pageTitle="Tipos de producto" />

        <x-common.component-card title="Tipos de producto" desc="Gestiona los tipos de producto (vendibles y suministros) por sucursal.">
            @if (session('error'))
                <div class="mb-4">
                    <x-ui.alert variant="error" title="Error" :message="session('error')" />
                </div>
            @endif
            @if (session('status'))
                <div class="mb-4">
                    <x-ui.alert variant="success" title="Listo" :message="session('status')" />
                </div>
            @endif

            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                    @if ($viewId ?? null)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    <x-ui.per-page-selector :per-page="$perPage" />
                    <div class="relative flex-1">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="ri-search-line"></i>
                        </span>
                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Buscar por nombre, descripción o comportamiento"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-12 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button size="md" variant="primary" type="submit" class="flex-1 sm:flex-none h-11 px-6 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95" style="background-color: #C43B25; border-color: #C43B25;">
                            <i class="ri-search-line text-gray-100"></i>
                            <span class="font-medium text-gray-100">Buscar</span>
                        </x-ui.button>
                        <x-ui.link-button size="md" variant="outline" href="{{ route('product_types.index', $viewId ? ['view_id' => $viewId] : []) }}" class="flex-1 sm:flex-none h-11 px-6 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                            <i class="ri-refresh-line"></i>
                            <span class="font-medium">Limpiar</span>
                        </x-ui.link-button>
                    </div>
                </form>

                <div class="flex items-center gap-2">
                    <x-ui.button size="md" variant="primary" type="button" style="background-color: #12f00e; color: #111827;" @click="$dispatch('open-product-type-modal')">
                        <i class="ri-add-line"></i>
                        <span>Nuevo tipo</span>
                    </x-ui.button>
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span>Total</span>
                    <x-ui.badge size="sm" variant="light" color="info">{{ $productTypes->total() }}</x-ui.badge>
                </div>
            </div>

            <div class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full min-w-[700px]">
                    <thead>
                        <tr style="background-color: #FF4622; color: #FFFFFF;">
                            <th class="px-5 py-3 text-left sm:px-6 first:rounded-tl-xl">
                                <p class="font-semibold text-theme-xs">Nombre</p>
                            </th>
                            <th class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-theme-xs">Descripción</p>
                            </th>
                            <th class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-theme-xs">Comportamiento</p>
                            </th>
                            <th class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-theme-xs">Sucursal</p>
                            </th>
                            <th class="px-5 py-3 text-center sm:px-6 last:rounded-tr-xl">
                                <p class="font-semibold text-theme-xs">Acciones</p>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($productTypes as $productType)
                            <tr class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $productType->name }}</p>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $productType->description ?? '—' }}</p>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-center">
                                    @if($productType->behavior === \App\Models\ProductType::BEHAVIOR_SELLABLE)
                                        <x-ui.badge size="sm" variant="light" color="info">Vendible</x-ui.badge>
                                    @elseif($productType->behavior === \App\Models\ProductType::BEHAVIOR_BOTH)
                                        <x-ui.badge size="sm" variant="light" color="success">Compras y ventas</x-ui.badge>
                                    @else
                                        <x-ui.badge size="sm" variant="light" color="warning">Suministro</x-ui.badge>
                                    @endif
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="text-center text-theme-sm text-gray-600 dark:text-gray-400">{{ $productType->branch->legal_name ?? '—' }}</p>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <div class="flex items-center justify-center gap-2">
                                        <div class="relative group">
                                            <x-ui.link-button
                                                size="icon"
                                                variant="edit"
                                                href="{{ route('product_types.edit', array_merge([$productType], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                class="rounded-xl"
                                                style="background-color: #FBBF24; color: #111827;"
                                                aria-label="Editar"
                                            >
                                                <i class="ri-pencil-line"></i>
                                            </x-ui.link-button>
                                            <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">Editar</span>
                                        </div>
                                        <form
                                            method="POST"
                                            action="{{ route('product_types.destroy', array_merge([$productType], $viewId ? ['view_id' => $viewId] : [])) }}"
                                            class="relative group js-swal-delete"
                                            data-swal-title="Eliminar tipo de producto?"
                                            data-swal-text="Se eliminará {{ $productType->name }}. Esta acción no se puede deshacer."
                                            data-swal-confirm="Sí, eliminar"
                                            data-swal-cancel="Cancelar"
                                            data-swal-confirm-color="#ef4444"
                                            data-swal-cancel-color="#6b7280"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            @if ($viewId ?? null)
                                                <input type="hidden" name="view_id" value="{{ $viewId }}">
                                            @endif
                                            <x-ui.button
                                                size="icon"
                                                variant="eliminate"
                                                type="submit"
                                                class="bg-error-500 text-white hover:bg-error-600 ring-0 rounded-xl"
                                                aria-label="Eliminar"
                                            >
                                                <i class="ri-delete-bin-line"></i>
                                            </x-ui.button>
                                            <span class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">Eliminar</span>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12">
                                    <div class="flex flex-col items-center gap-3 text-center text-sm text-gray-500">
                                        <div class="rounded-full bg-gray-100 p-3 text-gray-400 dark:bg-gray-800 dark:text-gray-300">
                                            <i class="ri-price-tag-3-line text-2xl"></i>
                                        </div>
                                        <p class="text-base font-semibold text-gray-700 dark:text-gray-200">No hay tipos de producto registrados.</p>
                                        <p class="text-gray-500">Crea un tipo para esta sucursal o selecciona una sucursal.</p>
                                        <x-ui.button size="sm" variant="primary" type="button" @click="$dispatch('open-product-type-modal')">
                                            <i class="ri-add-line"></i>
                                            <span>Nuevo tipo</span>
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
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $productTypes->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $productTypes->lastItem() ?? 0 }}</span>
                    de
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $productTypes->total() }}</span>
                </div>
                <div>
                    {{ $productTypes->links() }}
                </div>
            </div>
        </x-common.component-card>

        {{-- Modal crear tipo de producto --}}
        <x-ui.modal
            x-data="{ open: false }"
            @open-product-type-modal.window="open = true"
            @close-product-type-modal.window="open = false"
            :isOpen="false"
            class="max-w-2xl"
        >
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FF4622]/10 text-[#FF4622] dark:bg-[#FF4622]/20">
                            <i class="ri-price-tag-3-line text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Nuevo tipo de producto</h3>
                            <p class="mt-1 text-sm text-gray-500">El tipo se creará para la sucursal actual.</p>
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

                <form method="POST" action="{{ route('product_types.store') }}" class="space-y-6">
                    @csrf
                    @if ($viewId ?? null)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif

                    @include('product_types._form', ['productType' => null])

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
