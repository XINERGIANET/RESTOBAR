@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb pageTitle="{{ 'Editar Banco' }}" />

<x-common.component-card title="Editar banco" desc="Actualiza la información del banco.">
    <form method="POST" action="{{ route('admin.banks.update', $bank) }}" class="space-y-4">
        @csrf
        @method('PUT')
        
        @if ($errors->any())
            <div class="mb-5">
                <x-ui.alert variant="error" title="Revisa los campos" 
                    message="Hay errores en el formulario, corrige los datos e intenta nuevamente." />
            </div>
        @endif

        @include('banks._form')

        <div class="flex flex-wrap gap-3 justify-end">
            <x-ui.button type="submit" size="md" variant="primary">Actualizar</x-ui.button>
            <x-ui.button type="button" size="md" variant="outline" 
                @click="window.location.href='{{ route('admin.banks.index') }}'">
                Cancelar
            </x-ui.button>
        </div>
    </form>
</x-common.component-card>
@endsection
