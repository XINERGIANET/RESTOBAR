<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchParameter;
use App\Models\ParameterCategories;
use App\Models\Parameters;
use Illuminate\Database\Seeder;

class CloseTableParameterSeeder extends Seeder
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
            ['description' => 'Permitir cerrar mesas'],
            ['value' => 'Sí', 'parameter_category_id' => $category->id, 'status' => 1]
        );

        Branch::query()->pluck('id')->each(function ($branchId) use ($parameter) {
            BranchParameter::query()->firstOrCreate(
                ['branch_id' => (int) $branchId, 'parameter_id' => (int) $parameter->id],
                ['value' => 'Sí']
            );
        });
    }
}
