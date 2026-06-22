<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Consolidado de Productos</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; color: #C43B25; }
        .info { margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .text-right { text-align: right; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 9px; color: #999; }
        .totals-box { margin-bottom: 20px; border: 1px solid #C43B25; padding: 10px; }
        .totals-box p { margin: 5px 0; }
        .font-bold { font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Consolidado de Productos</h1>
        <p>Periodo: {{ $dateFrom }} al {{ $dateTo }}</p>
    </div>

    <div class="totals-box">
        <p><span class="font-bold">Total Vendido:</span> S/ {{ number_format($grandTotal, 2) }}</p>
        <p><span class="font-bold">Venta Neta (Sin IGV):</span> S/ {{ number_format($grandTotalNet, 2) }}</p>
        <p><span class="font-bold">IGV Total:</span> S/ {{ number_format($grandTax, 2) }}</p>
        <p><span class="font-bold">Unidades Vendidas:</span> {{ number_format($grandQuantity, 0) }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Producto</th>
                <th>Categoría</th>
                <th class="text-right">Cantidad</th>
                <th class="text-right">Precio Prom.</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row->product_name }}</td>
                    <td>{{ $row->category_name ?: '—' }}</td>
                    <td class="text-right">{{ number_format($row->total_quantity, 2) }}</td>
                    <td class="text-right">S/ {{ number_format($row->avg_price, 2) }}</td>
                    <td class="text-right">S/ {{ number_format($row->total_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Generado el {{ date('d/m/Y H:i:s') }}
    </div>
</body>
</html>
