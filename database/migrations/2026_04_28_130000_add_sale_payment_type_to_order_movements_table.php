<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_movements', function (Blueprint $table) {
            $table->string('sale_payment_type', 20)
                ->nullable()
                ->comment('CONTADO o CREDITO — condición de venta al cerrar el pedido');
        });
    }

    public function down(): void
    {
        Schema::table('order_movements', function (Blueprint $table) {
            $table->dropColumn('sale_payment_type');
        });
    }
};
