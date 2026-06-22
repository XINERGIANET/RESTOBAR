<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Productos vendidos — {{ $startDate->format('d/m/Y') }} @if($startDate->format('Y-m-d') !== $endDate->format('Y-m-d')) al {{ $endDate->format('d/m/Y') }} @endif</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 12px; margin: 0; padding: 16px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 4px; text-align: center; }
        .muted { color: #6b7280; font-size: 11px; text-align: center; margin-bottom: 16px; }
        .warn { background: #fef3c7; color: #92400e; padding: 8px 12px; border-radius: 8px; margin-bottom: 12px; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 11px; }
        th, td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #f3f4f6; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; }
        .text-right { text-align: right; }
        tfoot td { font-weight: 700; border-top: 2px solid #d1d5db; background: #f9fafb; }
        @media print {
            body { padding: 8px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    @if(!empty($pdfGenerationFailed))
        <div class="warn no-print">No se pudo generar el PDF en el servidor. Use Imprimir del navegador para guardar como PDF.</div>
    @endif

    <h1>Productos vendidos</h1>
    <p class="muted">
        Periodo:
        <strong>{{ $startDate->format('d/m/Y') }}</strong>
        @if($startDate->format('Y-m-d') !== $endDate->format('Y-m-d'))
            al <strong>{{ $endDate->format('d/m/Y') }}</strong>
        @endif
    </p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Producto</th>
                <th class="text-right">Cantidad</th>
                <th class="text-right">Importe (S/)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row['product'] }}</td>
                    <td class="text-right">{{ number_format((float) $row['qty'], 2) }}</td>
                    <td class="text-right">{{ number_format((float) $row['amount'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center muted" style="text-align:center;padding:24px;">No hay ventas en el periodo seleccionado.</td>
                </tr>
            @endforelse
        </tbody>
        @if(count($rows))
            <tfoot>
                <tr>
                    <td colspan="2">Total</td>
                    <td class="text-right">{{ number_format((float) $totalQty, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $totalAmount, 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
