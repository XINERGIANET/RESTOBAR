<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nombre</label>
        <input
            type="text"
            name="name"
            value="{{ old('name', $area->name ?? '') }}"
            required
            placeholder="Ingrese el nombre"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
    </div>

    @php
        $selectedPrinterIds = old('printer_ids', isset($area) ? $area->printers?->pluck('id')->all() : []);
        if (!is_array($selectedPrinterIds)) {
            $selectedPrinterIds = [$selectedPrinterIds];
        }
        $selectedPrinterIds = array_values(array_unique(array_filter(array_map(
            fn($id) => is_numeric($id) ? (int) $id : null,
            $selectedPrinterIds
        ))));
    @endphp
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Ticketeras</label>
        <div class="rounded-lg border border-gray-300 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
            <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Marca una o varias impresoras para esta área.</p>
            <div class="max-h-44 space-y-2 overflow-y-auto pr-1">
                @forelse (($printers ?? collect()) as $printer)
                    <label class="flex items-center gap-3 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200">
                        <input type="checkbox"
                            name="printer_ids[]"
                            value="{{ $printer->id }}"
                            @checked(in_array((int) $printer->id, $selectedPrinterIds, true))
                            class="h-4 w-4 rounded border-gray-300 text-[#C43B25] focus:ring-brand-500">
                        <span>{{ $printer->name }}</span>
                        <span class="ml-auto text-xs text-gray-400">{{ $printer->ip ?? '-' }}</span>
                    </label>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">No hay ticketeras registradas para esta sucursal.</p>
                @endforelse
            </div>
        </div>
        @error('printer_ids')
            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
        @error('printer_ids.*')
            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    {{-- Sucursal asignada por sesion --}}
</div>
