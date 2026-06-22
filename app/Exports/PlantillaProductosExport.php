<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// ─── Hoja principal: Productos ────────────────────────────────────────────────
class PlantillaProductosSheet implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    public function title(): string
    {
        return 'Productos';
    }

    public function array(): array
    {
        return [
            // Encabezados (fila 1)
            ['Codigo', 'nombre_producto', 'abreviacion', 'nombre_categoria',
             'tipo_menu', 'tipo_producto', 'kardex',
             'precio', 'precio_compra', 'stock', 'unidad'],
            // Fila de ejemplo (fila 2)
            ['PRD-001', 'Pollo a la brasa', 'POLLO', 'Platos a la carta',
             'VENTAS_PEDIDOS', 'Producto final', 'N',
             '40.00', '15.00', '0', 'Unidad(es)'],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12, 'B' => 26, 'C' => 15, 'D' => 26,
            'E' => 18, 'F' => 18, 'G' => 10,
            'H' => 12, 'I' => 14, 'J' => 10, 'K' => 18,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // ── Estilo encabezados ──────────────────────────────────────
                $sheet->getStyle('A1:K1')->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // ── Estilo fila de ejemplo ──────────────────────────────────
                $sheet->getStyle('A2:K2')->applyFromArray([
                    'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
                ]);

                // ── Listas desplegables ─────────────────────────────────────
                // tipo_menu → columna E
                $dv = $sheet->getDataValidation('E2:E500');
                $dv->setType(DataValidation::TYPE_LIST);
                $dv->setErrorStyle(DataValidation::STYLE_STOP);
                $dv->setAllowBlank(false);
                $dv->setShowDropDown(false);
                $dv->setShowErrorMessage(true);
                $dv->setErrorTitle('Valor inválido');
                $dv->setError('Elige: VENTAS_PEDIDOS, COMPRAS o GENERAL');
                $dv->setFormula1('"VENTAS_PEDIDOS,COMPRAS,GENERAL"');

                // tipo_producto → columna F
                $dv2 = $sheet->getDataValidation('F2:F500');
                $dv2->setType(DataValidation::TYPE_LIST);
                $dv2->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $dv2->setAllowBlank(true);
                $dv2->setShowDropDown(false);
                $dv2->setFormula1('"Producto final,Ingrediente"');

                // kardex → columna G
                $dv3 = $sheet->getDataValidation('G2:G500');
                $dv3->setType(DataValidation::TYPE_LIST);
                $dv3->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $dv3->setAllowBlank(true);
                $dv3->setShowDropDown(false);
                $dv3->setFormula1('"S,N"');
            },
        ];
    }
}

// ─── Hoja secundaria: Referencia ──────────────────────────────────────────────
class PlantillaReferenciaSheet implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    public function title(): string
    {
        return 'Referencia';
    }

    public function array(): array
    {
        return [
            ['VALORES VÁLIDOS', ''],
            ['', ''],
            ['tipo_menu', ''],
            ['VENTAS_PEDIDOS', '→ Aparece en Ventas y Pedidos/Mesas'],
            ['COMPRAS',        '→ Aparece en módulo de Compras'],
            ['GENERAL',        '→ Aparece en todos los módulos'],
            ['', ''],
            ['tipo_producto', ''],
            ['Producto final', '→ Producto vendible al cliente'],
            ['Ingrediente',    '→ Insumo o materia prima'],
            ['', ''],
            ['kardex', ''],
            ['S', '→ Lleva control de stock'],
            ['N', '→ No lleva stock (default)'],
            ['', ''],
            ['Unidades más usadas', ''],
            ['Unidad(es)', ''],   ['Porción(es)', ''],  ['Vaso(s)', ''],
            ['Jarra(s)', ''],     ['Botella(s)', ''],   ['Litro(s)', ''],
            ['Mililitro(s)', ''], ['Kilogramo(s)', ''], ['Gramo(s)', ''],
            ['Caja(s)', ''],      ['Paquete(s)', ''],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 25, 'B' => 48];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Título principal
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '1E40AF']],
                ]);

                // Subtítulos de sección (filas con valor en A pero B vacío)
                foreach ([3, 8, 12, 16] as $row) {
                    $sheet->getStyle("A{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '1D4ED8']],
                    ]);
                }
            },
        ];
    }
}

// ─── Coordinador de múltiples hojas ──────────────────────────────────────────
class PlantillaProductosExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new PlantillaProductosSheet(),
            new PlantillaReferenciaSheet(),
        ];
    }
}
