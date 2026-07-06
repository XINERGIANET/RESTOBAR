<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchParameter;
use App\Models\ParameterCategories;
use App\Models\Parameters;
use Illuminate\Database\Seeder;

class BranchQzCertificateParametersSeeder extends Seeder
{
    public function run(): void
    {
        $category = ParameterCategories::query()
            ->whereRaw('LOWER(description) LIKE ?', ['%config%'])
            ->first() ?? ParameterCategories::query()->firstOrCreate(['description' => 'Configuración de Sistema']);

        $certificate = Parameters::query()->updateOrCreate(
            ['description' => 'Certificado digital QZ Tray'],
            ['value' => '', 'parameter_category_id' => $category->id, 'status' => 1]
        );
        $privateKey = Parameters::query()->updateOrCreate(
            ['description' => 'Clave privada QZ Tray'],
            ['value' => '', 'parameter_category_id' => $category->id, 'status' => 1]
        );

        Branch::query()->pluck('id')->each(function ($branchId) use ($certificate, $privateKey) {
            foreach ([$certificate, $privateKey] as $parameter) {
                BranchParameter::query()->firstOrCreate(
                    ['branch_id' => (int) $branchId, 'parameter_id' => (int) $parameter->id],
                    ['value' => '']
                );
            }
        });
    }
}
