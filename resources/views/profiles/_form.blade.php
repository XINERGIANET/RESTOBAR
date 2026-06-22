@php
    $viewOptionsList = collect($viewOptions ?? [])->values()->all();
    $initialDefaultView = old('default_view_id', $profile->default_view_id ?? '');
    $initialDefaultView = $initialDefaultView !== '' && $initialDefaultView !== null ? (int) $initialDefaultView : null;
@endphp

<div class="grid gap-5">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nombre</label>
        <div class="relative">
            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <i class="ri-user-settings-line"></i>
            </span>
            <input
                type="text"
                name="name"
                value="{{ old('name', $profile->name ?? '') }}"
                required
                placeholder="Ingrese el nombre del perfil"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            />
        </div>
        @error('name')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Estado</label>
        <div class="relative">
            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <i class="ri-toggle-line"></i>
            </span>
            <select
                name="status"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
            >
                <option value="1" @selected(old('status', $profile->status ?? 1) == 1)>Activo</option>
                <option value="0" @selected(old('status', $profile->status ?? 1) == 0)>Inactivo</option>
            </select>
        </div>
        @error('status')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div x-data="{ defaultViewId: @js($initialDefaultView) }">
        <x-form.select.combobox
            label="Vista por defecto"
            name="default_view_id"
            x-model="defaultViewId"
            :options="$viewOptionsList"
            placeholder="Seleccione vista por defecto"
            icon="ri-layout-grid-line"
        />
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Tras iniciar sesión, usuarios con este perfil irán al inicio con esta vista (menú contextual).</p>
        @error('default_view_id')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>
</div>
