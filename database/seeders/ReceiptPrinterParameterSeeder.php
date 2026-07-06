<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchParameter;
use App\Models\ParameterCategories;
use App\Models\Parameters;
use App\Models\PrinterBranch;
use Illuminate\Database\Seeder;

class ReceiptPrinterParameterSeeder extends Seeder
{
    public function run(): void
    {
        $category = ParameterCategories::query()
            ->whereRaw('LOWER(description) LIKE ?', ['%config%'])
            ->first() ?? ParameterCategories::query()->first();

        if (! $category) {
            $category = ParameterCategories::create(['description' => 'Configuración de Sistema']);
        }

        $parameter = Parameters::query()->updateOrCreate(
            ['description' => 'Impresora de comprobantes y precuentas'],
            ['value' => '', 'parameter_category_id' => $category->id, 'status' => 1]
        );

        Branch::query()->pluck('id')->each(function ($branchId) use ($parameter) {
            $defaultPrinterId = PrinterBranch::query()
                ->where('branch_id', $branchId)
                ->where('status', 'E')
                ->orderBy('id')
                ->value('id');

            BranchParameter::query()->firstOrCreate(
                ['branch_id' => (int) $branchId, 'parameter_id' => (int) $parameter->id],
                ['value' => $defaultPrinterId ? (string) $defaultPrinterId : '']
            );
        });
    }
}
