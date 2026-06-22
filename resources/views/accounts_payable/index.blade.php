@extends('layouts.app')

@section('content')
    <div>
        <x-common.page-breadcrumb pageTitle="Cuentas por cobrar" />

        <x-common.component-card
            title="Créditos y saldos pendientes"
            desc="Clientes a quienes se les facturó a crédito: importe, saldo y fecha de vencimiento.">
            <form method="GET" class="mb-4 flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end">
                @if (request('view_id'))
                    <input type="hidden" name="view_id" value="{{ request('view_id') }}">
                @endif
                <div class="flex-1 min-w-[200px]">
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Buscar</label>
                    <input type="text" name="search" value="{{ $search }}"
                        placeholder="Cliente, DNI o N° documento..."
                        class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90" />
                </div>
                <div class="w-full sm:w-48">
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Estado</label>
                    <select name="status"
                        class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90">
                        <option value="pendiente" @selected($status === 'pendiente')>Solo pendientes de cobro</option>
                        <option value="todos" @selected($status === 'todos')>Todos</option>
                        <option value="NUEVO" @selected($status === 'NUEVO')>Nuevo</option>
                        <option value="PAGANDO" @selected($status === 'PAGANDO')>Pagando</option>
                        <option value="PAGADO" @selected($status === 'PAGADO')>Pagado (saldado)</option>
                        <option value="CANCELADO" @selected($status === 'CANCELADO')>Cancelado</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <x-ui.button size="md" variant="primary" type="submit" class="h-11 px-4" style="background-color: #C43B25;">
                        <i class="ri-search-line"></i> Filtrar
                    </x-ui.button>
                    <x-ui.link-button size="md" variant="outline" href="{{ route('accounts-receivable.index', array_filter(['view_id' => request('view_id')])) }}"
                        class="h-11 px-4">
                        Limpiar
                    </x-ui.link-button>
                </div>
            </form>

            <div
                class="table-responsive mt-2 overflow-x-auto max-w-full rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="w-full min-w-[900px]">
                    <thead style="background-color: #FF4622; color: #FFFFFF;">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">Cliente</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">Documento</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase">Total</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase">Saldo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase">Vencimiento</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase">Estado</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($accounts as $row)
                            @php
                                $person = $row->person;
                                $nombre = $person
                                    ? trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? ''))
                                    : '—';
                                $due = $row->due_at;
                                $vencido =
                                    $due &&
                                    $due->isPast() &&
                                    (float) $row->balance > 0 &&
                                    in_array($row->status, ['NUEVO', 'PAGANDO'], true);
                                $puedeCobrar =
                                    (float) $row->balance > 0.009 &&
                                    in_array($row->status, ['NUEVO', 'PAGANDO'], true);
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="px-4 py-3 text-sm text-gray-800 dark:text-white/90">
                                    <div class="font-medium">{{ $nombre !== '' ? $nombre : '—' }}</div>
                                    @if ($person && $person->document_number)
                                        <div class="text-xs text-gray-500">DNI/Doc: {{ $person->document_number }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ $row->movement?->number ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-medium tabular-nums">
                                    S/ {{ number_format((float) $row->total, 2) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-semibold tabular-nums text-gray-900 dark:text-white">
                                    S/ {{ number_format((float) $row->balance, 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if ($due)
                                        <div class="font-medium {{ $vencido ? 'text-red-600 dark:text-red-400' : 'text-gray-800 dark:text-white' }}">
                                            {{ $due->format('d/m/Y H:i') }}
                                        </div>
                                        @if ($vencido)
                                            <span
                                                class="mt-0.5 inline-block rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200">Vencido</span>
                                        @endif
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @php
                                        $badgeColor =
                                            in_array($row->status, ['PAGADO', 'CANCELADO'], true)
                                                ? 'success'
                                                : ($row->status === 'PAGANDO'
                                                    ? 'warning'
                                                    : 'info');
                                    @endphp
                                    <x-ui.badge variant="light" color="{{ $badgeColor }}">
                                        {{ $row->status }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($puedeCobrar)
                                        <x-ui.link-button size="sm" variant="primary"
                                            href="{{ route('accounts-receivable.collect', array_filter(['account_receivable_payable' => $row->id, 'view_id' => request('view_id')])) }}"
                                            style="background-color: #C43B25;">
                                            Cobrar
                                        </x-ui.link-button>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">
                                    No hay cuentas por cobrar con los filtros seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $accounts->links() }}
            </div>
        </x-common.component-card>
    </div>
@endsection
