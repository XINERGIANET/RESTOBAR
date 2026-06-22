<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class SalesAndFinancesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $dates;
    protected $sales;
    protected $purchases;

    public function __construct(array $dates, array $sales, array $purchases)
    {
        $this->dates = $dates;
        $this->sales = $sales;
        $this->purchases = $purchases;
    }

    public function collection()
    {
        $data = [];
        foreach ($this->dates as $i => $date) {
            $data[] = [
                'date' => $date,
                'sales' => $this->sales[$i] ?? 0,
                'purchases' => $this->purchases[$i] ?? 0,
                'net' => ($this->sales[$i] ?? 0) - ($this->purchases[$i] ?? 0)
            ];
        }
        return collect($data);
    }

    public function headings(): array
    {
        return [
            'Periodo',
            'Ventas',
            'Compras',
            'Margen Neto'
        ];
    }

    public function map($row): array
    {
        return [
            $row['date'],
            'S/ ' . number_format($row['sales'], 2),
            'S/ ' . number_format($row['purchases'], 2),
            'S/ ' . number_format($row['net'], 2),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
