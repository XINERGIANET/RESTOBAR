<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchParameter;
use App\Models\ParameterCategories;
use App\Models\Parameters;
use Illuminate\Database\Seeder;

class DefaultSaleDocumentTypeParameterSeeder extends Seeder
{
    public function run(): void
    {
        $category = ParameterCategories::query()
            ->where(function ($query) {
                $query->whereRaw('LOWER(description) LIKE ?', ['%sistema%'])
                    ->orWhereRaw('LOWER(description) LIKE ?', ['%config%']);
            })
            ->orderBy('id')
            ->first();

        if (! $category) {
            $category = ParameterCategories::query()->first();
        }

        if (! $category) {
            $category = ParameterCategories::create([
                'description' => 'Configuracion de Sistema',
            ]);
        }

        $parameter = Parameters::query()->updateOrCreate(
            ['description' => 'Tipo de comprobante por defecto'],
            [
                'value' => '5',
                'parameter_category_id' => $category->id,
                'status' => 1,
            ]
        );

        $now = now();
        Branch::query()->pluck('id')->each(function ($branchId) use ($parameter, $now) {
            BranchParameter::query()->updateOrCreate(
                [
                    'branch_id' => (int) $branchId,
                    'parameter_id' => (int) $parameter->id,
                ],
                [
                    'value' => '5',
                    'updated_at' => $now,
                ]
            );
        });
    }
}
