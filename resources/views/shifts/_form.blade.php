<div class="grid gap-5">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nombre del Turno</label>
        <div class="relative">
            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <i class="ri-text-spacing"></i>
            </span>
            <input
                type="text"
                name="name"
                value="{{ old('name', $shift->name ?? '') }}"
                required
                placeholder="Ej: Mañana"
                @if(($useAlpine ?? false)) x-model="form.name" @endif
                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            />
        </div>
        @error('name')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Abreviación</label>
            <div class="relative">
                <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    <i class="ri-font-size"></i>
                </span>
                <input
                    type="text"
                    name="abbreviation"
                    value="{{ old('abbreviation', $shift->abbreviation ?? '') }}"
                    required
                    placeholder="Ej: T-M"
                    @if(($useAlpine ?? false)) x-model="form.abbreviation" @endif
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                />
            </div>
            @error('abbreviation')
                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Hora Inicio</label>
            <div class="relative">
                <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    <i class="ri-time-line"></i>
                </span>
                <x-form.input.time
                    name="start_time"
                    :value="old('start_time', $shift->start_time ?? '')"
                    :required="true"
                    :useAlpine="($useAlpine ?? false)"
                />
            </div>
            @error('start_time')
                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Hora Fin</label>
            <div class="relative">
                <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    <i class="ri-time-fill"></i>
                </span>
                <x-form.input.time
                    name="end_time"
                    :value="old('end_time', $shift->end_time ?? '')"
                    :required="true"
                    :useAlpine="($useAlpine ?? false)"
                />
            </div>
            @error('end_time')
                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>
