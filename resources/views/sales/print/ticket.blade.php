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

        .company {
            margin: 0;
            font-size: 6.2mm;
            font-weight: 800;
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
            font-weight: 800;
            line-height: 1.05;
        }

        .separator {
            border-top: 0.3mm dashed #7ea1d4;
            margin: 1.8mm 0;
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
            font-weight: 800;
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
            font-weight: 800;
            border-bottom: 0.2mm solid #bdbdbd;
        }

        .items-table td {
            vertical-align: top;
        }

        .col-product {
            width: 38%;
            text-align: left;
            padding-left: 0.35mm;
            padding-right: 0.35mm;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .col-qty {
            width: 13%;
            text-align: right;
            padding-right: 0.35mm;
        }

        .col-unit {
            width: 21%;
            text-align: right;
            padding-right: 0.2mm;
        }

        .col-subtotal {
            width: 28%;
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
            font-weight: 800;
            padding-right: 1mm;
        }

        .totals-value {
            width: 48%;
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .grand-total td {
            border-top: 0.25mm solid #7ea1d4;
            padding-top: 1.1mm;
            font-size: 4.1mm;
            font-weight: 800;
        }

        /* Rollo 58 mm: menos ancho útil; tipografía y columnas más compactas */
        body.ticket-paper-58 .ticket {
            padding: 1.8mm 1mm 2.5mm;
        }

        body.ticket-paper-58 .items-table th,
        body.ticket-paper-58 .items-table td {
            font-size: 2.45mm;
            padding: 0.35mm 0.1mm;
        }

        body.ticket-paper-58 .col-qty {
            width: 12%;
        }

        body.ticket-paper-58 .col-product {
            width: 36%;
            padding-left: 0.2mm;
            padding-right: 0.2mm;
        }

        body.ticket-paper-58 .col-unit {
            width: 22%;
        }

        body.ticket-paper-58 .col-subtotal {
            width: 30%;
        }

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

        .notes {
            font-size: 3mm;
            line-height: 1.15;
        }

        .notes strong {
            font-weight: 800;
        }

        .qr-wrap {
            text-align: center;
            margin-top: 1.6mm;
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
<body class="ticket-paper-{{ (int) ($ticketPageWidthMm ?? 80) === 58 ? '58' : '80' }}">
@php
    $docName = strtoupper($sale->documentType?->name ?? 'TICKET DE VENTA');
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
                <img src="{{ $logoFileUrl ?: $logoUrl }}" alt="Logo sucursal" class="logo">
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
            <td class="info-label">Dir. cliente:</td>
            <td class="info-value">{{ $ticketAddressDisplay ?? '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">RUC/DNI:</td>
            <td class="info-value">{{ $customerDocument }}</td>
        </tr>
        <tr>
            <td class="info-label">Forma pago:</td>
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

    <table class="items-table">
        <thead>
        <tr>
            <th class="col-qty">CANT.</th>
            <th class="col-product">DESCRIPCION</th>
            <th class="col-unit">PRECIO</th>
            <th class="col-subtotal">IMPORTE</th>
        </tr>
        </thead>
        <tbody>
        @foreach($details as $detail)
            @php
                $qty = (float) $detail->quantity;
                $lineTotal = (float) $detail->amount;
                $unitPrice = $qty > 0 ? ($lineTotal / $qty) : 0;
            @endphp
            <tr>
                <td class="col-qty">{{ number_format($qty, 2) }}</td>
                <td class="col-product">{{ $detail->description ?? $detail->product?->description ?? '-' }}</td>
                <td class="col-unit">{{ number_format($unitPrice, 2) }}</td>
                <td class="col-subtotal">{{ number_format($lineTotal, 2) }}</td>
            </tr>
        @endforeach
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

    @if($sale->comment)
        <div class="separator"></div>
        <div class="notes"><strong>Notas:</strong> {{ $sale->comment }}</div>
    @endif

    @if(!empty($qrImageUrl))
        <div class="separator"></div>
        <div class="qr-wrap">
            <img src="{{ $qrImageUrl }}" alt="QR del comprobante">
        </div>
    @endif

    <div class="separator"></div>

    <div class="footer">
        Impreso: {{ $printedAt->format('d/m/Y H:i:s') }}<br>
        <div class="thanks">Gracias por su preferencia</div>
    </div>
</div>

@if(($autoPrint ?? true) === true)
<script>
    window.addEventListener('load', function () { window.print(); });
</script>
@endif
</body>
</html>
