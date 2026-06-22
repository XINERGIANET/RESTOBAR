<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Kardex</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; margin: 0; }
        .header { text-align: center; margin-bottom: 16px; }
        .header h1 { margin: 0; font-size: 18px; text-transform: uppercase; }
        .header p { margin: 2px 0; color: #555; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }

        th { background-color: #f0f0f0; font-weight: bold; font-size: 9px; text-transform: uppercase; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .w-min { width: 1%; white-space: nowrap; }
        .filters-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 8px 12px;
            margin-bottom: 12px;
            font-size: 9px;
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 4px 20px;
        }
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
        @if($branch)
            <p style="font-size: 10px; margin-top: 0; margin-bottom: 4px;">Sucursal: {{ $branch->legal_name }}</p>
        @endif
        <h1>Reporte de Kardex</h1>
        <p>Generado el: {{ now()->format('d/m/Y H:i') }}</p>
        <p>
            Desde: {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : 'Inicio' }}
            — Hasta: {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : 'Hoy' }}
        </p>
    </div>

    @php
        $filters = [];
        if ($productId !== 'all') {
            $product = $products->firstWhere('id', (int) $productId);
            $filters['Producto'] = $product
                ? ($product->code . ' - ' . $product->description)
                : 'ID ' . $productId;
        } else {
            $filters['Producto'] = 'Todos';
        }

        if (($sourceFilter ?? 'all') !== 'all') {
            $filters['Fuente'] = $sourceFilter === 'warehouse' ? 'Almacén' : 'Ventas';
        }
        if (($typeFilter ?? 'all') !== 'all') {
            $filters['Tipo de movimiento'] = $typeFilter;
        }
    @endphp

    @if(!empty($filters))
        <div class="filters-box">
            <strong style="margin-right: 8px;">Filtros aplicados:</strong>
            <span class="filters-row">
                @foreach($filters as $label => $value)
                    @if($value !== null && $value !== '')
                        <span class="filter-item" style="margin-right: 10px;">
                            <strong>{{ $label }}:</strong> {{ $value }}
                        </span>
                    @endif
                @endforeach
            </span>
        </div>
    @endif

    <table>
        <thead>
            <tr>
                @if($showAllProducts ?? false)
                    <th>Producto</th>
                @endif
                <th class="w-min">Fecha / Hora</th>
                <th>Tipo</th>
                <th>Unidad</th>
                <th class="text-right">Stock ant.</th>
                <th class="text-right">Cantidad</th>
                <th class="text-right">Stock actual</th>
                <th class="text-right">P. unit.</th>
                <th>Origen</th>
            </tr>
        </thead>
        <tbody>
            @forelse($movements as $m)
                <tr>
                    @if($showAllProducts ?? false)
                        <td>
                            {{ $m['product_code'] ?? '-' }} - {{ $m['product_description'] ?? '-' }}
                        </td>
                    @endif
                    <td class="w-min">
                        {{ $m['date'] ? \Carbon\Carbon::parse($m['date'])->format('d/m/Y') : '-' }}<br>
                        <span style="color: #666;">
                            {{ $m['date'] ? \Carbon\Carbon::parse($m['date'])->format('H:i') : '' }}
                        </span>
                    </td>
                    <td>{{ $m['type'] ?? '-' }}</td>
                    <td>{{ $m['unit'] ?? '-' }}</td>
                    <td class="text-right">
                        {{ ($m['type'] ?? '') === 'Saldo inicial' ? '-' : number_format($m['previous_stock'] ?? 0, 2) }}
                    </td>
                    <td class="text-right">
                        @php $qty = $m['quantity'] ?? 0; @endphp
                        {{ $qty > 0 ? number_format($qty, 2) : '-' }}
                    </td>
                    <td class="text-right">{{ number_format($m['balance'] ?? 0, 2) }}</td>
                    <td class="text-right">
                        @if(isset($m['unit_price']) && $m['unit_price'] !== null)
                            {{ number_format($m['unit_price'], 2) }}
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $m['origin'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ ($showAllProducts ?? false) ? 9 : 8 }}" class="text-center">
                        No hay movimientos para los filtros seleccionados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
