<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

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

        $locationCode = 1;
        $locationId = DB::table('locations')
            ->where('code', $locationCode)
            ->where('name', 'Principal')
            ->value('id');
        if (!$locationId) {
            $locationId = DB::table('locations')->insertGetId([
                'name' => 'Principal',
                'code' => $locationCode,
                'type' => 'city',
                'parent_location_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $branchRuc = '20100000001';
        $branchId = DB::table('branches')->where('ruc', $branchRuc)->value('id');
        if (!$branchId) {
            $branchId = DB::table('branches')->insertGetId([
                'ruc' => $branchRuc,
                'company_id' => $companyId,
                'legal_name' => 'Sucursal Principal',
                'logo' => 'logo.png',
                'address' => 'Direccion Sucursal Principal',
                'location_id' => $locationId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $profileName = 'Administrador de sistema';
        $profileId = DB::table('profiles')->where('name', $profileName)->value('id');
        if (!$profileId) {
            $profileId = DB::table('profiles')->insertGetId([
                'name' => $profileName,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $adminEmail = 'admin@xinergia.test';
        $personId = DB::table('people')->where('email', $adminEmail)->value('id');
        if (!$personId) {
            $personId = DB::table('people')->insertGetId([
                'first_name' => 'Admin',
                'last_name' => 'Sistema',
                'person_type' => 'ADMIN',
                'phone' => '0000000000',
                'email' => $adminEmail,
                'document_number' => 'ADMIN-0001',
                'address' => 'Direccion Admin',
                'location_id' => $locationId,
                'branch_id' => $branchId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $adminPassword = 'Admin@2026#Xinergia!';
        $userId = DB::table('users')->where('email', $adminEmail)->value('id');
        if (!$userId) {
            DB::table('users')->insert([
                'name' => 'ADMIN',
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'person_id' => $personId,
                'profile_id' => $profileId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
