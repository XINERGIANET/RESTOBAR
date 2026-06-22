@extends('layouts.app')

@section('content')
    <div class="flex flex-col gap-6 max-w-2xl mx-auto">

        <x-common.page-breadcrumb pageTitle="Importar Áreas y Mesas" />

        <div class="rounded-2xl border border-gray-200 bg-white dark:bg-gray-900 dark:border-gray-700 shadow-sm p-6 sm:p-8">

            {{-- Encabezado --}}
            <div class="flex items-center gap-4 mb-6">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400">
                    <i class="ri-map-2-line text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Importar Áreas y Mesas desde Excel</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        El archivo debe tener dos hojas: <strong>Areas</strong> y <strong>Mesas</strong>.
                    </p>
                </div>
            </div>

            {{-- Resultado --}}
            @if(session('import_imported') !== null || session('import_updated') !== null)
                <div class="mb-6 rounded-xl border border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800 p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="ri-checkbox-circle-line text-green-600 dark:text-green-400 text-lg"></i>
                        <span class="font-semibold text-green-700 dark:text-green-300">Importación completada</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm text-green-700 dark:text-green-300">
                        <div class="rounded-lg bg-green-100 dark:bg-green-900/30 p-3">
                            <p class="font-semibold text-xs uppercase mb-1 opacity-70">Áreas</p>
                            <p><i class="ri-add-circle-line mr-1"></i> Nuevas: <strong>{{ session('import_areas_imported', 0) }}</strong></p>
                            <p><i class="ri-refresh-line mr-1"></i> Existentes: <strong>{{ session('import_areas_updated', 0) }}</strong></p>
                        </div>
                        <div class="rounded-lg bg-green-100 dark:bg-green-900/30 p-3">
                            <p class="font-semibold text-xs uppercase mb-1 opacity-70">Mesas</p>
                            <p><i class="ri-add-circle-line mr-1"></i> Nuevas: <strong>{{ session('import_tables_imported', 0) }}</strong></p>
                            <p><i class="ri-refresh-line mr-1"></i> Actualizadas: <strong>{{ session('import_tables_updated', 0) }}</strong></p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Errores --}}
            @if(session('import_errors') && count(session('import_errors')) > 0)
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="ri-error-warning-line text-red-600 dark:text-red-400 text-lg"></i>
                        <span class="font-semibold text-red-700 dark:text-red-300">Errores en algunas filas:</span>
                    </div>
                    <ul class="text-sm text-red-700 dark:text-red-300 space-y-1 max-h-48 overflow-y-auto">
                        @foreach(session('import_errors') as $error)
                            <li class="flex items-start gap-1">
                                <i class="ri-close-circle-line mt-0.5 shrink-0"></i>{{ $error }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 p-4">
                    <div class="flex items-center gap-2 mb-1">
                        <i class="ri-error-warning-line text-red-600 text-lg"></i>
                        <span class="font-semibold text-red-700 dark:text-red-300">Error</span>
                    </div>
                    @foreach($errors->all() as $error)
                        <p class="text-sm text-red-700 dark:text-red-300">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- Paso 1: Plantilla --}}
            <div class="mb-6 rounded-xl border border-blue-100 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/10 p-4">
                <p class="text-sm font-semibold text-blue-700 dark:text-blue-300 mb-1">
                    <i class="ri-information-line mr-1"></i>
                    Paso 1 — Descarga la plantilla y llénala
                </p>
                <p class="text-xs text-blue-600 dark:text-blue-400 mb-3">
                    La plantilla tiene dos hojas: <strong>Areas</strong> (nombre del área) y
                    <strong>Mesas</strong> (nombre, capacidad, área). Reemplaza los ejemplos con tus datos.
                </p>
                <a target="_blank" href="{{ route('areas.import.template', $viewId ? ['view_id' => $viewId] : []) }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-blue-600 hover:bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow transition-colors">
                    <i class="ri-download-2-line"></i>
                    Descargar plantilla Excel
                </a>
            </div>

            {{-- Paso 2: Subir --}}
            <form method="POST"
                  action="{{ route('areas.import', $viewId ? ['view_id' => $viewId] : []) }}"
                  enctype="multipart/form-data"
                  x-data="{ fileName: '', dragOver: false }"
                  class="space-y-5">
                @csrf
                @if($viewId)
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif

                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                    <i class="ri-upload-cloud-2-line mr-1"></i>
                    Paso 2 — Sube el archivo con los datos
                </p>

                <label for="file-input"
                    class="flex flex-col items-center justify-center gap-3 cursor-pointer rounded-xl border-2 border-dashed transition-colors p-8"
                    :class="dragOver
                        ? 'border-green-500 bg-green-50 dark:bg-green-900/20'
                        : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 hover:border-green-400 hover:bg-green-50 dark:hover:bg-green-900/10'"
                    @dragover.prevent="dragOver = true"
                    @dragleave.prevent="dragOver = false"
                    @drop.prevent="
                        dragOver = false;
                        const f = $event.dataTransfer.files[0];
                        if (f) { fileName = f.name; $refs.fileInput.files = $event.dataTransfer.files; }
                    ">
                    <i class="ri-file-excel-2-line text-4xl"
                       :class="fileName ? 'text-green-500' : 'text-gray-400 dark:text-gray-500'"></i>
                    <div class="text-center">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300"
                           x-text="fileName || 'Arrastra el archivo aquí o haz clic para seleccionar'"></p>
                        <p class="text-xs text-gray-400 mt-0.5">xlsx, xls — máx. 5 MB</p>
                    </div>
                    <input id="file-input" x-ref="fileInput" type="file" name="file"
                           accept=".xlsx,.xls" class="hidden"
                           @change="fileName = $event.target.files[0]?.name || ''"/>
                </label>

                {{-- Estructura esperada --}}
                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="bg-blue-600 text-white px-3 py-2 font-semibold">Hoja: Areas</div>
                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            <div class="px-3 py-2 flex items-center gap-1.5">
                                <code class="text-blue-600 dark:text-blue-400 font-mono">nombre_area</code>
                                <span class="text-gray-400 text-[10px]">Requerido</span>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="bg-green-700 text-white px-3 py-2 font-semibold">Hoja: Mesas</div>
                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach([['nombre_mesa','Requerido'],['capacidad','Opcional (def. 4)'],['nombre_area','Requerido']] as [$col,$req])
                            <div class="px-3 py-2 flex items-center gap-1.5">
                                <code class="text-green-700 dark:text-green-400 font-mono">{{ $col }}</code>
                                <span class="text-gray-400 text-[10px]">{{ $req }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit"
                            x-bind:disabled="!fileName"
                            class="inline-flex items-center gap-2 rounded-xl bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed px-6 py-2.5 text-sm font-semibold text-white shadow transition-all">
                        <i class="ri-upload-2-line"></i>
                        Importar áreas y mesas
                    </button>
                    <a href="{{ route('areas.index', $viewId ? ['view_id' => $viewId] : []) }}"
                       class="px-5 py-2.5 rounded-xl border border-gray-300 text-gray-700 dark:border-gray-600 dark:text-gray-300 text-sm font-semibold hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        Cancelar
                    </a>
                </div>
            </form>
        </div>

        <div class="rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-900/10 dark:border-amber-800 p-4 text-sm text-amber-700 dark:text-amber-300">
            <p class="font-semibold mb-1"><i class="ri-lightbulb-line mr-1"></i> Notas importantes</p>
            <ul class="list-disc list-inside space-y-1 text-xs">
                <li>Primero se procesan las <strong>Áreas</strong> y luego las <strong>Mesas</strong>.</li>
                <li>Si un área ya existe en la sucursal, se omite (no se duplica).</li>
                <li>Si una mesa ya existe en el área, solo actualiza la <strong>capacidad</strong>.</li>
                <li>El campo <code>nombre_area</code> en la hoja Mesas debe coincidir <strong>exactamente</strong> con un nombre de la hoja Áreas.</li>
                <li>Los datos se importan solo para la <strong>sucursal activa</strong>.</li>
            </ul>
        </div>

    </div>
@endsection
