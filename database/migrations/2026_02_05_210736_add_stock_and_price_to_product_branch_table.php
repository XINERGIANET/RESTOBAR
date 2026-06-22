<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_branch', function (Blueprint $table) {
            // Agregar stock (cantidad en inventario)
            $table->integer('stock')->default(0)->after('status');
            
            // Agregar price (precio en esta sucursal)
            $table->decimal('price', 10, 2)->default(0.00)->after('stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_branch', function (Blueprint $table) {
            $table->dropColumn(['stock', 'price']);
        });
    }
};