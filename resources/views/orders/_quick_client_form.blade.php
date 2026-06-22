@php
    $initialPersonType = old('person_type', 'DNI');
@endphp

<div x-data="quickClientForm({
        initialPersonType: @js($initialPersonType),
        initialDocument: @js(old('document_number', '')),
        initialFirstName: @js(old('first_name', '')),
        initialLastName: @js(old('last_name', '')),
        initialBirthDate: @js(old('fecha_nacimiento', '')),
        initialGender: @js(old('genero', '')),
        initialPhone: @js(old('phone', '')),
        initialEmail: @js(old('email', '')),
        initialAddress: @js(old('address', '')),
        initialLocationId: @js(old('location_id', $branch->location_id ?? '')),
        departments: @js($departments ?? []),
        provinces: @js($provinces ?? []),
        districts: @js($districts ?? []),
    })" x-init="init()" class="space-y-6">

    <!-- Bloque 1: Identificación -->
    <div class="flex flex-col gap-5 md:flex-row">
        <div class="w-full md:w-1/3">
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Tipo de persona <span class="text-red-500">*</span>
            </label>
            <select name="person_type" x-model="personType" @change="onPersonTypeChange()"
                class="h-12 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 focus:border-[#FF4622] focus:ring-2 focus:ring-[#FF4622]/20 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <option value="DNI">DNI</option>
                <option value="RUC">RUC</option>
            </select>
            @error('person_type')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>
        
        <div class="w-full md:w-2/3">
            <label class="mb-1.5 block text-sm font-semibold text-[#2752FF]">
                <i class="ri-search-eye-line mr-1"></i> Documento (DNI / RUC)
                <span class="text-xs font-normal text-gray-500 hidden xl:inline-block">(ingresa y busca)</span>
            </label>
            <div class="flex flex-col gap-3 sm:flex-row">
                <input type="text" name="document_number" x-model.trim="documentNumber"
                    @keydown.enter.prevent="searchDocument()" :maxlength="isRuc ? 11 : 8"
                    placeholder="Ej: 12345678 o 20123456789"
                    class="h-12 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 placeholder:text-gray-400 focus:border-[#FF4622] focus:ring-2 focus:ring-[#FF4622]/20 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                <button type="button" @click="searchDocument()" :disabled="loading"
                    class="inline-flex h-12 shrink-0 items-center justify-center gap-2 rounded-xl bg-[#2752FF] px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#1f45dc] disabled:cursor-not-allowed disabled:opacity-60">
                    <i :class="loading ? 'ri-loader-4-line animate-spin' : 'ri-search-line'"></i>
                    <span x-text="loading ? 'Buscando...' : 'Buscar'"></span>
                </button>
            </div>
            <p x-show="errorMessage" x-text="errorMessage" class="mt-1 text-xs text-red-500"></p>
        </div>
    </div>


    <!-- Bloque 2: Información Personal y Contacto -->
    <div>
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 mb-5">
            <div :class="isRuc ? 'md:col-span-2' : ''">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    x-text="isRuc ? 'Razón social' : 'Nombres'"></label>
                <input type="text" name="first_name" x-model="firstName"
                    :placeholder="isRuc ? 'Ingrese la razón social' : 'Ingrese los nombres'" required
                    class="h-12 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 placeholder:text-gray-400 focus:border-[#FF4622] focus:ring-2 focus:ring-[#FF4622]/20 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                @error('first_name')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div x-show="!isRuc" x-cloak>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Apellidos <span class="text-red-500">*</span>
                </label>
                <input type="text" name="last_name" x-model="lastName" :required="!isRuc"
                    placeholder="Ingrese los apellidos"
                    class="h-12 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 placeholder:text-gray-400 focus:border-[#FF4622] focus:ring-2 focus:ring-[#FF4622]/20 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                @error('last_name')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    x-text="isRuc ? 'Inscripción' : 'Nacimiento'"></label>
                <input type="date" name="fecha_nacimiento" x-model="birthDate"
                    class="h-12 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 focus:border-[#FF4622] focus:ring-2 focus:ring-[#FF4622]/20 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                @error('fecha_nacimiento')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div x-show="!isRuc" x-cloak>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Género</label>
                <select name="genero" x-model="gender"
                    class="h-12 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 focus:border-[#FF4622] focus:ring-2 focus:ring-[#FF4622]/20 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="">Seleccione género</option>
                    <option value="MASCULINO">Masculino</option>
                    <option value="FEMENINO">Femenino</option>
                    <option value="OTRO">Otro</option>
                </select>
                @error('genero')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div :class="isRuc ? 'sm:col-span-1 lg:col-span-1' : ''">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Teléfono</label>
                <input type="text" name="phone" x-model="phone" placeholder="Sin guiones"
                    class="h-12 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 placeholder:text-gray-400 focus:border-[#FF4622] focus:ring-2 focus:ring-[#FF4622]/20 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                @error('phone')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div :class="isRuc ? 'sm:col-span-2 lg:col-span-2' : ''">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                <input type="email" name="email" x-model="email" placeholder="ejemplo@correo.com"
                    class="h-12 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 placeholder:text-gray-400 focus:border-[#FF4622] focus:ring-2 focus:ring-[#FF4622]/20 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                @error('email')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>


    <!-- Bloque 3: Ubicación y Dirección -->
    <div>
        <div class="mb-5">
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Dirección completa</label>
            <input type="text" name="address" x-model="address" placeholder="Av. Principal 123..."
                class="h-12 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 placeholder:text-gray-400 focus:border-[#FF4622] focus:ring-2 focus:ring-[#FF4622]/20 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
            @error('address')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Departamento</label>
                <select name="department_id" :value="departmentId" @change="departmentId = $event.target.value; onDepartmentChange()"
                    class="h-12 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 focus:border-[#FF4622] focus:ring-2 focus:ring-[#FF4622]/20 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="">Selección</option>
                    @foreach($departments ?? [] as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Provincia</label>
                <select name="province_id" x-model="provinceId" @change="onProvinceChange()"
                    class="h-12 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 focus:border-[#FF4622] focus:ring-2 focus:ring-[#FF4622]/20 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="">Selección</option>
                    <template x-for="province in filteredProvinces" :key="province.id">
                        <option :value="String(province.id)" x-text="province.name"></option>
                    </template>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Distrito</label>
                <select name="location_id" x-model="districtId" @change="districtId = $event.target.value"
                    class="h-12 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 focus:border-[#FF4622] focus:ring-2 focus:ring-[#FF4622]/20 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="">Selección</option>
                    <template x-for="district in filteredDistricts" :key="district.id">
                        <option :value="String(district.id)" x-text="district.name"></option>
                    </template>
                </select>
                @error('location_id')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>
</div>

<script>
    function quickClientForm(config) {
        return {
            personType: config.initialPersonType || 'DNI',
            documentNumber: config.initialDocument || '',
            firstName: config.initialFirstName || '',
            lastName: config.initialLastName || '',
            birthDate: config.initialBirthDate || '',
            gender: config.initialGender || '',
            phone: config.initialPhone || '',
            email: config.initialEmail || '',
            address: config.initialAddress || '',
            departmentId: '',
            provinceId: '',
            districtId: '',
            baseLocationId: config.initialLocationId ? String(config.initialLocationId) : '',
            departments: config.departments || [],
            provinces: config.provinces || [],
            districts: config.districts || [],
            loading: false,
            errorMessage: '',
            init() {
                this.onPersonTypeChange();
                
                if (this.baseLocationId) {
                    const targetLocation = String(this.baseLocationId);
                    
                    let district = this.districts.find(d => String(d.id) === targetLocation);
                    let province = null;
                    let department = null;
                    
                    if (district) {
                        province = this.provinces.find(p => String(p.id) === String(district.parent_location_id));
                    } else {
                        province = this.provinces.find(p => String(p.id) === targetLocation);
                        
                        if (province) {
                            district = this.districts.find(d => String(d.parent_location_id) === String(province.id) && String(d.name).trim().toUpperCase() === String(province.name).trim().toUpperCase());
                            
                            if (!district) {
                                const provDistricts = this.districts.filter(d => String(d.parent_location_id) === String(province.id));
                                if (provDistricts.length > 0) {
                                    district = provDistricts[0];
                                }
                            }
                        }
                    }
                    
                    if (province) {
                        department = this.departments.find(d => String(d.id) === String(province.parent_location_id));
                    } else if (!district && !province) {
                        department = this.departments.find(d => String(d.id) === targetLocation);
                    }
                    
                    if (department) this.departmentId = String(department.id);
                    
                    // Tiempo de respiro para que Alpine renderice los <option> antes del x-model
                    setTimeout(() => {
                        if (province) this.provinceId = String(province.id);
                        
                        setTimeout(() => {
                            if (district) this.districtId = String(district.id);
                        }, 100);
                    }, 100);
                }
            },
            get isRuc() {
                return String(this.personType).toUpperCase() === 'RUC';
            },
            get filteredProvinces() {
                return this.provinces.filter(item => String(item.parent_location_id) === String(this.departmentId));
            },
            get filteredDistricts() {
                return this.districts.filter(item => String(item.parent_location_id) === String(this.provinceId));
            },
            onPersonTypeChange() {
                this.errorMessage = '';
                if (this.isRuc) {
                    this.gender = '';
                    this.lastName = '';
                    if (this.documentNumber.length > 11) {
                        this.documentNumber = this.documentNumber.slice(0, 11);
                    }
                } else if (this.documentNumber.length > 8) {
                    this.documentNumber = this.documentNumber.slice(0, 8);
                }
            },
            onDepartmentChange() {
                this.provinceId = '';
                this.districtId = '';
            },
            onProvinceChange() {
                this.districtId = '';
            },
            resolveLocationHierarchy() {
                if (!this.districtId) return;
                const district = this.districts.find(item => String(item.id) === String(this.districtId));
                if (!district) return;
                this.provinceId = district.parent_location_id ? String(district.parent_location_id) : '';
                const province = this.provinces.find(item => String(item.id) === String(this.provinceId));
                this.departmentId = province?.parent_location_id ? String(province.parent_location_id) : '';
            },
            normalizeText(value) {
                return String(value || '')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .trim()
                    .toUpperCase();
            },
            normalizeGender(value) {
                const normalized = this.normalizeText(value);
                if (normalized === 'M' || normalized === 'MASCULINO') return 'MASCULINO';
                if (normalized === 'F' || normalized === 'FEMENINO') return 'FEMENINO';
                return normalized ? 'OTRO' : '';
            },
            formatApiDateToIso(value) {
                if (!value) return '';
                const raw = String(value).trim();
                if (/^\d{2}\/\d{2}\/\d{4}$/.test(raw)) {
                    const [day, month, year] = raw.split('/');
                    return `${year}-${month}-${day}`;
                }
                if (/^\d{4}-\d{2}-\d{2}/.test(raw)) {
                    return raw.slice(0, 10);
                }
                return '';
            },
            setLocationByNames(departmentName, provinceName, districtName) {
                const normalizedDepartment = this.normalizeText(departmentName);
                const normalizedProvince = this.normalizeText(provinceName);
                const normalizedDistrict = this.normalizeText(districtName);

                const department = this.departments.find(item => this.normalizeText(item.name) === normalizedDepartment);
                this.departmentId = department ? String(department.id) : '';

                const matchingProvinces = this.provinces.filter(item => String(item.parent_location_id) === String(this.departmentId));
                const province = matchingProvinces.find(item => this.normalizeText(item.name) === normalizedProvince);
                this.provinceId = province ? String(province.id) : '';

                const matchingDistricts = this.districts.filter(item => String(item.parent_location_id) === String(this.provinceId));
                const district = matchingDistricts.find(item => this.normalizeText(item.name) === normalizedDistrict);
                this.districtId = district ? String(district.id) : '';
            },
            async searchDocument() {
                const document = this.documentNumber.trim();
                const expectedLength = this.isRuc ? 11 : 8;

                if (document.length !== expectedLength) {
                    this.errorMessage = this.isRuc
                        ? 'Debe ingresar un RUC de 11 dígitos.'
                        : 'Debe ingresar un DNI de 8 dígitos.';
                    return;
                }

                this.loading = true;
                this.errorMessage = '';

                try {
                    const endpoint = this.isRuc ? `/api/ruc/${encodeURIComponent(document)}` : `/api/dni/${encodeURIComponent(document)}`;
                    const response = await fetch(endpoint, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await response.json();

                    if (!response.ok) {
                        this.errorMessage = data.error || 'No se encontraron datos para el documento.';
                        return;
                    }

                    if (this.isRuc) {
                        this.firstName = data.razon_social || '';
                        this.lastName = '';
                        this.birthDate = this.formatApiDateToIso(data.fecha_inscripcion);
                        this.address = data.direccion || this.address;
                        this.setLocationByNames(data.departamento, data.provincia, data.distrito);
                    } else {
                        this.firstName = data.nombres || '';
                        this.lastName = [data.apellido_paterno, data.apellido_materno].filter(Boolean).join(' ');
                        this.birthDate = this.formatApiDateToIso(data.fecha_nacimiento);
                        this.gender = this.normalizeGender(data.genero);
                    }
                } catch (error) {
                    this.errorMessage = 'No se pudo consultar el documento en este momento.';
                } finally {
                    this.loading = false;
                }
            }
        };
    }
</script>
