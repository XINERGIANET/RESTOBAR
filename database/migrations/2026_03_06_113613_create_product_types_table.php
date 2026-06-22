<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('behavior', 20)->comment('SELLABLE = vendible, SUPPLY = suministro');
            $table->string('icon', 100)->nullable();
            $table->timestamps();

            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });

        $this->createDefaultProductTypesForExistingBranches();
    }

    /**
     * Crea los dos tipos por defecto para cada sucursal existente.
     */
    private function createDefaultProductTypesForExistingBranches(): void
    {
        $branchIds = DB::table('branches')->whereNull('deleted_at')->pluck('id');
        $now = now();

        foreach ($branchIds as $branchId) {
            $exists = DB::table('product_types')->where('branch_id', $branchId)->exists();
            if ($exists) {
                continue;
            }
            DB::table('product_types')->insert([
                [
                    'branch_id' => $branchId,
                    'name' => 'Producto final',
                    'description' => 'Productos listos para la venta.',
                    'behavior' => 'SELLABLE',
                    'icon' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'branch_id' => $branchId,
                    'name' => 'Ingrediente',
                    'description' => 'Repuestos, insumos o materiales de apoyo.',
                    'behavior' => 'SUPPLY',
                    'icon' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_types');
    }
};
