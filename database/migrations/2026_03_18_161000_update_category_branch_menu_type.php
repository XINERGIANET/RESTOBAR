<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Los que ya tienen 'PLATOS A LA CARTA' → VENTAS_PEDIDOS
        DB::table('category_branch')
            ->where('menu_type', 'PLATOS A LA CARTA')
            ->whereNull('deleted_at')
            ->update(['menu_type' => 'VENTAS_PEDIDOS']);

        // Los que tienen 'GENERAL' los dejamos como están
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('category_branch')
            ->where('menu_type', 'VENTAS_PEDIDOS')
            ->update(['menu_type' => 'PLATOS A LA CARTA']);
    }
};
