<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Descripcion</label>
        <input type="text" name="description" value="{{ old('description', $category->description ?? '') }}" required
            placeholder="Ingrese la descripcion"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Abreviatura</label>
        <input type="text" name="abbreviation" value="{{ old('abbreviation', $category->abbreviation ?? '') }}" required
            placeholder="Ingrese la abreviatura"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
    </div>
    <!--Ventas/pedidos, Comras, general-->
    <div class="lg:col-span-2">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tipo de menú</label>
        <select name="menu_type" required
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
            @php
                $selectedType = old('menu_type', $currentMenuType ?? ($category->menu_type ?? 'VENTAS_PEDIDOS'));
            @endphp
            <option value="VENTAS_PEDIDOS" {{ $selectedType === 'VENTAS_PEDIDOS' ? 'selected' : '' }}>Ventas / Pedidos
            </option>
            <option value="COMPRAS" {{ $selectedType === 'COMPRAS' ? 'selected' : '' }}>Compras</option>
            <option value="GENERAL" {{ $selectedType === 'GENERAL' ? 'selected' : '' }}>General (todos)</option>
        </select>
    </div>

    <div class="lg:col-span-2" x-data="{ 
            imagePreview: '{{ isset($category) && $category->image ? asset('storage/' . $category->image) : '' }}',
            fileName: '{{ isset($category) && $category->image ? basename($category->image) : '' }}',
            imageError: '',
            
            showPreview(event) {
                this.imageError = '';
                const file = event.target.files[0];
                if (!file) {
                    this.imagePreview = '{{ isset($category) && $category->image ? asset('storage/' . $category->image) : '' }}';
                    this.fileName = '{{ isset($category) && $category->image ? basename($category->image) : '' }}';
                    return;
                }

                if (file.size > 2048 * 1024) {
                    this.imageError = 'El archivo es demasiado grande. Máximo 2MB.';
                    event.target.value = '';
                    return;
                }

                this.fileName = file.name;
                const reader = new FileReader();
                reader.onload = (e) => { 
                    this.imagePreview = e.target.result; 
                };
                reader.readAsDataURL(file);
            },

            removeImage() {
                this.imagePreview = '';
                this.fileName = '';
                this.imageError = '';
                document.getElementById('image-input').value = '';
            }
        }">

        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            Imagen (opcional)
        </label>

        <div x-show="imagePreview" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform scale-95"
            class="mb-3 flex items-center gap-3 p-2 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700">

            <img :src="imagePreview" alt="Vista previa"
                class="h-16 w-16 object-cover rounded border border-gray-300 dark:border-gray-600 shadow-sm">

            <div class="flex-1 min-w-0">
                <p class="text-xs font-medium text-gray-700 dark:text-gray-300 truncate"
                    x-text="fileName || 'Imagen seleccionada'"></p>
                <button type="button" @click="removeImage()"
                    class="text-[10px] text-red-600 hover:text-red-800 font-semibold uppercase tracking-wider">
                    Quitar archivo
                </button>
            </div>
        </div>

        <input type="file" name="image" id="image-input" accept="image/*" @change="showPreview($event)"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 file:mr-4 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-[#FF4622]/10 file:text-[#FF4622] hover:file:bg-[#FF4622]/20 dark:file:bg-[#FF4622]/20 dark:file:text-[#FF4622]" />

        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
            JPG, PNG, GIF, WEBP • Máximo 2MB
        </p>

        <p x-show="imageError" x-text="imageError" class="mt-1 text-xs text-error-600 dark:text-error-400" x-cloak></p>

        @error('image')
            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>
</div>
