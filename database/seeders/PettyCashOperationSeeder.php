<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PettyCashOperationSeeder extends Seeder
{
    /**
     * Crea o actualiza la vista y la operación de Caja chica con action "petty-cash"
     * para que coincida con lo esperado en base de datos y en la pantalla de caja chica.
     */
    public function run(): void
    {
        $now = now();

        // 1. Obtener o crear la vista "Caja chica"
        $view = DB::table('views')->where('name', 'Caja chica')->first();
        if (!$view) {
            $viewId = DB::table('views')->insertGetId([
                'name'        => 'Caja chica',
                'status'      => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $this->command->info("Vista 'Caja chica' creada (ID: {$viewId}).");
        } else {
            $viewId = $view->id;
        }

        // 2. Obtener o crear la operación con action "petty-cash"
        $operation = DB::table('operations')
            ->where('action', 'petty-cash')
            ->whereNull('deleted_at')
            ->first();

        if (!$operation) {
            $operationId = DB::table('operations')->insertGetId([
                'name'           => 'Caja chica',
                'icon'           => 'mdi-cash',
                'action'         => 'petty-cash',
                'view_id'        => $viewId,
                'color'          => '#3B82F6',
                'status'         => 1,
                'type'           => 'R',
                'view_id_action' => null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            $this->command->info("Operación 'Caja chica' (action: petty-cash) creada (ID: {$operationId}).");
        } else {
            $operationId = $operation->id;
            DB::table('operations')
                ->where('id', $operationId)
                ->update([
                    'view_id'    => $viewId,
                    'updated_at' => $now,
                ]);
            $this->command->info("Operación 'petty-cash' ya existía (ID: {$operationId}). Vista actualizada.");
        }

        // 3. Vincular la operación a todas las sucursales (branch_operation)
        $branches = DB::table('branches')->pluck('id');
        foreach ($branches as $branchId) {
            $exists = DB::table('branch_operation')
                ->where('operation_id', $operationId)
                ->where('branch_id', $branchId)
                ->whereNull('deleted_at')
                ->exists();
            if (!$exists) {
                DB::table('branch_operation')->insert([
                    'operation_id' => $operationId,
                    'branch_id'    => $branchId,
                    'status'       => 1,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
            }
        }

        // 4. Vincular la operación a perfil + sucursal (operation_profile_branch)
        $profiles = DB::table('profiles')->pluck('id');
        foreach ($branches as $branchId) {
            foreach ($profiles as $profileId) {
                $exists = DB::table('operation_profile_branch')
                    ->where('operation_id', $operationId)
                    ->where('profile_id', $profileId)
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at')
                    ->exists();
                if (!$exists) {
                    DB::table('operation_profile_branch')->insert([
                        'operation_id' => $operationId,
                        'profile_id'   => $profileId,
                        'branch_id'    => $branchId,
                        'status'       => 1,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]);
                }
            }
        }

        // 5. Normalizar acciones existentes: petty.cash → petty-cash
        $updated = 0;
        DB::table('operations')
            ->where('action', 'like', 'petty.cash%')
            ->get()
            ->each(function ($row) use (&$updated, $now) {
                $newAction = str_replace('petty.cash', 'petty-cash', $row->action);
                if ($newAction !== $row->action) {
                    DB::table('operations')->where('id', $row->id)->update([
                        'action'     => $newAction,
                        'updated_at' => $now,
                    ]);
                    $updated++;
                }
            });
        if ($updated > 0) {
            $this->command->info("Acciones actualizadas de petty.cash a petty-cash: {$updated}.");
        }

        $this->command->info('Seeder PettyCashOperation finalizado.');
    }
}
