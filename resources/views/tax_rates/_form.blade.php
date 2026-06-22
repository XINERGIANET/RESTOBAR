<div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Codigo</label>
        <input
            type="text"
            name="code"
            value="{{ old('code', $taxRate->code ?? '') }}"
            required
            placeholder="Ingrese el codigo"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Descripcion</label>
        <input
            type="text"
            name="description"
            value="{{ old('description', $taxRate->description ?? '') }}"
            required
            placeholder="Ingrese la descripcion"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        />
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tasa de impuesto</label>
        <input
            type="number"
            name="tax_rate"
            step="0.1"
            value="{{ old('tax_rate', $taxRate->tax_rate ?? '') }}"
            required
            placeholder="Ingrese la tasa de impuesto"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        />
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Orden</label>
        <input
            type="number"
            name="order_num"
            value="{{ old('order_num', $taxRate->order_num ?? '') }}"
            required
            placeholder="Ingrese el orden"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        />
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Estado</label>
        <div class="flex items-center gap-2">
            <input
                type="checkbox"
                name="status"
                value="1"
                @checked(old('status', $taxRate->status ?? true))
                class="h-4 w-4 rounded border-gray-300 text-[#FF4622] focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800"
            />
            <span class="text-sm text-gray-600 dark:text-gray-400">Activo</span>
        </div>
    </div>

</div>
