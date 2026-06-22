<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Ventas y Finanzas</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; color: #C43B25; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .text-right { text-align: right; }
        .totals-box { margin-bottom: 20px; border: 1px solid #C43B25; padding: 15px; }
        .totals-box p { margin: 5px 0; font-size: 14px; }
        .font-bold { font-weight: bold; }
        .success { color: #22c55e; }
        .danger { color: #ef4444; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de Ventas y Finanzas</h1>
        <p>Periodo: {{ $dateFrom }} al {{ $dateTo }}</p>
    </div>

    <div class="totals-box">
        <p><span class="font-bold">Total Ventas:</span> S/ {{ number_format($totalSales, 2) }}</p>
        <p><span class="font-bold text-danger">Total Compras/Gastos:</span> S/ {{ number_format($totalPurchases, 2) }}</p>
        <hr>
        <p><span class="font-bold">Utilidad Neta:</span> 
            <span class="{{ $netProfit >= 0 ? 'success' : 'danger' }}">
                S/ {{ number_format($netProfit, 2) }}
            </span>
        </p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Periodo</th>
                <th class="text-right">Ventas</th>
                <th class="text-right">Compras</th>
                <th class="text-right">Neto</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dates as $i => $date)
                <tr>
                    <td>{{ $date }}</td>
                    <td class="text-right">S/ {{ number_format($chartSales[$i], 2) }}</td>
                    <td class="text-right">S/ {{ number_format($chartPurchases[$i], 2) }}</td>
                    <td class="text-right">S/ {{ number_format($chartSales[$i] - $chartPurchases[$i], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 9px; color: #999;">
        Generado el {{ date('d/m/Y H:i:s') }}
    </div>
</body>
</html>
