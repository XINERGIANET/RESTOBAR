<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Ventas</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; margin: 0; } /* Letra pequeña para que entre todo */
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; text-transform: uppercase; }
        .header p { margin: 2px 0; color: #555; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 5px; text-align: left; vertical-align: top; }
        
        /* Encabezados con fondo gris */
        th { background-color: #f0f0f0; font-weight: bold; font-size: 9px; text-transform: uppercase; }
        
        /* Columnas de montos alineadas a la derecha */
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        
        /* Ajuste para columnas estrechas */
        .w-min { width: 1%; white-space: nowrap; }
        .filters-box { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 8px 12px; margin-bottom: 12px; font-size: 9px; display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 20px; }
        .filters-box .filter-item { white-space: nowrap; }
        .filters-box strong { color: #495057; }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($companyId))
            <p style="font-weight: bold; font-size: 12px; margin-bottom: 2px;">{{ $companyId }}</p>
        @endif
        <h1>Reporte Detallado de Ventas</h1>
        <p>Generado el: {{ now()->format('d/m/Y H:i') }}</p>
        <p>
            Desde: {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : 'Inicio' }}
            — Hasta: {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : 'Hoy' }}
        </p>
    </div>

    @if(!empty($filters))
    <div class="filters-box">
        <strong style="margin-right: 8px;">Filtros aplicados:</strong>
        <span class="filters-row">
            @foreach($filters as $label => $value)
                @if($value !== null && $value !== '')
                <span class="filter-item" style="margin-right: 10px;"><strong>{{ $label }}:</strong> {{ $value }}</span>
                @endif
            @endforeach
        </span>
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Fecha / Hora</th>
                <th>Documento (Origen)</th>
                <th>Persona / Cliente</th>
                <th>Usuario / Responsable</th>
                <th>Detalles (Tipo/Pago)</th>
                <th>Estado SUNAT</th>
                <th>Moneda (TC)</th>
                <th class="text-right">Subtotal</th>
                <th class="text-right">IGV</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sales as $sale)
            <tr>
                <td class="w-min">
                    {{ $sale->moved_at ? $sale->moved_at->format('d/m/Y') : '-' }}<br>
                    <span style="color: #666;">{{ $sale->moved_at ? $sale->moved_at->format('H:i') : '' }}</span>
                </td>

                <td>
                    <strong>{{ strtoupper(substr($sale->documentType?->name ?? 'D', 0, 1)) }}{{ $sale->salesMovement?->series }}-{{ $sale->number }}</strong><br>
                    <span style="font-size: 8px; color: #555;">{{ $sale->movementType?->description ?? 'Venta' }}</span>
                </td>

                <td>
                    {{ \Illuminate\Support\Str::limit($sale->person_name ?: 'Público General', 25) }}
                </td>

                <td>
                    <div style="margin-bottom: 2px;"><strong>U:</strong> {{ $sale->user_name ?: '-' }}</div>
                    <div><strong>R:</strong> {{ $sale->responsible_name ?: '-' }}</div>
                </td>

                <td>
                    <div><strong>Tipo:</strong> {{ $sale->salesMovement?->detail_type ?? '-' }}</div>
                    <div><strong>Pago:</strong> {{ $sale->salesMovement?->payment_type ?? '-' }}</div>
                    <div><strong>Consumo:</strong> {{ ($sale->salesMovement?->consumption ?? 'N') === 'Y' ? 'Sí' : 'No' }}</div>
                </td>

                <td>
                    {{ $sale->salesMovement?->status ?? '-' }}
                </td>

                <td>
                    {{ $sale->salesMovement?->currency ?? 'PEN' }} <br>
                    <span style="font-size: 9px;">TC: {{ number_format((float) ($sale->salesMovement?->exchange_rate ?? 1), 3) }}</span>
                </td>

                <td class="text-right w-min">
                    {{ number_format((float) ($sale->salesMovement?->subtotal ?? 0), 2) }}
                </td>
                <td class="text-right w-min">
                    {{ number_format((float) ($sale->salesMovement?->tax ?? 0), 2) }}
                </td>
                <td class="text-right w-min font-bold">
                    {{ number_format((float) ($sale->salesMovement?->total ?? 0), 2) }}
                </td>
            </tr>
            @if($sale->comment)
            <tr>
                <td colspan="10" style="background-color: #fafafa; font-style: italic; color: #555; padding: 3px 5px;">
                    <strong>Comentario:</strong> {{ $sale->comment }}
                </td>
            </tr>
            @endif
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #e2e8f0; font-weight: bold;">
                <td colspan="7" class="text-right">TOTAL GENERAL:</td>
                <td class="text-right">{{ number_format($sales->sum(fn($s) => $s->salesMovement?->subtotal ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format($sales->sum(fn($s) => $s->salesMovement?->tax ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format($sales->sum(fn($s) => $s->salesMovement?->total ?? 0), 2) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
