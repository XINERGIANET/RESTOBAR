<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountsReceivableMenuSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Menú: Cuentas por cobrar (Caja)...');

        $module = DB::table('modules')->where('name', 'Caja')->first();
        if (! $module) {
            $this->command->warn("No existe el módulo 'Caja'. Cree el módulo o ejecute MenuOptionSeeder primero.");

            return;
        }

        $defaultView = DB::table('views')->first();
        $viewId = $defaultView
            ? $defaultView->id
            : DB::table('views')->insertGetId([
                'name' => 'Vista General',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $opt = [
            'name' => 'Cuentas por cobrar',
            'action' => 'accounts-receivable.index',
            'icon' => 'mdi-cash-clock',
        ];

        $exists = DB::table('menu_option')
            ->where('name', $opt['name'])
            ->where('module_id', $module->id)
            ->exists();

        if ($exists) {
            $this->command->info("La opción '{$opt['name']}' ya existe.");

            return;
        }

        $menuOptionId = DB::table('menu_option')->insertGetId([
            'name' => $opt['name'],
            'icon' => $opt['icon'],
            'action' => $opt['action'],
            'view_id' => $viewId,
            'module_id' => $module->id,
            'status' => 1,
            'quick_access' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info("Opción de menú creada (ID {$menuOptionId}).");

        $targetProfileIds = [1, 2];
        $branches = DB::table('branches')->whereNull('deleted_at')->pluck('id');

        foreach ($branches as $branchId) {
            foreach ($targetProfileIds as $profileId) {
                $upExists = DB::table('user_permission')
                    ->where('profile_id', $profileId)
                    ->where('branch_id', $branchId)
                    ->where('menu_option_id', $menuOptionId)
                    ->first();

                if (! $upExists) {
                    DB::table('user_permission')->insert([
                        'id' => (string) Str::uuid(),
                        'name' => $opt['name'],
                        'profile_id' => $profileId,
                        'menu_option_id' => $menuOptionId,
                        'branch_id' => $branchId,
                        'status' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Permisos asignados a perfiles 1 y 2 por sucursal.');
    }
}
