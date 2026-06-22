<div x-data="{
    branch_id: '{{ old('branch_id', $sale->branch_id ?? '') }}',
    movement_type_id: '{{ old('movement_type_id', $sale->movement_type_id ?? '') }}',
    document_type_id: '{{ old('document_type_id', $sale->document_type_id ?? '') }}',
    person_id: '{{ old('person_id', $sale->person_id ?? '') }}',
    serie: '{{ old('serie', $sale->salesMovement->series ?? '') }}'
}" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Numero</label>
        <input type="text" name="number" value="{{ old('number', $sale->number ?? '') }}" required
            placeholder="Ingrese el numero"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Fecha</label>
        <input type="datetime-local" name="moved_at"
            value="{{ old('moved_at', isset($sale?->moved_at) ? $sale->moved_at->format('Y-m-d\TH:i') : '') }}" required
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
    </div>

    <div>
        <x-form.select.combobox disabled label="Sucursal" name="branch_id" x-model="branch_id" :required="true"
            :options="collect($branches ?? [])->map(fn($b) => ['id' => $b->id, 'description' => $b->legal_name])->values()->all()" icon="ri-store-2-line" />
    </div>

    <div style="display: none">
        <x-form.select.combobox label="Tipo movimiento" name="movement_type_id" x-model="movement_type_id"
            :required="true" :options="collect($movementTypes ?? [])->map(fn($m) => ['id' => $m->id, 'description' => $m->name])->values()->all()" icon="ri-file-list-3-line" />
    </div>
    <div>
        <x-form.select.combobox label="Tipo documento" name="document_type_id" x-model="document_type_id"
            :required="true" :options="collect($documentTypes ?? [])->map(fn($d) => ['id' => $d->id, 'description' => $d->name])->values()->all()" icon="ri-file-list-3-line" />
    </div>

    <div>
        <x-form.select.combobox label="Persona (opcional)" name="person_id" x-model="person_id"
            :options="collect($people ?? [])->map(fn($person) => [
        'id' => $person->id,
        'description' => trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? '')) . ($person->document_number ? ' - ' . $person->document_number : '')
    ])->values()->all()" icon="ri-user-line" />
    </div>

    <div class="sm:col-span-2 lg:col-span-3">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Comentario</label>
        <textarea name="comment" rows="3"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            placeholder="Ingrese comentario">{{ old('comment', $sale->comment ?? '') }}</textarea>
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Estado</label>
        <select name="status" required
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
            <option value="A" @selected(old('status', $sale->status ?? 'A') === 'A')>Activo</option>
            <option value="I" @selected(old('status', $sale->status ?? 'A') === 'I')>Inactivo</option>
        </select>
    </div>
    <!--Editar serie de venta (display:none para que solo se pueda editar en consola)-->
    <div style="display: none">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Serie</label>
        <input type="text" name="serie" value="{{ old('serie', $sale->salesMovement->series ?? '') }}" required
            placeholder="Ej. 001" maxlength="4"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
    </div>
</div>