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

            $normalizedAction = str_replace('cards', 'cards', $action);

            if (str_starts_with($normalizedAction, '/') || str_starts_with($normalizedAction, 'http')) {
                $url = $normalizedAction;
            } else {
                $routeCandidates = [$normalizedAction];
                if (!str_starts_with($normalizedAction, 'admin.')) {
                    $routeCandidates[] = 'admin.' . $normalizedAction;
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
            if (str_contains($action, 'cards.create')) {
                return '#111827';
            }
            return '#FFFFFF';
        };
    @endphp
    <x-common.page-breadcrumb pageTitle="{{ 'Tarjetas' }}" />
    <x-common.component-card title="Listado de tarjetas" desc="Gestiona las tarjetas registradas en el sistema.">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <form method="GET" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                @if ($viewId)
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif
                <div class="relative flex-1">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"> <i class="ri-search-line"></i>
                    </span>
                    <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Buscar por descripcion"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-12 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-ui.button size="md" variant="primary" type="submit" class="flex-1 sm:flex-none h-11 px-4 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95" style="background-color: #C43B25; border-color: #C43B25;">
                        <i class="ri-search-line text-gray-100"></i>
                        <span class="font-medium text-gray-100">Buscar</span>
                    </x-ui.button>
                    <x-ui.link-button size="md" variant="outline" href="{{ $viewId ? route('admin.cards.index', ['view_id' => $viewId]) : route('admin.cards.index') }}" class="flex-1 sm:flex-none h-11 px-4 border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-all duration-200">
                        <i class="ri-refresh-line"></i>
                        <span class="font-medium">Limpiar</span>
                    </x-ui.link-button>
                </div>
            </form>
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($topOperations as $operation)
                    @php
                        $topTextColor = $resolveTextColor($operation);
                        $topColor = $operation->color ?: '#3B82F6';
                        $topStyle = "background-color: {$topColor}; color: {$topTextColor};";
                        $topActionUrl = $resolveActionUrl($operation->action ?? '', null, $operation);
                        $isCreate = str_contains($operation->action ?? '', 'cards.create')
                            || str_contains($operation->action ?? '', 'open-create-card-modal');
                    @endphp
                    @if ($isCreate)
                        <x-ui.button size="md" variant="primary" type="button"
                            style="{{ $topStyle }}" @click="$dispatch('open-create-card-modal')">
                            <i class="{{ $operation->icon }}"></i>
                            <span>{{ $operation->name }}</span>
                        </x-ui.button>
                    @else
                        <x-ui.link-button size="md" variant="primary"
                            style="{{ $topStyle }}" href="{{ $topActionUrl }}">
                            <i class="{{ $operation->icon }}"></i>
                            <span>{{ $operation->name }}</span>
                        </x-ui.link-button>
                    @endif
                @endforeach
            </div>
        </div>
        @if ($cards->count() > 0)
            <div class="table-responsive mt-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full min-w-max">
                        <thead class="text-left text-theme-xs dark:text-gray-400">
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th style="background-color: #FF4622; color: #FFFFFF"  class="px-5 py-3 text-center sm:px-6 sticky-left-header">
                                    ID
                                </th>
                                <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                    Descripcion
                                </th>
                                <th style="background-color: #FF4622; color: #FFFFFF;"  class="px-5 py-3 text-center sm:px-6">
                                    Tipo
                                </th>
                                <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                    Orden
                                </th>
                                <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                    Icono
                                </th>
                                <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                    Estado
                                </th>
                                <th style="background-color: #FF4622; color: #FFFFFF;" class="px-5 py-3 text-center sm:px-6">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($cards as $card)
                                <tr
                                    class="border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                                    <td class="px-5 py-4 sm:px-6 text-center sticky-left">
                                        <p class="font-medium text-gray-900 text-theme-sm dark:text-white/90">
                                            {{ $card->id }}</p>
                                    </td>
                                    <td class="px-5 py-4 sm:px-6 text-center">
                                        <p class="font-medium text-gray-900 text-theme-sm dark:text-white/90">
                                            {{ $card->description }}</p>
                                    </td>
                                    <td class="px-5 py-4 sm:px-6 text-center">
                                        <p class="font-medium text-gray-900 text-theme-sm dark:text-white/90">
                                            {{ $card->type == 'C' ? 'Credito' : 'Debito' }}</p>
                                    </td>
                                    <td class="px-5 py-4 sm:px-6 text-center">
                                        <p class="font-medium text-gray-900 text-theme-sm dark:text-white/90">
                                            {{ $card->order_num }}</p>
                                    </td>
                                    <td class="px-5 py-4 sm:px-6 text-center">
                                        <p class="font-medium text-gray-900 text-theme-sm dark:text-white/90">
                                            <i class="{{ $card->icon ?? '-' }}"></i></p>
                                    </td>
                                    <td class="px-5 py-4 sm:px-6 text-center">
                                        <x-ui.badge variant="light" color="{{ $card->status ? 'success' : 'error' }}">
                                            {{ $card->status ? 'Activo' : 'Inactivo' }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="px-5 py-4 sm:px-6 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            @foreach ($rowOperations as $operation)
                                                @php
                                                    $action = $operation->action ?? '';
                                                    $isDelete = str_contains($action, 'destroy');
                                                    $actionUrl = $resolveActionUrl($action, $card, $operation);
                                                    $textColor = $resolveTextColor($operation);
                                                    $buttonColor = $operation->color ?: '#3B82F6';
                                                    $buttonStyle = "background-color: {$buttonColor}; color: {$textColor};";
                                                    $variant = $isDelete ? 'eliminate' : (str_contains($action, 'edit') ? 'edit' : 'primary');
                                                @endphp
                                                @if ($isDelete)
                                                    <form method="POST" action="{{ $actionUrl }}"
                                                        class="relative group js-swal-delete"
                                                        data-swal-title="Eliminar tarjeta?"
                                                        data-swal-text="Se eliminara {{ $card->description }}. Esta accion no se puede deshacer."
                                                        data-swal-confirm="Si, eliminar" data-swal-cancel="Cancelar"
                                                        data-swal-confirm-color="#ef4444" data-swal-cancel-color="#6b7280">
                                                        @csrf
                                                        @method('DELETE')
                                                        @if ($viewId)
                                                            <input type="hidden" name="view_id" value="{{ $viewId }}">
                                                        @endif
                                                        <x-ui.button size="icon" variant="{{ $variant }}" type="submit"
                                                            className="bg-error-500 text-white hover:bg-error-600 ring-0 rounded-xl"
                                                            style="{{ $buttonStyle }}"
                                                            aria-label="{{ $operation->name }}">
                                                            <i class="{{ $operation->icon }}"></i>
                                                        </x-ui.button>
                                                    </form>
                                                @elseif (str_contains($action, 'edit'))
                                                    <div class="relative group">
                                                        <x-ui.button size="icon" variant="{{ $variant }}" type="button"
                                                            className="rounded-xl"
                                                            style="{{ $buttonStyle }}"
                                                            aria-label="{{ $operation->name }}"
                                                            x-on:click.prevent="$dispatch('open-edit-card-modal', {{ Illuminate\Support\Js::from(['id' => $card->id, 'description' => $card->description, 'type' => $card->type, 'order_num' => $card->order_num, 'icon' => $card->icon ?? '', 'status' => $card->status]) }})"
                                                        >
                                                            <i class="{{ $operation->icon }}"></i>
                                                        </x-ui.button>
                                                    </div>
                                                @else
                                                    <div class="relative group">
                                                        <x-ui.link-button size="icon" variant="{{ $variant }}"
                                                            href="{{ $actionUrl }}"
                                                            className="rounded-xl"
                                                            style="{{ $buttonStyle }}"
                                                            aria-label="{{ $operation->name }}">
                                                            <i class="{{ $operation->icon }}"></i>
                                                        </x-ui.link-button>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
            </div>
        @else
            <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="p-6 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400 sm:text-base">
                        No hay tarjetas disponibles.
                    </p>
                </div>
            </div>
        @endif
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-gray-500">
                Mostrando
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $cards->firstItem() ?? 0 }}</span>
                -
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $cards->lastItem() ?? 0 }}</span>
                de
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $cards->total() }}</span>
            </div>
            <div>
                {{ $cards->links() }}
            </div>
            <div>
                <form method="GET" action="{{ route('admin.cards.index') }}">
                    @if ($viewId)
                        <input type="hidden" name="view_id" value="{{ $viewId }}">
                    @endif
                    <input type="hidden" name="search" value="{{ $search ?? '' }}">
                    <select name="per_page" onchange="this.form.submit()"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-[#FF4622] focus:ring-[#FF4622]/10 dark:focus:border-[#FF4622] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                        @foreach ($allowedPerPage ?? [10, 20, 50, 100] as $size)
                            <option value="{{ $size }}" {{ ($perPage ?? 10) == $size ? 'selected' : '' }}>{{ $size }} / pagina</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    </x-common.component-card>

    <!--Modal de creacion de tarjeta-->
    <x-ui.modal x-data="{ open: false }" @open-create-card-modal.window="open = true"
        @close-create-card-modal.window="open = false" :isOpen="false" class="max-w-md">
        <div class="p-6 space-y-4">
            <h3 class="mb-6 text-lg font-semibold text-gray-800 dark:text-white/90">Crear Tarjeta</h3>
            @if ($errors->any())
                <div class="mb-5">
                    <x-ui.alert variant="error" title="Revisa los campos" message="Hay errores en el formulario, corrige los datos e intenta nuevamente." />
                </div>
            @endif
            <form id="create-card-form" class="space-y-4" action="{{ $viewId ? route('admin.cards.store') . '?view_id=' . $viewId : route('admin.cards.store') }}"
                method="POST" enctype="multipart/form-data">
                @csrf
                @if ($viewId)
                    <input type="hidden" name="view_id" value="{{ $viewId }}">
                @endif
                @include('cards._form')
                <div class="flex flex-wrap gap-3 justify-end">
                    <x-ui.button type="submit" size="md" variant="primary">Guardar</x-ui.button>
                    <x-ui.button type="button" size="md" variant="outline"
                        @click="open = false">Cancelar</x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>

    @include('cards.edit')

@endsection
