@extends('layouts.app')

@php
    use Illuminate\Support\HtmlString;

    $SaveIcon = new HtmlString('
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    ');

    $BackIcon = new HtmlString('
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    ');
@endphp

@section('content')
    <x-common.page-breadcrumb
        pageTitle="Nueva sucursal"
        :crumbs="[
            ['label' => 'Empresas', 'url' => route('admin.companies.index')],
            ['label' =>  $company->legal_name . ' | Sucursales' , 'url' => route('admin.companies.branches.index', $company)],
            ['label' => 'Nueva sucursal']
        ]"
    />

    <x-common.component-card title="Registrar sucursal" desc="Ingresa la informacion de la sucursal para {{ $company->legal_name }}.">
        <form method="POST" action="{{ route('admin.companies.branches.store', $company) }}" class="space-y-6" enctype="multipart/form-data">
            @csrf
            @include('branches._form', ['branch' => null])

            <div class="flex flex-wrap gap-3">
                <x-ui.button type="submit" size="md" variant="primary" :startIcon="$SaveIcon">Guardar</x-ui.button>
                <x-ui.link-button href="{{ route('admin.companies.branches.index', $company) }}" size="md" variant="outline" :startIcon="$BackIcon">
                    Cancelar
                </x-ui.link-button>
            </div>
        </form>
    </x-common.component-card>
@endsection
