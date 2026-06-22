<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        // Obtener o crear la empresa
        $companyTaxId = '9000000000';
        $companyId = DB::table('companies')->where('tax_id', $companyTaxId)->value('id');
        if (!$companyId) {
            $companyId = DB::table('companies')->insertGetId([
                'tax_id' => $companyTaxId,
                'legal_name' => 'Empresa Administradora',
                'address' => 'Direccion Principal',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Obtener o crear locations
        $locations = [
            [
                'name' => 'Lima Centro',
                'code' => 150101,
                'type' => 'district',
            ],
            [
                'name' => 'Miraflores',
                'code' => 150122,
                'type' => 'district',
            ],
            [
                'name' => 'San Isidro',
                'code' => 150125,
                'type' => 'district',
            ],
            [
                'name' => 'Surco',
                'code' => 150140,
                'type' => 'district',
            ],
        ];

        $locationIds = [];
        foreach ($locations as $locationData) {
            $locationId = DB::table('locations')
                ->where('code', $locationData['code'])
                ->where('name', $locationData['name'])
                ->value('id');
            
            if (!$locationId) {
                $locationId = DB::table('locations')->insertGetId([
                    'name' => $locationData['name'],
                    'code' => $locationData['code'],
                    'type' => $locationData['type'],
                    'parent_location_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $locationIds[] = $locationId;
        }

        // Crear sucursales de prueba
        $branches = [
            [
                'ruc' => '20100000001',
                'legal_name' => 'Sucursal Principal',
                'address' => 'Av. Principal 123, Lima Centro',
                'location_id' => $locationIds[0] ?? null,
            ],
            [
                'ruc' => '20100000002',
                'legal_name' => 'Sucursal Miraflores',
                'address' => 'Av. Larco 456, Miraflores',
                'location_id' => $locationIds[1] ?? null,
            ],
            [
                'ruc' => '20100000003',
                'legal_name' => 'Sucursal San Isidro',
                'address' => 'Av. Javier Prado 789, San Isidro',
                'location_id' => $locationIds[2] ?? null,
            ],
            [
                'ruc' => '20100000004',
                'legal_name' => 'Sucursal Surco',
                'address' => 'Av. Caminos del Inca 321, Surco',
                'location_id' => $locationIds[3] ?? null,
            ],
        ];

        $inserted = 0;
        foreach ($branches as $branchData) {
            $exists = DB::table('branches')
                ->where('ruc', $branchData['ruc'])
                ->exists();

            if (!$exists) {
                DB::table('branches')->insert([
                    'ruc' => $branchData['ruc'],
                    'company_id' => $companyId,
                    'legal_name' => $branchData['legal_name'],
                    'logo' => null,
                    'address' => $branchData['address'],
                    'location_id' => $branchData['location_id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $inserted++;
            }
        }

        $this->command->info("✅ Se crearon {$inserted} sucursales de prueba.");
    }
}
