@extends('layouts.app')

@php
    use Illuminate\Support\HtmlString;

    $EditIcon = new HtmlString('
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M16.5 3.5L20.5 7.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M4 20L8.5 19L19.5 8L15.5 4L4.5 15L4 20Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    ');

    $BackIcon = new HtmlString('
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    ');

    $TrashIcon = new HtmlString('
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M3 6H21" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
            <path d="M8 6V4C8 3.44772 8.44772 3 9 3H15C15.5523 3 16 3.44772 16 4V6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
            <path d="M6.5 6L7.5 20C7.5 20.5523 7.94772 21 8.5 21H15.5C16.0523 21 16.5 20.5523 16.5 20L17.5 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
        </svg>
    ');
@endphp

@section('content')
    <x-common.page-breadcrumb
        pageTitle="Detalle de sucursal"
        :crumbs="[
            ['label' => 'Empresas', 'url' => route('admin.companies.index')],
            ['label' =>  $company->legal_name . ' | Sucursales' , 'url' => route('admin.companies.branches.index', $company)],
            ['label' => 'Detalle de sucursal']
        ]"
    />

    <x-common.component-card title="{{ $branch->legal_name }}" desc="Informacion general de la sucursal.">
        <div class="grid gap-6 md:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900/30 dark:text-gray-200">
                <p class="text-xs uppercase tracking-wide text-gray-400">RUC</p>
                <p class="mt-1 font-semibold">{{ $branch->ruc }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900/30 dark:text-gray-200">
                <p class="text-xs uppercase tracking-wide text-gray-400">Direccion</p>
                <p class="mt-1 font-semibold">{{ $branch->address ?? '-' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900/30 dark:text-gray-200">
                <p class="text-xs uppercase tracking-wide text-gray-400">Ubicacion</p>
                <p class="mt-1 font-semibold">{{ $branch->location_id }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900/30 dark:text-gray-200">
                <p class="text-xs uppercase tracking-wide text-gray-400">Creado</p>
                <p class="mt-1 font-semibold">{{ $branch->created_at?->format('Y-m-d H:i') }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900/30 dark:text-gray-200">
                <p class="text-xs uppercase tracking-wide text-gray-400">Actualizado</p>
                <p class="mt-1 font-semibold">{{ $branch->updated_at?->format('Y-m-d H:i') }}</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 pt-2">
            <x-ui.link-button href="{{ route('admin.companies.branches.edit', [$company, $branch]) }}" size="md" variant="primary" :startIcon="$EditIcon">
                Editar
            </x-ui.link-button>
            <x-ui.link-button href="{{ route('admin.companies.branches.index', $company) }}" size="md" variant="outline" :startIcon="$BackIcon">
                Volver
            </x-ui.link-button>
            <form method="POST" action="{{ route('admin.companies.branches.destroy', [$company, $branch]) }}" onsubmit="return confirm('Eliminar esta sucursal?')">
                @csrf
                @method('DELETE')
                <x-ui.button size="md" variant="outline" className="text-error-500 ring-error-500/30 hover:bg-error-500/10" :startIcon="$TrashIcon">
                    Eliminar
                </x-ui.button>
            </form>
        </div>
    </x-common.component-card>
@endsection
