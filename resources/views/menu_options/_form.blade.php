@php
    use Illuminate\Support\HtmlString;

    $NameIcon = new HtmlString('
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="9" y1="3" x2="9" y2="21"></line>
        </svg>
    '); 

    $AccesIcon = new HtmlString('
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <polygon points="10 8 16 12 10 16 10 8"></polygon>
        </svg>
    ');

    $IconIcon = new HtmlString('
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="7" height="7" rx="1"></rect>
            <rect x="14" y="3" width="7" height="7" rx="1"></rect>
            <rect x="14" y="14" width="7" height="7" rx="1"></rect>
            <rect x="3" y="14" width="7" height="7" rx="1"></rect>
        </svg>
    ');

    $LinkIcon = new HtmlString('
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
        </svg>
    '); 

    $StatusIcon = new HtmlString('
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
    '); 

    $ViewIcon = new HtmlString('
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="3" y1="9" x2="21" y2="9"></line>
            <line x1="9" y1="21" x2="9" y2="9"></line>
        </svg>
    ');
@endphp

<div class="grid gap-5 sm:grid-cols-2">

    <div class="sm:col-span-2">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nombre de la Opción</label>
        <div class="relative">
            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                {!! $NameIcon !!}
            </span>
            <input
                type="text"
                name="name"
                value="{{ old('name', $menuOption->name ?? '') }}"
                required
                placeholder="Ej: Gestionar Usuarios"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            />
        </div>
        @error('name')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-2">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Vista Asociada</label>
        <div class="relative">
            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                {!! $ViewIcon !!}
            </span>
            <select
                name="view_id"
                required
                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            >
                <option value="">Seleccione una vista</option>
                @foreach ($views as $view)
                    <option value="{{ $view->id }}" {{ old('view_id', $menuOption->view_id ?? '') == $view->id ? 'selected' : '' }}>
                        {{ $view->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @error('view_id')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-2">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Ruta / Acción (Route Name)</label>
        <div class="relative">
            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                {!! $LinkIcon !!}
            </span>
            <input
                type="text"
                name="action"
                value="{{ old('action', $menuOption->action ?? '') }}"
                required
                placeholder="Ej: admin.users.index"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            />
        </div>
        @error('action')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="relative sm:col-span-1" x-data="iconPicker()" x-init="init()">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Clase del Icono</label>
        <div class="relative">
            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <i class="ri-grid-line text-lg"></i>
            </span>
            <input
                type="text"
                name="icon"
                x-ref="iconInput"
                x-model="search"
                value="{{ old('icon', $menuOption->icon ?? '') }}"
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
        @error('icon')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-1">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Estado</label>
        <div class="relative">
            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                {!! $StatusIcon !!}
            </span>
            <select
                name="status"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            >
                <option value="1" {{ old('status', $menuOption->status ?? 1) == 1 ? 'selected' : '' }}>Activo</option>
                <option value="0" {{ old('status', $menuOption->status ?? 1) == 0 ? 'selected' : '' }}>Inactivo</option>
            </select>
        </div>
        @error('status')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-1">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">¿Acceso Rápido?</label>
        <div class="relative">
            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                {!! $AccesIcon !!}
            </span>
            <select
                name="quick_access"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            >
                <option value="0" {{ old('quick_access', $menuOption->quick_access ?? 0) == 0 ? 'selected' : '' }}>No</option>
                <option value="1" {{ old('quick_access', $menuOption->quick_access ?? 0) == 1 ? 'selected' : '' }}>Sí</option>
            </select>
        </div>
        @error('quick_access')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

</div>
