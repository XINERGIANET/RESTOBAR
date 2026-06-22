@extends('layouts.app')

@section('content')
    <div x-data="{}">
        @php
            use Illuminate\Support\Facades\Route;

            $viewId = request('view_id');
            $operacionesCollection = collect($operaciones ?? []);
            $topOperations = $operacionesCollection->where('type', 'T');
            $rowOperations = $operacionesCollection->where('type', 'R');

            $resolveActionUrl = function ($action, $warehouseMovement = null, $operation = null) use ($viewId) {
                if (!$action) {
                    return '#';
                }

                // DELETE debe ir a la ruta destroy con el ID del movimiento
                if (str_contains($action, 'destroy') && $warehouseMovement) {
                    $url = route('warehouse_movements.destroy', ['warehouseMovement' => $warehouseMovement->id]);
                    if ($viewId) {
                        $url .= (str_contains($url, '?') ? '&' : '?') . 'view_id=' . urlencode($viewId);
                    }
                    return $url;
                }

                if (str_starts_with($action, '/') || str_starts_with($action, 'http')) {
                    $url = $action;
                } else {
                    // Normalizar guiones a guiones bajos para coincidir con nombres de rutas Laravel
                    $normalizedAction = str_replace('-', '_', $action);

                    $routeCandidates = [$action, $normalizedAction];
                    if (!str_starts_with($action, 'admin.')) {
                        $routeCandidates[] = 'admin.' . $action;
                        $routeCandidates[] = 'admin.' . $normalizedAction;
                    }
                    // Agregar variantes con .index solo si no tiene ya un método específico
                    if (!str_contains($action, '.') || str_ends_with($action, '.index')) {
                        $routeCandidates = array_merge(
                            $routeCandidates,
                            array_map(
                                fn($name) => $name . '.index',
                                array_filter($routeCandidates, fn($n) => !str_contains($n, '.')),
                            ),
                        );
                    }

                    $routeName = null;
                    foreach ($routeCandidates as $candidate) {
                        if (Route::has($candidate)) {
                            $routeName = $candidate;
                            break;
                        }
                    }

                    if ($routeName) {
                        try {
                            $url = $warehouseMovement ? route($routeName, $warehouseMovement) : route($routeName);
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

            $resolveTextColor = function ($operation) {
                $action = $operation->action ?? '';
                if ($action === 'warehouse-movements.create') {
                    return '#111827';
                }
                return '#FFFFFF';
            };
        @endphp

        <x-common.page-breadcrumb pageTitle="{{ $title ?? 'Movimientos de Almacén' }}" />

        <x-common.component-card title="Listado de movimientos de almacén"
            desc="Gestiona los movimientos de almacén registrados en el sistema.">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between mb-6">
                <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center min-w-0">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif

                    <x-ui.per-page-selector :per-page="$perPage" />

                    <div class="flex-1 min-w-0">
                        <label class="mb-1.5 block text-xs font-medium text-gray-500 sm:hidden">Buscar</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                                <i class="ri-search-line"></i>
                            </span>
                            <input type="text" name="search" value="{{ $search }}"
                                placeholder="Buscar por número, persona, usuario..."
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-none">
                        <x-ui.button size="md" variant="primary" type="submit"
                            class="flex-1 sm:flex-none h-11 px-4 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95"
                            style="background-color: #C43B25; border-color: #C43B25;">
                            <i class="ri-search-line text-gray-100"></i>
                            <span class="font-medium text-gray-100">Buscar</span>
                        </x-ui.button>

                        <x-ui.link-button size="md" variant="outline"
                            href="{{ route('warehouse_movements.index', $viewId ? ['view_id' => $viewId] : []) }}"
                            class="flex-1 sm:flex-none h-11 px-4 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                            <i class="ri-refresh-line"></i>
                            <span class="font-medium">Limpiar</span>
                        </x-ui.link-button>
                    </div>
                </form>

            </div>
            <div class="flex items-center gap-3 border-t border-gray-100 pt-4 lg:border-0 lg:pt-0 flex-none ml-auto">
                @foreach ($topOperations as $operation)
                    @php
                        $topTextColor = $resolveTextColor($operation);
                        $topColor = $operation->color ?: '#3B82F6';
                        $topStyle = "background-color: {$topColor}; color: {$topTextColor};";
                        $topActionUrl = $resolveActionUrl($operation->action ?? '', null, $operation);
                    @endphp
                    <x-ui.link-button size="md" variant="primary" class="w-full sm:w-auto h-11 px-6 shadow-sm"
                        style="{{ $topStyle }}" href="{{ $topActionUrl }}">
                        <i class="{{ $operation->icon }} text-lg"></i>
                        <span>{{ $operation->name }}</span>
                    </x-ui.link-button>
                @endforeach
                @if ($topOperations->isEmpty())
                    <x-ui.link-button size="md" variant="primary"
                        href="{{ route('warehouse_movements.input', $viewId ? ['view_id' => $viewId] : []) }}"
                        class="w-full sm:w-auto h-11 px-6 shadow-sm" style="background-color: #00A389; color: #FFFFFF;">
                        <i class="ri-archive-line text-lg"></i>
                        <span>Entrada</span>
                    </x-ui.link-button>
                    <x-ui.link-button size="md" variant="primary"
                        href="{{ route('warehouse_movements.output', $viewId ? ['view_id' => $viewId] : []) }}"
                        class="w-full sm:w-auto h-11 px-6 shadow-sm" style="background-color: #EF4444; color: #FFFFFF;">
                        <i class="ri-archive-line text-lg"></i>
                        <span>Salida</span>
                    </x-ui.link-button>
                @endif
            </div>

            <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span>Total</span>
                    <x-ui.badge size="sm" variant="light"
                        color="info">{{ $warehouseMovements->total() }}</x-ui.badge>
                </div>
            </div>

            <div
                class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full min-w-[1100px]">
                    <thead style="background-color: #FF4622; color: #FFFFFF;">
                        <tr>
                            <th style="background-color: #FF4622; color: #FFFFFF;"
                                class="px-5 py-4 text-center first:rounded-tl-xl sticky-left-header">
                                <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">Número</p>
                            </th>
                            <th class="px-5 py-4 text-center align-middle break-words">
                                <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">Origen</p>
                            </th>
                            <th class="px-5 py-4 text-center align-middle break-words">
                                <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">Persona</p>
                            </th>
                            <th class="px-5 py-4 text-center align-middle break-words">
                                <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">Comentario</p>
                            </th>
                            <th class="px-5 py-4 text-center align-middle break-words">
                                <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">Movimiento</p>
                            </th>
                            <th class="px-5 py-4 text-center align-middle break-words">
                                <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">Fecha</p>
                            </th>
                            <th class="px-5 py-4 text-center last:rounded-tr-xl">
                                <p class="font-bold text-gray-100 text-xs uppercase tracking-wider">Acciones</p>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($warehouseMovements as $warehouseMovement)
                            @php
                                $movement = $warehouseMovement->movement;
                                $docTypeId = $movement->documentType->id ?? null;
                                $docName = $movement->documentType->name ?? '';
                                $isEntrada =
                                    $docTypeId == 7 ||
                                    stripos($docName, 'Entrada') !== false ||
                                    stripos($docName, 'entry') !== false;
                                $isSalida =
                                    $docTypeId == 8 ||
                                    stripos($docName, 'Salida') !== false ||
                                    stripos($docName, 'exit') !== false ||
                                    stripos($docName, 'output') !== false;
                                $tipoMovimiento = $isEntrada ? 'Entrada' : ($isSalida ? 'Salida' : '-');
                                $statusColor = $tipoMovimiento === 'Entrada' ? 'info' : ($tipoMovimiento === 'Salida' ? 'warning' : 'info');
                            @endphp
                            <tr class="group/row transition hover:bg-gray-50/80 dark:hover:bg-white/5">
                                <td class="px-5 py-4 align-middle">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#FF4622]/10 text-[#FF4622] dark:bg-[#FF4622]/20 shrink-0">
                                            <i class="ri-archive-line text-xs"></i>
                                        </div>
                                        <p class="font-semibold text-gray-800 text-theme-sm dark:text-white/90 truncate"
                                            title="{{ $movement->number ?? '-' }}">
                                            {{ $movement->number ?? '-' }}
                                        </p>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-center align-middle break-words">
                                    <x-ui.badge variant="light" color="info">
                                        {{ $movement->movementType->description ?? '-' }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-5 py-4 align-middle break-words">
                                    <p class="text-gray-600 text-theme-sm dark:text-gray-400">
                                        {{ $movement->person_name ?? ($movement->user_name ?? '-') }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 align-middle break-words">
                                    <p class="text-gray-600 text-theme-sm dark:text-gray-400 "
                                        title="{{ $movement->comment ?? '-' }}">
                                        {{ $movement->comment ?? '-' }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 text-center align-middle break-words">
                                    <x-ui.badge variant="light" color="{{ $statusColor }}">
                                        {{ $tipoMovimiento ?? '-' }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-5 py-4 text-center align-middle break-words">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $movement->moved_at ? $movement->moved_at->format('d/m/Y H:i') : '-' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 align-middle">
                                    <div class="flex items-center justify-center gap-2">
                                        @if ($rowOperations->isNotEmpty())
                                            @foreach ($rowOperations as $operation)
                                                @php
                                                    $action = $operation->action ?? '';
                                                    $isDelete = str_contains($action, 'destroy');
                                                    $actionUrl = $resolveActionUrl(
                                                        $action,
                                                        $warehouseMovement,
                                                        $operation,
                                                    );
                                                    $buttonColor = $operation->color ?: '#3B82F6';
                                                    $buttonTextColor = str_contains($action, 'edit')
                                                        ? '#111827'
                                                        : '#FFFFFF';
                                                    $buttonStyle = "background-color: {$buttonColor}; color: {$buttonTextColor};";
                                                    $variant = $isDelete
                                                        ? 'eliminate'
                                                        : (str_contains($action, 'edit')
                                                            ? 'edit'
                                                            : 'primary');
                                                @endphp
                                                @if ($isDelete)
                                                    <form method="POST" action="{{ $actionUrl }}"
                                                        class="relative group js-swal-delete"
                                                        data-swal-title="Eliminar movimiento?"
                                                        data-swal-text="Se eliminara el movimiento {{ $movement->number ?? '-' }}. Esta accion no se puede deshacer."
                                                        data-swal-confirm="Si, eliminar" data-swal-cancel="Cancelar"
                                                        data-swal-confirm-color="#ef4444"
                                                        data-swal-cancel-color="#6b7280">
                                                        @csrf
                                                        @method('DELETE')
                                                        @if ($viewId)
                                                            <input type="hidden" name="view_id"
                                                                value="{{ $viewId }}">
                                                        @endif
                                                        <x-ui.button size="icon" variant="{{ $variant }}"
                                                            type="submit" className="rounded-xl"
                                                            style="{{ $buttonStyle }}"
                                                            aria-label="{{ $operation->name }}">
                                                            <i class="{{ $operation->icon }}"></i>
                                                        </x-ui.button>
                                                        <span
                                                            class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50"
                                                            style="transition-delay: 0.5s;">{{ $operation->name }}</span>
                                                    </form>
                                                @else
                                                    <div class="relative group">
                                                        <x-ui.link-button size="icon" variant="{{ $variant }}"
                                                            href="{{ $actionUrl }}" className="rounded-xl"
                                                            style="{{ $buttonStyle }}"
                                                            aria-label="{{ $operation->name }}">
                                                            <i class="{{ $operation->icon }}"></i>
                                                        </x-ui.link-button>
                                                        <span
                                                            class="pointer-events-none absolute top-full left-1/2 -translate-x-1/2 mt-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100 z-50"
                                                            style="transition-delay: 0.5s;">{{ $operation->name }}</span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        @else
                                            <div class="relative group">
                                                <a href="{{ route('warehouse_movements.show', array_merge(['warehouseMovement' => $warehouseMovement->id], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                    class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-info-500 text-white hover:bg-info-600 transition-colors shadow-sm"
                                                    style="background-color: #FF4622; color: #FFFFFF;"
                                                    aria-label="Ver Registro">
                                                    <i class="ri-eye-line"></i>
                                                </a>
                                            </div>
                                            <div class="relative group">
                                                <a href="{{ route('warehouse_movements.edit', array_merge(['warehouseMovement' => $warehouseMovement->id], $viewId ? ['view_id' => $viewId] : [])) }}"
                                                    class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-warning-500 text-white hover:bg-warning-600 transition-colors shadow-sm"
                                                    style="background-color: #FBBF24; color: #111827;"
                                                    aria-label="Editar Registro">
                                                    <i class="ri-pencil-line"></i>
                                                </a>
                                            </div>
                                            <form method="POST"
                                                action="{{ route('warehouse_movements.destroy', ['warehouseMovement' => $warehouseMovement->id]) }}"
                                                class="inline js-swal-delete" data-swal-title="¿Eliminar movimiento?"
                                                data-swal-text="Se eliminará el movimiento {{ $movement->number ?? '-' }}. Esta acción no se puede deshacer."
                                                data-swal-confirm="Sí, eliminar" data-swal-cancel="Cancelar"
                                                data-swal-confirm-color="#ef4444">
                                                @csrf
                                                @method('DELETE')
                                                @if ($viewId)
                                                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                @endif
                                                <button type="submit"
                                                    class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-red-500 text-white hover:bg-red-600 transition-colors shadow-sm"
                                                    aria-label="Eliminar">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-16">
                                    <div class="flex flex-col items-center gap-4 text-center">
                                        <div
                                            class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-50 text-gray-400 dark:bg-gray-800/50 dark:text-gray-600">
                                            <i class="ri-archive-line text-3xl"></i>
                                        </div>
                                        <div class="space-y-1">
                                            <p class="text-base font-semibold text-gray-800 dark:text-white/90">No hay
                                                movimientos de almacén registrados</p>
                                            <p class="text-sm text-gray-500">Comienza registrando tu primer movimiento de
                                                almacén.</p>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-common.component-card>

        <div class="mt-5 mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between px-4 sm:px-6">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Mostrando
                <span
                    class="font-semibold text-gray-700 dark:text-gray-200">{{ $warehouseMovements->firstItem() ?? 0 }}</span>
                -
                <span
                    class="font-semibold text-gray-700 dark:text-gray-200">{{ $warehouseMovements->lastItem() ?? 0 }}</span>
                de
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $warehouseMovements->total() }}</span>
            </div>
            <div class="flex-none pagination-simple">
                {{ $warehouseMovements->links() }}
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function() {
                function showFlashToasts() {
                    const successMsg = sessionStorage.getItem('flash_success_message');
                    if (successMsg) {
                        sessionStorage.removeItem('flash_success_message');
                        if (window.Swal) {
                            Swal.fire({
                                toast: true,
                                position: 'bottom-end',
                                icon: 'success',
                                title: successMsg,
                                showConfirmButton: false,
                                timer: 3500,
                                timerProgressBar: true
                            });
                            return;
                        }
                    }
                    const errorMsg = sessionStorage.getItem('flash_error_message');
                    if (errorMsg) {
                        sessionStorage.removeItem('flash_error_message');
                        if (window.Swal) {
                            Swal.fire({
                                toast: true,
                                position: 'bottom-end',
                                icon: 'error',
                                title: errorMsg,
                                showConfirmButton: false,
                                timer: 4000,
                                timerProgressBar: true
                            });
                        }
                    }
                }
                showFlashToasts();
                document.addEventListener('turbo:load', showFlashToasts);
            })();
        </script>
    @endpush
@endsection
