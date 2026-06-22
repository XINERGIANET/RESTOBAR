@extends('layouts.app')

@section('content')
    <div x-data="{}">
        <x-common.page-breadcrumb pageTitle="Editar categoria" />

        <x-common.component-card title="Editar categoria" desc="Actualiza la informacion de la categoria.">
            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-error-500/30 bg-error-500/10 px-4 py-3 text-sm text-error-700 dark:border-error-500/50 dark:bg-error-500/20 dark:text-error-300">
                    <p class="font-semibold mb-2">Revisa los campos</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('categories.update', $category) }}" class="space-y-6" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @if (!empty($viewId))
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif

                @include('categories._form', ['category' => $category])

                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit" size="md" variant="primary">
                        <i class="ri-save-line"></i>
                        <span>Actualizar</span>
                    </x-ui.button>
                    <x-ui.link-button size="md" variant="outline" href="{{ route('categories.index', !empty($viewId) ? ['view_id' => $viewId] : []) }}">
                        <i class="ri-close-line"></i>
                        <span>Cancelar</span>
                    </x-ui.link-button>
                </div>
            </form>
        </x-common.component-card>
    </div>
@endsection
