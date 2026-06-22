<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalesExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function __construct(private readonly Collection $sales)
    {
    }

    public function headings(): array
    {
        return [
            'Comprobante',
            'Tipo documento',
            'Fecha',
            'Hora',
            'Cliente',
            'Subtotal',
            'IGV',
            'Total',
            'Tipo venta',
            'Estado',
            'Usuario',
        ];
    }

    public function array(): array
    {
        return $this->sales->map(function ($sale) {
            return [
                strtoupper(substr($sale->documentType?->name ?? 'T', 0, 1)).($sale->salesMovement?->series ?? '001').'-'.$sale->number,
                $sale->documentType?->name ?? '-',
                optional($sale->moved_at)->format('d/m/Y') ?? '-',
                optional($sale->moved_at)->format('H:i:s') ?? '-',
                $sale->person_name ?? 'Publico General',
                (float) ($sale->salesMovement?->subtotal ?? 0),
                (float) ($sale->salesMovement?->tax ?? 0),
                (float) ($sale->salesMovement?->total ?? 0),
                $sale->salesMovement?->detail_type ?? '-',
                ($sale->status ?? 'A') === 'P' ? 'Pendiente' : 'Activo',
                $sale->user_name ?? '-',
            ];
        })->all();
    }
}
