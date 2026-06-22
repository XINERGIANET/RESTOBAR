<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentConcept;

class PaymentConceptSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['description' => 'Apertura de caja', 'type' => 'I', 'restricted' => true],
            ['description' => 'Cierre de caja', 'type' => 'E', 'restricted' => true],
            ['description' => 'Pago de cliente', 'type' => 'I', 'restricted' => true],
            ['description' => 'Pago a proveedor', 'type' => 'E', 'restricted' => true],
            ['description' => 'Anulacion de venta', 'type' => 'E', 'restricted' => true],
            ['description' => 'Anulacion de compra', 'type' => 'I', 'restricted' => true],
            ['description' => 'Pago a planilla', 'type' => 'I', 'restricted' => false],
            ['description' => 'Para compra de dolares', 'type' => 'E', 'restricted' => false],
            ['description' => 'Por compra de dolares', 'type' => 'I', 'restricted' => false],
            ['description' => 'Ajuste de tipo de cambio', 'type' => 'I', 'restricted' => false],
            ['description' => 'Ajuste de tipo de cambio', 'type' => 'E', 'restricted' => false],
            ['description' => 'Prestamo', 'type' => 'I', 'restricted' => false],
            ['description' => 'Devolucion de prestamo', 'type' => 'E', 'restricted' => false],
            ['description' => 'Prestamo', 'type' => 'E', 'restricted' => false],
            ['description' => 'Devolucion de prestamo', 'type' => 'I', 'restricted' => false],
            ['description' => 'Deposito', 'type' => 'E', 'restricted' => false],
            ['description' => 'Monto por asignacion de caja chica', 'type' => 'I', 'restricted' => false],
            ['description' => 'Asignar monto de caja', 'type' => 'E', 'restricted' => false],
            ['description' => 'Otros ingresos', 'type' => 'I', 'restricted' => false],
            ['description' => 'Otros egresos', 'type' => 'E', 'restricted' => false],
            ['description' => 'Fondo de caja', 'type' => 'I', 'restricted' => false],
            ['description' => 'Gasto operativo', 'type' => 'E', 'restricted' => false],
            ['description' => 'Pago a proveedor de servicios', 'type' => 'E', 'restricted' => false],
            ['description' => 'Movilidad y pasajes', 'type' => 'E', 'restricted' => false],
            ['description' => 'Producto para venta', 'type' => 'E', 'restricted' => false],
            ['description' => 'Pago adelantado', 'type' => 'I', 'restricted' => false],
            ['description' => 'Para pago a proveedores', 'type' => 'I', 'restricted' => false],
            ['description' => 'Desembolso por retencion', 'type' => 'E', 'restricted' => false],
            ['description' => 'Impuestos', 'type' => 'E', 'restricted' => false],
            ['description' => 'Pago de venta al credito', 'type' => 'I', 'restricted' => false],
            ['description' => 'Compras', 'type' => 'E', 'restricted' => false],
            ['description' => 'Adelanto de personal', 'type' => 'E', 'restricted' => false],
            ['description' => 'Mantenimiento', 'type' => 'E', 'restricted' => false],
            ['description' => 'Devolucion de pago anticipado', 'type' => 'E', 'restricted' => false],
            ['description' => 'Pago de horas extra', 'type' => 'E', 'restricted' => false],
            ['description' => 'Pago a proveedor de insumos', 'type' => 'E', 'restricted' => false],
            ['description' => 'Pago de cuenta por cobrar', 'type' => 'I', 'restricted' => false],
            ['description' => 'Pago de cuenta por pagar', 'type' => 'I', 'restricted' => false],
        ];

        foreach ($rows as $row) {
            PaymentConcept::updateOrCreate(
                ['description' => $row['description'], 'type' => $row['type']],
                ['restricted' => $row['restricted']]
            );
        }
    }
}
