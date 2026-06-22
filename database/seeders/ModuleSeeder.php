<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creando m�dulos...');

        $modules = [
            [
                'name' => 'Desarrollador',
                'icon' => 'ri-tools-line',
                'order_num' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Dashboard',
                'icon' => 'ri-dashboard-line',
                'order_num' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pedidos',
                'icon' => 'ri-clipboard-line',
                'order_num' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Ventas',
                'icon' => 'ri-shopping-bag-3-line',
                'order_num' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Compras',
                'icon' => 'ri-shopping-cart-2-line',
                'order_num' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Almacen',
                'icon' => 'ri-archive-line',
                'order_num' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Caja',
                'icon' => 'ri-cash-line',
                'order_num' => 7,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Configuraci�n',
                'icon' => 'ri-settings-3-line',
                'order_num' => 8,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        $inserted = 0;
        $updated = 0;

        foreach ($modules as $module) {
            $existing = DB::table('modules')->where('name', $module['name'])->first();

            if ($existing) {
                DB::table('modules')
                    ->where('id', $existing->id)
                    ->update([
                        'icon' => $module['icon'],
                        'order_num' => $module['order_num'],
                        'updated_at' => now(),
                    ]);
                $updated++;
                $this->command->info("  ? M�dulo '{$module['name']}' actualizado (ID: {$existing->id})");
            } else {
                DB::table('modules')->insert($module);
                $inserted++;
                $this->command->info("  ? M�dulo '{$module['name']}' creado exitosamente");
            }
        }

        $this->command->info("? Proceso finalizado. {$inserted} m�dulos nuevos, {$updated} actualizados.");
    }
}
