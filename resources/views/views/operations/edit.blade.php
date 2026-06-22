@extends('layouts.app')

@section('content')
    @php
        $viewId = request('view_id');
    @endphp
    <x-common.page-breadcrumb pageTitle="Operaciones" />

    <x-ui.modal
        x-data="{ 
            open: true,
            redirectToIndex() {
                window.location.href = '{{ route('admin.views.operations.index', $view) }}';
            }
        }"
        x-init="$watch('open', value => {
            if (!value) redirectToIndex();
        })"
        @keydown.escape.window="redirectToIndex()"
        
        :isOpen="true"
        :showCloseButton="false"
        class="max-w-3xl"
    >
        <div class="p-6 sm:p-8">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FF4622]/10 text-[#FF4622] dark:bg-[#FF4622]/20">
                        <i class="ri-settings-3-line text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="mt-2 text-lg font-semibold text-gray-800 dark:text-white/90">Editar operación</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Editando botón para la vista: <strong>{{ $view->name }}</strong>
                        </p>
                    </div>
                </div>
                
                {{-- BOTÓN CERRAR SUPERIOR (X) --}}
                <button
                    type="button"
                    @click="redirectToIndex()"
                    class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                    aria-label="Cerrar"
                >
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            {{-- ALERTAS DE ERROR --}}
            @if ($errors->any())
                <div class="mb-5">
                    <x-ui.alert variant="error" title="Revisa los campos" message="Hay errores en el formulario." />
                </div>
            @endif

            <form method="POST" action="{{ $viewId ? route('admin.views.operations.update', [$view->id, $operation->id]) . '?view_id=' . $viewId : route('admin.views.operations.update', [$view->id, $operation->id]) }}" class="space-y-6">
                @csrf
                @method('PUT')
                @if ($viewId)
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif

                <div class="grid gap-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nombre de la Operación</label>
                        <div class="relative">
                            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line></svg>
                            </span>
                            <input
                                type="text"
                                name="name"
                                required
                                value="{{ old('name', $operation->name) }}"
                                placeholder="Ej: Crear Usuario"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            />
                        </div>
                        @error('name') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        
                        <div data-color-wrapper>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Acción (ID / Función)</label>
                            <div class="relative">
                                <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                                </span>
                                <input
                                    type="text"
                                    name="action"
                                    required
                                    value="{{ old('action', $operation->action) }}"
                                    placeholder="Ej: open-create-modal"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                />
                            </div>
                            @error('action') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        <div x-data="iconPicker()" x-init="init()" class="relative">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Clase del Icono</label>
                            <div class="relative">
                                <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                    <i class="ri-grid-line text-lg"></i>
                                </span>
                                <input
                                    type="text"
                                    name="icon"
                                    required
                                    x-ref="iconInput"
                                    x-model="search"
                                    value="{{ old('icon', $operation->icon) }}"
                                    placeholder="Busca o escribe un icono..."
                                    autocomplete="off"
                                    spellcheck="false"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                    @focus="openDropdown()"
                                    @input="openDropdown()"
                                    @keydown.escape.stop="closeDropdown()"
                                />
                                <button
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                                    @click="toggleDropdown()"
                                    aria-label="Abrir selector de iconos"
                                >
                                    <i class="ri-arrow-down-s-line text-lg transition-transform" :class="open ? 'rotate-180' : ''"></i>
                                </button>
                            </div>
                            @error('icon') <span class="text-xs text-red-500">{{ $message }}</span> @enderror

                            <div
                                x-show="open"
                                class="absolute left-0 top-full z-50 mt-2 w-full rounded-xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900"
                                @click.outside="closeDropdown()"
                            >
                                <div class="mb-2 flex items-center justify-between text-xs text-gray-500">
                                    <span x-text="loading ? 'Cargando iconos...' : `${filteredIcons.length} iconos`"></span>
                                    <button type="button" class="text-[#FF4622] hover:text-[#C43B25]" @click="clear()">Limpiar</button>
                                </div>
                                <template x-if="loading">
                                    <div class="flex items-center gap-3 text-sm text-gray-500">
                                        <div class="h-5 w-5 animate-spin rounded-full border-2 border-[#FF4622] border-t-transparent"></div>
                                        <span>Cargando iconos...</span>
                                    </div>
                                </template>

                                <template x-if="!loading && error">
                                    <div class="rounded-lg border border-dashed border-gray-200 px-3 py-4 text-center text-sm text-gray-500 dark:border-gray-800">
                                        No se pudieron cargar los iconos.
                                    </div>
                                </template>

                                <template x-if="!loading && !error && displayedIcons.length === 0">
                                    <div class="rounded-lg border border-dashed border-gray-200 px-3 py-4 text-center text-sm text-gray-500 dark:border-gray-800">
                                        No se encontraron iconos.
                                    </div>
                                </template>

                                <div class="max-h-64 overflow-y-auto custom-scrollbar" x-show="!loading && !error && displayedIcons.length">
                                    <div class="grid gap-2 grid-cols-4 sm:grid-cols-6">
                                        <template x-for="icon in displayedIcons" :key="icon">
                                            <button
                                                type="button"
                                                class="flex items-center justify-center rounded-lg border border-gray-200 bg-white px-2 py-3 text-gray-600 transition hover:border-brand-300 hover:text-[#C43B25] dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                                @click="select(icon)"
                                            >
                                                <span class="text-xl"><i :class="icon"></i></span>
                                            </button>
                                        </template>
                                    </div>
                                    <div class="mt-2 text-xs text-gray-400 text-center" x-show="filteredIcons.length > displayedIcons.length">
                                        Escribe para filtrar más resultados.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Vista destino (opcional)</label>
                            <div class="relative">
                                <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M2.75 6.75C2.75 5.50736 3.75736 4.5 5 4.5H19C20.2426 4.5 21.25 5.50736 21.25 6.75V17.25C21.25 18.4926 20.2426 19.5 19 19.5H5C3.75736 19.5 2.75 18.4926 2.75 17.25V6.75Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M7 9H17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M7 12.5H14.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                <select
                                    name="view_id_action"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                >
                                    <option value="">Sin vista</option>
                                    @foreach (($viewsList ?? []) as $viewItem)
                                        <option value="{{ $viewItem->id }}" @selected(old('view_id_action', $operation->view_id_action) == $viewItem->id)>
                                            {{ $viewItem->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @error('view_id_action') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        {{-- CAMPO: COLOR --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Color</label>
                            <div class="relative" data-color-group>
                                <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"></circle><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"></circle><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"></circle><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"></circle><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.551-2.5 5.551-5.551C21.988 6.5 17.5 2 12 2z"></path></svg>
                                </span>
                                <input
                                    type="text"
                                    name="color"
                                    required
                                    value="{{ old('color', $operation->color) }}"
                                    placeholder="#000000"
                                    data-color-input
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] pr-12 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                />
                                {{-- Selector de Color --}}
                                <input 
                                    type="color" 
                                    data-color-picker
                                    value="{{ old('color', $operation->color) }}"
                                    oninput="this.closest('[data-color-group]').querySelector('[data-color-input]').value = this.value"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 h-8 w-8 cursor-pointer border-0 p-0 bg-transparent rounded"
                                >
                            </div>
                            <div class="mt-2 flex items-center gap-2 text-xs text-gray-400">
                                <span>Primarios:</span>
                                <div class="flex items-center gap-2">
                                    @php
                                        $primaryColors = ['#FF0000', '#00FF00', '#0000FF', '#FFD700', '#00BFFF', '#8B5CF6', '#EC4899', '#F97316'];
                                    @endphp
                                    @foreach ($primaryColors as $primary)
                                        <button
                                            type="button"
                                            class="h-6 w-6 rounded-full border border-gray-200 shadow-theme-xs"
                                            style="background-color: {{ $primary }};"
                                            aria-label="Color {{ $primary }}"
                                            onclick="const scope = this.closest('[data-color-wrapper]') || this.closest('form') || document; const group = scope.querySelector('[data-color-group]'); if (!group) return; const input = group.querySelector('[data-color-input]'); const picker = group.querySelector('[data-color-picker]'); if (input) input.value='{{ $primary }}'; if (picker) picker.value='{{ $primary }}';"
                                        ></button>
                                    @endforeach
                                </div>
                            </div>
                            @error('color') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tipo</label>
                            <div class="relative">
                                <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                <select
                                    name="type"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                >
                                    <option value="R" {{ old('type', $operation->type) === 'R' ? 'selected' : '' }}>Registro</option>
                                    <option value="T" {{ old('type', $operation->type) === 'T' ? 'selected' : '' }}>Tabla</option>
                                </select>
                            </div>
                            @error('type') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Estado</label>
                            <div class="relative">
                                <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                </span>
                                <select
                                    name="status"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                >
                                    <option value="1" {{ old('status', $operation->status) == 1 ? 'selected' : '' }}>Activo</option>
                                    <option value="0" {{ old('status', $operation->status) == 0 ? 'selected' : '' }}>Inactivo</option>
                                </select>
                            </div>
                            @error('status') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit" size="md" variant="primary">
                        <i class="ri-save-line"></i>
                        <span>Actualizar</span>
                    </x-ui.button>
                    
                    <x-ui.button
                        type="button"
                        size="md"
                        variant="outline"
                        @click="redirectToIndex()"
                    >
                        <i class="ri-close-line"></i>
                        <span>Cancelar</span>
                    </x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>
@endsection
