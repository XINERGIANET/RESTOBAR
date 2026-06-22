<!--Modal de edicion de tarjeta-->
    <x-ui.modal x-data="{
        open: false,
        cardId: null,
        description: '',
        type: '',
        orderNum: null,
        icon: '',
        status: '1'
    }"
        @open-edit-card-modal.window="open = true; cardId = $event.detail.id; description = $event.detail.description; type = $event.detail.type; orderNum = $event.detail.order_num; icon = $event.detail.icon || ''; status = $event.detail.status.toString()"
        @close-edit-card-modal.window="open = false" :isOpen="false" :showCloseButton="false" class="max-w-md">
    <div class="p-6 space-y-4">
        <h3 class="mb-6 text-lg font-semibold text-gray-800 dark:text-white/90">Editar Tarjeta</h3>
        @if ($errors->any())
            <div class="mb-5">
                <x-ui.alert variant="error" title="Revisa los campos" message="Hay errores en el formulario, corrige los datos e intenta nuevamente." />
            </div>
        @endif
        <form id="edit-card-form" class="space-y-4"
            x-bind:action="cardId ? '{{ url('/admin/herramientas/tarjetas') }}/' + cardId + '{{ request('view_id') ? '?view_id=' . request('view_id') : '' }}' : '#'" method="POST"
            enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @if (request('view_id'))
                <input type="hidden" name="view_id" value="{{ request('view_id') }}">
            @endif
            <div class="grid gap-5">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Descripcion</label>
                    <div class="relative">
                        <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 7V17C4 18.1046 4.89543 19 6 19H18C19.1046 19 20 18.1046 20 17V7M4 7L12 12L20 7M4 7L12 2L20 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <input
                            type="text"
                            name="description"
                            id="edit-description"
                            x-model="description"
                            required
                            placeholder="Ingrese la descripcion"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tipo</label>
                    <div class="relative">
                        <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <i class="ri-bank-card-line"></i>
                        </span>
                        <select
                            name="type"
                            id="edit-type"
                            x-model="type"
                            required
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        >
                            <option value="">Seleccione un tipo</option>
                            <option value="C">Credito</option>
                            <option value="D">Debito</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Orden</label>
                    <div class="relative">
                        <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <i class="ri-numbers-line"></i>
                        </span>
                        <input
                            type="number"
                            name="order_num"
                            id="edit-order_num"
                            x-model="orderNum"
                            required
                            placeholder="Ingrese el orden"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Icono</label>
                    <div class="relative">
                        <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <i class="ri-image-line"></i>
                        </span>
                        <input
                            type="text"
                            name="icon"
                            id="edit-icon"
                            x-model="icon"
                            placeholder="Ingrese el icono (opcional)"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Estado</label>
                    <div class="relative">
                        <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <i class="ri-toggle-line"></i>
                        </span>
                        <select
                            name="status"
                            id="edit-status"
                            x-model="status"
                            required
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        >
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 justify-end">
                <x-ui.button type="submit" size="md" variant="primary">Guardar</x-ui.button>
                <x-ui.button type="button" size="md" variant="outline"
                    @click="open = false">Cancelar</x-ui.button>
            </div>
        </form>
    </div>
</x-ui.modal>
