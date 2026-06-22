<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ConsolidatedProductsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $rows;
    protected $grandTotal;

    public function __construct(Collection $rows, float $grandTotal)
    {
        $this->rows = $rows;
        $this->grandTotal = $grandTotal;
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            '#',
            'Producto',
            'Categoría',
            'Cantidad',
            'Cortesías',
            'Precio Prom.',
            'Descuento',
            'Total',
            '% del Total'
        ];
    }

    public function map($row): array
    {
        static $index = 0;
        $index++;
        $pct = $this->grandTotal > 0 ? ($row->total_amount / $this->grandTotal) * 100 : 0;

        return [
            $index,
            $row->product_name,
            $row->category_name ?: '—',
            number_format($row->total_quantity, 2),
            number_format($row->total_courtesy, 0),
            'S/ ' . number_format($row->avg_price, 2),
            $row->total_discount > 0 ? '- S/ ' . number_format($row->total_discount, 2) : '—',
            'S/ ' . number_format($row->total_amount, 2),
            number_format($pct, 1) . '%'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
