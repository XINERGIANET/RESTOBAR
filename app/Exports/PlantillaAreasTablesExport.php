<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// ─── Hoja 1: Áreas ───────────────────────────────────────────────────────────
class PlantillaAreasSheet implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    public function title(): string { return 'Areas'; }

    public function array(): array
    {
        return [
            ['nombre_area'],
            ['Salon principal'],
            ['Terraza'],
            ['Bar'],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 30];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $sheet->getStyle('A2:A4')->applyFromArray([
                    'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
                ]);
            },
        ];
    }
}

// ─── Hoja 2: Mesas ───────────────────────────────────────────────────────────
class PlantillaMesasSheet implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    public function title(): string { return 'Mesas'; }

    public function array(): array
    {
        return [
            ['numero_mesa', 'capacidad', 'area_name'],
            ['1', 4, 'Salon principal'],
            ['2', 4, 'Salon principal'],
            ['3', 6, 'Salon principal'],
            ['4', 2, 'Terraza'],
            ['5', 2, 'Terraza'],
            ['6', 1, 'Bar'],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 20, 'B' => 12, 'C' => 25];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle('A1:C1')->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '065F46']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $sheet->getStyle('A2:C7')->applyFromArray([
                    'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0FDF4']],
                ]);

                // Validación: capacidad mínima 1
                $dv = $sheet->getDataValidation('B2:B500');
                $dv->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_WHOLE);
                $dv->setOperator(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::OPERATOR_GREATERTHANOREQUAL);
                $dv->setFormula1('1');
                $dv->setAllowBlank(true);
                $dv->setShowErrorMessage(true);
                $dv->setErrorTitle('Valor inválido');
                $dv->setError('La capacidad debe ser un número entero mayor o igual a 1.');
            },
        ];
    }
}

// ─── Hoja 3: Referencia ──────────────────────────────────────────────────────
class PlantillaAreasTablesRefSheet implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    public function title(): string { return 'Referencia'; }

    public function array(): array
    {
        return [
            ['INSTRUCCIONES', ''],
            ['', ''],
            ['Hoja "Areas"', ''],
            ['nombre_area', '→ Nombre del área/salón (requerido). Ej: Salon principal, Terraza, Bar'],
            ['', ''],
            ['Hoja "Mesas"', ''],
            ['nombre_mesa', '→ Nombre de la mesa (requerido). Ej: Mesa 01, Barra 01'],
            ['capacidad',   '→ Cantidad máxima de personas (opcional, default: 4)'],
            ['nombre_area', '→ Debe coincidir exactamente con un nombre de la hoja Áreas'],
            ['', ''],
            ['NOTAS', ''],
            ['→ Primero se procesan las Áreas, luego las Mesas', ''],
            ['→ Si el área ya existe, se omite (no se duplica)', ''],
            ['→ Si la mesa ya existe en el área, solo actualiza la capacidad', ''],
            ['→ Los datos se importan solo para la sucursal activa', ''],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 30, 'B' => 55];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '1E40AF']],
                ]);

                foreach ([3, 6, 11] as $row) {
                    $sheet->getStyle("A{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '065F46']],
                    ]);
                }
            },
        ];
    }
}

// ─── Coordinador ─────────────────────────────────────────────────────────────
class PlantillaAreasTablesExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new PlantillaAreasSheet(),
            new PlantillaMesasSheet(),
            new PlantillaAreasTablesRefSheet(),
        ];
    }
}
