<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nombre</label>
        <input type="text" name="name" value="{{ old('name', $table->name ?? '') }}" required
            placeholder="Ingrese el nombre"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Capacidad</label>
        <input type="number" name="capacity" min="1" value="{{ old('capacity', $table->capacity ?? '') }}"
            placeholder="Ej: 4"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Area</label>
        <div class="flex items-center gap-2">
            <select name="area_id" required
                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="">Seleccione area</option>
                @foreach ($areas as $area)
                    <option value="{{ $area->id }}" @selected(old('area_id', $table->area_id ?? '') == $area->id)>
                        {{ $area->name }}
                    </option>
                @endforeach
            </select>
            <x-ui.button type="button"
                variant="primary"
                size="xs"
                @click="$dispatch('open-area-modal')">
                <i class="ri-add-line"></i>
            </x-ui.button>
        </div>
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Estado</label>
        <select name="status"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
            <option value="1" @selected(old('status', $table->status ?? 1) == 1)>Activo</option>
            <option value="0" @selected(old('status', $table->status ?? 1) == 0)>Inactivo</option>
        </select>
    </div>

    <div class="hidden">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Situacion</label>
        <select name="situation"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
            <option value="libre" @selected(old('situation', $table->situation ?? 'libre') === 'libre')>Libre</option>
            <option value="ocupada" @selected(old('situation', $table->situation ?? 'libre') === 'ocupada')>Ocupada</option>
        </select>
    </div>

    <div class="hidden">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Hora apertura</label>
        <input type="time" name="opened_at" value="{{ old('opened_at', $table->opened_at ?? '') }}"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
    </div>
</div>
