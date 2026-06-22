<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuOptionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Iniciando carga de Opciones de Menú...');
        $defaultView = DB::table('views')->first();
        if (!$defaultView) {
            $viewId = DB::table('views')->insertGetId([
                'name' => 'Vista General',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->command->info("Se creó una Vista por defecto (ID: $viewId) para vincular las opciones.");
        } else {
            $viewId = $defaultView->id;
        }

        $structure = [
            'Desarrollador' => [
                ['name' => 'Empresa',            'action' => 'admin.companies.index',                 'icon' => 'mdi-domain'],
                ['name' => 'Usuarios',           'action' => '/admin/herramientas/usuarios',    'icon' => 'mdi-account-group'],
                ['name' => 'Roles y permisos',   'action' => '/admin/herramientas/roles',       'icon' => 'mdi-shield-account'],
                ['name' => 'Sucursales',         'action' => '/admin/herramientas/sucursales',  'icon' => 'mdi-office-building'],
                ['name' => 'Modulos',            'action' => 'admin.modules.index',             'icon' => 'mdi-view-module'],
                ['name' => 'Categorias de Parametros',         'action' => 'admin.parameters.categories.index',           'icon' => 'mdi-settings'],
                ['name' => 'Parametros',         'action' => 'admin.parameters.index',           'icon' => 'mdi-settings'],
                ['name' => 'Operaciones',        'action' => 'admin.operations.index',           'icon' => 'mdi-settings'],
                ['name' => 'Vistas',        'action' => 'admin.views.index',           'icon' => 'mdi-settings'],
                ['name' => 'Tarjetas',        'action' => 'admin.cards.index',           'icon' => 'mdi-credit-card'],
                ['name' => 'Billeteras digitales',        'action' => 'admin.digital_wallets.index',           'icon' => 'mdi-wallet'],
                ['name' => 'Tipo de movimientos',        'action' => 'admin.movement_types.index',           'icon' => 'mdi-settings'],            
            ],
            'Pedidos' => [
                ['name' => 'Registro de areas',    'action' => 'areas.index',          'icon' => 'mdi-food'],
                ['name' => 'Areas de pedido',    'action' => 'areas.tables.index',          'icon' => 'mdi-food'],
            ],
            'Ventas' => [
                ['name' => 'POS',                'action' => '/admin/ventas/pos',               'icon' => 'mdi-cash-register'],
                ['name' => 'Facturación',        'action' => '/admin/ventas/facturacion',       'icon' => 'mdi-file-document'],
                ['name' => 'Reportes',           'action' => '/admin/ventas/reporte',          'icon' => 'mdi-chart-line'],
            ],
            'Compras' => [
                ['name' => 'Proveedores',        'action' => '/admin/compras/proveedores',      'icon' => 'mdi-truck'],
                ['name' => 'Ordenes de compra',  'action' => '/admin/compras/ordenes',          'icon' => 'mdi-file-plus'],
                ['name' => 'Recepciones',        'action' => '/admin/compras/recepciones',      'icon' => 'mdi-package-variant-closed'],
            ],
            'Almacen' => [
                ['name' => 'Inventario',         'action' => '/admin/almacen/inventario',       'icon' => 'mdi-clipboard-list'],
                ['name' => 'Insumos',            'action' => '/admin/almacen/insumos',          'icon' => 'mdi-fruit-watermelon'],
                ['name' => 'Movimientos',        'action' => '/admin/almacen/movimientos',      'icon' => 'mdi-transfer'],
                ['name' => 'Kardex',             'action' => 'kardex.index',                    'icon' => 'ri-file-list-3-line'],
            ],
            'Caja' => [
                ['name' => 'Apertura y cierre',  'action' => '/admin/caja/aperturas',           'icon' => 'mdi-lock-open-outline'],
                ['name' => 'Arqueos',            'action' => '/admin/caja/arqueos',             'icon' => 'mdi-cash-multiple'],
                ['name' => 'Gastos',             'action' => '/admin/caja/gastos',              'icon' => 'mdi-cash-minus'],
                ['name' => 'Cuentas por cobrar', 'action' => 'accounts-receivable.index',      'icon' => 'mdi-cash-clock'],
            ],
            'Configuraci�n' => [
                ['name' => 'Parametros',         'action' => '/admin/configuracion/parametros', 'icon' => 'mdi-cog'],
                ['name' => 'Menu y recetas',     'action' => '/admin/configuracion/menu',       'icon' => 'mdi-food-fork-drink'],
                ['name' => 'Impuestos',          'action' => '/admin/configuracion/impuestos',  'icon' => 'mdi-percent'],
            ],
        ];

        $totalInserted = 0;

        // 3. RECORRER Y GUARDAR EN BASE DE DATOS
        foreach ($structure as $moduleName => $options) {
            
            // Buscamos el ID del módulo por su nombre
            $module = DB::table('modules')->where('name', $moduleName)->first();

            if (!$module) {
                $this->command->error("⚠️ El módulo '$moduleName' no existe. Saltando opciones...");
                continue;
            }

            foreach ($options as $opt) {
                // Verificar si ya existe la opción para no duplicar
                $exists = DB::table('menu_option')
                    ->where('name', $opt['name'])
                    ->where('module_id', $module->id)
                    ->exists();

                if (!$exists) {
                    DB::table('menu_option')->insert([
                        'name'         => $opt['name'],
                        'icon'         => $opt['icon'], // Icono específico o genérico
                        'action'       => $opt['action'],
                        'view_id'      => $viewId,      // ID de la vista (requerido por FK)
                        'module_id'    => $module->id,  // ID del módulo encontrado
                        'status'       => 1,            // Activo
                        'quick_access' => false,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                    $totalInserted++;
                }
            }
        }

        $this->command->info("✅ Seeder finalizado. $totalInserted opciones de menú insertadas.");
    }
}