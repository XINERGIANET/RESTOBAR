<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PersonnelConfigSeeder extends Seeder
{
    /**
     * Perfiles que tendrán acceso a la nueva vista de Personal en Configuración.
     * ID 1 = Admin. de sistema | ID 2 = Admin. general
     */
    private array $targetProfileIds = [1, 2];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Configurando Vista y Opción de Menú para Personal en Configuración...');

        // ─────────────────────────────────────────────────────
        // 1. VISTA
        // ─────────────────────────────────────────────────────
        $viewId = 40;
        if (!DB::table('views')->where('id', $viewId)->exists()) {
            DB::table('views')->insert([
                'id'           => $viewId,
                'name'         => 'Personal Sucursal (Config)',
                'abbreviation' => 'PERS_CONF',
                'status'       => 1,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            $this->command->info("Vista creada con ID: $viewId");
        } else {
            DB::table('views')->where('id', $viewId)->update([
                'name'       => 'Personal Sucursal (Config)',
                'updated_at' => now(),
            ]);
        }

        // ─────────────────────────────────────────────────────
        // 2. MÓDULO DE CONFIGURACIÓN
        // ─────────────────────────────────────────────────────
        $module = DB::table('modules')->where('name', 'Configuración')->first();
        if (!$module) {
            $this->command->error("No se encontró el módulo 'Configuración'. Abortando.");
            return;
        }

        // ─────────────────────────────────────────────────────
        // 3. OPCIÓN DE MENÚ
        // ─────────────────────────────────────────────────────
        $menuOptionAction = 'configuracion.personal.index';
        $menuOption = DB::table('menu_option')->where('action', $menuOptionAction)->first();

        if (!$menuOption) {
            $menuOptionId = DB::table('menu_option')->insertGetId([
                'name'         => 'Personal',
                'icon'         => 'ri-team-line',
                'action'       => $menuOptionAction,
                'view_id'      => $viewId,
                'module_id'    => $module->id,
                'status'       => 1,
                'quick_access' => false,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            $this->command->info("Opción de Menú 'Personal' creada (ID: $menuOptionId).");
        } else {
            $menuOptionId = $menuOption->id;
            DB::table('menu_option')->where('id', $menuOptionId)->update([
                'view_id'    => $viewId,
                'module_id'  => $module->id,
                'updated_at' => now(),
            ]);
            $this->command->info("Opción de Menú 'Personal' ya existía (ID: $menuOptionId). Actualizada.");
        }

        // ─────────────────────────────────────────────────────
        // 4. OPERACIONES BASE
        // ─────────────────────────────────────────────────────
        $operationsDef = [
            ['name' => 'Nuevo Personal',         'action' => 'admin.companies.branches.people.create',      'type' => 'T', 'icon' => 'ri-add-line',              'color' => '#10B981'],
            ['name' => 'Editar Personal',        'action' => 'admin.companies.branches.people.edit',        'type' => 'R', 'icon' => 'ri-edit-line',              'color' => '#3B82F6'],
            ['name' => 'Eliminar Personal',      'action' => 'admin.companies.branches.people.destroy',     'type' => 'R', 'icon' => 'ri-delete-bin-line',        'color' => '#EF4444'],
            ['name' => 'Restablecer contraseña', 'action' => 'admin.companies.branches.people.restablecer', 'type' => 'R', 'icon' => 'ri-lock-password-line',     'color' => '#F59E0B'],
            ['name' => 'Ver usuario',            'action' => 'admin.companies.branches.people.verUsuario',  'type' => 'R', 'icon' => 'ri-user-search-line',       'color' => '#6B7280'],
        ];

        $operationIds = [];
        foreach ($operationsDef as $op) {
            $existing = DB::table('operations')
                ->where('action', $op['action'])
                ->where('view_id', $viewId)
                ->first();

            if (!$existing) {
                $operationIds[] = DB::table('operations')->insertGetId(array_merge($op, [
                    'view_id'    => $viewId,
                    'status'     => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
                $this->command->info("Operación '{$op['name']}' creada.");
            } else {
                $operationIds[] = $existing->id;
            }
        }

        // ─────────────────────────────────────────────────────
        // 5. PERMISOS POR SUCURSAL
        // ─────────────────────────────────────────────────────
        $branches = DB::table('branches')->whereNull('deleted_at')->pluck('id');
        $now = now();

        foreach ($branches as $branchId) {
            // a) branch_operation
            foreach ($operationIds as $opId) {
                $exists = DB::table('branch_operation')
                    ->where('operation_id', $opId)
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at')
                    ->exists();

                if (!$exists) {
                    DB::table('branch_operation')->insert([
                        'operation_id' => $opId,
                        'branch_id'    => $branchId,
                        'status'       => 1,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]);
                }
            }

            foreach ($this->targetProfileIds as $profileId) {
                // b) user_permission (visibilidad del menú)
                $upExists = DB::table('user_permission')
                    ->where('profile_id', $profileId)
                    ->where('branch_id', $branchId)
                    ->where('menu_option_id', $menuOptionId)
                    ->first();

                if (!$upExists) {
                    DB::table('user_permission')->insert([
                        'id'             => (string) Str::uuid(),
                        'name'           => 'Personal',
                        'profile_id'     => $profileId,
                        'menu_option_id' => $menuOptionId,
                        'branch_id'      => $branchId,
                        'status'         => true,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ]);
                } elseif (!empty($upExists->deleted_at)) {
                    DB::table('user_permission')
                        ->where('id', $upExists->id)
                        ->update(['deleted_at' => null, 'status' => true, 'updated_at' => $now]);
                }

                // c) operation_profile_branch (acceso a cada botón/acción)
                foreach ($operationIds as $opId) {
                    $opbExists = DB::table('operation_profile_branch')
                        ->where('operation_id', $opId)
                        ->where('profile_id', $profileId)
                        ->where('branch_id', $branchId)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (!$opbExists) {
                        DB::table('operation_profile_branch')->insert([
                            'operation_id' => $opId,
                            'profile_id'   => $profileId,
                            'branch_id'    => $branchId,
                            'status'       => 1,
                            'created_at'   => $now,
                            'updated_at'   => $now,
                        ]);
                    }
                }
            }
        }

        $this->command->info("✅ Seeder finalizado. Personal de Configuración visible para Admin. de sistema y Admin. general en todas las sucursales.");
    }
}
