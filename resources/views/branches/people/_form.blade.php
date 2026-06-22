@php
    $selectedDepartmentId = old('department_id', $selectedDepartmentId ?? null);
    $selectedProvinceId = old('province_id', $selectedProvinceId ?? null);
    $selectedDistrictId = old('location_id', $selectedDistrictId ?? (($person ?? null)?->location_id));
    $selectedRoleIds = old('roles', $selectedRoleIds ?? []);
    $selectedProfileId = old('profile_id', $selectedProfileId ?? null);
    $userName = old('user_name', $userName ?? null);
    $initialDefaultViewId = old(
        'default_view_id',
        isset($person) ? $person->user?->profile?->default_view_id : null
    );
    $initialDefaultViewId = $initialDefaultViewId !== '' && $initialDefaultViewId !== null ? (int) $initialDefaultViewId : null;
    $viewOptionsList = collect($viewOptions ?? [])->values()->all();
    // Permite ocultar campos de PIN/Roles y relajar requeridos cuando se usa solo para clientes (por ejemplo, en POS).
    $hidePinAndRoles = $hidePinAndRoles ?? false;
@endphp

<div
    class="grid gap-5"
    style="grid-template-columns: repeat(4, minmax(0, 1fr));"
    data-departments='@json($departments ?? [], JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-provinces='@json($provinces ?? [], JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-districts='@json($districts ?? [], JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-department-id='@json(old('department_id', $selectedDepartmentId ?? null), JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-province-id='@json(old('province_id', $selectedProvinceId ?? null), JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-district-id='@json(old('location_id', $selectedDistrictId ?? (($person ?? null)?->location_id)), JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-roles='@json($roles ?? [], JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-selected-roles='@json($selectedRoleIds ?? [], JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-profiles='@json($profiles ?? [], JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-selected-profile='@json($selectedProfileId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT)'
    x-data="{
        departments: JSON.parse($el.dataset.departments || '[]'),
        provinces: JSON.parse($el.dataset.provinces || '[]'),
        districts: JSON.parse($el.dataset.districts || '[]'),
        departmentId: JSON.parse($el.dataset.departmentId || 'null') || '',
        provinceId: JSON.parse($el.dataset.provinceId || 'null') || '',
        districtId: JSON.parse($el.dataset.districtId || 'null') || '',
        roles: JSON.parse($el.dataset.roles || '[]'),
        profiles: JSON.parse($el.dataset.profiles || '[]'),
        selectedRoleIds: JSON.parse($el.dataset.selectedRoles || '[]'),
        selectedProfileId: JSON.parse($el.dataset.selectedProfile || 'null') || '',
        selectedDefaultViewId: @js($initialDefaultViewId),
        roleToAdd: '',
        userRoleId: 1,
        init() {
            if (!this.provinceId && this.districtId) {
                const district = this.districts.find(d => d.id == this.districtId);
                if (district) {
                    this.provinceId = district.parent_location_id ?? '';
                }
            }
            if (!this.departmentId && this.provinceId) {
                const province = this.provinces.find(p => p.id == this.provinceId);
                if (province) {
                    this.departmentId = province.parent_location_id ?? '';
                }
            }
            this.$watch('selectedProfileId', (val) => {
                if (val === '' || val === null || val === undefined) return;
                const p = this.profiles.find(pr => String(pr.id) === String(val));
                if (!p) return;
                this.selectedDefaultViewId = p.default_view_id != null && p.default_view_id !== ''
                    ? parseInt(p.default_view_id, 10) : null;
            });
        },
        addRole() {
            const roleId = parseInt(this.roleToAdd || 0, 10);
            if (!roleId) return;
            if (!this.selectedRoleIds.includes(roleId)) {
                this.selectedRoleIds.push(roleId);
            }
            this.roleToAdd = '';
        },
        toggleRole(roleId) {
            if (this.selectedRoleIds.includes(roleId)) {
                this.selectedRoleIds = this.selectedRoleIds.filter(id => id !== roleId);
            } else {
                this.selectedRoleIds.push(roleId);
            }
        },
        hasUserRole() {
            return this.selectedRoleIds.includes(this.userRoleId);
        },
        get filteredProvinces() {
            return this.provinces.filter(p => p.parent_location_id == this.departmentId);
        },
        get filteredDistricts() {
            return this.districts.filter(d => d.parent_location_id == this.provinceId);
        },
        onDepartmentChange() {
            this.provinceId = '';
            this.districtId = '';
        },
        onProvinceChange() {
            this.districtId = '';
        }
    }"
    x-init="init()"
>
    @if($hidePinAndRoles)
    {{-- Buscador DNI/RUC modo POS --}}
    <div class="col-span-4 mb-1"
         x-data="{
            dniQuery: @js(old('document_number', $person->document_number ?? '')),
            dniLoading: false,
            dniError: '',
            async searchDni() {
                const q = this.dniQuery.trim();
                const len = q.length;
                if (len !== 8 && len !== 11) {
                    this.dniError = 'Debe ingresar 8 dígitos para DNI o 11 para RUC.';
                    return;
                }
                this.dniLoading = true;
                this.dniError = '';
                try {
                    const endpoint = len === 8 ? '/api/dni/' : '/api/ruc/';
                    const res = await fetch(endpoint + encodeURIComponent(q));
                    const data = await res.json();
                    if (!res.ok) { this.dniError = data.error || 'No se encontraron datos.'; return; }

                    const typeSelect = document.querySelector('[name=person_type]');
                    const fnInput = document.querySelector('[name=first_name]');
                    const lnInput = document.querySelector('[name=last_name]');
                    const addrInput = document.querySelector('[name=address]');

                    // Reset inputs
                    if (fnInput) fnInput.value = '';
                    if (lnInput) lnInput.value = '';
                    if (addrInput) addrInput.value = '';

                    if (len === 8) {
                        // DNI - Persona natural
                        const names = data.nombres || '';
                        const lastName = ((data.apellido_paterno || '') + ' ' + (data.apellido_materno || '')).trim();
                        if (fnInput) fnInput.value = names;
                        if (lnInput) lnInput.value = lastName;
                        if (typeSelect) typeSelect.value = 'DNI';
                    } else {
                        // RUC - Persona jurídica o con negocio
                        const businessName = data.razon_social || '';
                        const address = data.direccion || '';
                        if (fnInput) fnInput.value = 'EMPRESA / RUC';
                        if (lnInput) lnInput.value = businessName;
                        if (addrInput) addrInput.value = address;
                        if (typeSelect) typeSelect.value = 'RUC';
                    }
                } catch(e) {
                    this.dniError = 'Error al consultar. Intente de nuevo.';
                } finally {
                    this.dniLoading = false;
                }
            }
         }">
        <label class="mb-1.5 block text-sm font-semibold text-blue-700 dark:text-blue-400">
            <i class="ri-search-eye-line"></i> Documento (DNI / RUC)
            <span class="text-xs font-normal text-gray-500">(ingresa el número y pulsa Buscar para autocompletar)</span>
        </label>
        <div class="flex gap-2">
            <input
                type="text"
                name="document_number"
                x-model="dniQuery"
                @keydown.enter.prevent="searchDni()"
                maxlength="11"
                placeholder="Ej: 12345678 o 20123456789"
                class="h-10 w-full rounded-lg border border-blue-300 bg-transparent px-4 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none dark:border-blue-700 dark:bg-gray-900 dark:text-white/90"
            />
            <button
                type="button"
                @click="searchDni()"
                :disabled="dniLoading"
                class="shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700 active:scale-95 disabled:opacity-60 transition-all"
            >
                <i x-show="!dniLoading" class="ri-search-line"></i>
                <i x-show="dniLoading" class="ri-loader-4-line animate-spin"></i>
                <span x-text="dniLoading ? 'Buscando...' : 'Buscar'"></span>
            </button>
        </div>
        <p x-show="dniError" x-text="dniError" class="mt-1 text-xs text-red-500"></p>
    </div>
    @endif

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            Tipo de persona
            @unless($hidePinAndRoles)<span class="text-red-500">*</span>@endunless
        </label>
        <select
            name="person_type"
            @unless($hidePinAndRoles) required @endunless
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
            <option value="">Seleccione tipo</option>
            <option value="DNI" @selected(old('person_type', $person->person_type ?? '') === 'DNI')>DNI</option>
            <option value="RUC" @selected(old('person_type', $person->person_type ?? '') === 'RUC')>RUC</option>
            <option value="CARNET DE EXTRANGERIA" @selected(old('person_type', $person->person_type ?? '') === 'CARNET DE EXTRANGERIA')>CARNET DE EXTRANGERIA</option>
            <option value="PASAPORTE" @selected(old('person_type', $person->person_type ?? '') === 'PASAPORTE')>PASAPORTE</option>
        </select>
        @error('person_type')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    @if(!$hidePinAndRoles)
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            Documento
            <span class="text-red-500">*</span>
        </label>
        <input
            type="text"
            name="document_number"
            value="{{ old('document_number', $person->document_number ?? '') }}"
            required
            placeholder="Ingrese el documento"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
        @error('document_number')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>
    @endif
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nombres</label>
        <input
            type="text"
            name="first_name"
            value="{{ old('first_name', $person->first_name ?? '') }}"
            required
            placeholder="Ingrese los nombres"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
        @error('first_name')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>


    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Apellidos</label>
        <input
            type="text"
            name="last_name"
            value="{{ old('last_name', $person->last_name ?? '') }}"
            required
            placeholder="Ingrese los apellidos"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
        @error('last_name')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <x-form.date-picker
            name="fecha_nacimiento"
            label="Fecha de nacimiento"
            placeholder="Seleccione fecha de nacimiento"
            dateFormat="Y-m-d"
            :defaultDate="old('fecha_nacimiento', $person?->fecha_nacimiento ? \Carbon\Carbon::parse($person->fecha_nacimiento)->format('Y-m-d') : '')"
        />
        @error('fecha_nacimiento')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Genero</label>
        <select
            name="genero"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
            <option value="">Seleccione genero</option>
            <option value="MASCULINO" @selected(old('genero', $person->genero ?? '') === 'MASCULINO')>MASCULINO</option>
            <option value="FEMENINO" @selected(old('genero', $person->genero ?? '') === 'FEMENINO')>FEMENINO</option>
            <option value="OTRO" @selected(old('genero', $person->genero ?? '') === 'OTRO')>OTRO</option>
        </select>
        @error('genero')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    @unless($hidePinAndRoles)
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">PIN (mozo)</label>
            <input
                type="password"
                name="pin"
                value="{{ old('pin', $person->pin ?? '') }}"
                placeholder="{{ isset($person->id) ? 'Dejar en blanco para no cambiar' : 'Opcional: para identificar al mozo en pedidos' }}"
                maxlength="20"
                autocomplete="off"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            />
            @error('pin')
                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
            @enderror
        </div>
    @endunless

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Telefono</label>
        <input
            type="text"
            name="phone"
            value="{{ old('phone', $person->phone ?? '') }}"
            placeholder="Ingrese el telefono"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
        @error('phone')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Email</label>
        <input
            type="email"
            name="email"
            value="{{ old('email', $person->email ?? '') }}"
            placeholder="Ingrese el email"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
        @error('email')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-1 ">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Direccion</label>
        <input
            type="text"
            name="address"
            value="{{ old('address', $person->address ?? '') }}"
            placeholder="Ingrese la direccion"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
        />
        @error('address')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    @unless($hidePinAndRoles)
        <div class="sm:col-span-1">
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Departamento</label>
            <select
                name="department_id"
                x-model="departmentId"
                @change="onDepartmentChange()"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
            >
                <option value="">Seleccione departamento</option>
                <template x-for="department in departments" :key="department.id">
                    <option :value="department.id" :selected="department.id == departmentId" x-text="department.name"></option>
                </template>
            </select>
        </div>

        <div class="sm:col-span-1">
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Provincia</label>
            <select
                name="province_id"
                x-model="provinceId"
                @change="onProvinceChange()"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
            >
                <option value="">Seleccione provincia</option>
                <template x-for="province in filteredProvinces" :key="province.id">
                    <option :value="province.id" :selected="province.id == provinceId" x-text="province.name"></option>
                </template>
            </select>
        </div>

        <div class="sm:col-span-1">
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Distrito</label>
            <select
                name="location_id"
                x-model="districtId"
                required
                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
            >
                <option value="">Seleccione distrito</option>
                <template x-for="district in filteredDistricts" :key="district.id">
                    <option :value="district.id" :selected="district.id == districtId" x-text="district.name"></option>
                </template>
            </select>
            @error('location_id')
                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
            @enderror
        </div>
    @endunless

    @unless($hidePinAndRoles)
        <div class="sm:col-span-2 lg:col-span-3 xl:col-span-4">
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Roles</label>
            <div class="flex items-center gap-4 flex-nowrap">
                <template x-for="role in roles" :key="role.id">
                    <label class="inline-flex items-center gap-2 whitespace-nowrap  bg-white px-4 py-2 text-sm text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">
                        <input
                            type="checkbox"
                            name="roles[]"
                            :value="role.id"
                            :checked="selectedRoleIds.includes(role.id)"
                            @change="toggleRole(role.id)"
                            class="h-4 w-4 rounded border-gray-300 text-[#FF4622] focus:ring-brand-500/10"
                        />
                        <span x-text="role.name"></span>
                    </label>
                </template>
            </div>
        </div>

        <template x-if="hasUserRole()">
            <div class="sm:col-span-2 lg:col-span-3 xl:col-span-4">
                <div class="rounded-2xl border border-brand-100 bg-[#FF4622]/10/40 p-4 dark:border-[#FF4622]/20 dark:bg-[#FF4622]/5">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Usuario</label>
                            <input
                                type="text"
                                name="user_name"
                                value="{{ $userName }}"
                                placeholder="Ingrese el usuario"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 @error('user_name') border-red-500 dark:border-red-500 @enderror"
                            />
                            @error('user_name')
                                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Contraseña</label>
                            <input
                                type="password"
                                name="password"
                                placeholder="Ingrese la contraseña (mín. 8 caracteres)"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 @error('password') border-red-500 dark:border-red-500 @enderror"
                            />
                            @error('password')
                                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Confirmar contraseña</label>
                            <input
                                type="password"
                                name="password_confirmation"
                                placeholder="Confirme la contraseña"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 @error('password_confirmation') border-red-500 dark:border-red-500 @enderror"
                            />
                            @error('password_confirmation')
                                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="sm:col-span-2 lg:col-span-1">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Perfil</label>
                            <select
                                name="profile_id"
                                x-model="selectedProfileId"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 @error('profile_id') border-red-500 dark:border-red-500 @enderror"
                            >
                                <option value="">Seleccione perfil</option>
                                <template x-for="profile in profiles" :key="profile.id">
                                    <option :value="profile.id" :selected="profile.id == selectedProfileId" x-text="profile.name"></option>
                                </template>
                            </select>
                            @error('profile_id')
                                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="sm:col-span-2 lg:col-span-2">
                            <x-form.select.combobox
                                label="Vista por defecto"
                                name="default_view_id"
                                x-model="selectedDefaultViewId"
                                :options="$viewOptionsList"
                                placeholder="Seleccione vista por defecto"
                                icon="ri-layout-grid-line"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Se guarda en el <strong>perfil</strong> elegido (todos los usuarios con ese perfil la comparten al iniciar sesión).
                            </p>
                            @error('default_view_id')
                                <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </template>
    @endunless
</div>

