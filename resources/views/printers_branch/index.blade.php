@extends('layouts.app')

@section('content')
    @php
        use Illuminate\Support\Facades\Route;

        $viewId = request('view_id');
        $operacionesCollection = collect($operaciones ?? []);
        $topOperations = $operacionesCollection->where('type', 'T');
        $rowOperations = $operacionesCollection->where('type', 'R');

        $resolveActionUrl = function ($action, $model = null, $operation = null) use ($viewId) {
            if (!$action) {
                return '#';
            }

            $normalizedAction = str_replace('printers_branch', 'printers-branch', $action);

            if (str_starts_with($normalizedAction, '/') || str_starts_with($normalizedAction, 'http')) {
                $url = $normalizedAction;
            } else {
                $routeCandidates = [$normalizedAction];
                if (!str_starts_with($normalizedAction, 'admin.')) {
                    $routeCandidates[] = 'admin.' . $normalizedAction;
                }
                $routeCandidates = array_merge(
                    $routeCandidates,
                    array_map(fn($name) => $name . '.index', $routeCandidates),
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
                        $url = $model ? route($routeName, $model) : route($routeName);
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
            if (str_contains($action, 'printers_branch.create') || str_contains($action, 'printers-branch.create')) {
                return '#111827';
            }
            return '#FFFFFF';
        };
    @endphp

    <x-common.page-breadcrumb pageTitle="Impresoras de Sucursal" />

    <x-common.component-card title="Impresoras de Sucursal" desc="Gestiona las impresoras de sucursal.">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                @if ($viewId)
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif

                <x-ui.per-page-selector :per-page="$perPage" />

                <div class="relative flex-1">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                        <i class="ri-search-line"></i>
                    </span>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por nombre"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-12 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-ui.button size="md" variant="primary" type="submit"
                        class="flex-1 sm:flex-none h-11 px-6 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95"
                        style="background-color: #C43B25; border-color: #C43B25;">
                        <i class="ri-search-line text-gray-100"></i>
                        <span class="font-medium text-gray-100">Buscar</span>
                    </x-ui.button>

                    <x-ui.link-button size="md" variant="outline"
                        href="{{ route('printers_branch.index', $viewId ? ['view_id' => $viewId] : []) }}"
                        class="flex-1 sm:flex-none h-11 px-6 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                        <i class="ri-refresh-line"></i>
                        <span class="font-medium">Limpiar</span>
                    </x-ui.link-button>
                    <div>
                        <div class="flex-shrink-0">
                            @if ($topOperations->isNotEmpty())
                                @foreach ($topOperations as $operation)
                                    @php
                                        $topColor = $operation->color ?: '#3B82F6';
                                        $topTextColor = str_contains($operation->action ?? '', 'printers_branch.create')
                                            ? '#111827'
                                            : '#FFFFFF';
                                        $topStyle = "background-color: {$topColor}; color: {$topTextColor};";
                                    @endphp
                                    <x-ui.button size="md" variant="primary" type="button" style="{{ $topStyle }}"
                                        @click="$dispatch('open-create-printer-modal')" class="h-11">
                                        <i class="{{ $operation->icon }}"></i>
                                        <span>{{ $operation->name }}</span>
                                    </x-ui.button>
                                @endforeach
                            @else
                                <x-ui.button size="md" variant="primary" type="button"
                                    style="background-color: #12f00e; color: #111827;"
                                    @click="$dispatch('open-create-printer-modal')" class="h-11">
                                    <i class="ri-add-line"></i>
                                    <span>Nueva Impresora</span>
                                </x-ui.button>
                            @endif
                        </div>
                    </div>
                </div>
            </form>


        </div>

        <div
            class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="w-full min-w-[900px]">
                <thead style="background-color: #FF4622; color: #FFFFFF;">
                    <tr class="text-white">
                        <th class="px-5 py-3 text-center sm:px-6 first:rounded-tl-xl">
                            <p class="font-semibold text-white text-theme-xs uppercase">Nombre</p>
                        </th>
                        <th class="px-5 py-3 text-center sm:px-6">
                            <p class="font-semibold text-white text-theme-xs uppercase">Ancho</p>
                        </th>
                        <th class="px-5 py-3 text-center sm:px-6">
                            <p class="font-semibold text-white text-theme-xs uppercase">Sucursal</p>
                        </th>
                        <th class="px-5 py-3 text-center sm:px-6">
                            <p class="font-semibold text-white text-theme-xs uppercase">IP</p>
                        </th>
                        <th class="px-5 py-3 text-center sm:px-6">
                            <p class="font-semibold text-white text-theme-xs uppercase">Estado</p>
                        </th>
                        <th class="px-5 py-3 text-center sm:px-6 last:rounded-tr-xl">
                            <p class="font-semibold text-white text-theme-xs uppercase">Acciones</p>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($printers as $printer)
                        <tr
                            class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                            <td class="px-5 py-4 sm:px-6 text-center">
                                <p class="font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $printer->name }}
                                </p>
                            </td>
                            <td class="px-5 py-4 sm:px-6 text-center">
                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $printer->width ?? '-' }}</p>
                            </td>
                            <td class="px-5 py-4 sm:px-6 text-center">
                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                    {{ $printer->branch?->legal_name ?? '-' }}</p>
                            </td>
                            <td class="px-5 py-4 sm:px-6 text-center">
                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $printer->ip ?? '-' }}</p>
                            </td>
                            <td class="px-5 py-4 sm:px-6 text-center">
                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                    {{ $printer->status === 'E' ? 'Activo' : 'Inactivo' }}</p>
                            </td>
                            <td class="px-5 py-4 sm:px-6 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    @if ($rowOperations->isNotEmpty())
                                        @foreach ($rowOperations as $operation)
                                            @php
                                                $action = $operation->action ?? '';
                                                $isDelete = str_contains($action, 'destroy');
                                                $buttonColor = $operation->color ?: '#3B82F6';
                                                $textColor = $isDelete ? '#FFFFFF' : '#FFFFFF';
                                                $buttonStyle = "background-color: {$buttonColor}; color: {$textColor};";
                                            @endphp

                                            @if ($isDelete)
                                                <form method="POST"
                                                    action="{{ route('printers_branch.destroy', ['printerBranch' => $printer->id] + ($viewId ? ['view_id' => $viewId] : [])) }}"
                                                    class="relative group js-swal-delete"
                                                    data-swal-title="¿Eliminar ticketera?"
                                                    data-swal-text="Se eliminará {{ $printer->name }}. Esta acción no se puede deshacer."
                                                    data-swal-confirm="Sí, eliminar"
                                                    data-swal-cancel="Cancelar"
                                                    data-swal-confirm-color="#ef4444"
                                                    data-swal-cancel-color="#6b7280"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    @if ($viewId)
                                                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                    @endif
                                                    <x-ui.button size="icon" variant="eliminate" type="submit"
                                                        className="rounded-xl"
                                                        style="{{ $buttonStyle }}"
                                                        aria-label="{{ $operation->name }}">
                                                        <i class="{{ $operation->icon }}"></i>
                                                    </x-ui.button>
                                                </form>
                                            @else
                                                <div class="relative group">
                                                    <x-ui.link-button size="icon" variant="edit"
                                                        href="{{ route('printers_branch.edit', ['printerBranch' => $printer->id] + ($viewId ? ['view_id' => $viewId] : [])) }}"
                                                        className="rounded-xl"
                                                        style="{{ $buttonStyle }}"
                                                        aria-label="{{ $operation->name }}">
                                                        <i class="{{ $operation->icon }}"></i>
                                                    </x-ui.link-button>
                                                </div>
                                            @endif
                                        @endforeach
                                    @else
                                        {{-- Fallback si no hay operaciones configuradas --}}
                                        <x-ui.link-button size="icon" variant="edit"
                                            href="{{ route('printers_branch.edit', ['printerBranch' => $printer->id] + ($viewId ? ['view_id' => $viewId] : [])) }}"
                                            className="rounded-xl"
                                            aria-label="Editar">
                                            <i class="ri-edit-line"></i>
                                        </x-ui.link-button>
                                        <form method="POST"
                                            action="{{ route('printers_branch.destroy', ['printerBranch' => $printer->id] + ($viewId ? ['view_id' => $viewId] : [])) }}"
                                            class="relative group js-swal-delete"
                                            data-swal-title="¿Eliminar ticketera?"
                                            data-swal-text="Se eliminará {{ $printer->name }}. Esta acción no se puede deshacer."
                                            data-swal-confirm="Sí, eliminar"
                                            data-swal-cancel="Cancelar"
                                            data-swal-confirm-color="#ef4444"
                                            data-swal-cancel-color="#6b7280"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            @if ($viewId)
                                                <input type="hidden" name="view_id" value="{{ $viewId }}">
                                            @endif
                                            <x-ui.button size="icon" variant="eliminate" type="submit" className="rounded-xl"
                                                aria-label="Eliminar">
                                                <i class="ri-delete-bin-line"></i>
                                            </x-ui.button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12">
                                <div class="flex flex-col items-center gap-3 text-center text-sm text-gray-500">
                                    <i class="ri-inbox-line text-3xl"></i>
                                    <p>No hay impresoras registradas</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-gray-500">
                Mostrando
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $printers->firstItem() ?? 0 }}</span>
                -
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $printers->lastItem() ?? 0 }}</span>
                de
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $printers->total() }}</span>
            </div>
            <div>
                {{ $printers->links() }}
            </div>
        </div>
    </x-common.component-card>

    {{-- Modal: Crear impresora/ticketera --}}
    @php
        $sessionBranchId = session('branch_id');
    @endphp

    <x-ui.modal x-data="{ open: false }" @open-create-printer-modal.window="open = true"
        @close-create-printer-modal.window="open = false" :isOpen="false" :showCloseButton="false" class="w-full max-w-lg">
        <div x-show="open" x-cloak class="flex w-full flex-col min-h-0 p-6 sm:p-8">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#FF4622]/10 text-[#FF4622] dark:bg-[#FF4622]/20">
                        <i class="ri-printer-line text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Nueva ticketera</h3>
                        <p class="mt-1 text-sm text-gray-500">Registra una ticketera para la sucursal activa.</p>
                    </div>
                </div>
                <button type="button" @click="open = false"
                    class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                    aria-label="Cerrar">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            @if (!$sessionBranchId)
                <x-ui.alert variant="error" title="No hay sucursal activa"
                    message="Selecciona una sucursal antes de registrar ticketeras." />
            @endif

            <form method="POST" action="{{ route('printers_branch.store', $viewId ? ['view_id' => $viewId] : []) }}"
                class="mt-5 flex w-full flex-col min-h-0 space-y-5">
                @csrf

                @if ($viewId)
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif

                <input type="hidden" name="branch_id" value="{{ $sessionBranchId }}">
                <input type="hidden" name="status" value="E">

                @include('printers_branch._form', ['printer' => null])

                <div class="flex flex-wrap gap-3 pt-2">
                    <x-ui.button type="submit" size="md" variant="primary" :disabled="!$sessionBranchId">
                        <i class="ri-save-line"></i>
                        <span>Guardar</span>
                    </x-ui.button>
                    <x-ui.button type="button" size="md" variant="outline" @click="open = false">
                        <i class="ri-close-line"></i>
                        <span>Cancelar</span>
                    </x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>
@endsection
