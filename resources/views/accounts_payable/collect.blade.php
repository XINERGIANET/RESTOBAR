@extends('layouts.app')

@section('content')
    <div class="w-full min-w-0 max-w-[1600px] mx-auto px-3 py-4 sm:px-5">
        <x-common.page-breadcrumb pageTitle="Abono / cobro de crédito" />

        @php
            $person = $account->person;
            $nombre = $person ? trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? '')) : '—';
            $total = (float) $account->total;
            $totalPaid = (float) ($account->total_paid ?? 0);
            $pending = round(max(0, $total - $totalPaid), 2);
            $om = $account->movement?->orderMovement;
        @endphp

        {{-- Grid en lugar de flex-row: evita solapamientos. Una columna hasta lg. --}}
        <div class="grid min-w-0 grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_min(26rem,100%)] lg:items-start lg:gap-10">
            <div class="min-w-0 space-y-6">
                <x-common.component-card title="Cuenta y documento"
                    desc="Datos de la deuda que está cobrando.">
                    {{-- Sin col-span-full: bloques separados evitan texto superpuesto --}}
                    <div class="flex flex-col gap-4 rounded-xl border border-sky-100 bg-sky-50/70 p-4 text-sm dark:border-gray-700 dark:bg-white/5">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="min-w-0">
                                <p class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Cliente</p>
                                <p class="break-words font-semibold leading-snug text-gray-900 dark:text-white">
                                    {{ $nombre !== '' ? $nombre : '—' }}</p>
                            </div>
                            <div class="min-w-0">
                                <p class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Documento</p>
                                <p class="break-all font-semibold leading-snug text-gray-900 dark:text-white">
                                    {{ $account->movement?->number ?? '—' }}</p>
                            </div>
                            <div class="min-w-0">
                                <p class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Total facturado</p>
                                <p class="tabular-nums font-semibold leading-normal">S/ {{ number_format($total, 2) }}</p>
                            </div>
                            <div class="min-w-0">
                                <p class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Total abonado</p>
                                <p class="tabular-nums font-semibold leading-normal">S/ {{ number_format($totalPaid, 2) }}</p>
                            </div>
                        </div>

                        @if ($om)
                            <div
                                class="grid grid-cols-1 gap-3 border-t border-sky-200/80 pt-4 text-xs dark:border-gray-600 sm:grid-cols-3">
                                <div class="min-w-0 rounded-lg bg-white/60 px-3 py-2 dark:bg-black/20">
                                    <span class="mb-1 block text-gray-500 dark:text-gray-400">Subtotal pedido</span>
                                    <p class="text-sm font-medium tabular-nums text-gray-900 dark:text-white">S/
                                        {{ number_format((float) $om->subtotal, 2) }}</p>
                                </div>
                                <div class="min-w-0 rounded-lg bg-white/60 px-3 py-2 dark:bg-black/20">
                                    <span class="mb-1 block text-gray-500 dark:text-gray-400">Impuestos</span>
                                    <p class="text-sm font-medium tabular-nums text-gray-900 dark:text-white">S/
                                        {{ number_format((float) ($om->tax ?? 0), 2) }}</p>
                                </div>
                                <div class="min-w-0 rounded-lg bg-white/60 px-3 py-2 dark:bg-black/20">
                                    <span class="mb-1 block text-gray-500 dark:text-gray-400">Total pedido</span>
                                    <p class="text-sm font-medium tabular-nums text-gray-900 dark:text-white">S/
                                        {{ number_format((float) ($om->total ?? 0), 2) }}</p>
                                </div>
                            </div>
                        @endif

                        <div class="border-t border-sky-200/80 pt-4 dark:border-gray-600">
                            <p class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Saldo pendiente</p>
                            <p class="text-xl font-bold leading-normal tabular-nums text-[#C43B25] dark:text-orange-300">S/
                                {{ number_format($pending, 2) }}</p>
                        </div>
                    </div>
                </x-common.component-card>

                <x-common.component-card title="Detalle de productos"
                    desc="Ítems asociados al movimiento origen (pedido o venta).">
                    @if (count($lineItems) > 0)
                        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                            <div class="max-h-[min(480px,60vh)] w-full min-w-0 overflow-x-auto overflow-y-auto">
                                <table class="w-full min-w-[280px] text-left text-sm">
                                    <thead class="sticky top-0 z-10 text-xs uppercase tracking-wide"
                                        style="background-color: #FF4622; color: #FFFFFF;">
                                        <tr>
                                            <th class="px-3 py-2.5 font-semibold">Producto</th>
                                            <th class="w-20 px-3 py-2.5 text-right font-semibold">Cant.</th>
                                            <th class="w-24 px-3 py-2.5 text-right font-semibold">P. unit.</th>
                                            <th class="w-24 px-3 py-2.5 text-right font-semibold">Importe</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900/30">
                                        @foreach ($lineItems as $row)
                                            <tr class="align-top">
                                                <td class="max-w-[12rem] px-3 py-2.5 text-gray-900 dark:text-white/90 sm:max-w-none">
                                                    <div class="break-words font-medium leading-snug">{{ $row['name'] }}</div>
                                                    @if (!empty($row['comment']))
                                                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                            {{ $row['comment'] }}</div>
                                                    @endif
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                                    {{ rtrim(rtrim(number_format($row['qty'], 4, '.', ''), '0'), '.') }}
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                                    S/ {{ number_format($row['unit'], 2) }}</td>
                                                <td
                                                    class="whitespace-nowrap px-3 py-2.5 text-right font-semibold tabular-nums text-gray-900 dark:text-white">
                                                    S/ {{ number_format($row['line_total'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <p class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-600 dark:bg-white/5 dark:text-gray-400">
                            No hay líneas de producto vinculadas a este documento (p. ej. ajuste manual o movimiento sin
                            detalle). El cobro igualmente se aplicará al saldo mostrado arriba.
                        </p>
                    @endif
                </x-common.component-card>
            </div>

            <div class="min-w-0 lg:sticky lg:top-4 lg:self-start">
                <x-common.component-card title="Registrar abono"
                    desc="Una transacción en base de datos. El importe no puede superar el saldo pendiente.">
                    <form method="POST"
                        action="{{ route('accounts-receivable.collect.store', array_filter(['account_receivable_payable' => $account->id, 'view_id' => request('view_id')])) }}"
                        class="flex flex-col gap-4" id="form-abono-credito">
                        @csrf
                        @if (request('view_id'))
                            <input type="hidden" name="view_id" value="{{ request('view_id') }}">
                        @endif

                        <div>
                            <label for="amount"
                                class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Importe del abono
                                (S/)</label>
                            <input id="amount" name="amount" type="number" step="0.01" min="0.01" max="{{ $pending }}"
                                value="{{ old('amount', number_format($pending, 2, '.', '')) }}" required
                                class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:text-white/90" />
                            <p class="mt-1 text-xs text-gray-500">Máximo: S/ {{ number_format($pending, 2) }}</p>
                        </div>

                        <div>
                            <label for="payment_method_id"
                                class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Método de pago</label>
                            <select id="payment_method_id" name="payment_method_id" required
                                class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:text-white/90"
                                onchange="if (window.syncCollectPaymentExtras) window.syncCollectPaymentExtras()">
                                <option value="" data-pay-kind="">— Seleccione —</option>
                                @foreach ($paymentMethods as $pm)
                                    @php
                                        $payKind = $paymentMethodKinds[(string) $pm->id] ?? 'otro';
                                    @endphp
                                    <option value="{{ $pm->id }}" data-pay-kind="{{ $payKind }}"
                                        @selected((string) old('payment_method_id') === (string) $pm->id)>{{ $pm->description }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div id="collect-pay-extra-tarjeta" class="hidden space-y-3 rounded-lg border border-violet-200 bg-violet-50/50 p-3 dark:border-violet-900/40 dark:bg-violet-950/20">
                            <p class="text-xs font-semibold uppercase tracking-wide text-violet-800 dark:text-violet-300">Datos
                                de tarjeta</p>
                            <div>
                                <label class="mb-1 block text-xs text-gray-600 dark:text-gray-400" for="collect_card_id">Tipo
                                    de tarjeta</label>
                                <select id="collect_card_id" name="card_id"
                                    class="collect-pay-subselect dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:text-white/90">
                                    <option value="">— Seleccione —</option>
                                    @foreach ($cards as $card)
                                        <option value="{{ $card->id }}" @selected((string) old('card_id') === (string) $card->id)>
                                            {{ $card->description }}@if ($card->type)
                                                ({{ $card->type }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-gray-600 dark:text-gray-400"
                                    for="collect_payment_gateway_id">Pasarela / POS (opcional)</label>
                                <select id="collect_payment_gateway_id" name="payment_gateway_id"
                                    class="collect-pay-subselect dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:text-white/90">
                                    <option value="">— Ninguno —</option>
                                    @foreach ($paymentGateways as $pg)
                                        <option value="{{ $pg->id }}" @selected((string) old('payment_gateway_id') === (string) $pg->id)>
                                            {{ $pg->description }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div id="collect-pay-extra-wallet" class="hidden space-y-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-3 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-300">
                                Billetera digital (Yape, Plin, etc.)</p>
                            <div>
                                <label class="mb-1 block text-xs text-gray-600 dark:text-gray-400"
                                    for="collect_digital_wallet_id">Aplicación</label>
                                <select id="collect_digital_wallet_id" name="digital_wallet_id"
                                    class="collect-pay-subselect dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:text-white/90">
                                    <option value="">— Seleccione —</option>
                                    @foreach ($digitalWallets as $dw)
                                        <option value="{{ $dw->id }}" @selected((string) old('digital_wallet_id') === (string) $dw->id)>
                                            {{ $dw->description }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div id="collect-pay-extra-transfer" class="hidden space-y-3 rounded-lg border border-blue-200 bg-blue-50/50 p-3 dark:border-blue-900/40 dark:bg-blue-950/20">
                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-800 dark:text-blue-300">
                                Transferencia o depósito</p>
                            <div>
                                <label class="mb-1 block text-xs text-gray-600 dark:text-gray-400" for="collect_bank_id">Banco
                                    destino</label>
                                <select id="collect_bank_id" name="bank_id"
                                    class="collect-pay-subselect dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:text-white/90">
                                    <option value="">— Seleccione —</option>
                                    @foreach ($banks as $bank)
                                        <option value="{{ $bank->id }}" @selected((string) old('bank_id') === (string) $bank->id)>
                                            {{ $bank->description }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="payment_reference"
                                class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Referencia (n°
                                operación, voucher, celular…)</label>
                            <input id="payment_reference" name="payment_reference" type="text"
                                value="{{ old('payment_reference') }}" maxlength="120" placeholder="Opcional"
                                class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:text-white/90" />
                        </div>

                        <div>
                            <label for="notes" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Nota
                                interna (opcional)</label>
                            <input id="notes" name="notes" type="text" value="{{ old('notes') }}" maxlength="500"
                                class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:text-white/90" />
                        </div>

                        <div class="flex flex-wrap gap-2 pt-2">
                            <x-ui.button size="md" variant="primary" type="button" id="btn-abono-total"
                                style="background-color: #374151;" class="dark:bg-gray-600">
                                Usar saldo total
                            </x-ui.button>
                            <x-ui.button size="md" variant="primary" type="submit" style="background-color: #C43B25;">
                                Registrar abono
                            </x-ui.button>
                            <x-ui.link-button size="md" variant="outline"
                                href="{{ route('accounts-receivable.index', array_filter(['view_id' => request('view_id')])) }}">
                                Volver al listado
                            </x-ui.link-button>
                        </div>
                    </form>
                </x-common.component-card>
            </div>
        </div>
    </div>
    <script>
        (function() {
            const maxPending = {{ json_encode($pending) }};
            const amt = document.getElementById('amount');
            const btn = document.getElementById('btn-abono-total');
            if (btn && amt) {
                btn.addEventListener('click', function() {
                    amt.value = Number(maxPending).toFixed(2);
                });
            }

            const panels = {
                tarjeta: document.getElementById('collect-pay-extra-tarjeta'),
                wallet: document.getElementById('collect-pay-extra-wallet'),
                transfer: document.getElementById('collect-pay-extra-transfer'),
            };

            function collectPayKindFromSelect(sel) {
                if (!sel || !sel.value) return '';
                const opt = sel.selectedOptions[0];
                return (opt && opt.getAttribute('data-pay-kind')) || '';
            }

            function syncCollectPaymentExtras() {
                const sel = document.getElementById('payment_method_id');
                const kind = collectPayKindFromSelect(sel);

                Object.entries(panels).forEach(function(entry) {
                    const key = entry[0];
                    const el = entry[1];
                    if (!el) return;
                    const show = kind === key;
                    el.classList.toggle('hidden', !show);
                    el.querySelectorAll('select.collect-pay-subselect').forEach(function(sub) {
                        sub.disabled = !show;
                        sub.required = show && sub.name !== 'payment_gateway_id';
                    });
                });
            }

            window.syncCollectPaymentExtras = syncCollectPaymentExtras;

            if (!window.__collectAbonoPayTurboBound) {
                window.__collectAbonoPayTurboBound = true;
                document.addEventListener('turbo:load', syncCollectPaymentExtras);
                document.addEventListener('turbo:render', syncCollectPaymentExtras);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', syncCollectPaymentExtras, { once: true });
            } else {
                syncCollectPaymentExtras();
            }
        })();
    </script>
@endsection
