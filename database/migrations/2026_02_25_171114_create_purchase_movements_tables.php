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
        // 1. Tabla Padre: purchase_movements
        Schema::create('purchase_movements', function (Blueprint $table) {
            $table->id(); // bigint primary key auto-increment
            
            $table->json('json_persona');
            $table->string('serie');
            $table->string('anio');
            
            $table->string('tipo_detalle')->default('DETALLADO')
                ->comment('DETALLADO, GLOSA');
                
            $table->string('incluye_igv', 1)->default('N')
                ->comment('S => El total incluye IGV, N => El total no incluye IGV');
                
            $table->string('tipo_pago')->default('CONTADO')
                ->comment('CONTADO, CREDITO');
                
            $table->string('afecta_caja', 1)->default('N')
                ->comment('S => Tiene movimiento de caja, N => Solo informativo');
                
            $table->string('moneda')->default('PEN');
            
            // Numeric(8,3) -> decimal(8, 3)
            $table->decimal('tipocambio', 8, 3);
            
            // Numeric(8,2) -> decimal(8, 2)
            $table->decimal('subtotal', 8, 2);
            $table->decimal('igv', 8, 2);
            $table->decimal('total', 8, 2)
                ->comment('subtotal + igv');
                
            $table->string('afecta_kardex', 1)->default('S')
                ->comment('S => Genera registro de kárdex, N => Solo informativo');

            // Claves foráneas (Asegúrate de que las tablas 'movimiento' y 'sucursal' existan antes)
            // Si tus tablas se llaman 'movements' o 'branches' en laravel, cambia los nombres dentro de constrained()
            $table->foreignId('movement_id')
                  ->constrained('movements') 
                  ->onUpdate('cascade')
                  ->onDelete('cascade');

            $table->foreignId('branch_id')
                  ->constrained('branches')
                  ->onUpdate('cascade')
                  ->onDelete('cascade');

            $table->timestamps(); // created_at, updated_at
            $table->softDeletes(); // deleted_at
        });

        // 2. Tabla Hija: purchase_movement_details
        Schema::create('purchase_movement_details', function (Blueprint $table) {
            $table->id();
            
            $table->string('tipo_detalle')->default('DETALLADO')
                ->comment('DETALLADO, GLOSA');

            // Relación con la tabla padre que acabamos de crear arriba
            $table->foreignId('purchase_movement_id')
                  ->constrained('purchase_movements')
                  ->onUpdate('cascade')
                  ->onDelete('cascade');

            $table->string('codigo');
            $table->text('descripcion');

            // Campos relacionados con productos (Nullables según tu SQL original o lógica común)
            $table->unsignedBigInteger('producto_id')->nullable(); 
            $table->json('json_producto')->nullable();

            $table->unsignedBigInteger('unidad_id'); // Not null en tu SQL original
            $table->json('json_unidad')->nullable();

            $table->unsignedBigInteger('igv_id')->nullable();
            $table->json('json_igv')->nullable();

            // Numeric(24,6) -> decimal(24, 6) para alta precisión
            $table->decimal('cantidad', 24, 6);
            $table->decimal('monto', 24, 6);
            
            $table->text('comentario');
            $table->string('situacion', 1)->default('E');

            // Clave foránea a sucursal
            $table->foreignId('branch_id')
                  ->constrained('branches')
                  ->onUpdate('cascade')
                  ->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_movement_details');
        Schema::dropIfExists('purchase_movements');
    }
};