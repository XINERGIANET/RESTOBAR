<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Pedidos</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; margin: 0; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; text-transform: uppercase; }
        .header p { margin: 2px 0; color: #555; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 5px; text-align: left; vertical-align: top; }

        th { background-color: #f0f0f0; font-weight: bold; font-size: 9px; text-transform: uppercase; }
        
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .w-min { width: 1%; white-space: nowrap; }
        .filters-box { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 8px 12px; margin-bottom: 12px; font-size: 9px; display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 20px; }
        .filters-box .filters-row { display: flex; flex-wrap: wrap; gap: 12px 20px; align-items: baseline; }
        .filters-box .filter-item { white-space: nowrap; }
        .filters-box strong { color: #495057; }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($companyName))
            <p style="font-weight: bold; font-size: 12px; margin-bottom: 2px;">{{ $companyName }}</p>
        @endif
        @if(!empty($branchName))
            <p style="font-size: 10px; margin-top: 0; margin-bottom: 4px;">Sucursal: {{ $branchName }}</p>
        @endif
        <h1>Reporte detallado de pedidos</h1>
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
                <th>Número</th>
                <th>Persona / Cliente</th>
                <th>Usuario / Responsable</th>
                <th>Mesa / Área</th>
                <th>Estado</th>
                <th>Moneda (TC)</th>
                <th class="text-right">Subtotal</th>
                <th class="text-right">IGV</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orders as $order)
            @php
                $mov = $order->movement;
                $fecha = $order->finished_at ?? $mov?->moved_at ?? $order->created_at;
                $numero = $mov ? (ctype_digit((string) $mov->number) ? str_pad($mov->number, 8, '0', STR_PAD_LEFT) : $mov->number) : $order->id;
            @endphp
            <tr>
                <td class="w-min">
                    {{ $fecha ? $fecha->format('d/m/Y') : '-' }}<br>
                    <span style="color: #666;">{{ $fecha ? $fecha->format('H:i') : '' }}</span>
                </td>

                <td>
                    <strong>{{ strtoupper(substr($mov?->documentType?->name ?? 'PED', 0, 1)) }}{{ $mov?->documentType?->series }}-{{ $numero }}</strong><br>
                    <span style="font-size: 8px; color: #555;">{{ $mov?->movementType?->description ?? 'Pedido' }}</span>
                </td>

                <td>
                    {{ \Illuminate\Support\Str::limit($mov?->person_name ?? 'Público General', 25) }}
                </td>

                <td>
                    <div style="margin-bottom: 2px;"><strong>U:</strong> {{ $mov?->user_name ?? '-' }}</div>
                    <div><strong>R:</strong> {{ $mov?->responsible_name ?? '-' }}</div>
                </td>

                <td>
                    <div><strong>Mesa:</strong> {{ $order->table?->name ?? '-' }}</div>
                    <div><strong>Área:</strong> {{ $order->area?->name ?? '-' }}</div>
                    <div><strong>Personas:</strong> {{ (int) ($order->people_count ?? 0) ?: '-' }}</div>
                </td>

                <td>
                    @php
                        $estado = strtoupper((string) ($order->status ?? ''));
                        if (in_array($estado, ['FINALIZADO', 'F'], true)) {
                            $estadoTexto = 'Finalizado';
                        } elseif (in_array($estado, ['CANCELADO', 'C'], true)) {
                            $estadoTexto = 'Cancelado';
                        } elseif (in_array($estado, ['PENDIENTE', 'P'], true)) {
                            $estadoTexto = 'Pendiente';
                        } else {
                            $estadoTexto = $order->status ?? '-';
                        }
                    @endphp
                    {{ $estadoTexto }}
                </td>

                <td>
                    {{ $order->currency ?? 'PEN' }}<br>
                    <span style="font-size: 9px;">TC: {{ number_format((float) ($order->exchange_rate ?? 1), 3) }}</span>
                </td>

                <td class="text-right w-min">
                    {{ number_format((float) ($order->subtotal ?? 0), 2) }}
                </td>
                <td class="text-right w-min">
                    {{ number_format((float) ($order->tax ?? 0), 2) }}
                </td>
                <td class="text-right w-min font-bold">
                    {{ number_format((float) ($order->total ?? 0), 2) }}
                </td>
            </tr>
            @if($mov?->comment)
            <tr>
                <td colspan="11" style="background-color: #fafafa; font-style: italic; color: #555; padding: 3px 5px;">
                    <strong>Comentario:</strong> {{ $mov->comment }}
                </td>
            </tr>
            @endif
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #e2e8f0; font-weight: bold;">
                <td colspan="8" class="text-right">TOTAL GENERAL:</td>
                <td class="text-right">{{ number_format(collect($orders)->sum(fn($o) => (float) ($o->subtotal ?? 0)), 2) }}</td>
                <td class="text-right">{{ number_format(collect($orders)->sum(fn($o) => (float) ($o->tax ?? 0)), 2) }}</td>
                <td class="text-right">{{ number_format(collect($orders)->sum(fn($o) => (float) ($o->total ?? 0)), 2) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
