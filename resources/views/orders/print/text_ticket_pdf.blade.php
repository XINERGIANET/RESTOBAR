<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        @page {
            size: {{ (int) $paperWidth }}mm auto;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: {{ (int) $paperWidth }}mm;
            background: #fff;
            color: #000;
            font-family: "Liberation Mono", "DejaVu Sans Mono", "Courier New", monospace;
        }

        body {
            font-size: {{ (int) $paperWidth === 80 ? '12px' : '11px' }};
            line-height: 1.2;
        }

        .ticket {
            width: {{ (int) $paperWidth }}mm;
            padding: 2.5mm 2.2mm 2.8mm;
        }

        .line {
            white-space: pre;
            word-break: normal;
            overflow-wrap: normal;
            margin: 0;
            min-height: 1.15em;
        }

        .line + .line {
            margin-top: 0.45mm;
        }

        .header {
            text-align: center;
            font-weight: 800;
            font-size: {{ (int) $paperWidth === 80 ? '18px' : '15px' }};
            letter-spacing: 0.8px;
            margin-bottom: 1.3mm;
        }

        .preaccount-header {
            font-weight: 700;
            font-size: {{ (int) $paperWidth === 80 ? '15px' : '13px' }};
            letter-spacing: 0.3px;
            margin-bottom: 0.7mm;
        }

        .meta {
            font-weight: 700;
        }

        .meta .value {
            font-weight: 400;
        }

        .separator {
            position: relative;
            height: 0.95em;
            color: transparent;
            overflow: hidden;
        }

        .separator::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            top: 50%;
            border-top: 1.6px dashed #111;
            transform: translateY(-50%);
        }

        .table-head {
            font-weight: 800;
            margin-top: 0.4mm;
            margin-bottom: 0.2mm;
        }

        .item {
            font-weight: 500;
        }

        .preaccount-item {
            font-weight: 400;
            font-size: {{ (int) $paperWidth === 80 ? '13px' : '11px' }};
            line-height: 1.1;
        }

        .kitchen-item {
            font-weight: 800;
            font-size: {{ (int) $paperWidth === 80 ? '16px' : '14px' }};
            letter-spacing: 0.2px;
            line-height: 1.15;
        }

        .item-strong {
            font-weight: 700;
        }

        .status,
        .note,
        .aux {
            font-size: 0.93em;
        }

        .status {
            font-weight: 700;
        }

        .small-note {
            font-size: {{ (int) $paperWidth === 80 ? '9px' : '7.5px' }};
            line-height: 1;
            letter-spacing: 0;
            white-space: nowrap;
        }

        .section-gap {
            margin-top: 1mm;
        }

        .total-line {
            font-weight: 800;
            font-size: {{ (int) $paperWidth === 80 ? '15px' : '13px' }};
            margin-top: 0.4mm;
        }

        .emphasis {
            font-weight: 800;
        }
    </style>
</head>
<body>
@php
    $lines = preg_split("/\r\n|\n|\r/", trim($ticketText));
    $isKitchenTicket = stripos($title, 'comanda') !== false;
@endphp
    <div class="ticket">
        @foreach($lines as $index => $line)
            @php
                $trimmed = trim($line);
                if (preg_match('/^Salon:\s*Salon$/iu', $trimmed) === 1) {
                    continue;
                }

                $isSeparator = $trimmed !== '' && preg_match('/^[=\-]{6,}$/', $trimmed);
                $isHeader = $index === 0;
                $isTableHead = preg_match('/^(Producto|Prod\.|ANULADO)/iu', $trimmed) === 1;
                $isMeta = preg_match('/^(Salon|Sal[oó]n|Area|Á?rea|Mesa|Mozo|Fecha|Fecha\/Hora|Cliente|Caja|Documento|Comanda)\s*:/iu', $trimmed) === 1;
                $isTotalLine = preg_match('/^TOTAL\b/iu', $trimmed) === 1;
                $isSubtotalLike = preg_match('/^(Subtotal|Impuestos|IGV|Op\. gravada|Cambio)\b/iu', $trimmed) === 1;
                $isStatus = preg_match('/^(Estado|Motivo|Nota)\s*:/iu', $trimmed) === 1;
                $isItem = !$isHeader && !$isSeparator && !$isMeta && !$isTableHead && !$isTotalLine && !$isSubtotalLike && !$isStatus && $trimmed !== '';
                $isKitchenItem = $isKitchenTicket && preg_match('/^\d+\s{2,}.+/u', $trimmed) === 1;
                $isPreAccountItem = !$isKitchenTicket && preg_match('/^\d+\s{2,}.+/u', $trimmed) === 1;
                $isPreAccountHeader = !$isKitchenTicket && $index === 0;
                $isSmallNote = preg_match('/^<<No valido como documento contable>>$/iu', $trimmed) === 1;

                $classes = ['line'];
                if ($isHeader) $classes[] = 'header';
                if ($isSeparator) $classes[] = 'separator';
                if ($isTableHead) $classes[] = 'table-head';
                if ($isMeta) $classes[] = 'meta';
                if ($isTotalLine) $classes[] = 'total-line';
                if ($isSubtotalLike) $classes[] = 'item-strong';
                if ($isStatus) $classes[] = 'status';
                if ($isItem) $classes[] = 'item';
                if ($isKitchenItem) $classes[] = 'kitchen-item';
                if ($isPreAccountItem) $classes[] = 'preaccount-item';
                if ($isPreAccountHeader) $classes[] = 'preaccount-header';
                if ($isSmallNote) $classes[] = 'small-note';
                if ($trimmed === '') $classes[] = 'section-gap';

                $rendered = e($line);
                if ($isMeta || $isStatus) {
                
                    $rendered = preg_replace('/^([^:]+:\s*)(.*)$/u', '<span class="emphasis">$1</span><span class="value">$2</span>', $rendered);
                } elseif ($isTotalLine || $isSubtotalLike) {
                    $rendered = preg_replace('/^(.*?)(S\/\.\s*[\d\.,-]+)\s*$/u', '<span class="emphasis">$1</span><span class="value">$2</span>', $rendered);
                }
            @endphp
            <div class="{{ implode(' ', $classes) }}">{!! $rendered !== '' ? $rendered : '&nbsp;' !!}</div>
        @endforeach
    </div>
</body>
</html>
