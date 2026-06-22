<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Descripcion de la unidad</label>
        <input
            type="text"
            name="description"
            value="{{ old('description', $unit->description ?? '') }}"
            required
            placeholder="Ingrese la descripcion"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Abreviatura de la unidad</label>
        <input
            type="text"
            name="abbreviation"
            value="{{ old('abbreviation', $unit->abbreviation ?? '') }}"
            required
            placeholder="Ingrese la abreviatura"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tipo de unidad</label>
        <select
            name="type"
            required
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
            <option value="MASA" @selected(old('type', $unit->type ?? 'MASA') === 'MASA')>Masa</option>
            <option value="LONGITUD" @selected(old('type', $unit->type ?? 'LONGITUD') === 'LONGITUD')>Longitud</option>
            <option value="OTRO" @selected(old('type', $unit->type ?? 'OTRO') === 'OTRO')>Otro</option>
        </select>
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Es SUNAT</label>
        <div class="flex items-center gap-2">
            <input
                type="checkbox"
                name="is_sunat"
                value="1"
                @checked(old('is_sunat', $unit->is_sunat ?? false))
                class="h-4 w-4 rounded border-gray-300 text-[#FF4622] focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800"
            />
            <span class="text-sm text-gray-600 dark:text-gray-400">Marcar si es unidad SUNAT</span>
        </div>
    </div>
</div>
