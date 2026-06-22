<div class="grid gap-6 sm:grid-cols-2">
    <div>
        <label class="mb-2 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-400">
            <i class="ri-shield-user-line text-gray-400"></i>
            Nombre del Rol
        </label>
        <input
            type="text"
            name="name"
            value="{{ old('name', $role->name ?? '') }}"
            required
            placeholder="Ej: Administrador, Cajero..."
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
    </div>

    <div>
        <label class="mb-2 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-400">
            <i class="ri-information-line text-gray-400"></i>
            Descripción
        </label>
        <input
            type="text"
            name="description"
            value="{{ old('description', $role->description ?? '') }}"
            required
            placeholder="Breve descripción del propósito de este rol"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
    </div>
</div>
