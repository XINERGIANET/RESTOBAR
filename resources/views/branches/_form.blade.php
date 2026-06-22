@php
    use Illuminate\Support\HtmlString;

    $RucIcon = new HtmlString('
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.6" />
            <path d="M7 9H11" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
            <path d="M7 13H12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
            <circle cx="16.5" cy="11" r="2" stroke="currentColor" stroke-width="1.6" />
            <path d="M14 15H19" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
        </svg>
    ');

    $BranchIcon = new HtmlString('
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M4 7C4 5.89543 4.89543 5 6 5H10C11.1046 5 12 5.89543 12 7V21" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
            <path d="M12 9H18C19.1046 9 20 9.89543 20 11V21" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
            <path d="M4 21H20" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
            <path d="M7 9H9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
            <path d="M15 13H17" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
        </svg>
    ');

    $AddressIcon = new HtmlString('
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 21C12 21 18 16 18 10.5C18 7.18629 15.3137 4.5 12 4.5C8.68629 4.5 6 7.18629 6 10.5C6 16 12 21 12 21Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
            <circle cx="12" cy="10.5" r="2.5" stroke="currentColor" stroke-width="1.6" />
        </svg>
    ');

    $LocationIcon = new HtmlString('
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 12C13.6569 12 15 10.6569 15 9C15 7.34315 13.6569 6 12 6C10.3431 6 9 7.34315 9 9C9 10.6569 10.3431 12 12 12Z" stroke="currentColor" stroke-width="1.6" />
            <path d="M19 9C19 14 12 20 12 20C12 20 5 14 5 9C5 5.68629 7.68629 3 11 3H13C16.3137 3 19 5.68629 19 9Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    ');

    $LogoIcon = new HtmlString('
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.6" />
            <path d="M7 14L10 11L14 15L17 12L21 16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
            <circle cx="9" cy="9" r="1.5" stroke="currentColor" stroke-width="1.6" />
        </svg>
    ');
@endphp

@php
    $selectedDepartmentId = old('department_id', $selectedDepartmentId ?? null);
    $selectedProvinceId = old('province_id', $selectedProvinceId ?? null);
    $selectedDistrictId = old('location_id', $selectedDistrictId ?? ($branch->location_id ?? null));
    $waiterPinEnabledLocal = old('waiter_pin_enabled', ($waiterPinEnabled ?? false) ? '1' : '0');
    $electronicBillingConfig = $branch?->electronicBillingConfig;
    $electronicBillingEnabled = old('electronic_billing_enabled', ($electronicBillingConfig->enabled ?? false) ? '1' : '0');
    $electronicBillingApiUrl = old('electronic_billing_api_url', $electronicBillingConfig->api_url ?? config('apisunat.url', 'https://back.apisunat.com'));
    $electronicBillingPersonaId = old('electronic_billing_persona_id', $electronicBillingConfig->persona_id ?? '');
    $electronicBillingPersonaToken = old('electronic_billing_persona_token', $electronicBillingConfig->persona_token ?? '');
    $electronicBillingSeriesBoleta = old('electronic_billing_series_boleta', $electronicBillingConfig->series_boleta ?? config('apisunat.series.boleta', 'B001'));
    $electronicBillingSeriesFactura = old('electronic_billing_series_factura', $electronicBillingConfig->series_factura ?? config('apisunat.series.factura', 'F001'));
    $allowZeroStockSalesLocal = old(
        'allow_zero_stock_sales',
        (($branch->allow_zero_stock_sales ?? true) ? '1' : '0')
    );
@endphp

<div
    class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
    data-departments='@json($departments ?? [], JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-provinces='@json($provinces ?? [], JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-districts='@json($districts ?? [], JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-department-id='@json(old('department_id', $selectedDepartmentId ?? null), JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-province-id='@json(old('province_id', $selectedProvinceId ?? null), JSON_HEX_APOS | JSON_HEX_QUOT)'
    data-district-id='@json(old('location_id', $selectedDistrictId ?? ($branch->location_id ?? null)), JSON_HEX_APOS | JSON_HEX_QUOT)'
    x-data="{
        departments: JSON.parse($el.dataset.departments || '[]'),
        provinces: JSON.parse($el.dataset.provinces || '[]'),
        districts: JSON.parse($el.dataset.districts || '[]'),
        departmentId: JSON.parse($el.dataset.departmentId || 'null') || '',
        provinceId: JSON.parse($el.dataset.provinceId || 'null') || '',
        districtId: JSON.parse($el.dataset.districtId || 'null') || '',
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
    <div class="sm:col-span-1">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">RUC</label>
        <div class="relative">
            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                {!! $RucIcon !!}
            </span>
            <input
                type="text"
                name="ruc"
                value="{{ old('ruc', $branch->ruc ?? '') }}"
                required
                placeholder="Ingrese RUC"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            />
        </div>
        @error('ruc')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-1 lg:col-span-2">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Razon social</label>
        <div class="relative">
            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                {!! $BranchIcon !!}
            </span>
            <input
                type="text"
                name="legal_name"
                value="{{ old('legal_name', $branch->legal_name ?? '') }}"
                required
                placeholder="Ingrese el nombre de la sucursal"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            />
        </div>
        @error('legal_name')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-1 lg:col-span-2">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Direccion</label>
        <div class="relative">
            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                {!! $AddressIcon !!}
            </span>
            <input
                type="text"
                name="address"
                value="{{ old('address', $branch->address ?? '') }}"
                placeholder="Ingrese la direccion"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            />
        </div>
        @error('address')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>
    <div class="sm:col-span-1 lg:col-span-1">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Logo (opcional)</label>
        <div class="relative">
            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                {!! $LogoIcon !!}
            </span>
            <input
                type="file"
                name="logo"
                accept="image/*"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            />
        </div>
        @error('logo')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-1 lg:col-span-3 mt-2 rounded-2xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-900/40">
        <div class="mb-4">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white">Facturación electrónica SaaS</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Configura aquí las credenciales de Apisunat para esta sucursal. Solo las boletas y facturas se enviarán al proveedor.
            </p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div class="sm:col-span-1 lg:col-span-3">
                <label class="flex items-start gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        name="electronic_billing_enabled"
                        value="1"
                        class="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        {{ (string) $electronicBillingEnabled === '1' ? 'checked' : '' }}
                    />
                    <span>
                        <span class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                            Activar Apisunat para esta sucursal
                        </span>
                        <span class="block text-xs text-gray-500 dark:text-gray-400">
                            Si está activo, las ventas con boleta y factura se enviarán a SUNAT mediante Apisunat con estas credenciales.
                        </span>
                    </span>
                </label>
            </div>

            <div class="sm:col-span-2 lg:col-span-3">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">URL API</label>
                <input
                    type="url"
                    name="electronic_billing_api_url"
                    value="{{ $electronicBillingApiUrl }}"
                    placeholder="https://back.apisunat.com"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                />
                @error('electronic_billing_api_url')
                    <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-1">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Persona ID</label>
                <input
                    type="text"
                    name="electronic_billing_persona_id"
                    value="{{ $electronicBillingPersonaId }}"
                    placeholder="ID de Apisunat"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                />
                @error('electronic_billing_persona_id')
                    <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-1 lg:col-span-2">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Token producción</label>
                <input
                    type="text"
                    name="electronic_billing_persona_token"
                    value="{{ $electronicBillingPersonaToken }}"
                    placeholder="Token de Apisunat"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                />
                @error('electronic_billing_persona_token')
                    <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-1">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Serie boleta</label>
                <input
                    type="text"
                    name="electronic_billing_series_boleta"
                    value="{{ $electronicBillingSeriesBoleta }}"
                    placeholder="B001"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm uppercase text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                />
                @error('electronic_billing_series_boleta')
                    <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-1">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Serie factura</label>
                <input
                    type="text"
                    name="electronic_billing_series_factura"
                    value="{{ $electronicBillingSeriesFactura }}"
                    placeholder="F001"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm uppercase text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                />
                @error('electronic_billing_series_factura')
                    <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    <div class="sm:col-span-1">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Departamento</label>
        <div class="relative">
            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                {!! $LocationIcon !!}
            </span>
            <select
                name="department_id"
                x-model="departmentId"
                @change="onDepartmentChange()"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            >
                <option value="">Seleccione departamento</option>
                <template x-for="department in departments" :key="department.id">
                    <option :value="department.id" :selected="department.id == departmentId" x-text="department.name"></option>
                </template>
            </select>
        </div>
    </div>

    <div class="sm:col-span-1">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Provincia</label>
        <div class="relative">
            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                {!! $LocationIcon !!}
            </span>
            <select
                name="province_id"
                x-model="provinceId"
                @change="onProvinceChange()"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            >
                <option value="">Seleccione provincia</option>
                <template x-for="province in filteredProvinces" :key="province.id">
                    <option :value="province.id" :selected="province.id == provinceId" x-text="province.name"></option>
                </template>
            </select>
        </div>
    </div>

    <div class="sm:col-span-1">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Distrito</label>
        <div class="relative">
            <span class="absolute top-1/2 left-0 -translate-y-1/2 border-r border-gray-200 px-3.5 py-3 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                {!! $LocationIcon !!}
            </span>
            <select
                name="location_id"
                x-model="districtId"
                required
                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-[62px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
            >
                <option value="">Seleccione distrito</option>
                <template x-for="district in filteredDistricts" :key="district.id">
                    <option :value="district.id" :selected="district.id == districtId" x-text="district.name"></option>
                </template>
            </select>
        </div>
        @error('location_id')
            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-1 lg:col-span-3 mt-4">
        <label class="flex items-start gap-2 cursor-pointer">
            <input
                type="checkbox"
                name="waiter_pin_enabled"
                value="1"
                class="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                {{ (string) $waiterPinEnabledLocal === '1' ? 'checked' : '' }}
            />
            <span>
                <span class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                    Requerir PIN de mozo en salones de pedidos
                </span>
                <span class="block text-xs text-gray-500 dark:text-gray-400">
                    Si está activo, al tomar pedidos en esta sucursal se pedirá el PIN del mozo. Si está desactivado, se usará solo el usuario con el que se inicia sesión.
                </span>
            </span>
        </label>
    </div>

    <div class="sm:col-span-1 lg:col-span-3">
        <label class="flex items-start gap-2 cursor-pointer">
            <input
                type="checkbox"
                name="allow_zero_stock_sales"
                value="1"
                class="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                {{ (string) $allowZeroStockSalesLocal === '1' ? 'checked' : '' }}
            />
            <span>
                <span class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                    Permitir vender con stock 0 (o insuficiente)
                </span>
                <span class="block text-xs text-gray-500 dark:text-gray-400">
                    Si está activo, se podrá agregar/vender productos aunque el stock en la sucursal sea 0.
                </span>
            </span>
        </label>
    </div>

</div>
