<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Permitir stocks fraccionarios (p.ej. consumo por receta en cuartos).
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE product_branch ALTER COLUMN stock TYPE numeric(24,6) USING stock::numeric');
            DB::statement('ALTER TABLE product_branch ALTER COLUMN stock SET DEFAULT 0');
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `product_branch` MODIFY `stock` DECIMAL(24,6) NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // Al volver a entero, truncamos (no redondeamos) para evitar sorpresas.
            DB::statement('ALTER TABLE product_branch ALTER COLUMN stock TYPE integer USING floor(stock)::integer');
            DB::statement('ALTER TABLE product_branch ALTER COLUMN stock SET DEFAULT 0');
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `product_branch` MODIFY `stock` INT NOT NULL DEFAULT 0');
        }
    }
};

