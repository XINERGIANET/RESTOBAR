<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket {{ $sale->number }}</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
        }

        @page {
            size: {{ (int) ($ticketPageWidthMm ?? 80) }}mm auto;
            margin: 0;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: 100%;
            background: #fff;
        }

        body {
            font-size: 10.5px;
            line-height: 1.18;
        }

        .ticket {
            width: 90%;
            max-width: 90%;
            padding: 2.2mm 1.2mm 3mm;
            margin-left: 0;
            margin-right: auto;
            overflow: visible;
        }

        /* La ticketera tiene menos ancho imprimible que el rollo: desplaza el
           contenido a la izquierda y reserva 2 mm extra en el borde derecho. */
        body.thermal-print .ticket {
            width: calc(90% - 2mm);
            max-width: calc(90% - 2mm);
            padding-left: 0;
            padding-right: 2mm;
        }

        /* Respaldo visual si el servidor no dispone temporalmente del generador PDF. */
        @media screen {
            body {
                width: {{ (int) ($ticketPageWidthMm ?? 80) }}mm;
                max-width: {{ (int) ($ticketPageWidthMm ?? 80) }}mm;
            }
        }

        .center {
            text-align: center;
        }

        .logo-wrap {
            margin-bottom: 1.5mm;
        }

        .logo {
            display: block;
            max-width: 38mm;
            max-height: 16mm;
            margin: 0 auto;
            object-fit: contain;
        }

        @media print {
            .logo {
                -webkit-filter: grayscale(1) brightness(0);
                filter: grayscale(1) brightness(0);
                opacity: 1;
            }
        }

        .company {
            margin: 0;
            font-size: 6.2mm;
            font-weight: 700;
            line-height: 1;
        }

        .subhead {
            margin: 0.4mm 0 0;
            font-size: 3.8mm;
            line-height: 1.05;
        }

        .doc-code {
            margin: 1.2mm 0 0;
            font-size: 4.9mm;
            font-weight: 700;
            line-height: 1.05;
        }

        .separator {
            margin: 1.8mm 0;
            height: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .info-table td {
            padding: 0.2mm 0;
            vertical-align: top;
            font-size: 3.05mm;
            line-height: 1.08;
        }

        .info-label {
            width: 21mm;
            font-weight: 700;
            padding-right: 1mm;
            white-space: nowrap;
        }

        .info-value {
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .items-table th,
        .items-table td {
            padding: 0.45mm 0.15mm;
            font-size: 2.85mm;
        }

        .items-table th {
            font-weight: 700;
        }

        .items-table th strong {
            font-family: Arial, Helvetica, sans-serif;
            font-weight: 700;
        }

        .items-table td {
            vertical-align: top;
        }

        .item-description td {
            padding-top: 0.9mm;
            padding-bottom: 0.25mm;
            text-align: left;
            font-weight: 700;
            word-break: normal;
            overflow-wrap: anywhere;
        }

        .item-values td {
            padding-top: 0.2mm;
            padding-bottom: 0.7mm;
        }

        .item-values .col-qty {
            text-align: center;
        }

        .col-product {
            width: 44%;
            text-align: left;
            padding-left: 0.35mm;
            padding-right: 0.35mm;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .col-qty {
            width: 10%;
            text-align: left;
            padding-left: 0;
            padding-right: 0.35mm;
        }

        .col-unit {
            width: 20%;
            text-align: right;
            padding-right: 0.2mm;
        }

        .col-measure {
            text-align: center;
            white-space: nowrap;
            word-break: normal;
        }

        .items-table.has-measure-column .col-product { width: 18%; }
        .items-table.has-measure-column .col-qty { width: 12%; }
        .items-table.has-measure-column .col-measure { width: 28%; }
        .items-table.has-measure-column .col-unit { width: 20%; }
        .items-table.has-measure-column .col-subtotal { width: 22%; }

        .col-subtotal {
            width: 26%;
            text-align: right;
            padding-right: 0;
        }

        .totals-table {
            table-layout: fixed;
            width: 100%;
        }

        .totals-table td {
            padding: 0.45mm 0;
            font-size: 3.05mm;
            vertical-align: top;
        }

        .totals-label {
            width: 52%;
            font-weight: 700;
            padding-right: 1mm;
        }

        .totals-value {
            width: 48%;
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .grand-total td {
            padding-top: 1.1mm;
            font-size: 4.1mm;
            font-weight: 700;
        }

        .amount-in-words {
            margin-top: 1.2mm;
            line-height: 1.2;
        }

        .amount-in-words strong {
            font-weight: 700;
        }

        /* Rollo 58 mm: menos ancho útil; tipografía y columnas más compactas */
        body.ticket-paper-58 .ticket {
            padding: 1.8mm 1mm 2.5mm;
        }

        body.ticket-paper-58.thermal-print .ticket {
            padding-left: 0;
            padding-right: 2mm;
        }

        body.ticket-paper-58 .items-table th,
        body.ticket-paper-58 .items-table td {
            font-size: 2.45mm;
            padding: 0.35mm 0.1mm;
        }

        body.ticket-paper-58 .col-qty {
            width: 9%;
        }

        body.ticket-paper-58 .col-product {
            width: 42%;
            padding-left: 0.35mm;
            padding-right: 0.2mm;
        }

        body.ticket-paper-58 .col-qty {
            padding-right: 0.35mm;
        }

        body.ticket-paper-58 .col-unit {
            width: 21%;
        }

        body.ticket-paper-58 .col-subtotal {
            width: 28%;
        }

        body.ticket-paper-58 .items-table.has-measure-column .col-product { width: 14%; }
        body.ticket-paper-58 .items-table.has-measure-column .col-qty { width: 12%; }
        body.ticket-paper-58 .items-table.has-measure-column .col-measure { width: 30%; }
        body.ticket-paper-58 .items-table.has-measure-column .col-unit { width: 20%; }
        body.ticket-paper-58 .items-table.has-measure-column .col-subtotal { width: 24%; }

        body.ticket-paper-58 .info-label {
            width: 17mm;
            font-size: 2.75mm;
        }

        body.ticket-paper-58 .info-table td {
            font-size: 2.65mm;
        }

        body.ticket-paper-58 .grand-total td {
            font-size: 3.5mm;
        }

        body.ticket-paper-58 .company {
            font-size: 5.2mm;
        }

        body.ticket-paper-58 .doc-code {
            font-size: 4.2mm;
        }

        /* Tipografía optimizada para la salida física del botón verde. */
        body.ticket-paper-80.thermal-print .company { font-size: 6.7mm; }
        body.ticket-paper-80.thermal-print .subhead { font-size: 4.15mm; }
        body.ticket-paper-80.thermal-print .doc-code { font-size: 5.3mm; }
        body.ticket-paper-80.thermal-print .info-table td,
        body.ticket-paper-80.thermal-print .totals-table td { font-size: 3.4mm; }
        body.ticket-paper-80.thermal-print .items-table th { font-size: 3.2mm; }
        body.ticket-paper-80.thermal-print .items-table td {
            font-size: 3.55mm;
            line-height: 1.18;
        }
        body.ticket-paper-80.thermal-print .grand-total td { font-size: 4.5mm; }

        body.ticket-paper-58.thermal-print .info-table td { font-size: 2.9mm; }
        body.ticket-paper-58.thermal-print .items-table th { font-size: 2.7mm; }
        body.ticket-paper-58.thermal-print .items-table td {
            font-size: 2.95mm;
            line-height: 1.16;
        }
        body.ticket-paper-58.thermal-print .grand-total td { font-size: 3.8mm; }

        body.thermal-print .notes { font-size: 3.25mm; }
        body.thermal-print .footer { font-size: 3mm; }
        body.thermal-print .thanks { font-size: 3.25mm; }
        .dash-row td {
            height: 3mm;
            padding: 0;
            overflow: hidden;
            white-space: nowrap;
            font-family: "Courier New", monospace;
            font-size: 3mm;
            font-weight: 700;
            line-height: 3mm;
        }

        body.thermal-print .company,
        body.thermal-print .subhead,
        body.thermal-print .doc-code,
        body.thermal-print .sunat-warning,
        body.thermal-print .info-label,
        body.thermal-print .items-table th,
        body.thermal-print .totals-label,
        body.thermal-print .grand-total td,
        body.thermal-print .notes strong {
            font-family: Arial, Helvetica, sans-serif;
            font-weight: 700 !important;
        }

        /* En la impresión física todo el contenido usa una escala uniforme. */
        body.ticket-paper-80.thermal-print .ticket,
        body.ticket-paper-80.thermal-print .ticket p,
        body.ticket-paper-80.thermal-print .ticket th,
        body.ticket-paper-80.thermal-print .ticket td,
        body.ticket-paper-80.thermal-print .notes,
        body.ticket-paper-80.thermal-print .footer,
        body.ticket-paper-80.thermal-print .thanks,
        body.ticket-paper-80.thermal-print .sunat-warning {
            font-size: 3.4mm;
            line-height: 1.15;
        }

        body.ticket-paper-58.thermal-print .ticket,
        body.ticket-paper-58.thermal-print .ticket p,
        body.ticket-paper-58.thermal-print .ticket th,
        body.ticket-paper-58.thermal-print .ticket td,
        body.ticket-paper-58.thermal-print .notes,
        body.ticket-paper-58.thermal-print .footer,
        body.ticket-paper-58.thermal-print .thanks,
        body.ticket-paper-58.thermal-print .sunat-warning {
            font-size: 2.9mm;
            line-height: 1.15;
        }

        .sunat-warning {
            margin: 1.4mm 0 0;
            text-align: center;
            font-size: 3.2mm;
            font-weight: 700;
            line-height: 1.15;
        }

        .notes {
            font-size: 3mm;
            line-height: 1.15;
        }

        .notes strong {
            font-weight: 700;
        }

        .qr-wrap {
            text-align: center;
            margin-top: 1.6mm;
        }

        .qr-dash {
            height: 3mm;
            overflow: hidden;
            white-space: nowrap;
            font-family: "Courier New", monospace;
            font-size: 3mm;
            font-weight: 700;
            line-height: 3mm;
        }

        .ticket-footer-meta {
            margin-top: 1.2mm;
            text-align: left;
            line-height: 1.25;
        }

        .ticket-footer-meta strong {
            font-weight: 700;
        }

        .ticket-footer-condition {
            display: flex;
            justify-content: space-between;
            gap: 2mm;
        }

        .qr-wrap img {
            width: 24mm;
            height: 24mm;
            object-fit: contain;
        }

        .footer {
            text-align: center;
            font-size: 2.7mm;
            line-height: 1.15;
        }

        .thanks {
            margin-top: 0.6mm;
            font-size: 3mm;
        }
    </style>
</head>
<body class="ticket-paper-{{ (int) ($ticketPageWidthMm ?? 80) === 58 ? '58' : '80' }}{{ !empty($thermalPrint) ? ' thermal-print' : '' }}">
@php
    $docName = strtoupper($sale->documentType?->name ?? 'TICKET DE VENTA');
    $documentNameLower = mb_strtolower($docName, 'UTF-8');
    $isSaleTicket = str_contains($documentNameLower, 'ticket');
    $showUnitColumn = str_contains($documentNameLower, 'boleta') || str_contains($documentNameLower, 'factura');
    $ticketSeries = $sale->salesMovement?->series ?? '001';
    if (!empty($sale->electronic_invoice_series) && preg_match('/^[A-Z]+(\d+)$/i', (string) $sale->electronic_invoice_series, $seriesMatches) === 1) {
        $ticketSeries = $seriesMatches[1];
    }
    $docCode = strtoupper(substr($sale->documentType?->name ?? 'T', 0, 1)) . $ticketSeries . '-' . $sale->number;
    $ticketSubtotal = (float) ($sale->salesMovement?->subtotal ?? $sale->orderMovement?->subtotal ?? 0);
    $ticketTax = (float) ($sale->salesMovement?->tax ?? $sale->orderMovement?->tax ?? 0);
    $ticketTotal = (float) ($sale->salesMovement?->total ?? $sale->orderMovement?->total ?? 0);
    $customerName = trim((string) ($sale->person_name ?? ''));
    $customerLower = mb_strtolower($customerName, 'UTF-8');
    $customerDocument = trim((string) ($sale->person?->document_number ?? ''));
    if ($customerName === '' || $customerLower === 'sin cliente') {
        $customerName = 'CLIENTES VARIOS';
    }
    if ($customerDocument === '' || $customerDocument === '-') {
        $customerDocument = '0';
    }
@endphp

<div class="ticket">
    <div class="center">
        @if(!empty($logoFileUrl) || !empty($logoUrl))
            <div class="logo-wrap">
                <img src="{{ !empty($useEmbeddedAssets) && !empty($logoEmbeddedUrl) ? $logoEmbeddedUrl : ((!empty($autoPrint) || !empty($usePublicAssets)) ? $logoUrl : ($logoFileUrl ?: $logoUrl)) }}" alt="Logo sucursal" class="logo">
            </div>
        @endif
        <p class="company">{{ strtoupper($branchForLogo->legal_name ?? 'SUCURSAL') }}</p>
        <p class="subhead">RUC: {{ $branchForLogo->ruc ?? '-' }}</p>
        @if(!empty(trim((string) ($branchForLogo->address ?? ''))))
            <p class="subhead">Suc.: {{ trim((string) $branchForLogo->address) }}</p>
        @endif
        <p class="subhead">{{ $docName }}</p>
        <p class="doc-code">{{ $docCode }}</p>
    </div>

    <div class="separator"></div>

    <table class="info-table">
        <tr>
            <td class="info-label">Fecha:</td>
            <td class="info-value">{{ optional($sale->moved_at)->format('d/m/Y H:i') ?? '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">Cliente:</td>
            <td class="info-value">{{ $customerName }}</td>
        </tr>
        <tr>
            <td class="info-label">Dirección:</td>
            <td class="info-value">{{ $ticketAddressDisplay ?? '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">RUC/DNI:</td>
            <td class="info-value">{{ $customerDocument }}</td>
        </tr>
        <tr>
            <td class="info-label">F. pago:</td>
            <td class="info-value">{{ $paymentLabel }}</td>
        </tr>
        @if (($sale->orderMovement?->payment_type ?? '') === 'CREDITO' || in_array((string) ($sale->salesMovement?->payment_type ?? ''), ['CREDIT', 'CREDITO'], true))
            <tr>
                <td class="info-label">Nota:</td>
                <td class="info-value">Venta a crédito; el saldo queda pendiente de cobro.</td>
            </tr>
        @endif
    </table>

    <div class="separator"></div>

    <table class="items-table{{ $showUnitColumn ? ' has-measure-column' : '' }}">
        <thead>
        <tr class="dash-row">
            <th colspan="{{ $showUnitColumn ? 5 : 4 }}">------------------------------------------------------------</th>
        </tr>
        <tr>
            <th class="col-product"><strong>Prod.</strong></th>
            <th class="col-qty"><strong>Cant.</strong></th>
            @if($showUnitColumn)
                <th class="col-measure"><strong>Unidad</strong></th>
            @endif
            <th class="col-unit"><strong>P.Unit.</strong></th>
            <th class="col-subtotal"><strong>Subt.</strong></th>
        </tr>
        <tr class="dash-row">
            <th colspan="{{ $showUnitColumn ? 5 : 4 }}">------------------------------------------------------------</th>
        </tr>
        </thead>
        <tbody>
        @foreach($details as $detail)
            @php
                $qty = (float) $detail->quantity;
                $lineTotal = (float) $detail->amount;
                $unitPrice = $qty > 0 ? ($lineTotal / $qty) : 0;
            @endphp
            <tr class="item-description">
                <td colspan="{{ $showUnitColumn ? 5 : 4 }}">{{ $detail->description ?? $detail->product?->description ?? '-' }}</td>
            </tr>
            <tr class="item-values">
                <td class="col-product"></td>
                <td class="col-qty">{{ number_format($qty, 2) }}</td>
                @if($showUnitColumn)
                    <td class="col-measure">{{ $detail->unit?->description ?: '-' }}</td>
                @endif
                <td class="col-unit">{{ number_format($unitPrice, 2) }}</td>
                <td class="col-subtotal">{{ number_format($lineTotal, 2) }}</td>
            </tr>
        @endforeach
            <tr class="dash-row"><td colspan="{{ $showUnitColumn ? 5 : 4 }}">------------------------------------------------------------</td></tr>
        </tbody>
    </table>

    <div class="separator"></div>

    <table class="totals-table">
        <tr>
            <td class="totals-label">Op. gravada:</td>
            <td class="totals-value">S/ {{ number_format($ticketSubtotal, 2) }}</td>
        </tr>
        <tr>
            <td class="totals-label">IGV:</td>
            <td class="totals-value">S/ {{ number_format($ticketTax, 2) }}</td>
        </tr>
        <tr class="grand-total">
            <td class="totals-label">TOTAL:</td>
            <td class="totals-value">S/ {{ number_format($ticketTotal, 2) }}</td>
        </tr>
    </table>

    @if($showUnitColumn)
        <div class="amount-in-words"><strong>SON:</strong> {{ $totalInWords }}</div>
    @endif

    @if($sale->comment)
        <div class="separator"></div>
        <div class="notes"><strong>Notas:</strong> {{ $sale->comment }}</div>
    @endif

    @if(!empty($qrImageUrl))
        <div class="separator"></div>
        <div class="qr-dash">------------------------------------------------------------</div>
        <div class="qr-wrap">
            <img src="{{ $qrImageUrl }}" alt="QR del comprobante">
        </div>
        <div class="qr-dash">------------------------------------------------------------</div>
        @if(!empty($ticketFooterMeta))
            <div class="ticket-footer-meta">
                <div><strong>Pedido:</strong> {{ $ticketFooterMeta['order_number'] }}</div>
                <div><strong>Mostrador:</strong> {{ $ticketFooterMeta['location'] }}</div>
                <div><strong>Responsable:</strong> {{ $ticketFooterMeta['responsible'] }}</div>
                <div><strong>Caja:</strong> {{ $ticketFooterMeta['cash_register'] }}</div>
                <div><strong>Forma de pago:</strong></div>
                @forelse($ticketFooterMeta['payment_lines'] as $paymentLine)
                    <div>{{ $paymentLine }}</div>
                @empty
                    <div>{{ $paymentLabel }}</div>
                @endforelse
                <div class="ticket-footer-condition">
                    <span>{{ $ticketFooterMeta['condition'] }}</span>
                    <span><strong>Hora:</strong> {{ $ticketFooterMeta['time'] }}</span>
                </div>
            </div>
        @endif
    @endif

    <div class="separator"></div>

    <div class="footer">
        Impreso: {{ $printedAt->format('d/m/Y H:i:s') }}<br>
        <div class="thanks">Gracias por su preferencia</div>
        @if($isSaleTicket)
            <p class="sunat-warning">Este documento no constituye un Comprobante de Pago válido para efectos tributarios conforme a la normativa de SUNAT.</p>
        @endif
    </div>
</div>

@if(($autoPrint ?? true) === true)
<script>
    window.addEventListener('load', function () { window.print(); });
</script>
@endif
</body>
</html>
