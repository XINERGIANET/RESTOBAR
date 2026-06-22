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
        Schema::create('kardex', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('detalle_id')->nullable();
            $table->foreignId('producto_id')->constrained('products')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('unidad_id')->constrained('units')->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('cantidad', 24, 6);
            $table->decimal('preciounitario', 24, 6)->default(0);
            $table->string('moneda', 10)->default('PEN');
            $table->decimal('tipocambio', 8, 3)->default(1);
            $table->decimal('total', 24, 6)->default(0);
            $table->timestamp('fecha');
            $table->string('situacion', 1)->default('E');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('usuario', 255)->default('Sistema');
            $table->foreignId('movimiento_id')->constrained('movements')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('tipomovimiento_id')->constrained('movement_types')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('tipodocumento_id')->constrained('document_types')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('sucursal_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->decimal('stockanterior', 24, 6)->default(0);
            $table->decimal('stockactual', 24, 6)->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['producto_id', 'sucursal_id', 'fecha']);
            $table->index(['movimiento_id', 'producto_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kardex');
    }
};
