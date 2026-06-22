@props([
    'label' => null,
    'options' => [],
    'placeholder' => 'Seleccione una opción...',
    'required' => false,
    'icon' => 'ri-price-tag-3-line',
    'hideIcon' => false,
    'clearable' => false,
    'name' => '',
    'iconClickEvent' => null,
    'displayField' => 'description',
    'value' => null,
    'disabled' => false,
    'clearOnFocus' => false,
])

<div x-data="{
        allOptions: {{ \Illuminate\Support\Js::from($options) }},
        displayField: '{{ $displayField }}',
        value: @js($value),
        query: '',
        open: false,

        init() {
            this.syncQueryFromId();
            this.$watch('value', () => this.syncQueryFromId());
        },

        updateOptions(newOptions) {
            this.allOptions = Array.isArray(newOptions) ? newOptions : [];
            this.syncQueryFromId();
        },

        syncQueryFromId() {
            if (this.value && this.allOptions.length > 0) {
                const found = this.allOptions.find(c => c.id == this.value);
                this.query = found ? (found[this.displayField] || '') : '';
            } else {
                this.query = '';
            }
        },

        get filteredOptions() {
            const q = (this.query || '').toLowerCase();
            if (q === '') return this.allOptions;
            return this.allOptions.filter(item =>
                (String(item[this.displayField] || '')).toLowerCase().includes(q)
            );
        },

        selectOption(item) {
            this.value = item.id;
            this.query = item[this.displayField] || '';
            this.open = false;
        },

        closeDropdown() {
            if (this.open) {
                this.open = false;
                this.syncQueryFromId();
            }
        },

        clear() {
            this.value = null;
            this.query = '';
            this.open = false;
        },

        toggleDropdown() {
            this.open = !this.open;
            if (this.open) {
                this.$nextTick(() => this.$refs.input?.focus());
            } else {
                this.syncQueryFromId();
            }
        }
    }"
    x-modelable="value"
    {{ $attributes->whereStartsWith('x-model') }}
    @update-combobox-options.window="
        if (!$event.detail || !$event.detail.options) return;
        if (($event.detail.name || '') !== @js($name ?? '')) return;
        updateOptions($event.detail.options)
    "
    @clear-combobox.window="
        if (($event.detail?.name || '') !== @js($name ?? '')) return;
        clear()
    "
    class="space-y-1.5 relative"
    x-on:mousedown.document="if (!$el.contains($event.target)) closeDropdown()"
>
    @if($label)
        <label class="block text-sm font-medium text-gray-600 dark:text-gray-400">
            {{ $label }}
            @if($required) <span class="text-red-500">*</span> @endif
        </label>
    @endif

    <div class="relative">
        @if(!$hideIcon && $iconClickEvent)
            <button
                type="button"
                @click="$dispatch('{{ $iconClickEvent }}')"
                class="absolute inset-y-0 left-1 my-auto flex h-9 w-9 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-[#C43B25]/10 hover:text-[#C43B25] focus:outline-none z-10"
                title="Acción"
            >
                <i class="{{ $icon }} text-[18px]"></i>
            </button>
        @elseif(!$hideIcon)
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                <i class="{{ $icon }} text-gray-400 text-[18px]"></i>
            </div>
        @endif

        <input
            x-ref="input"
            type="text"
            x-model="query"
            @focus="open = true"
            @click="open = true; {{ $clearOnFocus ? 'query = \'\'' : '' }}"
            @input="open = true"
            @dblclick="$el.select()"
            @keydown.escape="closeDropdown()"
            @keydown.enter.prevent="if(filteredOptions.length > 0) selectOption(filteredOptions[0])"
            placeholder="{{ $placeholder }}"
            {{ $disabled ? 'disabled' : '' }}
            class="h-11 w-full rounded-lg border border-gray-200 bg-white {{ $hideIcon ? 'pl-4' : 'pl-11' }} pr-10 text-sm text-gray-800 placeholder-gray-400 focus:border-[#C43B25] focus:ring-1 focus:ring-[#C43B25] dark:border-gray-700 dark:bg-dark-900 dark:text-white/90 transition-all disabled:opacity-60 disabled:cursor-not-allowed disabled:bg-gray-50 dark:disabled:bg-gray-800/80"
            autocomplete="off"
        >

        @if($name)
            <input type="hidden" name="{{ $name }}" x-model="value">
        @endif

        <div class="absolute inset-y-0 right-0 flex items-center pr-2">
            @if($clearable)
                <button
                    type="button"
                    x-show="value"
                    x-cloak
                    @mousedown.prevent
                    @click="clear()"
                    class="flex h-8 w-8 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-red-50 hover:text-red-500"
                    title="Quitar selección"
                >
                    <i class="ri-close-line text-base"></i>
                </button>
                <button
                    type="button"
                    x-show="!value"
                    @mousedown.prevent
                    @click="toggleDropdown()"
                    class="flex h-8 w-8 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-[#C43B25]"
                    title="Abrir opciones"
                >
                    <i class="ri-arrow-down-s-line text-lg transition-transform duration-200" :class="{'rotate-180': open}"></i>
                </button>
            @else
                <button
                    type="button"
                    @mousedown.prevent
                    @click="toggleDropdown()"
                    class="flex h-8 w-8 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-[#C43B25]"
                    title="Abrir opciones"
                >
                    <i class="ri-arrow-down-s-line text-lg transition-transform duration-200" :class="{'rotate-180': open}"></i>
                </button>
            @endif
        </div>

        <div x-show="open"
             x-transition.opacity.duration.200ms
             class="absolute z-50 mt-1 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
             style="display: none; max-height: 12rem;">
            <ul class="py-1">
                <template x-for="item in filteredOptions" :key="item.id">
                    <li @click="selectOption(item)"
                        class="cursor-pointer px-4 py-2 text-sm text-gray-700 hover:bg-[#C43B25]/5 hover:text-[#C43B25] font-medium transition-colors">
                        <span x-text="item[displayField]"></span>
                    </li>
                </template>

                <li x-show="filteredOptions.length === 0" class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400 cursor-default">
                    No se encontraron resultados.
                </li>
            </ul>
        </div>
    </div>

    @if($name)
        @error($name)
            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    @endif
</div>
