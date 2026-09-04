@extends('layouts.app')

@section('title', $title ?? 'Configuración de Sistema')

@section('content')
    <div x-data="{ open: false }">
        @php
            use Illuminate\Support\Facades\Route;

            $viewId = request('view_id');
            $operacionesCollection = collect($operaciones ?? []);
            $topOperations = $operacionesCollection->where('type', 'T');

            $resolveActionUrl = function ($action, $company = null, $operation = null) use ($viewId) {
                if (!$action) return '#';

                if (str_starts_with($action, '/') || str_starts_with($action, 'http')) {
                    $url = $action;
                } else {
                    $routeCandidates = [$action];
                    if (!str_starts_with($action, 'admin.')) {
                        $routeCandidates[] = 'admin.' . $action;
                    }
                    $routeCandidates = array_merge(
                        $routeCandidates,
                        array_map(fn ($name) => $name . '.index', $routeCandidates)
                    );

                    $routeName = null;
                    foreach ($routeCandidates as $candidate) {
                        if (Route::has($candidate)) {
                            $routeName = $candidate;
                            break;
                        }
                    }

                    if ($routeName) {
                        try {
                            $url = $company ? route($routeName, $company) : route($routeName);
                        } catch (\Exception $e) {
                            $url = '#';
                        }
                    } else {
                        $url = '#';
                    }
                }

                $targetViewId = $viewId;
                if ($operation && !empty($operation->view_id_action)) {
                    $targetViewId = $operation->view_id_action;
                }

                if ($targetViewId && $url !== '#') {
                    $separator = str_contains($url, '?') ? '&' : '?';
                    $url .= $separator . 'view_id=' . urlencode($targetViewId);
                }

                if ($viewId && $operation && !empty($operation->view_id_action) && $url !== '#') {
                    $separator = str_contains($url, '?') ? '&' : '?';
                    $url .= $separator . 'company_view_id=' . urlencode($viewId);
                }

                if ($operation && !empty($operation->icon) && str_contains($action, 'branches') && $url !== '#') {
                    $separator = str_contains($url, '?') ? '&' : '?';
                    $url .= $separator . 'icon=' . urlencode($operation->icon);
                }

                return $url;
            };

            $resolveTextColor = function ($operation) {
                return '#FFFFFF';
            };
        @endphp

        <x-common.page-breadcrumb pageTitle="Configuración de Sistema" />
        
        <x-common.component-card>
        
            @if($topOperations->count() > 0)
                <div class="flex flex-wrap items-center gap-3 mb-6 justify-end border-b border-gray-100 dark:border-gray-800 pb-4">
                    @foreach ($topOperations as $operation)
                        @php
                            $topTextColor = $resolveTextColor($operation);
                            $topColor = $operation->color ?: '#10B981';
                            $topStyle = "background-color: {$topColor}; color: {$topTextColor}; border-color: {$topColor};";
                            $topActionUrl = $resolveActionUrl($operation->action ?? '', null, $operation);
                        @endphp
                        
                        <x-ui.link-button size="md" variant="primary"
                            class="w-full sm:w-auto h-11 px-6 shadow-sm hover:opacity-90 transition-opacity rounded-lg font-semibold"
                            style="{{ $topStyle }}"
                            href="{{ $topActionUrl }}">
                            <i class="{{ $operation->icon }} text-lg me-2"></i>
                            <span>{{ $operation->name }}</span>
                        </x-ui.link-button>
                    @endforeach
                </div>
            @endif

            <form action="{{ route('branch-parameter.store') }}" method="POST" enctype="multipart/form-data" class="mt-2">
                @csrf
                <input type="hidden" name="branch_payment_methods_include" value="1">

                <div class="flex border-b border-gray-200 dark:border-gray-700 overflow-x-auto no-scrollbar mb-8 gap-6">
                    @foreach($categories as $category)
                        <button type="button" 
                                class="tab-btn pb-3 text-sm font-bold relative whitespace-nowrap transition-colors duration-200 
                                       {{ $loop->first ? 'text-blue-700 dark:text-blue-500' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400' }}"
                                data-target="tab-category-{{ $category->id }}"
                                aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                            
                            {{ $category->description }}
                            
                            <span class="tab-indicator absolute bottom-0 left-0 w-full h-[3px] bg-blue-700 dark:bg-blue-500 transition-transform duration-300 origin-left 
                                         {{ $loop->first ? 'scale-x-100' : 'scale-x-0' }}"></span>
                        </button>
                    @endforeach
                </div>

                <div class="tabs-content-container">
                    @foreach($categories as $category)
                        <div id="tab-category-{{ $category->id }}" 
                             class="tab-pane animate-fade-in {{ $loop->first ? 'block' : 'hidden' }}">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 items-start">
                                @foreach($category->parameters as $parameter)
                                
                                    <div class="bg-white dark:bg-gray-900 p-5 rounded-xl border border-gray-200 dark:border-gray-800 flex flex-col justify-start h-fit shadow-sm hover:shadow-md transition-shadow">
                                        
                                        <label class="block text-sm font-bold text-gray-900 dark:text-gray-100 mb-3 uppercase">
                                            {{ $parameter->description }}
                                        </label>
                                        @php
                                            $paramKey = $parameter->branch_parameter_id ?? 'p' . $parameter->id;
                                            $desc = trim($parameter->description ?? '');
                                            $descLower = mb_strtolower($desc, 'UTF-8');
                                            $isRequerirPinMozo = strcasecmp($desc, 'Requerir PIN a mozo') === 0;
                                            $isIgvDefecto = strcasecmp($desc, 'igv_defecto') === 0;
                                            $isReceiptPrinterParam = str_contains($descLower, 'impresora') &&
                                                (str_contains($descLower, 'comprobante') || str_contains($descLower, 'precuenta'));
                                            $isQzCertificateParam = str_contains($descLower, 'certificado digital') && str_contains($descLower, 'qz');
                                            $isQzPrivateKeyParam = str_contains($descLower, 'clave privada') && str_contains($descLower, 'qz');
                                            // Por descripción (evita confundir con ids de contraseñas si "METODOS DE PAGO" tiene mal el id)
                                            $isMetodosPagoParam = str_contains($descLower, 'metodo') && str_contains($descLower, 'pago');
                                            // Solo por descripción para evitar confundir parámetros mal rotulados en producción.
                                            $showMetodosPagoUi = $isMetodosPagoParam;
                                            // Medio de pago por defecto (distinto del checklist de habilitados: requiere "pago" + "defecto")
                                            $isDefaultPaymentMethodParam = str_contains($descLower, 'pago') && str_contains($descLower, 'defecto');
                                            $isAllowZeroStockSalesParam =
                                                str_contains($descLower, 'permitir') &&
                                                str_contains($descLower, 'stock') &&
                                                (str_contains($descLower, '0') || str_contains($descLower, 'cero'));
                                            $isCloseTableParam =
                                                str_contains($descLower, 'permitir') &&
                                                str_contains($descLower, 'cerrar') &&
                                                str_contains($descLower, 'mesa');
                                            // Detectar "TIPO VENTA POR DEFECTO" por descripción (no por ID hardcodeado)
                                            $isTipoVentaParam =
                                                (str_contains($descLower, 'tipo') && str_contains($descLower, 'venta')) ||
                                                str_contains($descLower, 'tipo_venta') ||
                                                str_contains($descLower, 'comprobante') ||
                                                str_contains($descLower, 'tipo de comprobante');
                                            // Detectar parámetros de contraseña por descripción
                                            $isPasswordParam =
                                                str_contains($descLower, 'contrase') ||
                                                str_contains($descLower, 'password') ||
                                                str_contains($descLower, 'clave') ||
                                                (str_contains($descLower, 'pin') && !$isRequerirPinMozo);
                                            // Detectar parámetros de tipo IGV por descripción (además del id=2)
                                            $isIgvParam = $isIgvDefecto ||
                                                (str_contains($descLower, 'igv') && str_contains($descLower, 'defecto'));
                                        @endphp
                                        @if($isQzCertificateParam || $isQzPrivateKeyParam)
                                            @php
                                                $qzConfigured = !empty(trim((string) ($parameter->branch_value ?? '')));
                                                $qzInputName = $isQzCertificateParam ? 'qz_certificate_file' : 'qz_private_key_file';
                                                $qzAccept = $isQzCertificateParam ? '.txt,.pem,.crt,.cer' : '.txt,.pem,.key';
                                            @endphp
                                            <div class="space-y-3">
                                                <div class="flex items-center gap-2 text-xs font-semibold {{ $qzConfigured ? 'text-emerald-600' : 'text-amber-600' }}">
                                                    <i class="{{ $qzConfigured ? 'ri-checkbox-circle-line' : 'ri-alert-line' }}"></i>
                                                    <span>{{ $qzConfigured ? 'Archivo configurado para esta sucursal' : 'Archivo pendiente de cargar' }}</span>
                                                </div>
                                                <input type="file" name="{{ $qzInputName }}" accept="{{ $qzAccept }}"
                                                    class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-[#124731] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                                <p class="text-xs text-gray-500">Por seguridad el contenido no se muestra. Para reemplazarlo, cargue ambos archivos QZ y guarde.</p>
                                            </div>
                                        @elseif($isReceiptPrinterParam)
                                            <select name="parameters[{{ $paramKey }}]"
                                                    class="w-full border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors shadow-sm">
                                                <option value="">Seleccionar impresora...</option>
                                                @foreach($printers ?? [] as $printer)
                                                    <option value="{{ $printer->id }}" {{ (string) ($parameter->branch_value ?? '') === (string) $printer->id ? 'selected' : '' }}>
                                                        {{ $printer->name }}{{ $printer->ip ? ' — '.$printer->ip : ' — USB/QZ' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @elseif($isRequerirPinMozo)
                                            {{-- REQUERIR PIN A MOZO: 0 o 1 --}}
                                            <select name="parameters[{{ $paramKey }}]" 
                                                    class="w-full border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors shadow-sm">
                                                <option value="0" {{ ($parameter->branch_value ?? '') == '0' ? 'selected' : '' }}>No (0)</option>
                                                <option value="1" {{ ($parameter->branch_value ?? '') == '1' ? 'selected' : '' }}>Sí (1)</option>
                                            </select>
                                        @elseif($isIgvParam)
                                            {{-- IGV POR DEFECTO: selector de tasas de impuesto --}}
                                            <select name="parameters[{{ $paramKey }}]" 
                                                    class="w-full border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors shadow-sm">
                                                <option value="">Seleccionar...</option>
                                                @foreach($igv ?? [] as $igvItem)
                                                    <option value="{{ $igvItem->id }}" {{ $parameter->branch_value == $igvItem->id ? 'selected' : '' }}>
                                                        {{ trim(str_ireplace('de venta', '', $igvItem->description)) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @elseif($showMetodosPagoUi)
                                            {{-- Métodos de pago por sucursal (pivote branch_payment_methods) --}}
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                                Marca los métodos que podrán usarse en ventas y cobros de esta sucursal.
                                                Si marcas todos o ninguno y guardas, se considera "sin restricción" (aplican todos los métodos activos del sistema, incluidos los nuevos).
                                            </p>
                                            <div class="max-h-48 overflow-y-auto space-y-2 border border-gray-200 dark:border-gray-700 rounded-lg p-3 bg-gray-50 dark:bg-gray-800/50">
                                                @foreach($paymentMethods ?? [] as $method)
                                                    <label class="flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200 cursor-pointer">
                                                        <input type="checkbox"
                                                               name="branch_payment_method_ids[]"
                                                               value="{{ $method->id }}"
                                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                               {{ in_array((int) $method->id, $branchPaymentMethodIds ?? [], true) ? 'checked' : '' }}>
                                                        <span>{{ trim(str_ireplace('de venta', '', $method->description)) }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @elseif($isDefaultPaymentMethodParam)
                                            {{-- Medio de pago por defecto: preseleccionado al cobrar, se puede cambiar --}}
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                                Se mostrará seleccionado automáticamente al cobrar. El mozo/cajero podrá cambiarlo igual.
                                            </p>
                                            <select name="parameters[{{ $paramKey }}]"
                                                    class="w-full border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors shadow-sm">
                                                <option value="">Seleccionar...</option>
                                                @foreach($paymentMethods ?? [] as $method)
                                                    @continue(!in_array((int) $method->id, $branchPaymentMethodIds ?? [], true))
                                                    <option value="{{ $method->id }}" {{ (string) ($parameter->branch_value ?? '') === (string) $method->id ? 'selected' : '' }}>
                                                        {{ trim(str_ireplace('de venta', '', $method->description)) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @elseif($isAllowZeroStockSalesParam)
                                            {{-- Permitir vender con stock 0 (o insuficiente): 0/1 --}}
                                            <select name="parameters[{{ $paramKey }}]"
                                                    class="w-full border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors shadow-sm">
                                                <option value="0" {{ (string) ($parameter->branch_value ?? '') === '0' ? 'selected' : '' }}>No (0)</option>
                                                <option value="1" {{ (string) ($parameter->branch_value ?? '') === '1' ? 'selected' : '' }}>Sí (1)</option>
                                            </select>
                                        @elseif($isCloseTableParam)
                                            {{-- PERMITIR CERRAR MESAS: Selección de Sí/No + Checkboxes de tipos de usuario --}}
                                            @php
                                                $rawVal = trim((string) ($parameter->branch_value ?? ''));
                                                $closeTableEnabled = 'No';
                                                $closeTableProfiles = [];

                                                if (!empty($rawVal)) {
                                                    $normalizedRaw = mb_strtolower($rawVal, 'UTF-8');
                                                    if (in_array($normalizedRaw, ['no', '0', 'false'], true)) {
                                                        $closeTableEnabled = 'No';
                                                        $closeTableProfiles = [];
                                                    } elseif (in_array($normalizedRaw, ['sí', 'si', '1', 'true'], true)) {
                                                        $closeTableEnabled = 'Si';
                                                        $closeTableProfiles = collect($profiles ?? [])->pluck('id')->map(fn($id) => (int)$id)->all();
                                                    } else {
                                                        $decoded = json_decode($rawVal, true);
                                                        if (is_array($decoded)) {
                                                            $statusVal = mb_strtolower((string)($decoded['status'] ?? ''), 'UTF-8');
                                                            $closeTableEnabled = in_array($statusVal, ['no', '0', 'false'], true) ? 'No' : 'Si';
                                                            $closeTableProfiles = array_values(array_map('intval', (array) ($decoded['profiles'] ?? [])));
                                                        } else {
                                                            $closeTableEnabled = 'Si';
                                                            $closeTableProfiles = collect($profiles ?? [])->pluck('id')->map(fn($id) => (int)$id)->all();
                                                        }
                                                    }
                                                }
                                            @endphp

                                            <div x-data="{ 
                                                enabled: '{{ $closeTableEnabled }}', 
                                                selectedProfiles: {{ json_encode($closeTableProfiles) }} 
                                            }" class="w-full space-y-3">
                                                
                                                <select name="parameters[{{ $paramKey }}]" 
                                                        x-model="enabled"
                                                        class="w-full border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors shadow-sm">
                                                    <option value="No">No (Deshabilitado)</option>
                                                    <option value="Si">Sí (Permitir por tipo de usuario)</option>
                                                </select>

                                                <div x-show="enabled === 'Si'" 
                                                     x-transition:enter="transition ease-out duration-200"
                                                     x-transition:enter-start="opacity-0 -translate-y-2"
                                                     x-transition:enter-end="opacity-100 translate-y-0"
                                                     class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 bg-gray-50 dark:bg-gray-800/50 space-y-2">
                                                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-400">
                                                        Marcar tipos de usuario autorizados:
                                                    </p>
                                                    <div class="max-h-48 overflow-y-auto space-y-1.5">
                                                        @foreach($profiles ?? [] as $prof)
                                                            @php
                                                                $isSystemAdmin = ((int)$prof->id === 1 || str_contains(mb_strtolower($prof->name ?? '', 'UTF-8'), 'sistema'));
                                                            @endphp
                                                            <label class="flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700/50 p-1.5 rounded transition">
                                                                @if($isSystemAdmin)
                                                                    <input type="hidden" name="close_table_profiles[]" value="{{ $prof->id }}">
                                                                    <input type="checkbox"
                                                                           checked
                                                                           disabled
                                                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 opacity-70 cursor-not-allowed">
                                                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $prof->name }}</span>
                                                                    <span class="text-[10px] font-bold text-blue-700 bg-blue-100 dark:bg-blue-900/60 dark:text-blue-200 px-2 py-0.5 rounded-full uppercase tracking-wider">Por defecto</span>
                                                                @else
                                                                    <input type="checkbox"
                                                                           name="close_table_profiles[]"
                                                                           value="{{ $prof->id }}"
                                                                           x-model="selectedProfiles"
                                                                           :value="{{ $prof->id }}"
                                                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                                    <span class="font-medium">{{ $prof->name }}</span>
                                                                @endif
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        @elseif($isTipoVentaParam)
                                            {{-- TIPO DE VENTA POR DEFECTO: Ticket / Boleta / Factura (detectado por descripción) --}}
                                            <select name="parameters[{{ $paramKey }}]" 
                                                    class="w-full border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors shadow-sm">
                                                <option value="">Seleccionar...</option>
                                                @foreach($tiposVenta ?? [] as $tipo)
                                                    <option value="{{ $tipo->id }}" {{ $parameter->branch_value == $tipo->id ? 'selected' : '' }}>
                                                        {{ $tipo->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @elseif($isPasswordParam)
                                            {{-- PARÁMETROS DE CONTRASEÑA (detectado por descripción) --}}
                                            @php
                                                $tienePass = !empty($parameter->branch_value) && $parameter->branch_value !== 'No';
                                            @endphp
                                            <div x-data="{ pedirPass: '{{ $tienePass ? 'Si' : 'No' }}', showPass: false }" class="w-full">
                                                
                                                <select x-model="pedirPass" 
                                                        class="w-full border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors shadow-sm">
                                                    <option value="No">No (Sin contraseña)</option>
                                                    <option value="Si">Sí (Requiere contraseña)</option>
                                                </select>

                                                <input type="hidden" 
                                                       name="parameters[{{ $paramKey }}]" 
                                                       value="No" 
                                                       x-bind:disabled="pedirPass === 'Si'">

                                                <div x-show="pedirPass === 'Si'" 
                                                     x-transition:enter="transition ease-out duration-200"
                                                     x-transition:enter-start="opacity-0 -translate-y-2"
                                                     x-transition:enter-end="opacity-100 translate-y-0"
                                                     class="mt-3 relative">
                                                    <input :type="showPass ? 'text' : 'password'" 
                                                           name="parameters[{{ $paramKey }}]" 
                                                           value="{{ $tienePass ? e($parameter->branch_value) : '' }}"
                                                           x-bind:disabled="pedirPass === 'No'"
                                                           class="w-full border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-900 px-3 py-2.5 pr-10 text-sm font-medium text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors shadow-inner"
                                                           placeholder="Escribe la contraseña...">
                                                           
                                                    <button type="button" 
                                                            @click="showPass = !showPass" 
                                                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-blue-600 transition-colors focus:outline-none">
                                                        <i :class="showPass ? 'ri-eye-off-line' : 'ri-eye-line'" class="text-lg text-gray-800"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        @else
                                            {{-- POR DEFECTO: SELECT DE SÍ/NO --}}
                                            <select name="parameters[{{ $paramKey }}]" 
                                                    class="w-full border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors shadow-sm">
                                                <option value="">Seleccionar...</option>
                                                <option value="Si" {{ $parameter->branch_value == 'Si' ? 'selected' : '' }}>Sí</option>
                                                <option value="No" {{ $parameter->branch_value == 'No' ? 'selected' : '' }}>No</option>
                                            </select>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                        </div>
                    @endforeach
                </div>

                <div class="mt-12 pt-6 border-t border-gray-100 dark:border-gray-800 flex justify-end gap-3">
                    <x-ui.button type="submit" size="md" variant="primary">
                        <i class="ri-save-line"></i>
                        <span>Guardar</span>
                    </x-ui.button>
                </div>
            </form>

        </x-common.component-card>
    </div>

    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .animate-fade-in { animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>

    <script>
        function initBranchParameterTabs() {
            const container = document.querySelector('.tabs-content-container');
            if (!container || container.dataset.tabsInitialized === '1') return;
            container.dataset.tabsInitialized = '1';

            const tabBtns = document.querySelectorAll('.tab-btn');
            const tabPanes = document.querySelectorAll('.tab-pane');

            tabBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    // 1. Resetear todos los botones
                    tabBtns.forEach(b => {
                        b.classList.remove('text-blue-700', 'dark:text-blue-500');
                        b.classList.add('text-gray-500', 'hover:text-gray-800', 'dark:text-gray-400');
                        b.setAttribute('aria-selected', 'false');
                        const indicator = b.querySelector('.tab-indicator');
                        if(indicator) {
                            indicator.classList.replace('scale-x-100', 'scale-x-0');
                        }
                    });

                    // 2. Ocultar todos los paneles
                    tabPanes.forEach(p => {
                        p.classList.remove('block');
                        p.classList.add('hidden');
                    });

                    // 3. Activar el botón seleccionado
                    btn.classList.remove('text-gray-500', 'hover:text-gray-800', 'dark:text-gray-400');
                    btn.classList.add('text-blue-700', 'dark:text-blue-500');
                    btn.setAttribute('aria-selected', 'true');
                    const activeIndicator = btn.querySelector('.tab-indicator');
                    if(activeIndicator) {
                        activeIndicator.classList.replace('scale-x-0', 'scale-x-100');
                    }

                    // 4. Mostrar el panel vinculado
                    const targetId = btn.getAttribute('data-target');
                    const targetPane = document.getElementById(targetId);
                    if(targetPane) {
                        targetPane.classList.remove('hidden');
                        targetPane.classList.add('block');
                    }
                });
            });
        }

        document.addEventListener('DOMContentLoaded', initBranchParameterTabs);
        document.addEventListener('turbo:load', initBranchParameterTabs);
    </script>
@endsection
