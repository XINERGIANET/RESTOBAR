<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cierre de caja - {{ $shift->cashMovementEnd?->movement?->number ?? 'Turno' }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 12px; margin: 0; padding: 16px; color: #111827; }
        h1 { font-size: 25px; margin: 0 0 8px; text-align: center; }
        h2 { font-size: 20px; margin: 16px 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 14px; }
        th, td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .muted { color: #6b7280; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 9999px; font-size: 12px; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        .section { margin-bottom: 16px; page-break-inside: avoid; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media print {
            body { padding: 8px; }
        }
    </style>
</head>
<body>
    @php
        $r = $report ?? [];
        $opts = $options ?? [];
        $movApertura = $shift->cashMovementStart?->movement;
        $movCierre = $shift->cashMovementEnd?->movement;

        if (!empty($r)) {
            $ingresosTotal = (float) ($r['totals']['ingresos'] ?? 0);
            $egresosTotal = (float) ($r['totals']['egresos'] ?? 0);
            $neto = (float) ($r['totals']['neto'] ?? 0);
            $desgloseIngresos = $r['income_by_method'] ?? [];
            $desgloseEgresos = $r['expense_by_method'] ?? [];
            $movementsForDetail = $r['cash_movements'] ?? collect();
        } else {
            $ingresosTotal = 0;
            $egresosTotal = 0;
            $neto = 0;
            $desgloseIngresos = [];
            $desgloseEgresos = [];
            $movementsForDetail = $shift->movements ?? collect();
            foreach ($movementsForDetail as $mov) {
                if ($mov->id == $shift->cash_movement_start_id || $mov->id == $shift->cash_movement_end_id) {
                    continue;
                }
                if ($mov->paymentConcept && $mov->details) {
                    $tipo = $mov->paymentConcept->type;
                    foreach ($mov->details as $detail) {
                        $metodo = $detail->paymentMethod->name ?? ($detail->payment_method ?? 'Otros');
                        $monto = $detail->amount;
                        if ($tipo === 'I') {
                            $ingresosTotal += $monto;
                            $desgloseIngresos[$metodo] = ($desgloseIngresos[$metodo] ?? 0) + $monto;
                        } elseif ($tipo === 'E') {
                            $egresosTotal += $monto;
                            $desgloseEgresos[$metodo] = ($desgloseEgresos[$metodo] ?? 0) + $monto;
                        }
                    }
                }
            }
            $neto = $ingresosTotal - $egresosTotal;
        }
    @endphp

    @if(!empty($pdfGenerationFailed))
        <div style="margin-bottom:12px;padding:10px 12px;background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;font-size:12px;">
            No se pudo generar el archivo PDF (revisa <code>SNAPPY_PDF_BINARY</code> en <code>.env</code>). Usa <strong>Imprimir</strong> del navegador (Ctrl+P).
        </div>
    @endif

    <h1>Cierre de caja</h1>
    <p class="muted text-center">
        Caja: {{ $shift->cashMovementStart?->cashRegister?->number ?? '-' }} ·
        Empresa: {{ $shift->branch?->company?->legal_name ?? '-' }} ·
        Sucursal: {{ $shift->branch?->legal_name ?? '-' }} ·
        Impreso el {{ $printedAt->format('d/m/Y H:i:s') }}
    </p>

    <div class="section grid-2">
        <div>
            <h2>Apertura</h2>
            <table>
                <tr><th>Persona</th><td>{{ $movApertura?->person_name ?: '-' }}</td></tr>
                <tr><th>Responsable</th><td>{{ $movApertura?->responsible_name ?: '-' }}</td></tr>
                <tr><th>Documento</th><td>
                    {{ $movApertura?->movementType?->description ?? '-' }}
                    {{ $movApertura?->documentType?->name ? ' - '.$movApertura->documentType->name : '' }}
                    {{ $movApertura?->salesMovement?->series ?? '' }}-{{ $movApertura?->number ?? '-' }}
                </td></tr>
                <tr><th>Fecha / Hora</th><td>
                    @if($shift->started_at)
                        {{ \Carbon\Carbon::parse($shift->started_at)->format('d/m/Y H:i') }}
                    @else
                        -
                    @endif
                </td></tr>
                <tr><th>Monto apertura</th><td class="text-right">
                    S/ {{ number_format((float) ($shift->cashMovementStart?->total ?? 0), 2) }}
                </td></tr>
            </table>
        </div>
        <div>
            <h2>Cierre</h2>
            <table>
                <tr><th>Persona</th><td>{{ $movCierre?->person_name ?: '-' }}</td></tr>
                <tr><th>Responsable</th><td>{{ $movCierre?->responsible_name ?: '-' }}</td></tr>
                <tr><th>Documento</th><td>
                    {{ $movCierre?->movementType?->description ?? '-' }}
                    {{ $movCierre?->documentType?->name ? ' - '.$movCierre->documentType->name : '' }}
                    {{ $movCierre?->salesMovement?->series ?? '' }}-{{ $movCierre?->number ?? '-' }}
                </td></tr>
                <tr><th>Fecha / Hora</th><td>
                    @if($shift->ended_at)
                        {{ \Carbon\Carbon::parse($shift->ended_at)->format('d/m/Y H:i') }}
                    @else
                        --
                    @endif
                </td></tr>
                <tr><th>Monto cierre</th><td class="text-right">
                    S/ {{ number_format((float) ($shift->cashMovementEnd?->total ?? 0), 2) }}
                </td></tr>
            </table>
        </div>
    </div>

    @if(!empty($opts['sales_payments_summary']))
        <div class="section">
            <h2>Resúmenes — Ventas pagadas</h2>
            <table>
                <tr><th>Comprobantes (ventas cobradas en turno)</th><td class="text-right">{{ $r['paid_sales_summary']['count'] ?? 0 }}</td></tr>
                <tr><th>Total ventas pagadas</th><td class="text-right">S/ {{ number_format((float) ($r['paid_sales_summary']['total'] ?? 0), 2) }}</td></tr>
            </table>
        </div>
    @endif

    @if(!empty($opts['paid_sales_by_method']))
        <div class="section">
            <h2>Ventas por métodos de pago — pagadas</h2>
            <table>
                <thead><tr><th>Método</th><th class="text-right">Monto</th></tr></thead>
                <tbody>
                    @forelse($r['paid_sales_by_method'] ?? [] as $metodo => $monto)
                        <tr><td>{{ $metodo }}</td><td class="text-right">S/ {{ number_format((float) $monto, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-center muted">Sin cobros de ventas en el turno.</td></tr>
                    @endforelse
                    @if(!empty($r['paid_sales_by_method']))
                        <tr><th>Total</th><th class="text-right">S/ {{ number_format((float) ($r['paid_sales_by_method_total'] ?? 0), 2) }}</th></tr>
                    @endif
                </tbody>
            </table>
        </div>
    @endif

    @if(!empty($opts['income_by_payment_method_paid']))
        <div class="section">
            <h2>Ingresos por método de pago — pagados</h2>
            <table>
                <thead><tr><th>Método</th><th class="text-right">Monto</th></tr></thead>
                <tbody>
                    @forelse($desgloseIngresos as $metodo => $monto)
                        <tr><td>{{ $metodo }}</td><td class="text-right">S/ {{ number_format((float) $monto, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-center muted">Sin ingresos</td></tr>
                    @endforelse
                    <tr><th>Total ingresos</th><th class="text-right">S/ {{ number_format($ingresosTotal, 2) }}</th></tr>
                </tbody>
            </table>
        </div>
    @endif

    @if(!empty($opts['expenses_by_payment_method_paid']))
        <div class="section">
            <h2>Egresos (gastos) por método de pago — pagados</h2>
            <table>
                <thead><tr><th>Método</th><th class="text-right">Monto</th></tr></thead>
                <tbody>
                    @forelse($desgloseEgresos as $metodo => $monto)
                        <tr><td>{{ $metodo }}</td><td class="text-right">S/ {{ number_format((float) $monto, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-center muted">Sin egresos</td></tr>
                    @endforelse
                    <tr><th>Total egresos</th><th class="text-right">S/ {{ number_format($egresosTotal, 2) }}</th></tr>
                </tbody>
            </table>
        </div>
    @endif

    @if(!empty($opts['income_by_payment_method_paid']) || !empty($opts['expenses_by_payment_method_paid']))
        <div class="section">
            <h2>Detalle de movimientos de caja</h2>
            <table>
                <thead>
                    <tr><th>Tipo</th><th>Concepto</th><th>Método</th><th class="text-right">Monto</th></tr>
                </thead>
                <tbody>
                    @php $cashRows = 0; @endphp
                    @foreach($movementsForDetail as $mov)
                        @if($mov->id == $shift->cash_movement_start_id || $mov->id == $shift->cash_movement_end_id)
                            @continue
                        @endif
                        @if($mov->paymentConcept && $mov->details)
                            @foreach($mov->details as $detail)
                                @php $cashRows++; @endphp
                                <tr>
                                    <td>
                                        @if($mov->paymentConcept->type === 'I')
                                            <span class="badge badge-success">Ingreso</span>
                                        @elseif($mov->paymentConcept->type === 'E')
                                            <span class="badge badge-danger">Egreso</span>
                                        @else
                                            <span class="badge">{{ $mov->paymentConcept->type }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $mov->paymentConcept->description ?? '-' }}</td>
                                    <td>{{ $detail->paymentMethod->name ?? ($detail->payment_method ?? 'Otros') }}</td>
                                    <td class="text-right">S/ {{ number_format((float) $detail->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        @endif
                    @endforeach
                    @if($cashRows === 0)
                        <tr><td colspan="4" class="text-center muted">Sin movimientos operativos registrados.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    @endif

    @if(!empty($opts['products_sold_summary']))
        <div class="section">
            <h2>Consolidado de productos vendidos</h2>
            <table>
                <thead><tr><th>Producto</th><th class="text-right">Cantidad</th><th class="text-right">Importe</th></tr></thead>
                <tbody>
                    @forelse($r['products_sold'] ?? [] as $row)
                        <tr>
                            <td>{{ $row['product'] }}</td>
                            <td class="text-right">{{ number_format($row['qty'], 2) }}</td>
                            <td class="text-right">S/ {{ number_format($row['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center muted">Sin productos en ventas del turno.</td></tr>
                    @endforelse
                    @if(!empty($r['products_sold']))
                        <tr>
                            <th>Total</th>
                            <th class="text-right">{{ number_format((float) ($r['products_sold_totals']['qty'] ?? 0), 2) }}</th>
                            <th class="text-right">S/ {{ number_format((float) ($r['products_sold_totals']['amount'] ?? 0), 2) }}</th>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    @endif

    @if(!empty($opts['sales_details_by_product']))
        <div class="section">
            <h2>Detalles de venta — por producto</h2>
            <table>
                <thead>
                    <tr><th>Ticket</th><th>Cliente</th><th>Producto</th><th class="text-right">Cant.</th><th class="text-right">Importe</th></tr>
                </thead>
                <tbody>
                    @forelse($r['sales_details'] ?? [] as $row)
                        <tr>
                            <td>{{ $row['ticket'] }}</td>
                            <td>{{ $row['person'] }}</td>
                            <td>{{ $row['product'] }}</td>
                            <td class="text-right">{{ number_format($row['qty'], 2) }}</td>
                            <td class="text-right">S/ {{ number_format($row['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center muted">Sin líneas.</td></tr>
                    @endforelse
                    @if(!empty($r['sales_details']))
                        <tr>
                            <th colspan="3">Total</th>
                            <th class="text-right">{{ number_format((float) ($r['sales_details_totals']['qty'] ?? 0), 2) }}</th>
                            <th class="text-right">S/ {{ number_format((float) ($r['sales_details_totals']['amount'] ?? 0), 2) }}</th>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    @endif

    @if(!empty($opts['discounts_by_product']))
        <div class="section">
            <h2>Descuentos por producto</h2>
            <table>
                <thead>
                    <tr><th>Ticket</th><th>Producto</th><th class="text-right">Dto. %</th><th class="text-right">Importe descuento</th><th class="text-right">Importe línea (neto)</th></tr>
                </thead>
                <tbody>
                    @forelse($r['discounts_by_product_rows'] ?? [] as $row)
                        <tr>
                            <td>{{ $row['ticket'] }}</td>
                            <td>{{ $row['product'] }}</td>
                            <td class="text-right">{{ number_format($row['discount_pct'], 2) }}</td>
                            <td class="text-right">S/ {{ number_format((float) ($row['discount_amount'] ?? 0), 2) }}</td>
                            <td class="text-right">S/ {{ number_format($row['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center muted">Sin líneas con descuento.</td></tr>
                    @endforelse
                    @if(!empty($r['discounts_by_product_rows']))
                        <tr>
                            <th colspan="3">Total descuentos aplicados</th>
                            <th class="text-right">S/ {{ number_format((float) ($r['discounts_by_product_total'] ?? 0), 2) }}</th>
                            <td class="text-right muted">—</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    @endif

    @if(!empty($opts['discounts_by_person']))
        <div class="section">
            <h2>Descuentos — por persona</h2>
            <table>
                <thead><tr><th>Cliente / persona</th><th class="text-right">Descuento estimado</th></tr></thead>
                <tbody>
                    @forelse($r['discounts_by_person_rows'] ?? [] as $row)
                        <tr><td>{{ $row['person'] }}</td><td class="text-right">S/ {{ number_format($row['amount'], 2) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-center muted">Sin descuentos agrupados.</td></tr>
                    @endforelse
                    @if(!empty($r['discounts_by_person_rows']))
                        <tr><th>Total</th><th class="text-right">S/ {{ number_format((float) ($r['discounts_by_person_total'] ?? 0), 2) }}</th></tr>
                    @endif
                </tbody>
            </table>
        </div>
    @endif

    @if(!empty($opts['courtesies']))
        <div class="section">
            <h2>Cortesías</h2>
            <table>
                <thead>
                    <tr><th>Ticket</th><th>Producto</th><th class="text-right">Cant. cortesía</th><th class="text-right">Importe línea</th></tr>
                </thead>
                <tbody>
                    @forelse($r['courtesy_rows'] ?? [] as $row)
                        <tr>
                            <td>{{ $row['ticket'] }}</td>
                            <td>{{ $row['product'] }}</td>
                            <td class="text-right">{{ number_format($row['courtesy_qty'], 2) }}</td>
                            <td class="text-right">S/ {{ number_format($row['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center muted">Sin cortesías.</td></tr>
                    @endforelse
                    @if(!empty($r['courtesy_rows']))
                        <tr>
                            <th colspan="2">Total</th>
                            <th class="text-right">{{ number_format((float) ($r['courtesy_totals']['qty'] ?? 0), 2) }}</th>
                            <th class="text-right">S/ {{ number_format((float) ($r['courtesy_totals']['amount'] ?? 0), 2) }}</th>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    @endif

    @if(!empty($opts['debts_sales_summary']))
        <div class="section">
            <h2>Resúmenes — ventas adeudadas</h2>
            <table>
                <tr><th>Documentos a crédito (en período)</th><td class="text-right">{{ $r['debts_summary']['count'] ?? 0 }}</td></tr>
                <tr><th>Importe total adeudado</th><td class="text-right">S/ {{ number_format((float) ($r['debts_summary']['total'] ?? 0), 2) }}</td></tr>
            </table>
        </div>
    @endif

    @if(!empty($opts['debts_sales']))
        <div class="section">
            <h2>Ventas adeudadas (detalle)</h2>
            <table>
                <thead><tr><th>Documento</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                    @forelse($r['debts_sales'] ?? [] as $sm)
                        <tr>
                            <td>{{ $sm->movement?->number ?? ('#'.$sm->id) }}</td>
                            <td class="text-right">S/ {{ number_format((float) $sm->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-center muted">Sin ventas a crédito en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if(!empty($opts['sales_cancellations']))
        <div class="section">
            <h2>Anulaciones de ventas</h2>
            <p class="muted">Comprobantes anulados en el período: {{ $r['trashed_sales_totals']['count'] ?? 0 }} · Total: S/ {{ number_format((float) ($r['trashed_sales_totals']['total'] ?? 0), 2) }}</p>
            <table>
                <thead><tr><th>Ticket / id</th><th>Anulado el</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                    @forelse($r['trashed_sales'] ?? [] as $sm)
                        <tr>
                            <td>{{ $sm->movement?->number ?? ('#'.$sm->id) }}</td>
                            <td>{{ $sm->deleted_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            <td class="text-right">S/ {{ number_format((float) $sm->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center muted">Sin ventas anuladas en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if(!empty($opts['cancellations_history']))
        <div class="section">
            <h2>Histórico de anulaciones</h2>
            <table>
                <thead><tr><th>Fecha anulación</th><th>Documento</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                    @forelse($r['trashed_sales'] ?? [] as $sm)
                        <tr>
                            <td>{{ $sm->deleted_at?->format('d/m/Y H:i:s') ?? '-' }}</td>
                            <td>{{ $sm->movement?->number ?? ('#'.$sm->id) }}</td>
                            <td class="text-right">S/ {{ number_format((float) $sm->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center muted">Sin registros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if(!empty($opts['cancellations_products']))
        <div class="section">
            <h2>Anulaciones de productos</h2>
            <p class="muted">Consolidado por producto en comprobantes anulados en el período.</p>
            <table>
                <thead><tr><th>Producto</th><th class="text-right">Cant.</th><th class="text-right">Importe</th></tr></thead>
                <tbody>
                    @forelse($r['cancellation_products_consolidated'] ?? [] as $row)
                        <tr>
                            <td>{{ $row['product'] }}</td>
                            <td class="text-right">{{ number_format($row['qty'], 2) }}</td>
                            <td class="text-right">S/ {{ number_format($row['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center muted">Sin líneas en comprobantes anulados.</td></tr>
                    @endforelse
                    @if(!empty($r['cancellation_products_consolidated']))
                        <tr>
                            <th>Total</th>
                            <th class="text-right">{{ number_format((float) ($r['cancellation_products_totals']['qty'] ?? 0), 2) }}</th>
                            <th class="text-right">S/ {{ number_format((float) ($r['cancellation_products_totals']['amount'] ?? 0), 2) }}</th>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    @endif

    <div class="section">
        <h2>Balance final</h2>
        <table>
            <tr>
                <th>Balance operativo (Ingresos − Egresos)</th>
                <td class="text-right">S/ {{ number_format($neto, 2) }}</td>
            </tr>
            <tr>
                <th>Observaciones</th>
                <td>{{ $movCierre?->comment ?? '-' }}</td>
            </tr>
        </table>
    </div>

    @if(($autoPrint ?? false) === true)
        <script>
            window.addEventListener('load', function () {
                window.print();
            });
        </script>
    @endif
</body>
</html>
