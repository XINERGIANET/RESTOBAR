@extends('layouts.app')

@section('content')
    <div x-data="{ openRow: null }">
        @php
            use Illuminate\Support\Facades\Route;

            $viewId = request('view_id');
            $operacionesCollection = collect($operaciones ?? []);
            $topOperations = $operacionesCollection->where('type', 'T');
            $rowOperations = $operacionesCollection->where('type', 'R');

            // Función para resolver la URL de la acción dinámicamente
            $resolveActionUrl = function ($action, $purchase = null, $operation = null) use ($viewId) {
                if (!$action) {
                    return '#';
                }

                // CORRECCIÓN CLAVE: Transformamos el plural/error de la BD al singular que usa Laravel
                $action = str_replace(['purcharses.', 'purchases.'], 'purchase.', $action);

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

                    // SALVAVIDAS
                    if (!$routeName) {
                        if (str_contains($action, 'create')) $routeName = 'purchase.create';
                        elseif (str_contains($action, 'edit')) $routeName = 'purchase.edit';
                        elseif (str_contains($action, 'destroy')) $routeName = 'purchase.destroy';
                    }

                    if ($routeName) {
                        try {
                            $url = $purchase ? route($routeName, $purchase) : route($routeName);
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

                return $url;
            };

            // Función para resolver el color del texto
            $resolveTextColor = function ($operation) {
                $action = str_replace(['purcharses.', 'purchases.'], 'purchase.', $operation->action ?? '');
                // Si el botón es el de crear (usualmente verde claro), ponemos texto oscuro
                if (str_contains($action, 'create')) {
                    return '#111827';
                }
                return '#FFFFFF';
            };
        @endphp

        <x-common.page-breadcrumb pageTitle="Compras" />

        <x-common.component-card title="Listado de compras" desc="Gestiona las compras registradas.">
            
            {{-- Filtros y búsqueda --}}
            <div class="flex flex-col gap-4">
                <form method="GET" class="w-full flex flex-col gap-4">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    
                    <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-4">
                        <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center flex-wrap">
                            <x-ui.per-page-selector :per-page="$perPage ?? 10" :submit-form="false" />

                            <div class="relative flex-1 min-w-[200px]">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                                    <i class="ri-search-line"></i>
                                </span>
                                <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Buscar por proveedor, serie, documento..."
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-12 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                            </div>
                            <div class="flex gap-2">
                                <x-ui.button size="md" variant="primary" type="submit"
                                    class="h-11 px-6 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95"
                                    style="background-color: #C43B25; border-color: #C43B25;">
                                    <i class="ri-search-line text-gray-100"></i>
                                    <span class="font-medium text-gray-100 hidden sm:inline">Buscar</span>
                                </x-ui.button>

                                <x-ui.link-button size="md" variant="outline"
                                    href="{{ route('purchase.index', $viewId ? ['view_id' => $viewId] : []) }}"
                                    class="h-11 px-6 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                                    <i class="ri-refresh-line"></i>
                                    <span class="font-medium hidden sm:inline">Limpiar</span>
                                </x-ui.link-button>
                            </div>
                        </div>

                        {{-- 1. BOTONES SUPERIORES DINÁMICOS (Tipo 'T') --}}
                        <div class="flex items-center gap-2 flex-shrink-0">
                            @foreach ($topOperations as $operation)
                                @php
                                    $topTextColor = $resolveTextColor($operation);
                                    $topColor = $operation->color ?: '#12f00e';
                                    $topStyle = "background-color: {$topColor}; color: {$topTextColor};";
                                    $topActionUrl = $resolveActionUrl($operation->action ?? '', null, $operation);
                                @endphp
                                
                                <a href="{{ $topActionUrl }}" class="h-11 inline-flex items-center text-sm justify-center gap-2 px-4 py-2 rounded-lg font-medium shadow-sm hover:shadow-md transition-all duration-200" style="{{ $topStyle }}">
                                    <i class="{{ $operation->icon ?? 'ri-add-line' }}"></i>
                                    <span>{{ $operation->name }}</span>
                                </a>
                            @endforeach

                            {{-- Fallback si no hay permisos configurados pero es admin --}}
                            @if ($topOperations->isEmpty())
                                <a href="{{ route('purchase.create', ['view_id' => $viewId]) }}" class="h-11 inline-flex items-center gap-2 px-4 py-2 rounded-lg font-medium shadow-sm hover:shadow-md transition-all duration-200" style="background-color: #FF4622; color: #FFFFFF;">
                                    <i class="ri-add-line"></i>
                                    <span>Nueva Compra</span>
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap items-end gap-3 w-full">
                        <div class="w-[150px] shrink-0 [&_label]:mb-1 [&_label]:text-xs [&_label]:font-medium [&_label]:text-gray-600 dark:[&_label]:text-gray-400">
                            <x-form.date-picker name="date_from" label="Desde" :defaultDate="$dateFrom ?? null" dateFormat="Y-m-d" class="w-full" />
                        </div>
                        <div class="w-[150px] shrink-0 [&_label]:mb-1 [&_label]:text-xs [&_label]:font-medium [&_label]:text-gray-600 dark:[&_label]:text-gray-400">
                            <x-form.date-picker name="date_to" label="Hasta" :defaultDate="$dateTo ?? null" dateFormat="Y-m-d" class="w-full" />
                        </div>
                        <div class="w-[170px] shrink-0">
                            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">Método de pago</label>
                            <select name="payment_method_id"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-[#FF4622] focus:outline-hidden focus:ring-2 focus:ring-[#FF4622]/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90 dark:focus:border-[#FF4622]">
                                <option value="">Todos</option>
                                @foreach ($paymentMethods ?? [] as $pm)
                                    <option value="{{ $pm->id }}" @selected(($paymentMethodId ?? '') == $pm->id)>
                                        {{ $pm->description ?? $pm->id }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-[170px] shrink-0">
                            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">Tipo de documento</label>
                            <select name="document_type_id"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-[#FF4622] focus:outline-hidden focus:ring-2 focus:ring-[#FF4622]/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90 dark:focus:border-[#FF4622]">
                                <option value="">Todos</option>
                                @foreach ($documentTypes ?? [] as $dt)
                                    <option value="{{ $dt->id }}" @selected(($documentTypeId ?? '') == $dt->id)>
                                        {{ $dt->name ?? $dt->id }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-[130px] shrink-0">
                            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">Caja</label>
                            <select name="cash_register_id"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-[#FF4622] focus:outline-hidden focus:ring-2 focus:ring-[#FF4622]/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white/90 dark:focus:border-[#FF4622]">
                                <option value="">Todas</option>
                                @foreach ($cashRegisters ?? [] as $cr)
                                    <option value="{{ $cr->id }}" @selected(($cashRegisterId ?? '') == $cr->id)>
                                        {{ $cr->number ?? $cr->id }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Tabla Principal --}}
            <div class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full min-w-[1100px]">
                    <thead>
                        <tr class="text-white" style="background-color: #FF4622; color: #FFFFFF;">
                            <th class="px-5 py-3 text-center sm:px-6 first:rounded-tl-xl sticky-left-header">
                                <p class="font-semibold text-white text-theme-xs uppercase">#</p>
                            </th>
                            <th class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Comprobante</p>
                            </th>
                            <th class="px-5 py-3 text-left sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Subtotal</p>
                            </th>
                            <th class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">IGV</p>
                            </th>
                            <th class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Total</p>
                            </th>
                            <th class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Persona</p>
                            </th>
                            <th class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Tipo de pago</p>
                            </th>
                            <th class="px-5 py-3 text-center sm:px-6">
                                <p class="font-semibold text-white text-theme-xs uppercase">Fecha</p>
                            </th>
                            <th class="px-5 py-3 text-center sm:px-6 last:rounded-tr-xl">
                                <p class="font-semibold text-white text-theme-xs uppercase">Acciones</p>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($purchases as $purchase)
                            @php
                                $persona = json_decode($purchase->json_persona ?? '{}');
                                $nombreProveedor = $persona->legal_name ?? $persona->name ?? 'Proveedor Desconocido';
                            @endphp

                            <tr class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                                <td class="px-4 text-center justify-center py-4 sm:px-6 sticky-left">
                                    <div class="flex items-center justify-center gap-2">
                                        <button type="button"
                                            class="h-6 w-6 flex items-center justify-center rounded-full bg-[#FF4622] text-white hover:bg-[#C43B25] transition"
                                            @click="openRow === {{ $purchase->id }} ? openRow = null : openRow = {{ $purchase->id }}">
                                            <i class="ri-add-line" x-show="openRow !== {{ $purchase->id }}"></i>
                                            <i class="ri-subtract-line" x-show="openRow === {{ $purchase->id }}" x-cloak></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <div>
                                        <p class="font-bold text-gray-800 text-theme-sm dark:text-white/90">{{ $purchase->serie }}-{{ $purchase->anio }}</p>
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 uppercase font-medium">FACTURA</p>
                                    </div>
                                </td>
                                <td class="px-5 py-4 sm:px-6 text-gray-600 dark:text-gray-400">S/ {{ number_format($purchase->subtotal, 2) }}</td>
                                <td class="px-5 text-center py-4 sm:px-6 text-gray-600 dark:text-gray-400">S/ {{ number_format($purchase->igv, 2) }}</td>
                                <td class="px-5 text-center py-4 sm:px-6">
                                    <p class="font-bold text-[#FF4622] dark:text-[#FF4622]">S/ {{ number_format($purchase->total, 2) }}</p>
                                </td>
                                <td class="px-5 py-4 text-center justify-center sm:px-6">
                                    <p class="text-gray-800 text-center text-theme-sm dark:text-white/90 truncate max-w-[150px]" title="{{ $nombreProveedor }}">{{ $nombreProveedor }}</p>
                                </td>
                                <td class="px-5 py-4 text-center sm:px-6 text-gray-600 dark:text-gray-400"><span class="font-bold text-[#FF4622] dark:text-[#FF4622]">{{ $purchase->tipo_pago}}</span></td>
                                <td class="px-5 py-4 text-center sm:px-6 text-gray-600 dark:text-gray-400">{{ $purchase->created_at->format('d/m/Y H:i') }}</td>
                                
                                {{-- 2. BOTONES DE ACCIÓN DINÁMICOS DE LA FILA (Tipo 'R') --}}
                                <td class="px-5 py-4 text-center sm:px-6">
                                    <div class="flex items-center justify-center gap-2">
                                        @if ($rowOperations->isNotEmpty())
                                            @foreach ($rowOperations as $operation)
                                                @php
                                                    $rawAction = str_replace(['purcharses.', 'purchases.'], 'purchase.', $operation->action ?? '');
                                                    $isDelete = str_contains($rawAction, 'destroy');
                                                    $actionUrl = $resolveActionUrl($rawAction, $purchase, $operation);
                                                    $textColor = $resolveTextColor($operation);
                                                    $buttonColor = $operation->color ?: '#FF4622';
                                                    $buttonStyle = "background-color: {$buttonColor}; color: {$textColor};";
                                                    $variant = $isDelete ? 'eliminate' : (str_contains($rawAction, 'edit') ? 'edit' : 'primary');
                                                @endphp
                                                
                                                @if ($isDelete)
                                                    <form method="POST" action="{{ $actionUrl }}" class="relative group js-swal-delete" data-swal-title="¿Eliminar compra?" data-swal-text="Se eliminará la compra #{{ $purchase->id }}. Esta acción no se puede deshacer." data-swal-confirm="Sí, eliminar" data-swal-cancel="Cancelar" data-swal-confirm-color="#ef4444" data-swal-cancel-color="#6b7280">
                                                        @csrf
                                                        @method('DELETE')
                                                        @if ($viewId)
                                                            <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                        @endif
                                                        <x-ui.button size="icon" variant="{{ $variant }}" type="submit" className="rounded-xl ring-0" style="{{ $buttonStyle }}" aria-label="{{ $operation->name }}">
                                                            <i class="{{ $operation->icon ?? 'ri-delete-bin-line' }}"></i>
                                                        </x-ui.button>
                                                        <span class="pointer-events-none absolute top-full right-0 sm:left-1/2 sm:-translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">{{ $operation->name }}</span>
                                                    </form>
                                                @else
                                                    <div class="relative group">
                                                        <a href="{{ $actionUrl }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl transition-all duration-200" style="{{ $buttonStyle }}" aria-label="{{ $operation->name }}">
                                                            <i class="{{ $operation->icon ?? 'ri-pencil-line' }}"></i>
                                                        </a>
                                                        <span class="pointer-events-none absolute top-full right-0 sm:left-1/2 sm:-translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50" style="transition-delay: 0.5s;">{{ $operation->name }}</span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        @endif
                                    </div>
                                </td>
                            </tr>

                            {{-- 3. Acordeón: Sub-Tabla de Detalle de la Compra (Productos) --}}
                            <tr x-show="openRow === {{ $purchase->id }}" x-cloak x-transition 
                                class="bg-slate-50 dark:bg-slate-800/40 border-b border-gray-200 dark:border-gray-800">
                                <td colspan="9" class="px-6 py-5">
                                    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 overflow-hidden shadow-sm">
                                        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80 flex items-center gap-2">
                                            <i class="ri-shopping-bag-3-line text-[#FF4622]"></i>
                                            <h4 class="text-sm font-bold text-gray-700 dark:text-gray-200">Productos de la compra #{{ $purchase->id }}</h4>
                                        </div>
                                        
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-sm text-left">
                                                <thead class="text-xs text-gray-500 uppercase bg-white dark:bg-gray-900 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800">
                                                    <tr>
                                                        <th class="px-4 py-3 font-semibold">Código</th>
                                                        <th class="px-4 py-3 font-semibold">Descripción</th>
                                                        <th class="px-4 py-3 font-semibold text-center">Cantidad</th>
                                                    
                                                        <th class="px-4 py-3 font-semibold text-right">Precio Unit.</th>
                                                        <th class="px-4 py-3 font-semibold text-right">Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                                    @if($purchase->details && $purchase->details->count() > 0)
                                                        @foreach($purchase->details as $detail)
                                                            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/5 transition-colors">
                                                                <td class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">
                                                                    {{ $detail->codigo ?? '-' }}
                                                                </td>
                                                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                                                    {{ $detail->descripcion }}
                                                                    @if($detail->comentario)
                                                                        <span class="block mt-0.5 text-xs text-gray-400">
                                                                            <i class="ri-message-2-line mr-1"></i>{{ $detail->comentario }}
                                                                        </span>
                                                                    @endif
                                                                </td>
                                                                <td class="px-4 py-3 text-center font-medium text-gray-700 dark:text-gray-300">
                                                                    {{ number_format($detail->cantidad, 2) }}
                                                                    @php
                                                                        $unidad = json_decode($detail->json_unidad ?? '{}');
                                                                        $nombreUnidad = $unidad->abbreviation ?? $unidad->description ?? $unidad->name ?? '';
                                                                    @endphp
                                                                    <span class="text-xs text-gray-400 font-normal ml-1">{{ $nombreUnidad }}</span>
                                                                </td>
                                                                <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">
                                                                    S/ {{ number_format($detail->monto, 2) }}
                                                                </td>
                                                                <td class="px-4 py-3 text-right font-bold text-gray-700 dark:text-gray-300">
                                                                    S/ {{ number_format($detail->cantidad * $detail->monto, 2) }}
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                        
                                                        <tr class="bg-gray-50 dark:bg-gray-800/50">
                                                            <td colspan="3"></td>
                                                            <td class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">
                                                                Total Documento:
                                                            </td>
                                                            <td class="px-4 py-3 text-right font-bold text-[#FF4622] dark:text-[#FF4622]">
                                                                S/ {{ number_format($purchase->total, 2) }}
                                                            </td>
                                                        </tr>
                                                    @else
                                                        <tr>
                                                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                                                <i class="ri-box-3-line text-2xl mb-2 block text-gray-400"></i>
                                                                No hay productos registrados en esta compra.
                                                            </td>
                                                        </tr>
                                                    @endif
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-12">
                                    <div class="flex flex-col items-center gap-3 text-center text-sm text-gray-500">
                                        <i class="ri-inbox-line text-3xl"></i>
                                        <p>No hay compras registradas</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-500">
                    Mostrando <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $purchases->firstItem() ?? 0 }}</span> - <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $purchases->lastItem() ?? 0 }}</span> de <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $purchases->total() }}</span>
                </div>
                <div>
                    {{ $purchases->links() }}
                </div>
            </div>
        </x-common.component-card>
    </div>

    @push('scripts')
    <script>
    (function() {
        function showFlashToast() {
            const msg = sessionStorage.getItem('flash_success_message');
            if (!msg) return;
            sessionStorage.removeItem('flash_success_message');
            if (window.Swal) {
                Swal.fire({
                    toast: true,
                    position: 'bottom-end',
                    icon: 'success',
                    title: msg,
                    showConfirmButton: false,
                    timer: 3500,
                    timerProgressBar: true
                });
            }
        }
        showFlashToast();
        document.addEventListener('turbo:load', showFlashToast);
    })();
    </script>
    @endpush
@endsection
