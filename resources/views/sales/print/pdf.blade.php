<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante {{ $sale->number }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; }
        .logo { max-height: 96px; max-width: 240px; object-fit: contain; }
        .doc-box { border: 1px solid #334155; padding: 16px; min-width: 300px; text-align: center; }
        .doc-box h1 { margin: 0; font-size: 30px; }
        .doc-box p { margin: 4px 0; font-size: 18px; }
        
        /* --- ESTILOS EXCLUSIVOS PARA LA TABLA DE METADATOS (Cliente, Fecha, etc) --- */
        .table-meta { width: 100%; border-collapse: collapse; margin-top: 18px; }
        .table-meta td { padding: 4px 0; font-size: 15px; border: none; vertical-align: top; }
        .table-meta .label { width: 160px; font-weight: 700; }

        /* --- ESTILOS EXCLUSIVOS PARA LA TABLA DE PRODUCTOS --- */
        .table-items { width: 100%; border-collapse: collapse; margin-top: 18px; }
        .table-items th, .table-items td { border: 1px solid #cbd5e1; padding: 8px; font-size: 13px; }
        .table-items th { background: #0f172a; color: #fff; text-align: left; }
        
        .num { text-align: right; }
        .totals { margin-top: 18px; width: 360px; margin-left: auto; }
        .totals div { display: flex; justify-content: space-between; padding: 3px 0; }
        .totals .final { border-top: 2px solid #111827; margin-top: 6px; padding-top: 6px; font-weight: 700; font-size: 20px; }
        .notes { margin-top: 20px; }
        .notes p { margin: 6px 0 0; }
        .qr-box { margin-top: 18px; display: flex; justify-content: flex-end; }
        .qr-box-inner { width: 180px; text-align: center; }
        .qr-box img { width: 170px; height: 170px; object-fit: contain; display: block; margin: 0 auto; }
        .qr-caption { margin-top: 6px; font-size: 10px; color: #475569; word-break: break-all; }
        @media print {
            body { margin: 10mm; }
        }
    </style>
</head>
<body>
@php
    $docName = strtoupper($sale->documentType?->name ?? 'COMPROBANTE');
    $ticketSeries = $sale->salesMovement?->series ?? '001';
    if (!empty($sale->electronic_invoice_series) && preg_match('/^[A-Z]+(\d+)$/i', (string) $sale->electronic_invoice_series, $seriesMatches) === 1) {
        $ticketSeries = $seriesMatches[1];
    }
    $docCode = strtoupper(substr($sale->documentType?->name ?? 'X', 0, 1)) . $ticketSeries . '-' . $sale->number;
    $ticketSubtotal = (float) ($sale->salesMovement?->subtotal ?? $sale->orderMovement?->subtotal ?? 0);
    $ticketTax = (float) ($sale->salesMovement?->tax ?? $sale->orderMovement?->tax ?? 0);
    $ticketTotal = (float) ($sale->salesMovement?->total ?? $sale->orderMovement?->total ?? 0);
    $ticketCurrency = $sale->salesMovement?->currency ?? 'PEN';
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

<div class="head">
    <div>
        @if(!empty($logoFileUrl) || !empty($logoUrl))
            <img src="{{ $logoFileUrl ?: $logoUrl }}" alt="Logo sucursal" class="logo">
        @endif
        <h3 style="margin:12px 0 2px;">{{ strtoupper($branchForLogo->legal_name ?? 'SUCURSAL') }}</h3>
        <p style="margin:0;">RUC: {{ $branchForLogo->ruc ?? '-' }}</p>
        @if(!empty(trim((string) ($branchForLogo->address ?? ''))))
            <p style="margin:4px 0 0;">Suc.: {{ trim((string) $branchForLogo->address) }}</p>
        @endif
    </div>
    <div class="doc-box">
        <h1>{{ $docName }}</h1>
        <p>{{ $docCode }}</p>
    </div>
</div>

<table class="table-meta">
    <tr>
        <td class="label">Fecha de emision:</td>
        <td>{{ optional($sale->moved_at)->format('d/m/Y H:i') ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Cliente:</td>
        <td>{{ $customerName }}</td>
    </tr>
    <tr>
        <td class="label">RUC/DNI:</td>
        <td>{{ $customerDocument }}</td>
    </tr>
    <tr>
        <td class="label">Direccion cliente:</td>
        <td>{{ $sale->person?->address ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Moneda:</td>
        <td>{{ $ticketCurrency }}</td>
    </tr>
    <tr>
        <td class="label">Forma de pago:</td>
        <td>{{ $paymentLabel }}</td>
    </tr>
</table>

<table class="table-items">
    <thead>
    <tr>
        <th style="width:50px;">Item</th>
        <th>Descripcion</th>
        <th style="width:60px;">U.M.</th>
        <th style="width:80px;" class="num">Cantidad</th>
        <th style="width:90px;" class="num">P.Unit</th>
        <th style="width:100px;" class="num">Subtotal</th>
    </tr>
    </thead>
    <tbody>
    @forelse($details as $i => $detail)
        @php
            $qty = (float) $detail->quantity;
            $lineTotal = (float) $detail->amount;
            $unitPrice = $qty > 0 ? ($lineTotal / $qty) : 0;
        @endphp
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $detail->description ?? $detail->product?->description ?? '-' }}</td>
            <td>{{ $detail->unit?->code ?? $detail->unit?->description ?? '-' }}</td>
            <td class="num">{{ number_format($qty, 2) }}</td>
            <td class="num">S/ {{ number_format($unitPrice, 2) }}</td>
            <td class="num">S/ {{ number_format($lineTotal, 2) }}</td>
        </tr>
    @empty
        <tr><td colspan="6">Sin detalle</td></tr>
    @endforelse
    </tbody>
</table>

<div class="totals">
    <div><span>Op. gravada:</span><span>S/ {{ number_format($ticketSubtotal, 2) }}</span></div>
    <div><span>I.G.V.:</span><span>S/ {{ number_format($ticketTax, 2) }}</span></div>
    <div class="final"><span>Importe total:</span><span>S/ {{ number_format($ticketTotal, 2) }}</span></div>
</div>

@if(!empty($qrImageUrl))
    <div class="qr-box">
        <div class="qr-box-inner">
            <img src="{{ $qrImageUrl }}" alt="QR del comprobante">
        </div>
    </div>
@endif

<div class="notes">
    <b>Observacion:</b>
    <p>{{ $sale->comment ?: '-' }}</p>
    <p style="margin-top:14px; color:#475569;">Impreso el {{ $printedAt->format('d/m/Y H:i:s') }}</p>
</div>

@if(($autoPrint ?? true) === true)
<script>
    window.addEventListener('load', function () { window.print(); });
</script>
@endif
</body>
</html>
