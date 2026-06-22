<div>
    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
        Descripción <span class="text-red-500">*</span>
    </label>
    <input type="text" name="description" value="{{ old('description', $bank->description ?? '') }}" required
        placeholder="Ingrese el nombre del banco"
        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
    @error('description')
        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>

<div>
    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
        Orden <span class="text-red-500">*</span>
    </label>
    <input type="number" name="order_num" value="{{ old('order_num', $bank->order_num ?? '') }}" required
        placeholder="Ingrese el número de orden"
        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
    @error('order_num')
        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>

<div>
    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
        Estado
    </label>
    <select name="status" required
        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
        <option value="1" {{ old('status', $bank->status ?? 1) == 1 ? 'selected' : '' }}>Activo</option>
        <option value="0" {{ old('status', $bank->status ?? 1) == 0 ? 'selected' : '' }}>Inactivo</option>
    </select>
    @error('status')
        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
