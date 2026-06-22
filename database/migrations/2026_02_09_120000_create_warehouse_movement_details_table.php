<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_movement_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_movement_id')
                ->constrained('warehouse_movements')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('product_id')
                ->constrained('products');
            $table->json('product_snapshot');
            $table->foreignId('unit_id')
                ->constrained('units');
            $table->decimal('quantity', 24, 6);
            $table->text('comment');
            $table->string('status', 1)->default('A');
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_movement_details');
    }
};
