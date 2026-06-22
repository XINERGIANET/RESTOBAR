<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SalesAndFinancesReportSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Obtener el módulo "Reportes"
        $module = DB::table('modules')->where('name', 'Reportes')->first();

        if (!$module) {
            $this->command->error("No se encontró el módulo 'Reportes'. Por favor, ejecuta primero ReportesMenuSeeder.");
            return;
        }

        // 2. Vista por defecto (requerida por menu_option)
        $defaultView = DB::table('views')->first();
        $viewId = $defaultView ? $defaultView->id : 1;

        // 3. Registrar la nueva opción
        $optionName = 'Ventas y Finanzas';
        $exists = DB::table('menu_option')
            ->where('name', $optionName)
            ->where('module_id', $module->id)
            ->exists();

        if (!$exists) {
            DB::table('menu_option')->insert([
                'name'         => $optionName,
                'icon'         => 'ri-funds-line',
                'action'       => 'reports.sales_and_finances',
                'view_id'      => $viewId,
                'module_id'    => $module->id,
                'status'       => 1,
                'quick_access' => false,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            $this->command->info("✅ Opción '$optionName' registrada con éxito.");
        } else {
            $this->command->info("ℹ️ La opción '$optionName' ya está registrada.");
        }

        // 4. Asignar permisos a Admin. de sistema (1) y Admin. general (2)
        $this->command->info('Asignando permisos a administradores para Ventas y Finanzas...');
        $targetProfileIds = [1, 2];
        $branches = DB::table('branches')->whereNull('deleted_at')->pluck('id');
        $menuOption = DB::table('menu_option')
            ->where('name', $optionName)
            ->where('module_id', $module->id)
            ->first();

        if ($menuOption) {
            foreach ($branches as $branchId) {
                foreach ($targetProfileIds as $profileId) {
                    $upExists = DB::table('user_permission')
                        ->where('profile_id', $profileId)
                        ->where('branch_id', $branchId)
                        ->where('menu_option_id', $menuOption->id)
                        ->first();

                    if (!$upExists) {
                        DB::table('user_permission')->insert([
                            'id'             => (string) \Illuminate\Support\Str::uuid(),
                            'name'           => $menuOption->name,
                            'profile_id'     => $profileId,
                            'menu_option_id' => $menuOption->id,
                            'branch_id'      => $branchId,
                            'status'         => true,
                            'created_at'     => now(),
                            'updated_at'     => now(),
                        ]);
                    } elseif (!empty($upExists->deleted_at)) {
                        DB::table('user_permission')
                            ->where('id', $upExists->id)
                            ->update(['deleted_at' => null, 'status' => true, 'updated_at' => now()]);
                    }
                }
            }
        }
        $this->command->info('✅ Permisos asignados correctamente.');
    }
}
